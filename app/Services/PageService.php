<?php

namespace App\Services;

use App\Contracts\Models\ItemInterface;
use App\Contracts\Models\ListingPivotInterface;
use App\Models\Channel;
use App\Models\ChannelLayer;
use App\Models\Company;
use App\Models\Page;
use App\Models\PageGroup;
use App\Models\Playlist;
use App\Models\PlaylistListing;
use App\Models\User;
use App\Traits\Services\ItemTrait;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Kalnoy\Nestedset\Collection as NestedsetCollection;
use LogicException;

class PageService
{
    use ItemTrait;

    const PAGE_SIZE = 500;

    protected Playlist|ChannelLayer|null $parentModel = null;

    protected Playlist|ChannelLayer|null $oldParentModel = null;

    protected bool $isOldParentModelSet = false;

    public function __construct()
    {
    }

    public function getParentModel(): Playlist|ChannelLayer|null
    {
        return $this->parentModel;
    }

    public function setParentModel(Playlist|ChannelLayer|null $parentModel): PageService
    {
        $this->parentModel = $parentModel;
        return $this;
    }

    public function getOldParentModel(): Playlist|ChannelLayer|null
    {
        return $this->oldParentModel;
    }

    public function setOldParentModel(Playlist|ChannelLayer|null $oldParentModel): PageService
    {
        $this->oldParentModel = $oldParentModel;
        $this->isOldParentModelSet = true;
        return $this;
    }

