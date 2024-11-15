<?php

namespace App\Traits\Services;

use App\Contracts\Models\GroupInterface;
use App\Contracts\Models\ItemInterface;
use App\Contracts\Models\ListingPivotInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

trait ItemTrait
{
    use ListingPivotTrait, UniqueNameTrait;

    abstract protected function getItemClass(): string;

    abstract protected function getGroupClass(): string;

    /**
     * @return \App\Traits\Services\GroupTrait
     */
    abstract protected function getGroupService();

    public function canItemBeInManyParentModels(): bool
    {
        return false;
    }

    public function setParentModelToItem(ItemInterface $item)
    {
        return $this;
    }

    abstract public function getItemListingPivot(ItemInterface $item): ListingPivotInterface;

    public function unsetRelationItemListingPivot(ItemInterface $item): void
    {
        $item->unsetRelation('parentListingPivot');
    }

    protected function getOldGroupOfItemWhenReplicating(ItemInterface $item): ?GroupInterface
    {
        return $item->group;
    }

    abstract protected function getQueryItemsWithoutGroup(ItemInterface $item): Relation;

    abstract protected function getParamGroupName(): string;

    protected function associateParentModel(ItemInterface $item, ListingPivotInterface $listingPivot): void
    {
    }

    protected function replicateDependencies(ItemInterface $item, ItemInterface $newItem, User $createdBy): void
    {
    }

    protected function baseBatchUpdate(array $params = []): Collection
    {
        return DB::transaction(function () use ($params) {
            /** @var \App\Contracts\Models\ItemInterface|\Illuminate\Database\Eloquent\Model $itemModel */
            $itemModel = new ($this->getItemClass());
            $paramGroupName = $this->getParamGroupName();

            $items = $itemModel->newQuery()->findMany($params['ids']);
            /** @var \App\Contracts\Models\ListingPivotInterface[]|\App\Contracts\Models\TreeSortable[] $listingPivots */
            $listingPivots = [];

            foreach ($items as $item) {
                /** @var \App\Contracts\Models\ItemInterface $item */

                if (array_key_exists('color', $params)) {
                    $item->fill([
                        'color' => $params['color'],
                    ]);

                    $item->save();
                }

                if (array_key_exists($paramGroupName, $params)) {
                    $this->setParentModelToItem($item);
                    $listingPivot = $this->getItemListingPivot($item);
                    $listingPivot->restoreIfTrashed();
                    $listingPivots[] = $listingPivot;

                    $this->unsetRelationItemListingPivot($item);

                    if ($listingPivot->group_id === $params[$paramGroupName]) {
                        continue;
                    }

                    if (! $this->canItemBeInManyParentModels()) {
                        $item->group()->associate($params[$paramGroupName]);
                    }

                    $item->save();

                    $listingPivot->group()->associate($params[$paramGroupName]);
                    $listingPivot->setHighestOrderNumber();
                    $listingPivot->saveOrRestore();

                    $this->unsetRelationItemListingPivot($item);

                    $item->name = $this->replicateUniqueNameOfItem($item);
                    $item->save();
                }
            }

            if (! empty($listingPivots)) {
                if (isset($params['sort_order'])) {
                    $this->sortManyToOrder($listingPivots, $params['sort_order']);
                } else {
                    $listingPivots[0]->updateSortOrderOfList();
                }
            }

            return $items;
        });
    }

    protected function baseBatchDuplicate(User $authUser, array $params = []): Collection
    {
        return DB::transaction(function () use ($authUser, $params) {
            /** @var \App\Contracts\Models\ItemInterface|\Illuminate\Database\Eloquent\Model $itemModel */
            $itemModel = new ($this->getItemClass());
            $paramGroupName = $this->getParamGroupName();

            $itemIdNewItem = [];
            $items = $itemModel->newQuery()->with(['company'])->findMany($params['ids']);

            if (array_key_exists($paramGroupName, $params)) {
                /** @var \App\Contracts\Models\ItemInterface|\Illuminate\Database\Eloquent\Model $groupModel */
                $groupModel = new ($this->getGroupClass());

                /** @var \App\Contracts\Models\GroupInterface|\Illuminate\Database\Eloquent\Model|null $group */
                $group = is_null($params[$paramGroupName])
                    ? null : $groupModel->newQuery()->find($params[$paramGroupName]);

                $itemIdNewItem = $this->replicateManyToGroup($items, $group, $authUser, $params);
            } else {
                $itemIdNewItem = $this->replicateMany($items, $authUser, [], $params);
            }

            // Sort order.
            /** @var \App\Contracts\Models\ListingPivotInterface[]|\App\Contracts\Models\TreeSortable[] $listingPivots */
            $listingPivots = [];

            foreach ($itemIdNewItem as $newItem) {
                $listingPivots[] = $this->getItemListingPivot($newItem);
            }

            if (! empty($listingPivots)) {
                if (isset($params['sort_order'])) {
                    $this->sortManyToOrder($listingPivots, $params['sort_order']);
                } else {
                    $listingPivots[0]->updateSortOrderOfList();
                }
            }

            //delete old ones
            if (isset($params['move_to']) && $params['move_to'])
                $this->baseBatchDelete($params);

            return $itemModel->newCollection(array_values($itemIdNewItem));
        });
    }

    protected function baseBatchDelete(array $params = []): void
    {
        DB::transaction(function () use ($params) {
            $listingPivot = null;

            /** @var \App\Contracts\Models\ItemInterface|\Illuminate\Database\Eloquent\Model $itemModel */
            $itemModel = new ($this->getItemClass());

            $items = $itemModel->newQuery()->findMany($params['ids']);

            foreach ($items as $item) {
                /** @var \App\Contracts\Models\ItemInterface $item */

                if (is_null($listingPivot)) {
                    $listingPivot = $this->getItemListingPivot($item);
                }

                $item->delete();
            }

            if (! is_null($listingPivot)) {
                $listingPivot->updateSortOrderOfList();
            }
        });
    }

