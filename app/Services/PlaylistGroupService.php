<?php

namespace App\Services;

use App\Contracts\Models\GroupInterface;
use App\Contracts\Models\ListingPivotInterface;
use App\Contracts\Models\TreeSortable;
use App\Models\Playlist;
use App\Models\PlaylistGroup;
use App\Models\Project;
use App\Models\User;
use App\Traits\Services\GroupTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

class PlaylistGroupService
{
    use GroupTrait;

    protected ?Project $project = null;

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;
        return $this;
    }

    protected function getItemClass(): string
    {
        return Playlist::class;
    }

    protected function getGroupClass(): string
    {
        return PlaylistGroup::class;
    }

    protected function getItemService(): PlaylistService
    {
        return new PlaylistService();
    }

    protected function getGroupListingPivot(PlaylistGroup|GroupInterface $group): ListingPivotInterface|TreeSortable
    {
        return $group->parentListingPivot()->withTrashed()->firstOrNew([
            'project_id' => $group->project_id,
        ]);
    }

    protected function getQueryGroupsWithoutGroup(PlaylistGroup|GroupInterface $group): Relation
    {
        return $group->company->playlistGroups()->whereNull('parent_id');
    }

    /**
     * Associate the group with the project.
     * The group will be dissociated from its parent group
     * if their projects are not the same. This is not recursive,
     * that is, the children of the group will not be changed.
     */
    protected function associateParentModel(PlaylistGroup|GroupInterface $group, ListingPivotInterface $listingPivot): void
    {
        if (is_null($this->project)) {
            return;
        }

        $group->project()->associate($this->project);

        if (! is_null($group->parent) && $group->parent->project_id !== $group->project_id) {
            $group->parent()->dissociate();
        }

        $listingPivot->associateList($group->project_id);
        $listingPivot->group()->associate($group->parent_id);
    }

    public function store(User $authUser, Project $project, array $params = []): PlaylistGroup
    {
        $playlistGroupParams = $params;
        unset($playlistGroupParams['sort_order']);

        $group = new PlaylistGroup($playlistGroupParams);

        $group->createdBy()->associate($authUser);
        $group->project()->associate($project);
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

    public function update(PlaylistGroup $group, array $params = []): PlaylistGroup
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

    public function delete(PlaylistGroup $group): void
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
