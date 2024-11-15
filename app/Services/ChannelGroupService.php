<?php

namespace App\Services;

use App\Contracts\Models\GroupInterface;
use App\Contracts\Models\ListingPivotInterface;
use App\Contracts\Models\TreeSortable;
use App\Models\Channel;
use App\Models\ChannelGroup;
use App\Models\CompanyChannelListing;
use App\Models\User;
use App\Traits\Services\GroupTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

class ChannelGroupService
{
    use GroupTrait;

    protected function getItemClass(): string
    {
        return Channel::class;
    }

    protected function getGroupClass(): string
    {
        return ChannelGroup::class;
    }

    protected function getItemService(): ChannelService
    {
        return new ChannelService();
    }

    protected function getGroupListingPivot(ChannelGroup|GroupInterface $group): ListingPivotInterface|TreeSortable
    {
        return $group->parentListingPivot()->withTrashed()->firstOrNew([
            'company_id' => $group->company_id,
        ]);
    }

    protected function getQueryGroupsWithoutGroup(ChannelGroup|GroupInterface $group): Relation
    {
        return $group->company->channelGroups()->whereNotNull('parent_id');
    }

    public function store(User $authUser, array $params = []): ChannelGroup
    {
        $channelGroupParams = $params;
        unset($channelGroupParams['sort_order']);

        $group = new ChannelGroup($channelGroupParams);

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

    public function update(ChannelGroup $group, array $params = []): ChannelGroup
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

    public function delete(ChannelGroup $group): void
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
}
