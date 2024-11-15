<?php

namespace App\Services\Schedule;

use App\Models\ChannelLayer;
use App\Models\Page;
use App\Models\PageGroup;
use App\Models\Playlist;
use App\Models\PlaylistGroup;
use App\Models\PlaylistListing;
use App\Models\ProjectListing;
use App\Models\Schedule\ScheduleListing;
use App\Models\Schedule\States\Playing;
use App\Models\User;
use App\Services\Schedule\Helpers\LoopModeService;
use App\Services\Schedule\Helpers\ScheduleFactory;
use App\Services\Schedule\Helpers\ScheduleRulesetAdapter;
use Illuminate\Support\Facades\DB;

/**
 * Service to help handle actions taken on the children (Page,Playlist) of the Scheduler's Layers
 */
class ScheduleLayerListingService
{
    public function __construct(
        protected ScheduleFactory           $scheduleFactory,
        protected ScheduleRulesetAdapter    $ruleAdapter,
        protected LoopModeService           $loopModeService,
    )
    {
    }

    /**
     * @refactor
     * Add the child (Playlist, etc.) to the given layer in the scheduler listing
     */
    public function store(User $authUser, ChannelLayer $layer, int $layer_child_id, $layer_child_type) /*: Playlist|PlaylistGroup*/
    {
        $layerNode = ScheduleListing::with('descendants')
                                     ->where('scheduleable_id', $layer->id)
                                     ->where('scheduleable_type', ChannelLayer::class)
                                     ->first();
        $has_existing_descendants = ($layerNode->descendants->count() > 0);
        ray(' # of layer descendants: ' . $layerNode->descendants->count());
        // ray("store new layer child", $layer->name, $layerNode, $layer_child_id, $layer_child_type);

        $childNode = DB::transaction(function () use ($authUser, $layerNode, $layer_child_id, $layer_child_type) {

            if ($layer_child_type === Playlist::class || $layer_child_type === PlaylistGroup::class) {
                $listing_model = ProjectListing::class;
                $listing_morph_col = 'projectable';
            } else {
                $listing_model = PlaylistListing::class;
                $listing_morph_col = 'playlistable';
            }
            $listing_morph_id_col = $listing_morph_col . '_id';
            $listing_morph_type_col = $listing_morph_col . '_type';

            // ray($listing_model::where($listing_morph_id_col, $layer_child_id)->where($listing_morph_type_col, $layer_child_type)->toSql(), $layer_child_id, $layer_child_type);

            $listing_entry = $listing_model::where($listing_morph_id_col, $layer_child_id)
                                           ->where($listing_morph_type_col, $layer_child_type)
                                           ->whereNull('group_id')
                                           ->first();

            // ray('listing pivot entry: ', $listing_entry)->purple();

            // We only want to retrieve existing child nodes of this layer if they had already
            // existed before and were deleted, because even if we are referencing the same
            // $listing_entry, its ScheduleListing node should be unique, i.e., the same
            // playlist in different layers
            $childNode = ScheduleListing::onlyTrashed()->firstOrNew([
                'listing_id'        => $listing_entry->id,
                'listing_type'      => $listing_entry::class,
                'scheduleable_id'   => $layer_child_id,
                'scheduleable_type' => $layer_child_type,
                'parent_id'         => $layerNode->id,
            ]);

            // order should be the same as other listing pivots
            $childNode->sort_order = $listing_entry->sort_order;

            // **NOTE**: attempting to mute events via ScheduleListing::withoutEvents/saveOrRestoreQuietly()
            // throws `Node must exists.` error from NestedSet
            $childNode->saveOrRestore();

            // ray('new ScheduleListing node: (sort: '.$listing_entry->sort_order.') ', $childNode)->purple();

            // $childNode->saveOrRestore() will restore deleted descendants, but if changes were made to
            // their Playlist/Project Listing entries while they were deleted, then those relevant
            // ScheduleListings need to be updated, and any new children of $childNode that were
            // added while the $childNode was deleted must be created now.
            if ($layer_child_type === Playlist::class) {
                $childNode = $this->createPlaylistChildren($childNode, $layer_child_id);
            } elseif ($layer_child_type === PlaylistGroup::class) {
                $childNode = $this->createPlaylistGroupChildren($childNode, $layer_child_id);
            }

            return $childNode;
        });

        DB::transaction(function () use ($layer, $layerNode, $authUser, $has_existing_descendants) {
            if ($layer->scheduleSet->status instanceof Playing && !$has_existing_descendants) {
                // layer was previously empty but should be playing, so find
                // the next playable item in the layer's newly added children
                // don't update the UI here because all UI data must be sent together or
                // some rows will not be highlighted correctly
                $this->loopModeService->createNewPlayoutsForLayer($layer->scheduleSet, $authUser, $layerNode, now());
            }
        });

        return $childNode->refresh();
    }

