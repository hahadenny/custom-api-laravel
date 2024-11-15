<?php

namespace App\Services;

use App\Contracts\Models\GroupInterface;
use App\Contracts\Models\ListingPivotInterface;
use App\Contracts\Models\TreeSortable;
use App\Models\Template;
use App\Models\TemplateGroup;
use App\Models\User;
use App\Traits\Services\GroupTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TemplateGroupService
{
    use GroupTrait;

    protected function getItemClass(): string
    {
        return Template::class;
    }

    protected function getGroupClass(): string
    {
        return TemplateGroup::class;
    }

    protected function getItemService(): TemplateService
    {
        return new TemplateService();
    }

    protected function getGroupListingPivot(TemplateGroup|GroupInterface $group): ListingPivotInterface|TreeSortable
    {
        return $group->parentListingPivot()->withTrashed()->firstOrNew([
            'company_id' => $group->company_id,
        ]);
    }

    protected function getQueryGroupsWithoutGroup(TemplateGroup|GroupInterface $group): Relation
    {
        return $group->company->templateGroups()->whereNotNull('parent_id');
    }

    public function store(User $authUser, array $params = []): TemplateGroup
    {
        $templateGroupParams = $params;
        unset($templateGroupParams['sort_order']);

        $group = new TemplateGroup($templateGroupParams);

        $group->createdBy()->associate($authUser);
        $group->company()->associate($authUser->company);

        DB::transaction(function () use ($group, $params) {
            $group->save();

            $listingPivot = $this->getGroupListingPivot($group);
            $listingPivot->group()->associate($group->parent_id);
            $listingPivot->saveOrRestore();

            $this->saveChildrenAndItems($group, $params);

            if (isset($params['sort_order'])) {
                $listingPivot->moveToOrder($params['sort_order']);
            } else {
                $listingPivot->setHighestOrderNumber();
                $listingPivot->saveOrRestore();
                $listingPivot->updateSortOrderOfList();
            }
        });

        return $group;
    }

    public function update(TemplateGroup $group, array $params = []): TemplateGroup
    {
        DB::transaction(function () use ($group, $params) {
            $parent = $group->parent;

            $groupParams = $params;
            unset($groupParams['sort_order']);

            $group->update($groupParams);
            $group->refresh();

            $listingPivot = $this->getGroupListingPivot($group);
            $listingPivot->group()->associate($group->parent_id);
            $listingPivot->saveOrRestore();

            if ($group->parent_id !== $parent?->id) {
                $listingPivot->setHighestOrderNumber();
                $listingPivot->saveOrRestore();
            }

            $this->saveChildrenAndItems($group, $params);

            if (isset($params['sort_order'])) {
                $listingPivot->moveToOrder($params['sort_order']);
            } else {
                $listingPivot->updateSortOrderOfList();
            }
        });

        return $group;
    }

    public function batchUpdate(array $params = []): Collection
    {
        return $this->baseBatchUpdate($params);
    }

    public function batchDuplicate(User $authUser, array $params = []): void
    {
        $this->baseBatchDuplicate($authUser, $params);
    }

    public function batchUngroup(array $params = []): void
    {
        $this->baseBatchUngroup($params);
    }

    public function delete(TemplateGroup $group): void
    {
        DB::transaction(function () use ($group) {
            $listingPivot = $this->getGroupListingPivot($group);

            $this->deleteGroup($group);

            $listingPivot->updateSortOrderOfList();
        });
    }

    public function batchDelete(array $params = []): void
    {
        $this->baseBatchDelete($params);
    }

    public function batchRestore(array $params = []): void
    {
        DB::transaction(function () use ($params) {
            $parentGroupListingPivot = null;
            // some templates may be deleted from a group that is not deleted,
            // so fetch both trashed and not-trashed groups
            $groups = TemplateGroup::query()
                             ->withTrashed()
                             ->whereIn('id', $params['ids'])
                             ->get();

            if($groups->count() === 0){
                return;
            }

            $groups->makeVisible('deleted_at');

            foreach ($groups as $group) {
                // if $group has a `parent_id`, then $group has a parent group
                if (! is_null($group)) {

                    // !!NOTE: this does NOT get deeper descendants than immediate children!
                    $descendantTemplates = $group->listingTemplatesPivotWithTrashed()->select(Template::COLUMNS)->get();
                    $descendantTemplateGroups = $group->listingTemplateGroupsPivotWithTrashed()->get();

                    // dont need //$parentGroupListingPivot = $group->parentListingPivotWithTrashed()->first();
                    $parentGroupListingPivot = $this->getGroupListingPivot($group);

                    if(isset($parentGroupListingPivot->id)){
                        if($parentGroupListingPivot->trashed()){
                            $parentGroupListingPivot->restore();
                        }
                        // this restore also triggers a restore of all descendants who were deleted at the same time or after this group
                        if($group->trashed()){
                            $group->restore();
                        }

                        $this->restoreItems($descendantTemplates);
                        $this->restoreGroups($descendantTemplateGroups);

                        // this group belongs to a deleted group, ungroup it so we can restore it without it's parent
                        $group->makeRoot()->save();
                        $parentGroupListingPivot->group()->dissociate();
                        $parentGroupListingPivot->save();
                        $parentGroupListingPivot->updateSortOrderOfList();
                    }
                }
            } // end each group
            // throw new \Exception('debugging');
        });
    }

    protected function restoreGroups($groups, $level=0)
    {
        // ray($groups->pluck('name')->all())->green()->label('(before restoring them) GROUPS $groups -- LEVEL ::'.$level);

        $numChangedNodes = TemplateGroup::fixTree();
        // ray($numChangedNodes)->orange()->label('CHANGED NODES');
        if($numChangedNodes > 0){
            Log::warning("TemplateGroupService::restoreGroups() -- $numChangedNodes nodes were fixed in the TemplateGroup tree.");
        }

        $groups->makeVisible('deleted_at');

        foreach ($groups as $childGroup) {
            // ray($childGroup)->green()->label('before $childGroup->restore() -- LEVEL ::'.$level);
            $listingPivot = null;
            $listingPivot = $this->getGroupListingPivot($childGroup);

            // ray($listingPivot)->orange()->label('-- (before restore) TEMPLATE GROUP PIVOT $listingPivot -- LEVEL ::'.$level);

            /**
             * Check if trashed, because error is thrown if trying to restore a non-trashed item.
             * Some descendants will already be restored when the root group is restored, but
             * the nestedSet restore only restores descendants that were deleted at the same
             * time or later than the group.
             *
             * In order to restore ALL of the descendants, we need to restore them manually here.
             */
            if($listingPivot?->trashed()){
                $listingPivot->restore();
            }

            if(isset($childGroup) && $childGroup->trashed()){
                $childGroup->restore();
            }

            $listingPivot?->updateSortOrderOfList();

            $innerChildTemplates = $childGroup->listingTemplatesPivotWithTrashed()->select(Template::COLUMNS)->get();
            if($innerChildTemplates->isNotEmpty()) {
                $this->restoreItems($innerChildTemplates, $level + 1);
            }
            $innerChildGroups = $childGroup->listingTemplateGroupsPivotWithTrashed()->get();
            if($innerChildGroups->isNotEmpty()) {
                $this->restoreGroups($innerChildGroups, $level + 1);
            }
        }
    }

    protected function restoreItems($items, $level=0)
    {
        // ray($items->pluck('name')->all())->blue()->label('(before restoring them) TEMPLATES $items -- LEVEL ::'.$level);

        $items->makeVisible('deleted_at');

        foreach ($items as $childItem) {
            // ray($childItem)->blue()->label('before $childItem->restore() -- LEVEL ::'.$level);
            $listingPivot = null;
            $listingPivot = $this->getItemService()->getItemListingPivot($childItem);
            if($listingPivot?->trashed()){
                $listingPivot->restore();
            }

            /**
             * Check if trashed, because error is thrown if trying to restore a non-trashed item.
             * Some descendants will already be restored when the root group is restored, but
             * the nestedSet restore only restores descendants that were deleted at the same
             * time or later than the group.
             *
             * In order to restore ALL of the descendants, we need to restore them manually here.
             */
            if(isset($childItem) && $childItem->trashed()){
                $childItem->restore();
            }

            $listingPivot?->updateSortOrderOfList();
        }
    }
}
