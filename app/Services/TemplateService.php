<?php

namespace App\Services;

use App\Contracts\Models\ItemInterface;
use App\Contracts\Models\ListingPivotInterface;
use App\Models\Company;
use App\Models\Template;
use App\Models\TemplateGroup;
use App\Models\User;
use App\Traits\Services\ItemTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class TemplateService
{
    use ItemTrait;

    protected function getItemClass(): string
    {
        return Template::class;
    }

    protected function getGroupClass(): string
    {
        return TemplateGroup::class;
    }

    protected function getGroupService(): TemplateGroupService
    {
        return new TemplateGroupService();
    }

    public function getItemListingPivot(Template|ItemInterface $item): ListingPivotInterface
    {
        return $item->parentListingPivot()->withTrashed()->firstOrNew([
            'company_id' => $item->company_id,
        ]);
    }

    protected function getQueryItemsWithoutGroup(Template|ItemInterface $item): Relation
    {
        return $item->company->templates()->whereNull('template_group_id');
    }

    protected function getParamGroupName(): string
    {
        return 'template_group_id';
    }

    public function listing(User $authUser, $filter = []): SupportCollection
    {
        $deleted = isset($filter['is_deleted']) && $filter['is_deleted'] == 1;
// ray()->showQueries();
        $company = $authUser->company;
        $companyIntegrations = $company
            ->companyIntegrations()
            ->where('type', 'disguise')
            ->pluck('is_active')
            ->toArray();
        $d3Disabled = true; //disable by default
        if ($companyIntegrations && $companyIntegrations[0]){
            $d3Disabled = false;
        }

        $companyListingQuery = $deleted
            ? $company->listingTemplatesPivotWithTrashed()
            : $company->listingTemplates();
        $parentListingRelation = $deleted
            ? 'parentListingPivotWithTrashed'
            : 'parentListingPivot';

        $templateQuery = $companyListingQuery->select(Template::COLUMNS)
            ->with([$parentListingRelation, 'createdBy:id,email', 'tags:id,name']);
        if ($deleted) {
            $templateQuery->onlyTrashed();
        }
        $templateQuery->wherePivotNull('group_id')
                      ->orderByPivot('sort_order');

        if ($d3Disabled) {
            $templateQuery->where(function($q) {
                $q->whereNull('preset')
                    ->orWhere('preset', '!=', 'd3');
            });
        }
        $inactive = isset($filter['is_active']) && $filter['is_active'] == 0;

        if ($inactive) {
            $templateQuery->inactive();
        } else {
            $templateQuery->active();
        }

        if ($deleted) {
            $templateQuery->onlyTrashed();
        }
        $templates = $templateQuery->get();
        $templates->makeHidden(['group']);
// ray($templates->pluck('name')->all())->green()->label('$templates -> name');
        if ($inactive) {
            return $templates;
        }
// ray()->stopShowingQueries();
        if($deleted){
            $companyListingQuery = $company->listingTemplateGroupsPivotWithTrashed();
            $templateListingRelation = 'listingTemplatesPivotWithTrashed';
            $parentListingRelation = 'parentListingPivotWithTrashed';
        } else {
            $companyListingQuery = $company->listingTemplateGroups();
            $templateListingRelation = 'listingTemplates';
            $parentListingRelation = 'parentListingPivot';
        }

        /** @var \Kalnoy\Nestedset\Collection $templateGroups */
        $templateGroupsQuery = $companyListingQuery
            ->with([
                $parentListingRelation,
                $templateListingRelation => function (MorphToMany $morphToMany) use ($d3Disabled, $deleted) {
                    $morphToMany->select(Template::COLUMNS)->orderByPivot('sort_order');

                    if ($d3Disabled) {
                        $morphToMany->where(function($q) {
                            $q->whereNull('preset')
                                ->orWhere('preset', '!=', 'd3');
                        });
                    }

                    if($deleted){
                        $morphToMany->onlyTrashed();
                    }
                },
                $templateListingRelation.'.'.$parentListingRelation, $templateListingRelation.'.createdBy:id,email',
                $templateListingRelation.'.tags:id,name'
            ]);
        if ($deleted) {
            // some templates may be deleted from a group that is not deleted,
            // so fetch both trashed and not-trashed groups
            $templateGroupsQuery = $templateGroupsQuery->withTrashed();
        }
        $templateGroups = $templateGroupsQuery->orderByPivot('sort_order')
            ->get();
        // ray($templateGroups)->blue()->label('BEFORE HIDDEN --ALL template groups -- $templateGroups');
        $templateGroups->makeHidden(['children', 'items', 'templates', $templateListingRelation, /*$parentListingRelation*/]);

        foreach ($templateGroups as $templateGroup) {
            /** @var \App\Models\TemplateGroup $templateGroup */
            $templateGroup->$templateListingRelation->makeHidden(['group', 'data']);

            if($deleted){
                // TODO: remove empty groups so they don't display
                /*$descendantTemplateGroups = $templateGroup->listingTemplateGroupsPivotWithTrashed()->get();
                if($templateGroup->$templateListingRelation->isEmpty() && $descendantTemplateGroups->isEmpty()){
                    ray($templateGroup->$templateListingRelation)->green()->label('templates of group');
                    ray($descendantTemplateGroups)->green()->label('groups of group');
                    $templateGroups = $templateGroups->reject(function ($group) use ($templateGroup) {
                        return $group->id === $templateGroup->id;
                    });
                }*/
            }

            $templateGroup->setRelation('items', $templateGroup->$templateListingRelation);
            // $templateGroup->setRelation('parentListingPivot', $templateGroup->$parentListingRelation);
        }
        // ray($templateGroups)->blue()->label('ALL template groups -- $templateGroups');
        $results = TemplateGroupService::sortTreeComponents($templateGroups->toTree()->toBase()->merge($templates));

        //apply d3 templates at the end
        if (!$d3Disabled) {
            $template= new Template();
            $d3TemplatesQuery = $template->select(Template::COLUMNS)
                            ->whereNull('template_group_id')
                            ->where('preset', 'd3');
            if ($deleted) {
                $d3TemplatesQuery->onlyTrashed();
            }
            $d3Templates = $d3TemplatesQuery->get();
            $d3Templates->makeHidden(['group', 'data', 'sort_order']);

            $results = $results->concat($d3Templates);
        }

        return $results;
    }

    public function store(User $authUser, array $params = []): Template
    {
        $templateParams = $params;
        unset($templateParams['sort_order']);

        $template = new Template($templateParams);

        $template->createdBy()->associate($authUser);
        $template->company()->associate($authUser->company);

        DB::transaction(function () use ($template, $params) {
            $template->save();

            $listingPivot = $this->getItemListingPivot($template);
            $listingPivot->group()->associate($template->template_group_id);
            $listingPivot->saveOrRestore();

            if (isset($params['sort_order'])) {
                $listingPivot->moveToOrder($params['sort_order']);
            } else {
                $listingPivot->updateSortOrderOfList();
            }
        });
        return $template;
    }

    public function update(Template $template, array $params = []): Template
    {
        DB::transaction(function () use ($template, $params) {
            $oldGroup = $template->group;

            $templateParams = $params;
            unset($templateParams['sort_order']);

            $template->update($templateParams);
            $template->refresh();

            if (!empty($params['tags'])) {
                $template->syncTagsWithType($params['tags'], 'template');
            }

            $listingPivot = $this->getItemListingPivot($template);
            $listingPivot->group()->associate($template->template_group_id);
            $listingPivot->saveOrRestore();

            if ($template->template_group_id !== $oldGroup?->id) {
                $listingPivot->setHighestOrderNumber();
                $listingPivot->saveOrRestore();
            }

            if (isset($params['sort_order'])) {
                $listingPivot->moveToOrder($params['sort_order']);
            } else {
                $listingPivot->updateSortOrderOfList();
            }
        });

        return $template;
    }

    public function batchUpdate(array $params = []): Collection
    {
        return $this->baseBatchUpdate($params);
    }

    public function batchDuplicate(User $authUser, array $params = []): void
    {
        $this->baseBatchDuplicate($authUser, $params);
    }

    public function delete(Template $template): void
    {
        DB::transaction(function () use ($template) {
            $listingPivot = $this->getItemListingPivot($template);

            $template->delete();

            $listingPivot->updateSortOrderOfList();
        });
    }

    public function batchDelete(array $params = []): void
    {
        $this->baseBatchDelete($params);
    }

    public function batchActivate(array $params = [], $flag = true): void
    {
        DB::transaction(function () use ($params, $flag) {
            $items = Template::query()->withTrashed()->whereIn('id', $params['ids'])->get();

            foreach ($items as $item) {
                $listingPivot = $this->getItemListingPivot($item);
                $params = ['is_active' => $flag];
                if (!!$item->template_group_id) {
                    $params['template_group_id'] = null;
                }
                $item->update($params);

                if (!$flag) {
                    $listingPivot->group()->dissociate();
                    $listingPivot->saveOrRestore();
                }
                $listingPivot->updateSortOrderOfList();
            }
        });
    }

    public function batchRestore(array $params = [], $flag = true): void
    {
        DB::transaction(function () use ($params, $flag) {
            $items = Template::query()->select(Template::COLUMNS)
                                      ->onlyTrashed()
                                      ->whereIn('id', $params['ids'])
                                      ->get();

            foreach ($items as $item) {
                $listingPivot = $this->getItemListingPivot($item);
                $item->restore();
                $listingPivot->restore();
                $group = $item->group()->onlyTrashed()->first();
                if(isset($group->id)){
                    // this item belongs to a deleted group, ungroup it so we can restore it
                    if (!empty($item->template_group_id)) {
                        $listingPivot->group()->dissociate();
                        $listingPivot->save();
                        $updateRes = $item->update(['template_group_id'=>null]);
                    }
                }
                $listingPivot->updateSortOrderOfList();
                // $listingPivot->saveOrRestore();
            }
        });
    }

    /**
     * Get the related (non-grouped) Templates and TemplateGroups (with their Templates), including
     * intermediate pivot values, then merge the results into a sorted tree
     */
    public function buildTreeToUpdateSort(Company $company): SupportCollection
    {
        $templates = $company->listingTemplates()->select(Template::COLUMNS)
            ->wherePivotNull('group_id')
            ->orderByPivot('sort_order')
            ->orderByPivot('id')
            ->get();

        /** @var \Kalnoy\Nestedset\Collection $templateGroups */
        $templateGroups = $company->listingTemplateGroups()
            ->with([
                'listingTemplates' => function (MorphToMany $morphToMany) {
                    $morphToMany->select(Template::COLUMNS)
                        ->orderByPivot('sort_order')
                        ->orderByPivot('id');
                },
            ])
            ->orderByPivot('sort_order')
            ->orderByPivot('id')
            ->get();

        // add an alias `items` relation for the `listingTemplates` relation
        foreach ($templateGroups as $templateGroup) {
            /** @var \App\Models\TemplateGroup $templateGroup */
            $templateGroup->setRelation('items', $templateGroup->listingTemplates);
        }

        return ChannelGroupService::sortTreeComponents($templateGroups->toTree()->toBase()->merge($templates));
    }

    public function getFields(Company $company, $templateIds = [])
    {
        $fields = [];
        $templates = $company->templates();
        if (!empty($templateIds)) {
            $templates->whereIn('id', $templateIds);
        }
        $templates->each(function(Template $template) use (&$fields) {
            $schema = $template->data;
            if (empty($schema)) {
                return true;
            }
            $template->propsFromSchema($schema, function ($node) use (&$fields, $template) {
                if (isset($node['input']) && $node['input']) {
                    $fields[$node['key']] = [
                        'label' => $node['label'] ?? $node['key'],
                        'template_id' => $template->id,
                        'template_name' => $template->name,
                    ];
                }
            });
        });
        return collect($fields)->map(fn ($field, $name) => [
            'name' => $name,
            'label' => $field['label'],
            'template_id' => $field['template_id'],
            'template_name' => $field['template_name']
        ])
            ->values()
            ->toArray();
    }
}