    /**
     * @refactor - move into own class
     */
    private function createListingEntryChildren(
        ScheduleListing $parentNode,
        string          $child_listing_class, // i.e., PlaylistListing / ProjectListing
        int             $parent_entity_id,
        string          $parent_id_col,
        string          $child_listing_morph_col // i.e., playlistable / projectable
    ) : ScheduleListing
    {
        $child_listing_morph_id_col = $child_listing_morph_col . '_id';
        $child_listing_morph_type_col = $child_listing_morph_col . '_type';

        /** @var PlaylistListing|ProjectListing $child_listing_class */
        $child_listings_query = $child_listing_class::where($parent_id_col, $parent_entity_id);
        if ($parent_id_col !== 'group_id') {
            $child_listings_query->whereNull('group_id');
        }
        $childListings = $child_listings_query->get();

        // ray("child listings", $childListings);

        // tie each child to ScheduleListing parent node, Scheduleable item, and its original listing
        $childNodesData = [];
        foreach ($childListings as $childListingPivot) {

            // ray("child listing loop: listing", $childListingPivot, $child_listing_morph_col . ': ', $childListingPivot->$child_listing_morph_col);

            if($childListingPivot->$child_listing_morph_type_col === Page::class
                && !isset($childListingPivot->$child_listing_morph_col->channel_id)){
                // only include Pages with a channel assigned
                continue;
            }

            // the related listing will be unique per layer, so we can update based on this data
            // we don't need to scope by parent_id because $parentNode->children() will
            // constrain the results for us
            $childAttr = [
                'listing_id'        => $childListingPivot->id,
                'listing_type'      => $childListingPivot::class,
                'scheduleable_id'   => $childListingPivot->$child_listing_morph_id_col,
                'scheduleable_type' => $childListingPivot->$child_listing_morph_type_col,
            ];
            $childNodesData = $childAttr;
            $childNodesData['sort_order']= $childListingPivot->sort_order;

            $childNode = $parentNode->children()->updateOrCreate($childAttr, $childNodesData);

            // ray('$childListingPivot->$child_listing_morph_type_col', $childListingPivot->$child_listing_morph_type_col);

            match ($childListingPivot->$child_listing_morph_type_col) {
                PlaylistGroup::class => $this->createPlaylistGroupChildren($childNode, $childListingPivot->$child_listing_morph_id_col),
                PageGroup::class     => $this->createPageGroupChildren($childNode, $childListingPivot->$child_listing_morph_id_col),
                Playlist::class      => $this->createPlaylistChildren($childNode, $childListingPivot->$child_listing_morph_id_col),
                default              => null
            };
        }

        return $parentNode;
    }

    /**
     * @refactor
     */
    private function createPlaylistGroupChildren($parentNode, $parent_entity_id) : ScheduleListing
    {
        return $this->createListingEntryChildren(
            $parentNode,
            ProjectListing::class,
            $parent_entity_id,
            'group_id',
            'projectable',
        );
    }

    /**
     * @refactor
     */
    private function createPlaylistChildren($parentNode, $parent_entity_id) : ScheduleListing
    {
        return $this->createListingEntryChildren(
            $parentNode,
            PlaylistListing::class,
            $parent_entity_id,
            'playlist_id',
            'playlistable',
        );
    }

    /**
     * @refactor
     */
    private function createPageGroupChildren($parentNode, $parent_entity_id) : ScheduleListing
    {
        return $this->createListingEntryChildren(
            $parentNode,
            PlaylistListing::class,
            $parent_entity_id,
            'group_id',
            'playlistable',
        );
    }
}
