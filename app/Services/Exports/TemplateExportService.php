<?php

namespace App\Services\Exports;

use App\Models\Company;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection as SupportCollection;

class TemplateExportService
{
    const TYPE = 'template';

    public function batchExport(User $authUser, array $params = []): SupportCollection
    {
        $paramsIds = array_values(array_filter(array_unique($params['ids'] ?? [])));
        $paramsGroupIds = array_values(array_filter(array_unique($params['group_ids'] ?? [])));

        $groups = $this->getGroups($authUser->company, $paramsIds, $paramsGroupIds);
        $templates = $this->getTemplates($authUser->company, $paramsIds);

        return collect([
            '_type' => self::TYPE,
            'templateGroups' => $this->prepareGroups($groups),
            'templates' => $this->prepareTemplates($templates),
        ]);
    }

    public function generateFileName(Company $company): string
    {
        $fileName = preg_replace('/\W+/', '-', $company->name);
        $fileName .= '_templates_'.now()->format('Y-m-d_H-i-s').'.json';

        return $fileName;
    }

    protected function getTemplates(Company $company, array $paramsIds): Collection
    {
        return $company->listingTemplates()
            ->with(['parentListingPivot'])
            ->wherePivotNull('group_id')
//            ->where(function (Builder $q) {
//                $q
//                    ->whereNull('preset')
//                    ->orWhereNot('preset', Template::PRESET_D3);
//            })
            ->findMany($paramsIds);
    }

    protected function getGroups(Company $company, array $paramsIds, array $paramsGroupIds): Collection
    {
        $groupIds = $company->templates()->whereKey($paramsIds)->pluck('template_group_id')
            ->merge($paramsGroupIds)->unique()->values();

        foreach ($groupIds as $groupId) {
            /** @var \Kalnoy\Nestedset\QueryBuilder|\Illuminate\Database\Eloquent\Relations\HasMany $query */
            $query = $company->templateGroups();
            $groupIds = $query->whereAncestorOf($groupId)->pluck('id')
                ->merge($groupIds)->unique()->values();
        }

        $templateIds = collect($paramsIds);

        foreach ($this->getGroupIdsNoSelectionIn($company, $paramsIds, $paramsGroupIds) as $noSelectionInGroupId) {
            /** @var \Kalnoy\Nestedset\QueryBuilder|\Illuminate\Database\Eloquent\Relations\HasMany $query */
            $query = $company->templateGroups();
            $noSelectionInGroups = $query->whereDescendantOrSelf($noSelectionInGroupId)->with(['items'])->get();

            $groupIds = $noSelectionInGroups->pluck('id')
                ->merge($groupIds)->unique()->values();
            $templateIds = $noSelectionInGroups->pluck('items.*.id')->flatten()
                ->merge($templateIds)->unique()->values();
        }

        return $company->templateGroups()
            ->with([
                'parentListingPivot',
                'items' => function (HasMany $hasMany) use ($templateIds) {
                    $hasMany
                        ->whereKey($templateIds);
//                        ->where(function (Builder $q) {
//                            $q
//                                ->whereNull('preset')
//                                ->orWhereNot('preset', Template::PRESET_D3);
//                        });
                },
                'items.parentListingPivot',
            ])
            ->findMany($groupIds);
    }

    protected function getGroupIdsNoSelectionIn(Company $company, array $paramsIds, array $paramsGroupIds): array
    {
        $noSelectionInGroupIds = [];

        /** @var \Kalnoy\Nestedset\QueryBuilder|\Illuminate\Database\Eloquent\Relations\HasMany $selectionInGroupQuery */
        $selectionInGroupQuery = $company->templateGroups();
        $selectionInGroupQuery
            ->where(function (Builder $q) use ($company, $paramsIds, $paramsGroupIds) {
                $q
                    ->whereIn('id', $company->templateGroups()
                        ->select(['parent_id'])
                        ->whereKey($paramsGroupIds)
                    )
                    ->orWhereIn('id', $company->templates()
                        ->select(['template_group_id'])
                        ->whereKey($paramsIds)
                    );
            });

        foreach ($paramsGroupIds as $paramsGroupId) {
            if ($selectionInGroupQuery->clone()->whereDescendantOrSelf($paramsGroupId)->doesntExist()) {
                $noSelectionInGroupIds[] = $paramsGroupId;
            }
        }

        return $noSelectionInGroupIds;
    }

    public function prepareTemplates(Collection $templates): SupportCollection
    {
        $results = collect();

        foreach ($templates as $template) {
            /** @var \App\Models\Template $template */

            $preparedTemplate = $template->only([
                'id',
                'company_id',
                'preset',
                'name',
                'type',
                'engine',
                'color',
                'data',
                'sort_order',
            ]);

            $results->add($preparedTemplate);
        }

        return $results;
    }

    protected function prepareGroups(Collection $groups): SupportCollection
    {
        $results = collect();

        foreach ($groups as $group) {
            /** @var \App\Models\TemplateGroup $group */

            $preparedGroup = $group->only([
                'id',
                'name',
                '_lft',
                '_rgt',
                'parent_id',
                'sort_order',
            ]);

            $preparedGroup['templates'] = $this->prepareTemplates($group->items);

            $results->add($preparedGroup);
        }

        return $results;
    }
}
