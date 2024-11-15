<?php

namespace App\Observers;

use App\Models\Page;
use App\Models\PlaylistGroup;
use App\Models\PlaylistListing;
use App\Models\ProjectListing;
use App\Models\Schedule\ScheduleListing;
use App\Services\Schedule\Helpers\ScheduleListingFactory;
use App\Services\Schedule\ScheduleListingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * When relevant, copy actions taken on PlaylistListing, ProjectListing or ScheduleListing between the models
 *
 * refactor/optimize: look into a way to batch the copy? especially when multi-select is enabled
 */
class ListingObserver
{
    /**
     * Handle events after all transactions are committed.
     *
     * @var bool
     */
    protected $afterCommit = true;

    protected PlaylistListing|ProjectListing|ScheduleListing $fromListing;
    protected PlaylistListing|ProjectListing|ScheduleListing|null $oldListing;

    public function __construct(public ScheduleListingFactory $factory, public ScheduleListingService $scheduleListingService)
    {
    }

    private function initListings(PlaylistListing|ProjectListing|ScheduleListing $listing, string $event='[doing something with]'){
        $this->fromListing = $listing;

        /**
         * Keep track of the initial values when the event starts so that we can use them to
         * find the relevant listing and make the same changes after the event has completed
         */
        $this->oldListing = $this->fromListing->fresh();

        ray("A '".Str::afterLast($listing::class, '\\')."' $event '".($this->fromListing->playlistable?->name ?? $this->fromListing->projectable?->name ?? $this->fromListing->scheduleable?->name)."'...")->purple();
        ray('FROM listing entry: ', $this->fromListing)->purple();
    }

    /**
     * Copy a newly created entry to another listing(s)
     *
     * @throws \ErrorException
     */
    public function created(PlaylistListing|ProjectListing|ScheduleListing $listing) : void
    {
        $this->initListings($listing, 'created');

        if(!$this->shouldCopy('created')){
            ray("Listing should NOT copy")->orange();
            return;
        }

        $this->copy();
    }

    /**
     * Copy an updated entry to another listing(s)
     *
     * @throws \ErrorException
     */
    public function updating(PlaylistListing|ProjectListing|ScheduleListing $listing) : void
    {
        /*if($listing instanceof ScheduleListing){
            return;
        }*/

        $this->initListings($listing, 'updating');

        if(!$this->shouldCopy('updating')){
            ray("Listing should NOT copy")->orange();
            return;
        }

        $this->copy();
    }

    /**
     * Delete the related schedule listing if it exists
     */
    public function deleted(PlaylistListing|ProjectListing|ScheduleListing $listing) : void
    {
        if($listing instanceof ScheduleListing){
            // don't copy delete from scheduler to other listings
            return;
        }

        $this->initListings($listing, 'deleted');

        // the same item could be in multiple layers, so remove all that match
        $scheduleListingPivots = ScheduleListing::whereMatchesListing($listing)->get();

        if($scheduleListingPivots->count() === 0){
            return;
        }

        foreach($scheduleListingPivots as $scheduleListingPivot){
            $scheduleListingPivot->deleteQuietly();
        }
    }

    public function forceDeleted(PlaylistListing|ProjectListing|ScheduleListing $listing) : void
    {
        $this->deleted($listing);
    }

    /**
     * @throws \ErrorException
     */
    protected function copy() : void
    {
        if($this->fromListing instanceof ScheduleListing){
            $this->copyFromScheduleListing();
        } else {
            $this->copyToScheduleListing();
        }
    }

    // refactor: probably move the following methods into another class
    /**
     * ORDERING: We don't need to copy order from Scheduler because the front end drag&drop
     * within Scheduler widget directly calls the PlaylistListing/ProjectListing.
     * ScheduleListing order is only changed via ListingObserver after that.
     *
     * RELATIONS: Relation changes that we can let existing functions handle:
     *      - page -> pageGroup -- dragAndDropInPlaylist()
     *      - pageGroup -> pageGroup -- dragAndDropInPlaylist()
     *      - playlist -> playlistGroup -- dragAndDropInPlaylist()
     */
    protected function copyFromScheduleListing() : void
    {
        $listingPivot = $this->fromListing->listing;

        if(!isset($listingPivot)){
            return;
        }

        ray('copying FROM ScheduleListing to '.$listingPivot::class);

        // NOTE: Don't need to copy order from Scheduler because the front end drag&drop
        // within Scheduler widget directly calls the PlaylistListing/ProjectListing.
        // ScheduleListing order is only changed via ListingObserver after that.

        if($this->oldListing?->parent_id !== $this->fromListing->parent_id){
            // parent/group changed

        }

        // not quietly because changes may need to cascade back to the scheduler
        // i.e., same playlist in 2 different layers, and playlist order was
        // changed in only one of those layers
        $listingPivot->saveOrRestore();
    }