    public function unsetOldParentModel(): PageService
    {
        $this->oldParentModel = null;
        $this->isOldParentModelSet = false;
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

    protected function getGroupService(): PageGroupService
    {
        return (new PageGroupService())->setParentModel($this->parentModel);
    }

    public function canItemBeInManyParentModels(): bool
    {
        return true;
    }

    public function setParentModelToItem(Page|ItemInterface $item)
    {
        $item->setParentModel($this->parentModel);
        return $this;
    }

    public function getItemListingPivot(
        Page|ItemInterface $item, bool $isParentModelFromService = false
    ): ListingPivotInterface
    {
        $parentModel = $isParentModelFromService ? $this->parentModel : $item->getParentModel();
        return $item->playlistListingPivots()->withTrashed()->firstOrNew(
            ['playlist_id' => $parentModel?->id], ['company_id' => $item->company_id]
        );
    }

    public function unsetRelationItemListingPivot(Page|ItemInterface $item): void
    {
        $item->unsetRelation('playlistListingPivots');
    }

    protected function getOldGroupOfItemWhenReplicating(Page|ItemInterface $item): ?PageGroup
    {
        return $item->getGroup($this->isOldParentModelSet ? $this->oldParentModel : $this->parentModel);
    }

    protected function getQueryItemsWithoutGroup(Page|ItemInterface $item): Relation
    {
        throw new LogicException('The "getQueryItemsWithoutGroup" method does not apply to page.');
    }

    protected function getParamGroupName(): string
    {
        return 'page_group_id';
    }

    protected function associateParentModel(Page|ItemInterface $item, ListingPivotInterface $listingPivot): void
    {
        if (! $listingPivot->isBelongedToList($this->parentModel)) {
            $listingPivot->group()->dissociate();
        }

        $listingPivot->associateList($this->parentModel);
    }

    public function listing(Playlist $playlist): SupportCollection
    {
        $listingPivots = $playlist->listingPivots()
            ->with(['playlistable'])
            ->orderBy('sort_order')
            ->get();

        $pages = (new Page())->newCollection();
        /** @var \Kalnoy\Nestedset\Collection $pageGroups */
        $pageGroups = (new PageGroup())->newCollection();

        foreach ($listingPivots as $listingPivot) {
            /** @var \App\Models\PlaylistListing $listingPivot */

            if ($listingPivot->playlistable instanceof Page) {
                $pages->add($listingPivot->playlistable);
                $listingPivot->playlistable->setParentModel($playlist);
            } elseif ($listingPivot->playlistable instanceof PageGroup) {
                $pageGroups->add($listingPivot->playlistable);
            }
        }

        $pages->load(['playlistListingPivots', 'createdBy:id,email']);
        $pageGroups->load(['parentListingPivot']);

        return PageGroupService::toTreeComponents($listingPivots->pluck('playlistable'));
    }

    public function companyPageListing(User $authUser): LengthAwarePaginator
    {
        return $authUser->company->withoutPlaylistPages()
            ->with([
                'template',
                'channel',
                'channelEntity',
                'playlistListingPivots',
                'original',
                'original.template',
                'original.channel',
                'original.channelEntity',
                'original.playlistListingPivots',
            ])
            ->orderByPivot('sort_order')
            ->paginate(self::PAGE_SIZE);
    }

    public function generateUniqueName(User $authUser, Playlist $parentModel, array $params = []): array
    {
        $this->setParentModel($parentModel);

        return [
            'name' => $this->replicateUniqueNameByListingParams(
                $params['name'], $authUser->company_id, $parentModel->id, $params['page_group_id'] ?? null
            ),
        ];
    }

    public function store(User $authUser, Playlist|ChannelLayer|null $parentModel, array $params = []): Page
    {
        $generateUniqueName = false;

        if (array_key_exists('generate_unique_name', $params)) {
            $generateUniqueName = (bool) $params['generate_unique_name'];
            unset($params['generate_unique_name']);
        }

        $this->setParentModel($parentModel);

        $page = new Page($params);

        if (! array_key_exists('color', $params) && ! is_null($page->template)) {
            $page->color = $page->template->color;
        }

        $page->setParentModel($parentModel);
        $page->createdBy()->associate($authUser);
        $page->company()->associate($authUser->company);

        $defaultChannel = $authUser->company->channels()->defaultOfType($page->template?->engine)->first();
        $defaultGroup = null;
        if (!$defaultChannel) {
            $defaultGroup = $authUser->company->channelGroups()->where('is_default', true)->first();
        }


        if ($defaultChannel && !$page->channel_id) {
            $page->channel()->associate($defaultChannel);
        }
        if (!$page->channel_entity_id) {
            if ($defaultChannel) {
                $page->channelEntity()->associate($defaultChannel);
            } elseif ($defaultGroup) {
                $page->channelEntity()->associate($defaultGroup);
            }
        }

        // Sync channel_id and channel_entity_id.
        if (array_key_exists('channel_id', $params) && ! array_key_exists('channel_entity_id', $params)) {
            $page->channelEntity()->associate($page->channel);
        } elseif (! array_key_exists('channel_id', $params) && array_key_exists('channel_entity_id', $params)) {
            if (is_null($page->channelEntity) || $page->channelEntity instanceof Channel) {
                $page->channel()->associate($page->channelEntity);
            }
        }

        DB::transaction(function () use ($page, $params, $generateUniqueName, $parentModel) {
            if ($previewUrl = $this->storePreview($page, $params)) {
                $page->preview_url = $previewUrl;
            }
            $page->save();

            if (is_null($parentModel) || $parentModel instanceof Playlist) {
                // ChannelLayer doesn't need this
                $listingPivot = $this->getItemListingPivot($page);

                $listingPivot->group()->associate($params['page_group_id'] ?? null);
                $listingPivot->saveOrRestore();

                if (isset($params['sort_order'])) {
                    $listingPivot->moveToOrder($params['sort_order']);
                } else {
                    $listingPivot->updateSortOrderOfList();
                }

                $this->unsetRelationItemListingPivot($page);
            }

            if (!$page->page_number) {
                $page->page_number = $this->replicateUniquePageNum($page->newQuery(), 0, $page->playlist_id);
            }

            if ($generateUniqueName) {
                $page->name = $this->replicateUniqueNameOfItem($page);
                $page->save();
            }
        });

        return $page;
    }

    public function update(Page $page, Playlist|ChannelLayer|null $parentModel, array $params = []): Page
    {
        $this->setParentModel($parentModel);
        $page->setParentModel($parentModel);

        DB::transaction(function () use ($page, $params, $parentModel) {
            if (isset($params['workflow_run_log_id'])) {
                WorkflowRunLogService::startListening($params['workflow_run_log_id']);
            }

            $original = $page->original ?? $page;
            $original->setParentModel($parentModel);

            $oldGroup = $page->group;
            if ($previewUrl = $this->storePreview($original, $params)) {
                $original->preview_url = $previewUrl;
            }
            $referenceParams = collect($params)->only(['name', 'is_live', 'page_group_id'])->all();
            $page->update($referenceParams);
            $original->update(array_diff_key($params, $referenceParams));

            // Sync channel_id and channel_entity_id.
            if (array_key_exists('channel_id', $params) && ! array_key_exists('channel_entity_id', $params)) {
                $original->channelEntity()->associate($original->channel);
                $original->save();
            } elseif (! array_key_exists('channel_id', $params) && array_key_exists('channel_entity_id', $params)) {
                if (is_null($original->channelEntity) || $original->channelEntity instanceof Channel) {
                    $original->channel()->associate($original->channelEntity);
                    $original->save();
                }
            }

            if (is_null($parentModel) || $parentModel instanceof Playlist) {
                // ChannelLayer doesn't need this

                $listingPivot = $this->getItemListingPivot($page);

                if (array_key_exists('page_group_id', $params)) {
                    $listingPivot->group()->associate($params['page_group_id']);
                }

                $listingPivot->saveOrRestore();

                if ($listingPivot->group_id !== $oldGroup?->id) {
                    $listingPivot->setHighestOrderNumber();
                    $listingPivot->saveOrRestore();
                }

                $this->unsetRelationItemListingPivot($page);
                $this->unsetRelationItemListingPivot($original);
            }

            if (isset($params['workflow_run_log_id'])) {
                WorkflowRunLogService::stopListening(true);
            }
        });

        return $page;
    }

    public function batchUpdate(Playlist $playlist, array $params = []): Collection
    {
        $this->setParentModel($playlist);
        return $this->baseBatchUpdate($params);
    }

    public function batchDuplicate(User $authUser, Playlist $playlist, array $params = []): Collection
    {
        $pages = $this->setParentModel($playlist)->baseBatchDuplicate($authUser, $params);
        $pages->makeHidden(['data']);

        foreach ($pages as $page) {
            /** @var \App\Models\Page $page */
            $page->unsetRelations();
        }

        return $pages;
    }

    public function batchAttach(User $authUser, array $params = []): void
    {
        DB::transaction(function () use ($authUser, $params) {
            /** @var \App\Models\Playlist $playlist */
            $playlist = $authUser->company->playlists()->find($params['playlist_id']);
            $pageGroup = null;

            if (isset($params['page_group_id'])) {
                $pageGroup = $playlist->pageGroups()->find($params['page_group_id']);
            }

            /** @var \App\Contracts\Models\ListingPivotInterface[]|\App\Contracts\Models\TreeSortable[] $listingPivots */
            $listingPivots = [];

            foreach ($params['ids'] as $id) {
                /** @var \App\Models\Page $page */
                $page = $authUser->company->withoutPlaylistPages()->find($id);

                /** @var \App\Models\PlaylistListing $listingPivot */
                $listingPivot = $page->playlistListingPivots()->withTrashed()->firstOrNew([
                    'playlist_id' => $playlist->id,
                ]);

                $listingPivot->group()->associate($pageGroup);
                $listingPivot->setHighestOrderNumber();
                $listingPivot->saveOrRestore();

                $listingPivots[] = $listingPivot;

                $this->unsetRelationItemListingPivot($page);
            }

            if (! empty($listingPivots)) {
                if (isset($params['sort_order'])) {
                    $this->sortManyToOrder($listingPivots, $params['sort_order']);
                } else {
                    $listingPivots[0]->updateSortOrderOfList();
                }
            }
        });
    }

    public function batchDetach(User $authUser, array $params = []): void
    {
        DB::transaction(function () use ($authUser, $params) {
            /** @var \App\Models\Playlist $playlist */
            $playlist = $authUser->company->playlists()->find($params['playlist_id']);

            $listingPivotUpdateList = null;

            foreach ($params['ids'] as $id) {
                /** @var \App\Models\Page $page */
                $page = $authUser->company->withoutPlaylistPages()->find($id);

                /** @var \App\Models\PlaylistListing $listingPivot */
                $listingPivot = $page->playlistListingPivots()
                    ->where('playlist_id', $playlist->id)
                    ->first();

                if (! is_null($listingPivot)) {
                    if (is_null($listingPivotUpdateList)) {
                        $listingPivotUpdateList = $listingPivot;
                    }

                    $listingPivot->delete();
                }
            }

            if (! is_null($listingPivotUpdateList)) {
                $listingPivotUpdateList->updateSortOrderOfList();
            }
        });
    }

    public function storeReference(User $authUser, Page $page): Page
    {
        $original = $page->original ?? $page;

        $reference = new Page();
        $reference->company()->associate($authUser->company);
        $reference->original()->associate($original);
        $reference->createdBy()->associate($authUser);

        $reference->name = $original->name;
        $reference->page_number = 0;
        $reference->save();

        $listingPivot = $this->getItemListingPivot($reference);
        $listingPivot->saveOrRestore();
        $listingPivot->updateSortOrderOfList();

        $reference->name = $this->replicateUniqueNameOfItem($reference);
        $reference->save();
        $reference->refresh();

        return $reference;
    }

    public function delete(Page $page, Playlist|ChannelLayer|null $parentModel): void
    {
        $original = $page->original ?? $page;

        $this->setParentModel($parentModel);
        $original->setParentModel($parentModel);

        DB::transaction(function () use ($page, $original) {
            $listingPivot = $this->getItemListingPivot($page);
            $original->delete();
            $listingPivot->updateSortOrderOfList();
            $page->refresh();
        });
    }

    public function batchDelete(Playlist $playlist, array $params = []): void
    {
        $this->setParentModel($playlist);
        $this->baseBatchDelete($params);
    }

    public function batchRestore(Playlist $playlist, array $params = []): void
    {
        $this->setParentModel($playlist);

        DB::transaction(function () use ($playlist, $params) {
            /** @var \Illuminate\Database\Eloquent\Builder $query */
            $query = Page::query()->onlyTrashed();
            $pages = $query->with([
                'groupsViaPlaylistListingWithPivotTrashed' => function ($withQuery) use ($playlist) {
                    /** @var \Illuminate\Database\Eloquent\Relations\BelongsToMany $withQuery */
                    $withQuery->withTrashed();
                },
            ])->findMany($params['ids']);

            /** @var \App\Models\PlaylistListing[] $listingPivots */
            $listingPivots = [];
            $groupService = new PageGroupService();

            foreach ($pages as $page) {
                /** @var \App\Models\Page $page */

                $page->setParentModel($playlist);
                $page->restore();

                $group = $groupService->restoreGroupAndAncestors($page->groupsViaPlaylistListingWithPivotTrashed->first());

                /** @var \App\Models\PlaylistListing $listingPivot */
                $listingPivot = $this->getItemListingPivot($page);
                $listingPivot->group()->associate($group);
                $listingPivot->saveOrRestore();

                if (! isset($listingPivots[$listingPivot->company_id ?? 0])) {
                    $listingPivots[$listingPivot->company_id ?? 0] = $listingPivot;
                }

                $this->unsetRelationItemListingPivot($page);

                $page->name = $this->replicateUniqueNameOfItem($page);
                $page->save();
            }

            foreach ($listingPivots as $listingPivot) {
                $listingPivot->updateSortOrderOfList();
            }
        });
    }

    /**
     * @param  \App\Contracts\Models\ItemInterface  $item The page and the playlist listing must have been saved.
     * @return string
     */
    public function replicateUniqueNameOfItem(ItemInterface $item): string
    {
        /** @var \App\Models\Page $item */

        $listingsQuery = PlaylistListing::query()
            ->select(['company_id'])
            ->selectRaw('IFNULL(`playlist_id`, 0)')
            ->selectRaw('IFNULL(`group_id`, 0)')
            ->where('playlistable_type', Page::class)
            ->where('playlistable_id', $item->id);

        $pageIdsQuery = PlaylistListing::query()
            ->select(['playlistable_id'])
            ->where('playlistable_type', Page::class)
            ->whereIn(
                DB::raw('(`company_id`, IFNULL(`playlist_id`, 0), IFNULL(`group_id`, 0))'),
                $listingsQuery
            );

        $query = Page::query()->whereIn('id', $pageIdsQuery)->whereKeyNot($item);

        return $this->replicateUniqueName($query, $item->name);
    }

    protected function replicateUniqueNameByListingParams(string $name, int $companyId, ?int $playlistId, ?int $groupId)
    {
        $pageIdsQuery = PlaylistListing::query()
            ->select(['playlistable_id'])
            ->where('group_id', $groupId)
            ->where('playlist_id', $playlistId)
            ->where('company_id', $companyId)
            ->where('playlistable_type', Page::class);

        $query = Page::query()->whereIn('id', $pageIdsQuery);

        return $this->replicateUniqueName($query, $name);
    }

    public function buildTreeToUpdateSort(?Playlist $playlist, Company $company): SupportCollection
    {
        $pages = new Collection();
        $pageGroups = new NestedsetCollection();

        if (! is_null($playlist)) {
            $pages = $playlist->pages()
                ->wherePivotNull('group_id')
                ->orderByPivot('sort_order')
                ->orderByPivot('id')
                ->get();

            /** @var \Kalnoy\Nestedset\Collection $pageGroups */
            $pageGroups = $playlist->listingPageGroups()
                ->with([
                    'items' => function (MorphToMany $morphToMany) {
                        $morphToMany
                            ->orderByPivot('sort_order')
                            ->orderByPivot('id');
                    },
                ])
                ->orderByPivot('sort_order')
                ->orderByPivot('id')
                ->get();
        } else {
            $pages = $company->withoutPlaylistPages()
                ->orderByPivot('sort_order')
                ->orderByPivot('id')
                ->get();
        }

        foreach ($pages as $page) {
            /** @var \App\Models\Page $page */
            $page->setParentModel($playlist);
        }

        foreach ($pageGroups as $pageGroup) {
            /** @var \App\Models\PageGroup $pageGroup */

            foreach ($pageGroup->items as $page) {
                /** @var \App\Models\Page $page */
                $page->setParentModel($playlist);
            }
        }

        return PageGroupService::sortTreeComponents($pageGroups->toTree()->toBase()->merge($pages));
    }

    private function storePreview(Page $page, array $params) : ?string
    {
        if (empty($params['preview_img'])) {
            return null;
        }
        $storage = Storage::disk(config('media-library.disk_name'));
        $url = 'pages/preview-' . uniqid() . '.jpg';
        if ($page->preview_url) {
            $storage->delete($page->preview_url);
        }
        $storage->put($url, base64_decode($params['preview_img']));
        return $url;
    }
}
