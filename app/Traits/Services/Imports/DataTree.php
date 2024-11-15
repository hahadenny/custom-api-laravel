<?php

namespace App\Traits\Services\Imports;

trait DataTree
{
    protected function buildTree(array $data): array
    {
        $children = collect($data['children'])->sortBy(['sort_order', 'id'])->values()->all();

        foreach ($children as &$child) {
            $child['items'] = collect($child['items'])->sortBy(['sort_order', 'id'])->values()->all();
        }
        unset($child);

        $groupsTree = $this->buildTreeRecursive($children);
        $children = array_values($children);

        // If parent_id is not empty and no parent was found for an element,
        // then this element will remain in $children.
        // Set parent_id to null, and add the elements to the tree.
        foreach ($children as &$child) {
            $child['parent_id'] = null;
        }
        unset($child);

        return [
            'children' => collect($groupsTree)->merge($children)->sortBy(['sort_order', 'id'])->values()->all(),
            'items' => collect($data['items'])->sortBy(['sort_order', 'id'])->values()->all(),
        ];
    }

    /**
     * @internal
     */
    protected function buildTreeRecursive(array &$groups, ?array $parent = null): array
    {
        $tree = [];

        foreach ($groups as $key => $group) {
            if ($group['parent_id'] === (is_null($parent) ? null : $parent['id'])) {
                unset($groups[$key]);

                $group['children'] = $this->buildTreeRecursive($groups, $group);
                $tree[] = $group;
            }
        }

        return collect($tree)->sortBy(['sort_order', 'id'])->values()->all();
    }

    protected static function toTreeOfComponents(array $tree): array
    {
        return static::toTreeOfComponentsRecursive($tree);
    }

    /**
     * @internal
     */
    protected static function toTreeOfComponentsRecursive(array $tree): array
    {
        foreach ($tree['children'] as &$child) {
            $child = static::toTreeOfComponentsRecursive($child);
        }
        unset($child);

        $tree['components'] = collect($tree['children'])
            ->merge($tree['items'])
            ->sortBy(['sort_order', 'id'])
            ->values()
            ->all();

        unset($tree['children']);
        unset($tree['items']);

        return $tree;
    }

    protected static function separateComponentsInTree(array $tree): array
    {
        return static::separateComponentsInTreeRecursive($tree);
    }

    /**
     * @internal
     */
    protected static function separateComponentsInTreeRecursive(array $tree): array
    {
        $tree['children'] = [];
        $tree['items'] = [];

        foreach ($tree['components'] as $component) {
            if (isset($component['components'])) {
                $tree['children'][] = static::separateComponentsInTreeRecursive($component);
            } else {
                $tree['items'][] = $component;
            }
        }

        unset($tree['components']);

        return $tree;
    }

    protected function setParentToGroups(array $groups): array
    {
        return $this->setParentToGroupsRecursive($groups, null);
    }

    /**
     * @internal
     */
    protected function setParentToGroupsRecursive(array $groups, ?array $parent): array
    {
        $newGroups = [];

        foreach ($groups as $group) {
            $group['parent'] = $parent;
            $group['children'] = $this->setParentToGroupsRecursive($group['children'], $group);

            $newGroups[] = $group;
        }

        return $newGroups;
    }

    /**
     * A component is an item or group.
     * If a component is selected then its ancestors (groups) will be selected.
     * A content of a group is its descendants (groups) and items at any depth.
     * If a group is selected and any element of its content is not selected
     * then the whole content of this group will be selected.
     * In other cases the selections will remain the same.
     */
    protected function determineSelectedData(array $groups, array $params): array
    {
        $params = [
            'ids' => $params['ids'] ?? [],
            'group_ids' => $params['group_ids'] ?? [],
        ];

        if (empty($params['ids']) && empty($params['group_ids'])) {
            return $params;
        }

        return $this->determineSelectedInGroupsRecursive(
            $this->setParentToGroups($groups),
            $params,
            ['ids' => [], 'group_ids' => []],
            $this->getGroupIdsHaveSelectedRecursive($groups, $params)
        );
    }

    /**
     * @param  array  $groups
     * @param  array  $params The group ids and item ids that were originally selected.
     * @param  array  $newParams New selected group ids and item ids that were selected in the previous iterations.
     * @param  array  $groupIdsHaveSelected Ids of groups that have a selected item or a selected child group
     *   at any depth.
     * @param  bool  $selectAll Whether the method should select the $groups and their content
     *   if any of this content is not selected in the $params.
     * @return array New selected group ids and item ids.
     *
     * @internal
     */
    protected function determineSelectedInGroupsRecursive(
        array $groups, array $params, array $newParams, array $groupIdsHaveSelected, bool $selectAll = false
    ): array
    {
        foreach ($groups as $group) {
            if (
                in_array($group['id'], $params['group_ids'], true)
                || in_array($group['id'], $groupIdsHaveSelected, true)
            ) {
                $selectAll = false;
                break;
            }
        }

        foreach ($groups as $group) {
            $isGroupSelected = in_array($group['id'], $params['group_ids'], true);
            $groupHasSelected = in_array($group['id'], $groupIdsHaveSelected, true);
            $isAnySelected = false;

            if ($selectAll || $isGroupSelected) {
                $newParams['group_ids'][] = $group['id'];
                $isAnySelected = true;
            }

            foreach ($group['items'] as $item) {
                if (
                    $selectAll
                    || ($isGroupSelected && ! $groupHasSelected)
                    || in_array($item['id'], $params['ids'], true)
                ) {
                    $newParams['ids'][] = $item['id'];
                    $isAnySelected = true;
                }
            }

            if ($isAnySelected) {
                $newParams = $this->selectAncestorsAndSelfRecursive($group, $newParams);
            }

            $newParams = $this->determineSelectedInGroupsRecursive(
                $group['children'], $params, $newParams, $groupIdsHaveSelected, $selectAll || $isGroupSelected
            );
        }

        $newParams['ids'] = array_values(array_filter(array_unique($newParams['ids'])));
        $newParams['group_ids'] = array_values(array_filter(array_unique($newParams['group_ids'])));

        return $newParams;
    }

    /**
     * @internal
     */
    protected function selectAncestorsAndSelfRecursive(?array $group, array $newParams): array
    {
        if (is_null($group)) {
            return $newParams;
        }
        if (in_array($group['id'], $newParams['group_ids'], true)) {
            return $newParams;
        }

        $newParams['group_ids'][] = $group['id'];

        return $this->selectAncestorsAndSelfRecursive($group['parent'], $newParams);
    }

    /**
     * @internal
     */
    protected function getGroupIdsHaveSelectedRecursive(array $groups, array $params): array
    {
        $groupIdsHaveSelected = [];

        foreach ($groups as $group) {
            $descendantIdsHaveSelected = $this->getGroupIdsHaveSelectedRecursive($group['children'], $params);
            $groupIdsHaveSelected = array_merge($groupIdsHaveSelected, $descendantIdsHaveSelected);
            $hasSelected = ! empty($descendantIdsHaveSelected);

            if (! $hasSelected) {
                foreach ($group['children'] as $child) {
                    if (in_array($child['id'], $params['group_ids'], true)) {
                        $hasSelected = true;
                        break;
                    }
                }
            }

            if (! $hasSelected) {
                foreach ($group['items'] as $item) {
                    if (in_array($item['id'], $params['ids'], true)) {
                        $hasSelected = true;
                        break;
                    }
                }
            }

            if ($hasSelected) {
                $groupIdsHaveSelected[] = $group['id'];
            }
        }

        return $groupIdsHaveSelected;
    }
}