    /**
     * Copy from PlaylistListing or ProjectListing to ScheduleListing if $this->fromListing
     * is present in, or has a parent in, schedule_listings.
     *
     * @throws \ErrorException
     */
    protected function copyToScheduleListing() : void
    {
        ray("copy from '".$this->fromListing::class."' to 'ScheduleListing'");

        DB::transaction(function() {

            // the same item could be in multiple layers, so change all that match
            $scheduleListingPivots = ScheduleListing::whereMatchesListing($this->fromListing)->get();

            ray("scheduleListingPivots to change: ", $scheduleListingPivots)/*->green()*/;

            if($scheduleListingPivots->count() === 0){
                ray("NOT IN SCHEDULER YET");
                // Find the "parent" (PageGroup/Playlist/PlaylistGroup) in the scheduler, if it exists
                // The same parent could be in multiple layers, so get all that match
                $parentScheduleListingPivots = ScheduleListing::whereHasListingParent($this->fromListing)->get();

                ray("parentScheduleListingPivots", $parentScheduleListingPivots);

                if($parentScheduleListingPivots->count() === 0){
                    ray("don't copy; no parents in scheduler")->orange();
                    // don't copy; no parents in scheduler
                    return;
                }

                // create new entries for each copy of the parent that exists in the Scheduler listing
                // NOTE: Ideally this would not fire model events, but when events are muted NestedSet
                //       breaks when trying to append the new node to the parent node
                $scheduleListingPivots = $this->factory->createManyFrom($this->fromListing, $parentScheduleListingPivots);

                return;
            }

            // update the records in ScheduleListing
            foreach($scheduleListingPivots as $scheduleListingPivot){
                if($this->oldListing?->sort_order !== $this->fromListing->sort_order) {
                    // sort_order can be copied directly because it is the same regardless of groups,
                    // since buildSortQuery scopes by parent_id for ScheduleListing, which is
                    // analogous to PlaylistListing->playlist_id, etc.
                    $scheduleListingPivot->sort_order = $this->fromListing->sort_order;
                }

                // relation changes
                if($this->oldListing?->group_id !== $this->fromListing->group_id){

                    ray('group changed: '.$this->oldListing?->group_id.' --> '.$this->fromListing->group_id)/*->green()*/;

                    $parent_col = $this->determineParentColumn();

                    ray('parent column: '.$parent_col)/*->green()*/;

                    // Only change the parent of THIS specific node in the ScheduleListing.
                    // Any other copies of the scheduleable that exist in the Scheduler
                    // will be handled by the rest of the iterations of the loop
                    $scheduleListingPivot = $this->changeScheduleListingParentNode($scheduleListingPivot, $parent_col);
                }

                ray('changed listing pivot', $scheduleListingPivot)/*->green()*/;

                $scheduleListingPivot->saveOrRestoreQuietly();

            } // end foreach $scheduleListingPivot
        }); // end transaction
    }

    /**
     * Determine whether we moved INTO a group and set group_id, or OUT of all groups and
     * need to make a playlist/layer the parent
     */
    private function determineParentColumn() : string
    {
        return match($this->fromListing::class){
            // moved Page/PageGroup
            PlaylistListing::class => $this->fromListing->group_id === null
                ? 'playlist_id' // moved out of a group
                : 'group_id',   // moved into a group
            // moved Playlist/PlaylistGroup
            ProjectListing::class => $this->fromListing->group_id === null
                ? 'project_id'  // moved out of a group
                : 'group_id',   // moved into a group
        };
    }

    /**
     * Find the related ProjectListing/PlaylistListing entry of the parent of a given
     * ScheduleListing node. Change the parent of $scheduleListingPivot based on
     * the parent column of the original listing.
     */
    private function changeScheduleListingParentNode(ScheduleListing $scheduleListingPivot, string $parent_col='group_id') : ScheduleListing
    {
        // To ensure we have retrieved the parent of the correct node, make sure their roots match.
        // Otherwise, another node/multiple nodes matching $parentListingPivot could be returned.
        $commonRootNode = ScheduleListing::whereRootOf($scheduleListingPivot)->first();

        ray('changeScheduleListingParentNode', 'common root node: ', $commonRootNode)->blue();

        // find the entry of the parent in ProjectListing/PlaylistListing
        if($parent_col === 'group_id'){
            // the entity was moved into a group, parent will be a PageGroup or PlaylistGroup

            ray('the entity was moved into a group, parent will be a PageGroup or PlaylistGroup', $this->fromListing->group)->blue();

            $parentListingPivot = $this->fromListing->group->parentListingPivot;
        } elseif($parent_col === 'project_id'){
            // a playlist/playlistGroup was moved out of a group, its non-schedule parent will be a project,
            // so schedule parent will be the layer

            ray('a playlist/playlistGroup was moved out of a group, its non-schedule parent will now be a project, so schedule parent will be the layer', $this->fromListing->project)->blue();

            $parentListingPivot = $commonRootNode;
        } else {
            // a page/pageGroup was moved out of a group, its parent will be a playlist

            ray('a page/pageGroup was moved out of a group, its parent will be a playlist', $this->fromListing->playlist)->blue();

            $parentListingPivot = $this->fromListing->playlist->parentListingPivot;
        }

        ray('$parent_col: '.$parent_col, '$parentListingPivot', $parentListingPivot)->blue();

        // Get the new parent node that we should move $scheduleListingPivot to in ScheduleListing.
        $scheduleParentPivot = ($parentListingPivot === $commonRootNode)
            ? $parentListingPivot // the parent is the layer
            : ScheduleListing::whereMatchesListing($parentListingPivot)
                             ->whereDescendantOf($commonRootNode)
                             ->first();

        ray('$scheduleParentPivot', $scheduleParentPivot, $scheduleParentPivot?->scheduleable?->name)->blue();

        $scheduleListingPivot->parent()->associate($scheduleParentPivot);

        return $scheduleListingPivot;
    }

