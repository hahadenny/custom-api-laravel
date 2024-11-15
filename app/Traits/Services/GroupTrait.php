<?php

namespace App\Traits\Services;

use App\Contracts\Models\GroupInterface;
use App\Contracts\Models\ItemInterface;
use App\Contracts\Models\ListingPivotInterface;
use App\Contracts\Models\TreeSortable;
use App\Models\ChangeLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

trait GroupTrait
{
    use ListingPivotTrait, UniqueNameTrait;

    abstract protected function getItemClass(): string;

    abstract protected function getGroupClass(): string;

    /**
     * @return \App\Traits\Services\ItemTrait
     */
    abstract protected function getItemService();

    abstract protected function getGroupListingPivot(GroupInterface $group): ListingPivotInterface|TreeSortable;

    abstract protected function getQueryGroupsWithoutGroup(GroupInterface $group): Relation;

    protected function associateParentModel(GroupInterface $group, ListingPivotInterface $listingPivot): void
    {
    }

    /**
     * Zero-based.
     */
    protected function getMaxNodeDepth(): int
    {
        return 2;
    }

    protected function baseBatchUpdate(array $params = []): Collection
    {
        return DB::transaction(function () use ($params) {
            /** @var \App\Contracts\Models\GroupInterface|\Illuminate\Database\Eloquent\Model $groupModel */
            $groupModel = new ($this->getGroupClass());
            $groups = $groupModel->newCollection();
            $itemModel = new ($this->getItemClass());
            /** @var \App\Contracts\Models\ListingPivotInterface[]|\App\Contracts\Models\TreeSortable[] $listingPivots */
            $listingPivots = [];

            $itemService = $this->getItemService();
            if (!empty($params['items_order'])) {
                foreach ($params['items_order'] as $item) {
                    $listingPivot = $item['type'] === 'group' ?
                        $this->processGroup($item['id'], $groupModel, $params) :
                        $this->processItem($item['id'], $itemModel, $params, $itemService, $groupModel);
                    if ($listingPivot) {
                        $listingPivots[] = $listingPivot;
                    }
                }
            } else {
                if (isset($params['ids'])) {
                    foreach ($params['ids'] as $groupId) {
                        $listingPivot = $this->processGroup($groupId, $groupModel, $params);
                        if ($listingPivot) {
                            $listingPivots[] = $listingPivot;
                        }
                    }
                }

                if (isset($params['item_ids'])) {
                    /** @var \App\Contracts\Models\ItemInterface|\Illuminate\Database\Eloquent\Model $itemModel */
                    foreach ($params['item_ids'] as $itemId) {
                        $listingPivot = $this->processItem($itemId, $itemModel, $params, $itemService, $groupModel);
                        if ($listingPivot) {
                            $listingPivots[] = $listingPivot;
                        }
                    }
                }
            }



            if (! empty($listingPivots)) {
                if (isset($params['sort_order'])) {
                    $this->sortManyToOrder($listingPivots, $params['sort_order']);
                } else {
                    $listingPivots[0]->updateSortOrderOfList();
                }
            }

            return $groups;
        });
    }

    private function processGroup($groupId, $groupModel, $params)
    {
        /** @var \App\Contracts\Models\GroupInterface $group */
        $group = $groupModel->newQuery()->findOrFail($groupId);

        if (array_key_exists('color', $params)) {
            $group->fill([
                'color' => $params['color'],
            ]);

            $group->save();
        }

        if (array_key_exists('parent_id', $params)) {
            $listingPivot = $this->getGroupListingPivot($group);
            $listingPivot->restoreIfTrashed();

            if ($group->parent_id === $params['parent_id']) {
                return $listingPivot;
            }

            $parent = ! is_null($params['parent_id'])
                ? $groupModel->newQuery()->find($params['parent_id']) : null;
            $group->parent()->associate($parent);

            $group->name = $this->replicateUniqueNameOfGroup($group);
            $group->save();

            $listingPivot->group()->associate($group->parent_id);
            $listingPivot->setHighestOrderNumber();
            $listingPivot->saveOrRestore();
            return $listingPivot;
        }
    }

