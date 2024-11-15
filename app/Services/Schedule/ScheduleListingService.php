<?php

namespace App\Services\Schedule;

use App\Models\ChannelLayer;
use App\Models\Page;
use App\Models\Playlist;
use App\Models\PlaylistGroup;
use App\Models\Schedule\ScheduleListing;
use App\Models\Schedule\ScheduleSet;
use App\Services\Schedule\Helpers\ScheduleListingFactory;
use App\Traits\Services\GroupTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection as SupportCollection;

class ScheduleListingService
{
    public function __construct(protected ChannelLayerService $layerService) {}

    /**
     * @throws \Exception
     */
    public function update(ScheduleListing $listingNode, array $validated)
    {
        $listingNode->update($validated);
        $listingNode->refresh();
        // ray('updating listing', $validated, $listingNode);
        return $listingNode;
    }

    /**
     * Display a listing of layers, with playlists,
     * pages, and their schedules
     *
     * @param ScheduleSet $scheduleSet
     *
     * @return SupportCollection
     */
    public function listing(ScheduleSet $scheduleSet)
    {
        $listing = ScheduleListing::with([
            'scheduleSet',
            'scheduleable',
            'parent',
            'children.scheduleable' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    Page::class => ['channel'],
                ]);
            },
            'children.children.scheduleable' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    Page::class => ['channel'],
                ]);
            }
        ])
         ->where('schedule_set_id', $scheduleSet->id)
         // don't retrieve items with a deleted scheduleable
         ->has('scheduleable')
         ->orderBy('sort_order')
         ->get();
        return static::sortTreeChildren($listing->toFlatTree()->toBase());
    }

    /**
     * Display a listing of layers, with playlists,
     * pages, and their schedules
     *
     * @param ScheduleSet $scheduleSet
     *
     * @return SupportCollection
     */
    public function listingForCalendar(ScheduleSet $scheduleSet)
    {
        $listing = ScheduleListing::forCalendar($scheduleSet)->get();
        return static::sortTreeChildren($listing->toFlatTree()->toBase());
    }

    public function batchUpdate(array $params = []) : Collection
    {
        $ids = $params['ids'];
        unset($params['ids']);
        ScheduleListing::whereIn('id', $ids)->update($params);

        return ScheduleListing::findMany($ids);
    }

    public function batchDelete(array $params = []) : void
    {
        // NOTE: must delete models for nested set to delete descendants properly, can't use delete() query
        // In Scheduler, pages and groups should not be deleted separately from their parents, so just
        // delete the parents and let it cascade to descendants
        $listings = ScheduleListing::whereIn('id', $params['ids'])
                                   ->whereIn('scheduleable_type', [
                                       Playlist::class,
                                       PlaylistGroup::class,
                                       ChannelLayer::class,
                                   ])->get();
        $layerIds = [];
        foreach($listings as $listing){
            if($listing->scheduleable_type === 'App\Models\ChannelLayer'){
                $layerIds []= $listing->scheduleable_id;
            }
            $listing->delete();
        }
        // layers only exist in Scheduler, so delete them if removed
        $this->layerService->batchDelete(['ids'=>$layerIds]);
    }


    /**
     * @refactor -> move to tree Class?
     * @throws \ErrorException
     */
    public function buildTreeToUpdateSort(?ScheduleSet $scheduleSet, ?ScheduleListing $parent): SupportCollection
    {
        $query = ScheduleListing::with([
            'descendants',
            'scheduleable',
            'children.scheduleable',
            'children.children.scheduleable'
        ]);

        if(isset($scheduleSet)){
            $query = $query->where('schedule_set_id', $scheduleSet->id);
        } elseif(isset($parent)) {
            $query = $query->where('parent_id', $parent->id);
        } else {
            throw new \ErrorException("ScheduleListing must have a ScheduleSet or parent to build sort tree");
        }

        $schedule_listing = $query->orderBy('sort_order')->get();

        return static::sortTreeChildren($schedule_listing->toTree()->toBase());
    }

    /**
     * @refactor -> move to tree Class?
     * Override the `children` relation to be ordered by `sort_order` instead of nested set order
     *
     * @see \App\Traits\Services\GroupTrait::sortTreeComponents()
     */
    public static function sortTreeChildren(SupportCollection $tree): SupportCollection
    {
        return static::sortTreeChildrenRecursive($tree);
    }

    /**
     * @refactor -> move to tree Class?
     * @see \App\Traits\Services\GroupTrait::sortTreeChildrenRecursive()
     */
    protected static function sortTreeChildrenRecursive(SupportCollection $tree): SupportCollection
    {
        $sortedTree = $tree->sortBy(['sort_order'])->values();

        foreach ($sortedTree as $node) {
            // ray('child "'. $node->scheduleable->name.'" order ['.$node->sort_order.']');

            if ($node->scheduleable_type === Page::class) {
                // we reached a leaf
                continue;
            }

            // ray('sortTreeChildrenRecursive - node with children: '. $node?->scheduleable->name.' ('.$node?->children?->count().' children) ');

            // NOTE: setting this to ->children instead of ->components may
            // interfere with the nested set ordering/relations...
            $node->setRelation('children', static::sortTreeChildrenRecursive(
                collect($node->children)
            ));
        }
        return $sortedTree;
    }

    /**
     * @refactor -> move to tree Class?
     * Get the full tree of a given node, starting at its root, and sort the tree hierarchy by
     * `sort_order` instead of nested set order (`_lft`/`_rgt`)
     */
    public function getAndSortTreeOfNode(ScheduleListing $node)
    {
        $rootNode = ScheduleListing::whereRootOf($node)->get()->first();
        $tree = ScheduleListing::defaultOrder()->descendantsOf($rootNode->id);

        // sorting doesn't help after ->prevNodes()/nextNodes(), because they are ordered by `_lft` and `_rgt`
        // and therefore may contain the "wrong" nodes according to the `sort_order`

        return ScheduleListingService::sortTreeChildren($tree->toTree());
    }

    /**
     * @refactor -> move to tree Class?
     * Get the nodes for finding the next valid node in a layer
     */
    public function getSortOrderedTreeNodes(ScheduleListing $node) : SupportCollection
    {
        ['prev' => $prevNodes, 'next' => $nextNodes] = $this->getNextAndPrevNodesBySortOrder($node);
        $mergedNodes = $nextNodes->toBase()->merge($prevNodes);
        // ray('$mergedNodes ordered after "'.$this->scheduleable->name.'"', $mergedNodes->pluck('scheduleable')->pluck('name')->all())->purple();
        return $mergedNodes;
    }

    /**
     * @refactor -> move to tree Class?
     * Get the nodes that come before and after the given $node's sort_order,
     * instead of its nested set order (_lft/_rgt)
     */
    private function getNextAndPrevNodesBySortOrder(ScheduleListing $node) : array
    {
        // entities in $sortedTree are direct children of the layer (root)
        // ex) Playlist 1, Playlist 2, PlaylistGroup B
        $sortedTree = ScheduleListingService::getAndSortTreeOfNode($node);

        // ray($sortedTree->pluck('scheduleable')->pluck('name'))->label('sortedTree of '.$node?->scheduleable?->name)->green();

        $prevNodes = new \Kalnoy\Nestedset\Collection();
        $nextNodes = new \Kalnoy\Nestedset\Collection();

        // find $node in the sorted tree, starting from (but not including) root, so we know where to
        // start checking for the next valid playable node that comes after this node
        $sortedTree->each(function ($sortedNode) use ($node, $sortedTree, &$prevNodes, &$nextNodes) {

            if ($sortedNode->id === $node->id) {
                // we found this $node in the sorted tree, so we know all nodes that come after it are
                // viable $nextNodes and we can stop searching

                // don't include the matching node
                $sortedTree->shift();
                // the rest of the tree comes after the matching node
                $nextNodes = $nextNodes->toBase()->merge($sortedTree);
                return false; // aka break;
            }

            // sorted node comes before the $node
            $prevNodes->push($sortedTree->shift());

            if ($sortedNode->children->count() <= 0) {
                // leaf node
                return true; // aka continue;
            }

            $found = $this->findInNestedChildren($node, $sortedNode, $prevNodes, $nextNodes);

            if ($found) {
                // the rest of the tree comes after the matching node
                $nextNodes = $nextNodes->toBase()->merge($sortedTree);
                return false; // aka break;
            }
        });

        return ['prev' => $prevNodes, 'next' => $nextNodes];
    }

    /**
     * @refactor -> move to tree Class?
     */
    private function findInNestedChildren($node, &$sortedNode, &$prevNodes, &$nextNodes) : bool
    {
        $found = false;

        $children = clone $sortedNode->children;
        $children->each(function ($child) use ($node, &$children, &$prevNodes, &$nextNodes, &$found) {
            // does this sorted node's children come before or after the $node?
            if ($child->id === $node->id) {
                // we found this $node in the sorted tree, so we know all nodes that come after it are
                // viable $nextNodes and can stop searching

                // discard the matching node
                $children->shift();
                // the rest of the tree comes after the matching node
                $nextNodes = $nextNodes->toBase()->merge($children);
                // make sure we also add any remaining nodes from the level above this one to the $nextNodes list
                $found = true;
                return false; // aka break;
            }

            // child node comes before the $node
            $prevNodes->push($children->shift());

            if ($child->children->count() > 0) {
                $found = $this->findInNestedChildren($node, $child,$prevNodes, $nextNodes);
            }

            if ($found) {
                // the rest of the tree comes after the matching node
                $nextNodes = $nextNodes->toBase()->merge($children);
                return false; // aka break;
            }
        });

        return $found;
    }

    /**
     * @refactor -> move to tree Class?
     * If the page belongs to a Playlist or PageGroup that is already in the Scheduler then add it
     *
     * @throws \ErrorException
     */
    public static function addPageToParent(Page $page) : void
    {
        $parentScheduleListingPivots = ScheduleListing::whereHasListingParent($page->parentListingPivot)->get();
        if($parentScheduleListingPivots->count() === 0){
            ray("don't add page; parent not in scheduler")->orange();
            return;
        }

        // create new ScheduleListing entries for this Page
        $factory = new ScheduleListingFactory();
        $factory->createManyFrom(
            $page->parentListingPivot,
            $parentScheduleListingPivots
        );
    }

    /**
     * @refactor -> move to tree Class?
     */
    public static function findRootOf(ScheduleListing $node) : ScheduleListing
    {
        return $node->isRoot() ? $node : ScheduleListing::whereRootOf($node)->first();
    }
}
