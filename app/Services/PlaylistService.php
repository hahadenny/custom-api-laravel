<?php

namespace App\Services;

use App\Contracts\Models\ItemInterface;
use App\Contracts\Models\ListingPivotInterface;
use App\Models\Playlist;
use App\Models\PlaylistGroup;
use App\Models\Project;
use App\Models\User;
use App\Traits\Services\ItemTrait;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class PlaylistService
{
    use ItemTrait;

    protected ?Project $project = null;

    public function __construct()
    {
    }

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

    protected function getGroupService(): PlaylistGroupService
    {
        return new PlaylistGroupService();
    }

    public function getItemListingPivot(Playlist|ItemInterface $item): ListingPivotInterface
    {
        return $item->parentListingPivot()->withTrashed()->firstOrNew([
            'project_id' => $item->project_id,
        ]);
    }

    protected function getQueryItemsWithoutGroup(Playlist|ItemInterface $item): Relation
    {
        return $item->project->playlists()->whereNull('playlist_group_id');
    }

    protected function getParamGroupName(): string
    {
        return 'playlist_group_id';
    }

    protected function associateParentModel(Playlist|ItemInterface $item, ListingPivotInterface $listingPivot): void
    {
        if (is_null($this->project)) {
            return;
        }

        $item->project()->associate($this->project);

        if (! is_null($item->group) && $item->group->project_id !== $item->project_id) {
            $item->group()->dissociate();
        }

        $listingPivot->associateList($item->project);
        $listingPivot->group()->associate($item->group);
    }

    protected function replicateDependencies(Playlist $item, Playlist $newItem, User $createdBy): void
    {
        $groupIdNewGroup = (new PageGroupService())
            ->setParentModel($newItem)
            ->replicateMany($item->pageGroups, $createdBy);

        (new PageService())
            ->setOldParentModel($item)
            ->setParentModel($newItem)
            ->replicateMany($item->pages, $createdBy, $groupIdNewGroup);
    }

    public function listing(Project $project): SupportCollection
    {
        $playlists = $project->listingPlaylists()
            ->with(['parentListingPivot', 'createdBy:id,email'])
            ->wherePivotNull('group_id')
            ->orderByPivot('sort_order')
            ->get();

        /** @var \Kalnoy\Nestedset\Collection $playlistGroups */
        $playlistGroups = $project->listingPlaylistGroups()
            ->with([
                'parentListingPivot',
                'listingPlaylists' => function (MorphToMany $morphToMany) {
                    $morphToMany->orderByPivot('sort_order');
                },
                'listingPlaylists.parentListingPivot', 'listingPlaylists.createdBy:id,email',
            ])
            ->orderByPivot('sort_order')
            ->get();

        $playlists->makeHidden(['group']);
        $playlistGroups->makeHidden(['children', 'items', 'playlists', 'listingPlaylists']);

        foreach ($playlistGroups as $playlistGroup) {
            /** @var \App\Models\PlaylistGroup $playlistGroup */
            $playlistGroup->listingPlaylists->makeHidden(['group']);
            $playlistGroup->setRelation('items', $playlistGroup->listingPlaylists);
        }

        return PlaylistGroupService::sortTreeComponents($playlistGroups->toTree()->toBase()->merge($playlists));
    }

    public function plainListing(User $authUser, array $filter = [], $pageSize = 50): LengthAwarePaginator
    {
        $query = $authUser->company->playlists()->orderByDesc('id');

        if (isset($filter['has_media'])) {
            $pagePlaylistIdQuery = $authUser->company->playlistPages()
                ->select(['playlist_id'])
                ->where('has_media', $filter['has_media']);

            $query->whereIn('id', $pagePlaylistIdQuery);
        }
        if (!empty($filter['is_active'])) {
            $query->where('is_active', true);
        }

        if (!empty($filter['with_pages'])) {
            $query->with(['pages:id,name,channel_id', 'pages.channel:id,name']);
        }

        return $query->paginate($pageSize);
    }

    public function store(User $authUser, Project $project, array $params = []): Playlist
    {
        $playlistParams = $params;
        unset($playlistParams['sort_order']);

        $playlist = new Playlist($playlistParams);

        $playlist->createdBy()->associate($authUser);
        $playlist->project()->associate($project);
        $playlist->company()->associate($authUser->company);

        DB::transaction(function () use ($playlist, $params) {
            $playlist->save();

            $listingPivot = $this->getItemListingPivot($playlist);
            $listingPivot->group()->associate($playlist->playlist_group_id);
            $listingPivot->saveOrRestore();

            if (isset($params['sort_order'])) {
                $listingPivot->moveToOrder($params['sort_order']);
            } else {
                $listingPivot->updateSortOrderOfList();
            }
        });

        return $playlist;
    }

    public function update(Playlist $playlist, array $params = []): Playlist
    {
        DB::transaction(function () use ($playlist, $params) {
            $oldGroup = $playlist->group;

            $playlistParams = $params;
            unset($playlistParams['sort_order']);

            $playlist->update($playlistParams);
            $playlist->refresh();

            $listingPivot = $this->getItemListingPivot($playlist);
            $listingPivot->group()->associate($playlist->playlist_group_id);
            $listingPivot->saveOrRestore();

            if ($playlist->playlist_group_id !== $oldGroup?->id) {
                $listingPivot->setHighestOrderNumber();
                $listingPivot->saveOrRestore();
            }

            if (isset($params['sort_order'])) {
                $listingPivot->moveToOrder($params['sort_order']);
            } else {
                $listingPivot->updateSortOrderOfList();
            }
        });

        return $playlist;
    }

    public function batchUpdate(array $params = []): Collection
    {
        return $this->baseBatchUpdate($params);
    }

    public function batchDuplicate(User $authUser, array $params = []): void
    {
        $this->baseBatchDuplicate($authUser, $params);
    }

    public function delete(Playlist $playlist): void
    {
        DB::transaction(function () use ($playlist) {
            $listingPivot = $this->getItemListingPivot($playlist);

            $playlist->delete();

            $listingPivot->updateSortOrderOfList();
        });
    }

    public function batchDelete(array $params = []): void
    {
        $this->baseBatchDelete($params);
    }

    public function buildTreeToUpdateSort(Project $project): SupportCollection
    {
        $playlists = $project->listingPlaylists()
            ->wherePivotNull('group_id')
            ->orderByPivot('sort_order')
            ->orderByPivot('id')
            ->get();

        /** @var \Kalnoy\Nestedset\Collection $playlistGroups */
        $playlistGroups = $project->listingPlaylistGroups()
            ->with([
                'listingPlaylists' => function (MorphToMany $morphToMany) {
                    $morphToMany
                        ->orderByPivot('sort_order')
                        ->orderByPivot('id');
                },
            ])
            ->orderByPivot('sort_order')
            ->orderByPivot('id')
            ->get();

        foreach ($playlistGroups as $playlistGroup) {
            /** @var \App\Models\PlaylistGroup $playlistGroup */
            $playlistGroup->setRelation('items', $playlistGroup->listingPlaylists);
        }

        return PlaylistGroupService::sortTreeComponents($playlistGroups->toTree()->toBase()->merge($playlists));
    }
}
