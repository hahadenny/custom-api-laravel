<?php

namespace App\Services;

use App\Contracts\Models\GroupInterface;
use App\Contracts\Models\ListingPivotInterface;
use App\Contracts\Models\TreeSortable;
use App\Enums\ChangeLogAction;
use App\Models\ChangeLog;
use App\Models\Page;
use App\Models\PageGroup;
use App\Models\Playlist;
use App\Models\User;
use App\Traits\Services\GroupTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class PageGroupService
{
    use GroupTrait;

    protected ?User $user = null;

    protected ?Playlist $parentModel = null;

    public function __construct()
    {
    }

    public function getParentModel(): ?Playlist
    {
        return $this->parentModel;
    }

    public function setParentModel(?Playlist $parentModel): PageGroupService
    {
        $this->parentModel = $parentModel;
        return $this;
    }

    protected function getItemClass(): string
    {
        return Page::class;
    }

    protected function getGroupClass(): string
    {
        return PageGroup::class;
    }

    protected function getItemService(): PageService
    {
        return (new PageService())->setParentModel($this->parentModel);
    }

    protected function getGroupListingPivot(PageGroup|GroupInterface $group): ListingPivotInterface|TreeSortable
    {
        return $group->parentListingPivot()->withTrashed()->firstOrNew(
            ['playlist_id' => $group->playlist_id], ['company_id' => $group->company_id]
        );
    }

    protected function getQueryGroupsWithoutGroup(PageGroup|GroupInterface $group): Relation
    {
        return $group->playlist->pageGroups()->whereNull('parent_id');
    }

    /**
     * Associate the group with the playlist.
     * The group will be dissociated from its parent group
     * if their playlists are not the same. This is not recursive,
     * that is, the children of the group will not be changed.
     */
    protected function associateParentModel(PageGroup|GroupInterface $group, ListingPivotInterface $listingPivot): void
    {
        if (! $this->parentModel instanceof Playlist) {
            throw new UnexpectedValueException('A page group can only be in a playlist.');
        }

        $group->playlist()->associate($this->parentModel);

        if (! is_null($group->parent) && $group->parent->playlist_id !== $group->playlist_id) {
            $group->parent()->dissociate();
        }

        $listingPivot->associateList($group->playlist_id);
        $listingPivot->group()->associate($group->parent_id);
    }

    public function store(User $authUser, Playlist $playlist, array $params = []): PageGroup
    {
        $this->setParentModel($playlist);

        $pageGroupParams = $params;
        unset($pageGroupParams['sort_order']);

        $group = new PageGroup($pageGroupParams);

        $group->playlist()->associate($playlist);
        $group->company()->associate($authUser->company);

        DB::transaction(function () use ($group, $params) {
            if (isset($params['workflow_run_log_id'])) {
                WorkflowRunLogService::startListening($params['workflow_run_log_id']);
            }

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

            if (isset($params['workflow_run_log_id'])) {
                WorkflowRunLogService::stopListening(true);
            }
        });

        return $group;
    }

    public function update(PageGroup $group, Playlist $playlist, array $params = []): PageGroup
    {
        $this->setParentModel($playlist);

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

    public function batchUpdate(Playlist $playlist, array $params = []): Collection
    {
        $this->setParentModel($playlist);
        return $this->baseBatchUpdate($params);
    }

    public function batchDuplicate(User $authUser, Playlist $playlist, array $params = []): Collection
    {
        [
            'groups' => $groups,
            'items' => $pages,
        ] = $this->setParentModel($playlist)->baseBatchDuplicate($authUser, $params);

        foreach ($groups as $group) {
            /** @var \App\Models\PageGroup $group */
            $group->unsetRelations();
        }

        foreach ($pages as $page) {
            /** @var \App\Models\Page $page */
            $page->unsetRelations();
            $page->makeHidden(['data']);
        }

        return Collection::make($groups)->add(['pages' => $pages]);
    }

    public function batchUngroup(Playlist $playlist, array $params = []): void
    {
        $this->setParentModel($playlist);
        $this->baseBatchUngroup($params);
    }

    public function delete(PageGroup $group, Playlist $playlist): void
    {
        $this->setParentModel($playlist);

        DB::transaction(function () use ($group) {
            $listingPivot = $this->getGroupListingPivot($group);

            $this->deleteGroup($group);

            $listingPivot->updateSortOrderOfList();
        });
    }

    public function batchDelete(User $authUser, Playlist $playlist, array $params = []): void
    {
        $this->setParentModel($playlist);
        $this->user = $authUser;
        $this->baseBatchDelete($params);
    }

    public function batchRestore(Playlist $playlist, array $params = []): void
    {
        $this->setParentModel($playlist);

        DB::transaction(function () use ($params) {
            /** @var \Illuminate\Database\Eloquent\Builder $query */
            $query = PageGroup::query()->withDepth()->withTrashed();
            $pageGroups = $query->with(['changeLog'])->findMany($params['ids']);
            /** @var \App\Models\PlaylistListing[] $listingPivots */
            $listingPivots = [];

            foreach ($pageGroups as $pageGroup) {
                /** @var \App\Models\PageGroup $pageGroup */

                if (! $pageGroup->trashed() || is_null($pageGroup->changeLog)) {
                    continue;
                }

                if ($pageGroup->depth > $this->getMaxNodeDepth()) {
                    $pageGroup->parent()->associate(null);
                } else {
                    $pageGroupIdQuery = $pageGroup->changeLog->children()
                        ->select(['changeable_id'])
                        ->where('changeable_type', PageGroup::class);

                    $maxDepthDescendant = $pageGroup->descendants()->whereIn('id', $pageGroupIdQuery)
                        ->withDepth()->withTrashed()->orderByDesc('depth')->first();

                    if (! is_null($maxDepthDescendant) && $maxDepthDescendant->depth > $this->getMaxNodeDepth()) {
                        $pageGroup->parent()->associate(null);
                    }
                }

                $this->restoreGroupAndAncestors($pageGroup, true);
                $pageService = $this->getItemService();

                $pageGroup->changeLog->children()->with(['changeable' => function ($q) {
                    /** @var \Illuminate\Database\Eloquent\Relations\Relation $q */
                    $q->onlyTrashed();
                }])->each(function ($changeLog) use ($pageGroup, $pageService, &$listingPivots) {
                    /** @var \App\Models\ChangeLog $changeLog */

                    if (! is_null($changeLog->changeable)) {
                        $changeLog->changeable->restore();
                        $listingPivot = null;

                        if ($changeLog->changeable instanceof PageGroup) {
                            /** @var \App\Models\PlaylistListing $listingPivot */
                            $listingPivot = $this->getGroupListingPivot($changeLog->changeable);
                            $listingPivot->group()->associate($changeLog->changeable->parent_id);
                        } elseif ($changeLog->changeable instanceof Page) {
                            /** @var \App\Models\PlaylistListing $listingPivot */
                            $pageService->setParentModelToItem($changeLog->changeable);
                            $listingPivot = $pageService->getItemListingPivot($changeLog->changeable);
                            $pageService->unsetRelationItemListingPivot($changeLog->changeable);

                            if (is_null($listingPivot->group_id)) {
                                $listingPivot->group()->associate($pageGroup);
                            }
                        } else {
                            return;
                        }

                        $listingPivot->saveOrRestore();

                        if (! isset($listingPivots[$listingPivot->company_id ?? 0])) {
                            $listingPivots[$listingPivot->company_id ?? 0] = $listingPivot;
                        }
                    }

                    $changeLog->delete();
                });

                $pageGroup->changeLog->delete();
            }

            foreach ($listingPivots as $listingPivot) {
                $listingPivot->updateSortOrderOfList();
            }
        });
    }

    protected function createDeletionLogChange(Model $model, ?ChangeLog $parent = null): ChangeLog
    {
        $changeLog = new ChangeLog();
        $changeLog->action = ChangeLogAction::Delete;
        $changeLog->changeable()->associate($model);
        $changeLog->user()->associate($this->user);
        $changeLog->parent()->associate($parent);
        $changeLog->save();

        return $changeLog;
    }
}