    private function processItem($itemId, $itemModel, $params, $itemService, $groupModel)
    {
        /** @var \App\Contracts\Models\ItemInterface $item */
        $item = $itemModel->newQuery()->findOrFail($itemId);

        if (array_key_exists('color', $params)) {
            $item->fill([
                'color' => $params['color'],
            ]);

            $item->save();
        }

        if (array_key_exists('parent_id', $params)) {
            $itemService->setParentModelToItem($item);
            $listingPivot = $itemService->getItemListingPivot($item);
            $listingPivot->restoreIfTrashed();

            $itemService->unsetRelationItemListingPivot($item);

            if ($item->group?->id === $params['parent_id']) {
                return $listingPivot;
            }

            /** @var \App\Contracts\Models\GroupInterface|null $parent */
            $parent = ! is_null($params['parent_id'])
                ? $groupModel->newQuery()->find($params['parent_id']) : null;

            if (! $itemService->canItemBeInManyParentModels()) {
                $item->group()->associate($parent);
            }

            $item->save();

            $listingPivot->group()->associate($parent);
            $listingPivot->setHighestOrderNumber();
            $listingPivot->saveOrRestore();

            $itemService->unsetRelationItemListingPivot($item);

            $item->name = $itemService->replicateUniqueNameOfItem($item);
            $item->save();
            return $listingPivot;
        }
    }

    protected function baseBatchDuplicate(User $authUser, array $params = []): array
    {
        return DB::transaction(function () use ($authUser, $params) {
            $groupIdNewGroup = [];
            $itemIdNewItem = [];

            /** @var \Illuminate\Database\Eloquent\Model $groupModel */
            $groupModel = new ($this->getGroupClass());
            $itemService = $this->getItemService();

            /** @var \App\Contracts\Models\GroupInterface|\Illuminate\Database\Eloquent\Model|null $parent */
            $parent = isset($params['parent_id']) ? $groupModel->newQuery()->find($params['parent_id']) : null;

            if (isset($params['ids'])) {
                /** @var \Kalnoy\Nestedset\QueryBuilder $query */
                $query = $groupModel->newQuery();
                $orderedIds = $query->whereKey($params['ids'])->defaultOrder()->pluck('id');

                while ($orderedIds->isNotEmpty()) {
                    $id = $orderedIds->pop();

                    /** @var \Kalnoy\Nestedset\QueryBuilder $query */
                    $query = $groupModel->newQuery();
                    $ancestors = $query
                        ->whereKey($orderedIds->merge([$id]))
                        ->defaultOrder()
                        ->ancestorsAndSelf($id);

                    $currentParent = $parent;

                    if (! array_key_exists('parent_id', $params)) {
                        /** @var \App\Contracts\Models\GroupInterface|\Illuminate\Database\Eloquent\Model $highestGroup */
                        $highestGroup = $ancestors->first();
                        $currentParent = $highestGroup->parent;
                    }

                    foreach ($ancestors as $ancestor) {
                        /** @var \App\Contracts\Models\GroupInterface|\Illuminate\Database\Eloquent\Model $ancestor */

                        if (! isset($groupIdNewGroup[$ancestor->id])) {
                            $groupIdNewGroup[$ancestor->id] = $this
                                ->replicateToParent($ancestor, $currentParent, $authUser);
                        }

                        $currentParent = $groupIdNewGroup[$ancestor->id];
                    }

                    /** @var \App\Contracts\Models\GroupInterface|\Illuminate\Database\Eloquent\Model $deepestGroup */
                    $deepestGroup = $ancestors->last();

                    /** @var \Kalnoy\Nestedset\QueryBuilder $query */
                    $query = $groupModel->newQuery();

                    $hasSelectedItems = isset($params['item_ids']) && $query
                        ->clone()
                        ->whereDescendantOrSelf($deepestGroup)
                        ->whereHas('items', function (Builder $q) use ($params) {
                            $q->whereKey($params['item_ids']);
                        })
                        ->exists();

                    if (! $hasSelectedItems) {
                        $descendantsOfDeepest = $query
                            ->clone()
                            ->with(['parent', 'items'])
                            ->defaultOrder()
                            ->descendantsOf($deepestGroup);

                        $groupIdNewGroup = $this->replicateMany($descendantsOfDeepest, $authUser, $groupIdNewGroup);

                        foreach (array_merge([$deepestGroup], $descendantsOfDeepest->all()) as $group) {
                            /** @var \App\Contracts\Models\GroupInterface|\Illuminate\Database\Eloquent\Model $group */

                            foreach ($group->items as $item) {
                                $itemService->setParentModelToItem($item);
                            }

                            $itemIdNewItem += $itemService->replicateManyToGroup(
                                $group->items, $groupIdNewGroup[$group->id], $authUser
                            );
                        }
                    }

                    $orderedIds = $orderedIds->diff($ancestors->modelKeys())->values();
                }
            }

            if (isset($params['item_ids'])) {
                /** @var \Illuminate\Database\Eloquent\Model $itemModel */
                $itemModel = new ($this->getItemClass());

                $items = $itemModel
                    ->newQuery()
                    ->findMany(collect($params['item_ids'])->diff(array_keys($itemIdNewItem))->values());

                foreach ($items as $item) {
                    /** @var \App\Contracts\Models\ItemInterface|\Illuminate\Database\Eloquent\Model $item */

                    $itemService->setParentModelToItem($item);

                    /** @var \Kalnoy\Nestedset\QueryBuilder $query */
                    $query = $groupModel->newQuery();
                    $itemGroup = $itemService->getItemListingPivot($item)->group;

                    /** @var \App\Contracts\Models\GroupInterface|\Illuminate\Database\Eloquent\Model $itemAncestor */
                    $itemAncestor = $query
                        ->reversed()
                        ->whereKey(array_keys($groupIdNewGroup))
                        ->whereAncestorOrSelf($itemGroup)
                        ->first();

                    $newItemGroup = ! is_null($itemAncestor)
                        ? $groupIdNewGroup[$itemAncestor->id]
                        : (array_key_exists('parent_id', $params) ? $parent : $itemGroup);

                    $itemIdNewItem[$item->id] = $itemService->replicateToGroup($item, $newItemGroup, $authUser);
                }
            }

            // Sort order.
            /** @var \App\Contracts\Models\ListingPivotInterface[]|\App\Contracts\Models\TreeSortable[] $listingPivots */
            $listingPivots = [];
            $newGroupIds = collect($groupIdNewGroup)->pluck('id')->all();

            foreach ($groupIdNewGroup as $newGroup) {
                $listingPivot = $this->getGroupListingPivot($newGroup);

                if (! in_array($listingPivot->group_id, $newGroupIds)) {
                    $listingPivots[] = $listingPivot;
                }
            }

            foreach ($itemIdNewItem as $newItem) {
                $listingPivot = $itemService->getItemListingPivot($newItem);

                if (! in_array($listingPivot->group_id, $newGroupIds)) {
                    $listingPivots[] = $listingPivot;
                }
            }

            if (! empty($listingPivots)) {
                if (isset($params['sort_order'])) {
                    $this->sortManyToOrder($listingPivots, $params['sort_order']);
                } else {
                    $listingPivots[0]->updateSortOrderOfList();
                }
            }

            return [
                'groups' => array_values($groupIdNewGroup),
                'items' => array_values($itemIdNewItem),
            ];
        });
    }