    /**
     * Should $this->from_listing have its changes copied to another listing(s)?
     */
    protected function shouldCopy(string $event) : bool
    {
        // we may want to copy creations, such as add page, since they will have channels on add if a default channel is set
        if(isset($this->oldListing) && $this->oldListing->sort_order === $this->fromListing->sort_order
            && $this->oldListing->group_id === $this->fromListing->group_id && $event !== 'created'){

            // ray("old Listing", $this->oldListing);
            // ray("from Listing", $this->fromListing);
            ray("don't copy; the listing (sort_order, group_id) wasn't changed, and nothing was created")->orange();

            // the listing wasn't changed
            return false;
        }

        if ($this->fromListing instanceof PlaylistListing) {
            return $this->shouldCopyFromPlaylistListing();
        }

        if($this->fromListing instanceof ScheduleListing){
            return $this->shouldCopyFromScheduleListing();
        }

        if($this->fromListing instanceof ProjectListing){
            /* for debugging --> */ if(!$this->shouldCopyFromProjectListing()){ ray("don't copy, the edited record was NOT a PlaylistGroup or Playlist in a PlaylistGroup"); }
            return $this->shouldCopyFromProjectListing();
        }

        return true;
    }

    /**
     * Copy only PageGroups, Pages with a PageGroup or Playlist, or Pages with a Channel assigned.
     */
    protected function shouldCopyFromPlaylistListing() : bool
    {
        if(is_null($this->fromListing->playlist_id) && is_null($this->fromListing->group_id)){
            // don't copy, we don't care about Pages with no Playlist or PageGroup
            ray("don't copy, we don't care about Pages with no Playlist or PageGroup")->orange();
            return false;
        }

        if(($this->fromListing->playlistable_type === Page::class && $this->fromListing->playlistable->channel_id === null)){
            // don't copy, we don't care about Pages with no Channel
            ray("don't copy, we don't care about Pages with no Channel")->orange();
            return false;
        }

        if ($this->fromListing->playlistable_type === Page::class && $this->fromListing->playlistable->original_id !== null) {
            // don't copy, we don't care about Pages that are References
            ray("don't copy, we don't care about Pages that are References")->orange();
            return false;
        }

        return true;
    }

    /**
     * Copy only PlaylistGroups or Playlists inside a PlaylistGroup
     */
    protected function shouldCopyFromProjectListing() : bool
    {
        return ($this->oldListing?->sort_order !== $this->fromListing->sort_order
            || $this->oldListing?->group_id !== $this->fromListing->group_id);
    }

    /**
     * Copy if the listing entry does NOT represent a layer, or if the entry represents
     * a PlaylistGroup, or Playlist in a PlaylistGroup
     */
    protected function shouldCopyFromScheduleListing() : bool
    {
        if(empty($this->fromListing->listing_type)){
            // this is a layer's entry, no listing to copy to
            ray("don't copy, the edited record is a Layer")->orange();
            return false;
        }

        if($this->fromListing->listing_type === ProjectListing::class
            && $this->fromListing->scheduleable_type !== PlaylistGroup::class
        ){
            // the listing entry belongs to a playlist
            $listings = $this->fromListing->scheduleable->scheduleListingPivots->filter(function($scheduleListingPivot){
                // is the playlist in a playlistGroup?
                return $scheduleListingPivot->parent?->scheduleable_type === PlaylistGroup::class;
            });

            if($listings->count() >= 1){
                return true;
            }

            // the edited record was NOT a PlaylistGroup or Playlist in a PlaylistGroup
            ray("don't copy, the edited record was NOT a PlaylistGroup or Playlist in a PlaylistGroup")->orange();
            return false;
        }

        return true;
    }
}