    protected function baseBatchRestore(array $params = []): void
    {
        DB::transaction(function () use ($params) {
            $listingPivot = null;

            /** @var \App\Contracts\Models\ItemInterface|\Illuminate\Database\Eloquent\Model $itemModel */
            $itemModel = new ($this->getItemClass());

            $items = $itemModel->newQuery()->findMany($params['ids']);

            foreach ($items as $item) {
                /** @var \App\Contracts\Models\ItemInterface $item */

                if (is_null($listingPivot)) {
                    $listingPivot = $this->getItemListingPivot($item);
                }

                $item->restore();
            }

            if (! is_null($listingPivot)) {
                $listingPivot->updateSortOrderOfList();
            }
        });
    }

    public function replicateToGroup(
        ItemInterface|Model $item, GroupInterface|Model|null $group, User $createdBy, array $params = [], int $ikey = 0
    ): ItemInterface|Model
    {
        // The item can be not related to the current parent model (playlist, etc.),
        // i.e. be from other parent model, and are copying to the current parent model.
        // Therefore, we do not set the current parent model to the item.

        /** @var \App\Contracts\Models\ItemInterface|\Illuminate\Database\Eloquent\Model $newItem */
        $newItem = $item->replicate([
            'created_by',
        ]);

        if ($newItem->isRelation('createdBy')) {
            $newItem->createdBy()->associate($createdBy);
        }

        if (! $this->canItemBeInManyParentModels()) {
            $newItem->group()->associate($group);
        }

        $this->setParentModelToItem($newItem);
        $newListingPivot = $this->getItemListingPivot($newItem);
        $this->associateParentModel($newItem, $newListingPivot);

        //for page number pages only
        if (($item->page_number || $item->page_number===0) && $newItem->playlist_id === $item->playlist_id) {
            if (isset($params['new_page_num']) && $params['new_page_num']) {
                if ($ikey > 0)
                    $newItem->page_number = $this->replicateUniquePageNum($item->newQuery(), $params['new_page_num'], $newItem->playlist_id);
                else
                    $newItem->page_number = $params['new_page_num'];

                //delete existing page_number first
                $itemModel = new ($this->getItemClass());
                //$ditems = $itemModel->newQuery()->with(['group'])->where([['playlist_id', $newItem->playlist_id], ['page_number', $newItem->page_number]])->get();
                $pivot_playlist_id = $this->getParentModel()->id;
                $ditems = $itemModel->newQuery()
                            ->select('pages.id')
                            ->join('playlist_listings', 'playlist_listings.playlistable_id', '=', 'pages.id')
                            ->where([['playlist_id', $pivot_playlist_id], ['page_number', $newItem->page_number]])
                            ->get();

                foreach ($ditems as $ditem) {
                    // @var \App\Contracts\Models\ItemInterface $item
                    $ditem->delete();
                }
            }
            else
                $newItem->page_number = $this->replicateUniquePageNum($item->newQuery(), $item->page_number, $newItem->playlist_id);
        }

        $newItem->save();

        $this->saveReplicatedListingPivot(
            $newListingPivot, $newItem, $group?->id, $this->getItemListingPivot($item, true)
        );

        $this->unsetRelationItemListingPivot($newItem);

        $newItem->name = $this->replicateUniqueNameOfItem($newItem);
        $newItem->save();

        $this->replicateDependencies($item, $newItem, $createdBy);

        return $newItem;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection  $items
     * @param  \App\Contracts\Models\GroupInterface|\Illuminate\Database\Eloquent\Model|null  $group
     * @param  \App\Models\User  $createdBy
     * @param  array  $params
     * @return array An array that contains the item ids as keys and their new replicated items as values.
     */
    public function replicateManyToGroup(
        Collection $items, GroupInterface|Model|null $group, User $createdBy, array $params = []
    ): array
    {
        $itemIdNewItem = [];

        foreach ($items as $ikey => $item) {
            /** @var \App\Contracts\Models\ItemInterface|\Illuminate\Database\Eloquent\Model $item */
            $itemIdNewItem[$item->id] = $this->replicateToGroup($item, $group, $createdBy, $params, $ikey);
        }

        return $itemIdNewItem;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection  $items
     * @param  \App\Models\User  $createdBy
     * @param  array  $groupIdNewGroup An array that contains the group ids as keys
     * and their new replicated groups as values.
     * @param  array  $params
     * @return array An array that contains the item ids as keys and their new replicated items as values.
     */
    public function replicateMany(
        Collection $items, User $createdBy, array $groupIdNewGroup = [], array $params = []
    ): array
    {
        $itemIdNewItem = [];

        foreach ($items as $ikey => $item) {
            /** @var \App\Contracts\Models\ItemInterface|\Illuminate\Database\Eloquent\Model $item */

            $group = $this->getOldGroupOfItemWhenReplicating($item);

            if (! is_null($group) && array_key_exists($group->id, $groupIdNewGroup)) {
                $group = $groupIdNewGroup[$group->id];
            }

            $itemIdNewItem[$item->id] = $this->replicateToGroup($item, $group, $createdBy, $params, $ikey);
        }

        return $itemIdNewItem;
    }

    public function replicateUniqueNameOfItem(ItemInterface $item): string
    {
        $query = ! is_null($item->group)
            ? $item->group->items()
            : $this->getQueryItemsWithoutGroup($item);
        $query->whereKeyNot($item);

        return $this->replicateUniqueName($query, $item->name);
    }
}