    protected function baseBatchUngroup(array $params = []): void
    {
        DB::transaction(function () use ($params) {
            /** @var \Illuminate\Database\Eloquent\Model $groupModel */
            $groupModel = new ($this->getGroupClass());
            $itemService = $this->getItemService();
            $listingPivotUpdateList = null;

            // Among ids in the $params['ids'] can be ids of groups that are nested into each other.
            // To properly handle this case we do not use with(['parent', 'children', 'playlists']).
            // Each foreach iteration of groups queries data, including those saved in the previous iterations.
            $groups = $groupModel->newQuery()->findMany($params['ids']);

            foreach ($groups as $group) {
                /** @var \App\Contracts\Models\GroupInterface $group */

                foreach ($group->children as $child) {
                    /** @var \App\Contracts\Models\GroupInterface|\Illuminate\Database\Eloquent\Model $child */
                    $child->parent()->associate($group->parent);
                    $child->name = $this->replicateUniqueNameOfGroup($child);
                    $child->save();

                    $listingPivot = $this->getGroupListingPivot($child);
                    $listingPivot->group()->associate($child->parent_id);
                    $listingPivot->saveOrRestore();

                    if (is_null($listingPivotUpdateList)) {
                        $listingPivotUpdateList = $listingPivot;
                    }
                }

                foreach ($group->items as $item) {
                    /** @var \App\Contracts\Models\ItemInterface|\Illuminate\Database\Eloquent\Model $item */
                    $itemService->setParentModelToItem($item);

                    if (! $itemService->canItemBeInManyParentModels()) {
                        $item->group()->associate($group->parent);
                    }

                    $item->save();

                    $listingPivot = $itemService->getItemListingPivot($item);
                    $listingPivot->group()->associate($group->parent_id);
                    $listingPivot->saveOrRestore();

                    if (is_null($listingPivotUpdateList)) {
                        $listingPivotUpdateList = $listingPivot;
                    }

                    $itemService->unsetRelationItemListingPivot($item);

                    $item->name = $itemService->replicateUniqueNameOfItem($item);
                    $item->save();
                }

                $group->delete();
            }

            if (! is_null($listingPivotUpdateList)) {
                $listingPivotUpdateList->updateSortOrderOfList();
            }
        });
    }

    protected function baseBatchDelete(array $params = []): void
    {
        DB::transaction(function () use ($params) {
            $listingPivot = null;

            foreach ($params['ids'] ?? [] as $id) {
                /** @var \Illuminate\Database\Eloquent\Model $groupModel */
                $groupModel = new ($this->getGroupClass());

                // Query each group separately to handle nested groups properly.
                /** @var \App\Contracts\Models\GroupInterface|null $group */
                $group = $groupModel->newQuery()->find($id);

                if (! is_null($group)) {
                    if (is_null($listingPivot)) {
                        $listingPivot = $this->getGroupListingPivot($group);
                    }

                    $this->deleteGroup($group);
                }
            }

            if (isset($params['item_ids'])) {
                /** @var \Illuminate\Database\Eloquent\Model $itemModel */
                $itemModel = new ($this->getItemClass());

                $items = $itemModel->newQuery()->findMany($params['item_ids']);

                foreach ($items as $item) {
                    /** @var \App\Contracts\Models\ItemInterface $item */

                    if (is_null($listingPivot)) {
                        $listingPivot = $this->getItemService()->getItemListingPivot($item);
                    }

                    $item->delete();
                }
            }

            if (! is_null($listingPivot)) {
                $listingPivot->updateSortOrderOfList();
            }
        });
    }

    protected function baseBatchRestore(array $params = []): void
    {
        ray($params)->green()->label('$params');

        DB::transaction(function () use ($params) {
            $listingPivot = null;

            foreach ($params['ids'] ?? [] as $id) {
                /** @var \Illuminate\Database\Eloquent\Model $groupModel */
                $groupModel = new ($this->getGroupClass());
                // Query each group separately to handle nested groups properly.
                /** @var \App\Contracts\Models\GroupInterface|null $group */
                $group = $groupModel->newQuery()->find($id);
                ray($group)->purple()->label('$group');

                if (! is_null($group)) {
                    if (is_null($listingPivot)) {
                        $listingPivot = $this->getGroupListingPivot($group);
                    }
                    ray($listingPivot)->purple()->label('$listingPivot');

                    $this->restoreGroupAndAncestors($group, true);
                }
            }

            if (isset($params['item_ids'])) {
                /** @var \Illuminate\Database\Eloquent\Model $itemModel */
                $itemModel = new ($this->getItemClass());

                $items = $itemModel->newQuery()->findMany($params['item_ids']);

                foreach ($items as $item) {
                    ray($item)->blue()->label('$item');
                    /** @var \App\Contracts\Models\ItemInterface $item */

                    if (is_null($listingPivot)) {
                        $listingPivot = $this->getItemService()->getItemListingPivot($item);
                        ray($listingPivot)->blue()->label('is_null $listingPivot');
                    }
                    ray($listingPivot)->blue()->label('$listingPivot');

                    $item->restore();
                }
            }

            if (! is_null($listingPivot)) {
                $listingPivot->updateSortOrderOfList();
            }
        });
    }

    protected function saveChildrenAndItems(GroupInterface $group, array $params): void
    {
        if (isset($params['children_ids'])) {
            // the existing/new children of this $group that are also groups themselves

            /** @var \Illuminate\Database\Eloquent\Model $groupModel */
            $groupModel = new ($this->getGroupClass());

            $children = $groupModel->newQuery()->findMany($params['children_ids']);

            foreach ($children as $child) {
                /** @var \App\Contracts\Models\GroupInterface $child */
                if ($child->parent_id !== $group->id) {
                    $child->parent()->associate($group);
                    $child->save();

                    $listingPivot = $this->getGroupListingPivot($child);
                    $listingPivot->group()->associate($child->parent_id);
                    $listingPivot->saveOrRestore();
                }
            }
        }

        if (isset($params['item_ids'])) {
            // the existing/new children of this $group that are not groups themselves

            /** @var \Illuminate\Database\Eloquent\Model $itemModel */
            $itemModel = new ($this->getItemClass());
            $itemService = $this->getItemService();

            foreach ($params['item_ids'] as $itemId) {
                /** @var \App\Contracts\Models\ItemInterface $item */
                $item = $itemModel->newQuery()->findOrFail($itemId);
                $itemService->setParentModelToItem($item);

                /**
                 * The `group` attribute for items that can be in many parents (i.e., Page) will be
                 * the first group from its listing pivot (i.e., PlaylistListing, @see Page::getGroupAttribute())
                 *
                 * These single-parent items have group set directly and in the listing pivot
                 */
                if ($item->group?->id !== $group->id) {
                    if (! $itemService->canItemBeInManyParentModels()) {
                        // not a Page (or other multi-parent model)
                        $item->group()->associate($group);
                    }

                    $item->save();

                    $listingPivot = $itemService->getItemListingPivot($item);
                    $listingPivot->group()->associate($group);
                    $listingPivot->saveOrRestore();

                    $itemService->unsetRelationItemListingPivot($item);
                }
            }
        }
    }

    public function replicateToParent(
        GroupInterface|Model $group, GroupInterface|Model|null $parent, User $createdBy
    ): GroupInterface|Model
    {
        /** @var \App\Contracts\Models\GroupInterface|\Illuminate\Database\Eloquent\Model $newGroup */
        $newGroup = $group->replicate([
            'created_by',
            'parent_id',
        ]);

        if ($newGroup->isRelation('createdBy')) {
            $newGroup->createdBy()->associate($createdBy);
        }

        $newGroup->parent()->associate($parent);
        $newListingPivot = $this->getGroupListingPivot($newGroup);
        $this->associateParentModel($newGroup, $newListingPivot);

        $newGroup->name = $this->replicateUniqueNameOfGroup($newGroup);
        $newGroup->save();

        $this->saveReplicatedListingPivot(
            $newListingPivot, $newGroup, $newGroup->parent_id, $group->parentListingPivot
        );

        return $newGroup;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection  $groups
     * @param  \App\Contracts\Models\GroupInterface|\Illuminate\Database\Eloquent\Model|null  $parent
     * @param  \App\Models\User  $createdBy
     * @param  array  $groupIdNewGroup An array that contains the group ids as keys
     * and their new replicated groups as values.
     * @return array An array that contains the group ids as keys and their new replicated groups as values.
     */
    public function replicateManyToParent(
        Collection $groups, GroupInterface|Model|null $parent, User $createdBy, array $groupIdNewGroup = []
    ): array
    {
        foreach ($groups as $group) {
            /** @var \App\Contracts\Models\GroupInterface|\Illuminate\Database\Eloquent\Model $group */
            $groupIdNewGroup[$group->id] = $this->replicateToParent($group, $parent, $createdBy);
        }

        return $groupIdNewGroup;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection  $groups
     * @param  \App\Models\User  $createdBy
     * @param  array  $groupIdNewGroup An array that contains the group ids as keys
     * and their new replicated groups as values.
     * @return array An array that contains the group ids as keys and their new replicated groups as values.
     */
    public function replicateMany(Collection $groups, User $createdBy, array $groupIdNewGroup = []): array
    {
        // When a group is being handled, its parent must have already existed or been created.
        foreach ($groups as $group) {
            /** @var \App\Contracts\Models\GroupInterface|\Illuminate\Database\Eloquent\Model $group */

            // Get replicated or existing group, or null if there is no one.
            // Null means the group is global.
            $parent = $group->parent;

            if (! is_null($group->parent)) {
                $parent = $groupIdNewGroup[$group->parent->id] ?? $group->parent;
            }

            $groupIdNewGroup[$group->id] = $this->replicateToParent($group, $parent, $createdBy);
        }

        return $groupIdNewGroup;
    }

    protected function deleteGroup(GroupInterface $group): void
    {
        /** @var \App\Contracts\Models\GroupInterface|\Illuminate\Database\Eloquent\Model $group */

        $parentLogChange = $this->createDeletionLogChange($group);

        foreach ($group->items as $item) {
            /** @var \App\Contracts\Models\ItemInterface|\Illuminate\Database\Eloquent\Model $item */

            $this->createDeletionLogChange($item, $parentLogChange);
            $item->delete();
        }

        foreach ($group->descendants as $descendant) {
            /** @var \App\Contracts\Models\GroupInterface|\Illuminate\Database\Eloquent\Model $descendant */

            foreach ($descendant->items as $item) {
                /** @var \App\Contracts\Models\ItemInterface|\Illuminate\Database\Eloquent\Model $item */

                $this->createDeletionLogChange($item, $parentLogChange);
                $item->delete();
            }

            $this->createDeletionLogChange($descendant, $parentLogChange);
            $descendant->delete();
        }

        $group->delete();
    }

    /**
     * Restores the group and their ancestors if they can be restored.
     * Returns the group passed to the method if it is restored,
     * or an ancestor possible to restore nearest to the group.
     *
     * @param  \App\Contracts\Models\GroupInterface|null  $group
     * @param  bool  $forceRestoreGroup If true, the passed group will be restored anyway,
     * and its ancestors will be restored if possible.
     * @return \App\Contracts\Models\GroupInterface|null
     */
    public function restoreGroupAndAncestors(?GroupInterface $group, bool $forceRestoreGroup = false): ?GroupInterface
    {
        if (is_null($group)) {
            return null;
        }

        $maxAncestorDepth = $this->getMaxNodeDepth();

        if ($forceRestoreGroup) {
            --$maxAncestorDepth;
        }

        if ($maxAncestorDepth < 0) {
            $group->parent()->dissociate();
            return $group;
        }

        /** @var \Kalnoy\Nestedset\QueryBuilder $query */
        $query = $group->newQuery()
            ->withDepth()
            ->having('depth', '<=', $maxAncestorDepth)
            ->defaultOrder()
            ->withTrashed();

        if ($forceRestoreGroup) {
            $ancestors = $query->ancestorsOf($group);
            $ancestors->add($group);
        } else {
            $ancestors = $query->ancestorsAndSelf($group);
        }

        foreach ($ancestors as $ancestor) {
            /** @var \App\Contracts\Models\GroupInterface|\Kalnoy\Nestedset\NodeTrait $ancestor */

            if ($forceRestoreGroup && $ancestor->id === $group->id) {
                $group->parent()->associate($ancestors->slice(-2, 1)->first());
            }

            if ($ancestor->trashed()) {
                $ancestor->name = $this->replicateUniqueNameOfGroup($ancestor);
                $ancestor->restore();
            }

            $listingPivot = $this->getGroupListingPivot($ancestor);
            $listingPivot->group()->associate($ancestor->parent_id);
            $listingPivot->saveOrRestore();
            $listingPivot->moveToCurrentOrder();
        }

        if ($forceRestoreGroup) {
            return $group;
        }

        /** @var \App\Contracts\Models\GroupInterface $lastAncestor */
        $lastAncestor = $ancestors->last();

        return $group->id === $lastAncestor->id ? $group : $lastAncestor;
    }

    protected function replicateUniqueNameOfGroup(GroupInterface $group): string
    {
        $query = ! is_null($group->parent)
            ? $group->parent->children()
            : $this->getQueryGroupsWithoutGroup($group);
        $query->whereKeyNot($group);

        return $this->replicateUniqueName($query, $group->name);
    }

    protected function createDeletionLogChange(Model $model, ?ChangeLog $parent = null): void
    {
    }

    public static function sortTreeComponents(SupportCollection $tree): SupportCollection
    {
        return static::sortTreeComponentsRecursive($tree);
    }

    protected static function sortTreeComponentsRecursive(SupportCollection $tree): SupportCollection
    {
        $sortedTree = $tree->sortBy(['sort_order'])->values();
        // ray($sortedTree)->label('$sortedTree')->purple();

        foreach ($sortedTree as $component) {
            /** @var \App\Contracts\Models\GroupInterface|\App\Contracts\Models\ItemInterface $component */

            if ($component instanceof ItemInterface) {
                continue;
            }
            // if($component instanceof TemplateGroup){
            //     ray($component)->label('$component')->green();
            //     ray($component->children)->label('$component->children')->green();
            //     ray($component->items)->label('$component->items')->green();
            // }
            // ray()->showQueries();
            $component->components = static::sortTreeComponentsRecursive(
                collect($component->children)->merge($component->items)
            );
            // ray()->stopShowingQueries();

        }

        return $sortedTree;
    }

    public static function toTreeComponents(SupportCollection $components): SupportCollection
    {
        if ($components->isEmpty()) {
            return collect();
        }

        $groupedComponents = $components
            ->groupBy(fn ($c) => $c instanceof ItemInterface ? $c->getGroupId() : $c->parent_id);

        foreach ($components as $component) {
            /** @var \App\Contracts\Models\GroupInterface|\App\Contracts\Models\ItemInterface $component */

            if ($component instanceof ItemInterface) {
                continue;
            }

            $component->components = $groupedComponents->get($component->id, collect());
        }

        return $groupedComponents->get('');
    }
}
