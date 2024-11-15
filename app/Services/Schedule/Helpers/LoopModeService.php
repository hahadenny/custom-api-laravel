<?php

namespace App\Services\Schedule\Helpers;

use App\Events\PlayoutFinished;
use App\Jobs\SendSchedulerPlayoutJob;
use App\Jobs\UpdateUIStatusJob;
use App\Models\ChannelLayer;
use App\Models\Page;
use App\Models\Schedule\ScheduleChannelPlayout;
use App\Models\Schedule\ScheduleListing;
use App\Models\Schedule\ScheduleSet;
use App\Models\Schedule\States\Finished;
use App\Models\Schedule\States\Idle;
use App\Models\Schedule\States\Next;
use App\Models\Schedule\States\Paused;
use App\Models\Schedule\States\Playing;
use App\Models\Schedule\States\PlayoutState;
use App\Models\Schedule\States\Stopped;
use App\Models\User;
use App\Services\Schedule\ScheduleListingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoopModeService
{
    public array $playingNow    = [];
    public array $playingNext   = [];
    public array $playingPaused = [];

    public function __construct(
        protected ScheduleRulesetAdapter $rulesetAdapter,
        protected ScheduleListingService $listingService,
        protected PlayoutProcessor       $processor,
        protected PlayoutValidator       $validator,
    )
    {
    }

    public function stop(ScheduleSet $scheduleSet) : void
    {
        // delete all playouts for set
        ScheduleChannelPlayout::where('schedule_set_id', $scheduleSet->id)->delete();
        $this->updateUiStatus([], [], [], $scheduleSet->id);
    }

    /**
     * Don't need to check for paused AND new layers because we would create the playouts when
     * the pages were added to the paused listing
     *
     * @throws \Exception
     */
    public function play(ScheduleSet $scheduleSet, User $creator, $user_timezone = null) : void
    {
        $playouts = ScheduleChannelPlayout::forLoopOfSet($scheduleSet, Paused::class)->get();

        if ($playouts->count() > 0) {
            $this->resume($playouts, $scheduleSet, $creator, $user_timezone = null);
            return;
        }

        $this->createPlayoutsForChannel($scheduleSet, $creator);
    }

    /**
     * Pause any playouts that are currently playing.
     * Set pause status of playing items even if they have no remaining duration
     * and have been transitioned to `Finished` state
     *
     * @throws \Exception
     */
    public function pause(ScheduleSet $scheduleSet, User $user, $user_timezone = null)
    {
        DB::transaction(function () use ($scheduleSet, $user, $user_timezone) {
            $playouts = ScheduleChannelPlayout::forLoopOfSet($scheduleSet, Playing::class)->get();

            if ($playouts->count() === 0) {
                // nothing playing
                return;
            }

            foreach ($playouts as $oldPlayout) {

                if ( !isset($oldPlayout)) {
                    continue;
                }
                $listing = $oldPlayout->listing;
                $page = $listing->scheduleable;

                $oldPlayout->elapsed = $this->processor->calculateElapsed($listing, $oldPlayout->start);
                $oldPlayout = $this->processor->calculateRemaining($oldPlayout, $listing);

                // ray('id: '.$oldPlayout->id . ' -- elapsed: '.$oldPlayout->elapsed.' -- remaining: '.$oldPlayout->remaining.' ---> status: '.$oldPlayout->status);

                if (empty($oldPlayout->remaining)) {
                    // we won't be able to play this on resume, so we should use "next" as "this" playout
                    // and find new potentially valid playouts for new "next"
                    $oldPlayout->status->transitionTo(Finished::class);
                    $oldPlayout->save();
                    // fire event for internal use (tracking playout history)
                    PlayoutFinished::dispatch($oldPlayout);

                    // ready the "paused" status for UI on oldPlayout
                    $this->playingPaused [$oldPlayout->schedule_set_id][$oldPlayout->id] = ['page' => $page, 'playout' => $oldPlayout];
                    $this->playingNow [$oldPlayout->schedule_set_id][$oldPlayout->id] = ['page' => $page, 'playout' => $oldPlayout];

                    // use $oldPlayout->next as "this" playout, or create new one if `next` doesn't exist
                    // don't need to check valid schedule since we don't know if it would still be valid on resume anyway
                    $playout = $this->nextPlayoutFromExisting($oldPlayout);

                    $playout->status->transitionTo(Paused::class);
                    // refresh listing data for this "new" playout
                    $playout->loadMissing(['listing']);
                    $listing = $playout->listing;
                    $listing->loadMissing(['scheduleable', 'schedule.rules']);
                    $page = $listing->scheduleable;

                    // ready the "paused" status for UI
                    $this->playingPaused [$playout->schedule_set_id][$playout->id] = ['page' => $page, 'playout' => $playout];
                    $this->playingNext [$playout->schedule_set_id][$playout->id] = ['page' => $page, 'playout' => $playout];

                    // create new "next" playout; find the next playable item in the listing
                    // order after $playout, and add to $this->playingPaused
                    // don't need to check valid schedule since we don't know if it would still be valid on resume anyway
                    $next_playable_node = $this->findNextPlayableNode($listing);
                    [$nextPlayout, $this->playingNow, $this->playingNext] = $this->processor->createNewPlayout(
                        $scheduleSet,
                        $user,
                        $next_playable_node,
                        null,
                        Paused::$name,
                        $this->playingNow,
                        $this->playingNext
                    );
                    $playout->next()->associate($nextPlayout);
                    $playout->save();
                    continue;
                }

                $playout = $oldPlayout;
                $playout->status->transitionTo(Paused::class);
                $playout->start = null;
                $playout->end = null;
                $playout->save();

                $nextPlayout = $this->nextPlayoutFromExisting($playout, Next::class);

                $nextPlayout->status->transitionTo(Paused::class);
                $nextPage = $nextPlayout->listing->scheduleable;
                $nextPlayout->start = null;
                $nextPlayout->end = null;
                $nextPlayout->save();

                // ready the "paused" status for UI
                $this->playingPaused [$playout->schedule_set_id][$playout->id] = ['page' => $page, 'playout' => $playout];
                $this->playingNow [$playout->schedule_set_id][$playout->id] = ['page' => $page, 'playout' => $playout];
                $this->playingPaused [$playout->schedule_set_id][$nextPlayout->id] = ['page' => $nextPage, 'playout' => $nextPlayout];
                $this->playingNext [$playout->schedule_set_id][$nextPlayout->id] = ['page' => $nextPage, 'playout' => $nextPlayout];
            } // end each listing

            $this->playingPaused = array_unique($this->playingPaused);

            $this->updateUiStatus();
        });

    }

    /**
     * @throws \Exception
     * @deprecated - move to Validator/Processor
     *
     * Make sure `next` exists for the given $oldPlayout (maybe a page was removed and it was reset)
     * If one doesn't exist, then create it.
     *
     */
    public function nextPlayoutFromExisting(
        ScheduleChannelPlayout    $oldPlayout,
        string                    $status = Idle::class,
        bool                      $validate = false,
        string|\DateTimeInterface $starting = null
    ) : ScheduleChannelPlayout
    {
        if (empty($oldPlayout->next) || ($validate && !$this->validator->validate($oldPlayout->listing, $starting))) {
            $playable_node = $validate
                ? $this->findNextValidPlayableNode($oldPlayout->listing, $starting)
                : $this->findNextPlayableNode($oldPlayout->listing);

            /** @var PlayoutState $status */
            [$playout, $this->playingNow, $this->playingNext] = $this->processor->createNewPlayout(
                $oldPlayout->listing->scheduleSet,
                null,
                $playable_node,
                null,
                $status::$name,
                $this->playingNow,
                $this->playingNext
            );

            return $playout;
        }

        return $oldPlayout->next;
    }

    public function resume(Collection $playouts, ScheduleSet $scheduleSet, User $creator, $user_timezone = null) : void
    {
        DB::transaction(function () use ($scheduleSet, $creator, $playouts) {
            // send together
            $this->playingNow = [];
            $this->playingNext = [];

            $starting = now();

            $playouts->each(function ($playout) use ($starting) {

                if ( !isset($playout)) {
                    return true; // aka continue;
                }

                $listingNode = $playout->listing;
                $page = $listingNode->scheduleable;

                // NOTE: we know paused playouts have remaining duration

                // update playout time
                $start = ScheduleDatetimeHelper::createDateTime($starting->format("Y-m-d"), $starting->format("H:i:s"));
                $end = ScheduleDatetimeHelper::calculateDurationEnd($start, $playout->remaining);
                $playout->start = $start->format("Y-m-d H:i:s");
                $playout->end = $end->format("Y-m-d H:i:s");

                $playout = $this->validator->validate($listingNode, $starting)
                    ? $playout
                    : $this->nextValidPlayoutFromExisting($playout, $playout->start);

                // set to playing
                $playout->status->transitionTo(Playing::class);

                // send new "playing" playout
                // todo: (after commit? - might be too delayed, should transaction be moved inside loop?)
                SendSchedulerPlayoutJob::dispatch($page, $playout->playoutChannel)->afterCommit();

                $this->playingNow [$playout->schedule_set_id][$playout->id] = ['page' => $page, 'playout' => $playout];

                // determine next playout for $playout for the selected ScheduleSet listing
                $nextPlayout = $this->nextValidPlayoutFromExisting($playout, $playout->start, Next::class);

                $nextPlayout->status->transitionTo(Next::class);
                $nextPlayout->loadMissing(['listing.scheduleable', 'listing.schedule.rules']);
                $nextPage = $nextPlayout->listing->scheduleable;

                $duration = $nextPlayout->listing->duration;
                $next_start = ScheduleDatetimeHelper::createDateTime($playout->end->format("Y-m-d"), $playout->end->format("H:i:s"));
                $next_end = ScheduleDatetimeHelper::calculateDurationEnd($next_start, $duration);
                $nextPlayout->start = $next_start;
                $nextPlayout->end = $next_end;
                $nextPlayout->save();

                if ($nextPage->id !== $page->id) {
                    $this->playingNext [$playout->schedule_set_id][$nextPlayout->id] = ['page' => $nextPage, 'playout' => $nextPlayout];
                }
            });

            $this->updateUiStatus();
        });
    }

    /**
     * @throws \Exception
     * @deprecated - move to Validator/Processor
     *
     */
    public function nextValidPlayoutFromExisting(ScheduleChannelPlayout $oldPlayout, string|\DateTimeInterface $starting, string $status = Idle::class,) : ScheduleChannelPlayout
    {
        $status ??= Idle::class;
        return $this->nextPlayoutFromExisting($oldPlayout, $status, true, $starting);
    }

    /**
     * Create playouts for a ScheduleSet
     *
     * @throws \Exception
     */
    public function createPlayoutsForChannel(ScheduleSet $scheduleSet, User $creator)
    {
        DB::transaction(function () use ($creator, $scheduleSet) {

            // optimize:
            //    Probably Look into using `descendants` instead of `children` so that we can load all relations together,
            //    but we are using children elsewhere (i.e., when sorting the tree and checking for next playable) so
            //    that would be a more complex change than there is time for right now
            // get all layers with children and other relevant data
            $layerNodes = ScheduleListing::with([
                'scheduleable',
                'schedule.rules',
                'children.scheduleable',
                'children.schedule.rules',
                'children.children.scheduleable',
                'children.children.schedule.rules',
            ])->where('schedule_set_id', $scheduleSet->id)
              ->has('scheduleable')
                                         ->orderBy('parent_id')
                                         ->orderBy('sort_order')
                                         ->get();
            $layerNodes = ScheduleListingService::sortTreeChildren($layerNodes->toFlatTree()->toBase());

            $starting = now();

            foreach ($layerNodes as $layerNode) {
                $this->createNewPlayoutsForLayer($scheduleSet, $creator, $layerNode, $starting);
            } // end each layer node

            if ($scheduleSet->status instanceof Playing) {
                $this->updateUiStatus();
            }
        }); // end transaction
    }

    public function updateUiStatus(array $playingNow = null, array $playingNext = null, array $playingPaused = null, int $schedule_set_id = null) : void
    {
        $playingNow ??= $this->playingNow;
        $playingNext ??= $this->playingNext;
        $playingPaused ??= $this->playingPaused;

        $payload = [
            Playing::$name => [],
            Next::$name    => [],
            Paused::$name  => [],
            Stopped::$name => [],
        ];

        // stopped
        if (isset($schedule_set_id) && empty($playingNow) && empty($playingNext) && empty($playingPaused)) {
            UpdateUIStatusJob::dispatch($schedule_set_id, $payload)->afterCommit();
            return;
        }

        // optimize: (after commit might be too delayed, should transaction be moved inside loop? or batched?)
        foreach ($playingNow as $schedule_set_id => $playing_data) {
            array_push(
                $payload [Playing::$name],
                ...collect($playing_data)->pluck('playout')->pluck('schedule_listing_id')->all()
            );
            if (isset($playingNext[$schedule_set_id])) {
                array_push(
                    $payload [Next::$name],
                    ...collect($playingNext[$schedule_set_id])->pluck('playout')->pluck('schedule_listing_id')->all()
                );
            } else {
                Log::warning("Next playable for schedule set '{$schedule_set_id}' is not set. All next values: " . json_encode($playingNext, JSON_FORCE_OBJECT));
            }

            if (isset($playingPaused[$schedule_set_id])) {
                array_push(
                    $payload [Paused::$name],
                    ...collect($playingPaused[$schedule_set_id])->pluck('playout')->pluck('schedule_listing_id')->all()
                );
            }

            UpdateUIStatusJob::dispatch($schedule_set_id, $payload)->afterCommit();
        }
    }

    /**
     * @throws \Exception
     */
    public function createNewPlayoutsForLayer(ScheduleSet $scheduleSet, User $creator, ScheduleListing $layerNode, $starting) : void
    {
        $playable_node = $this->findNextValidPlayableNode($layerNode, $starting);
        if ( !isset($playable_node)) {
            return;
        }

        // create playout
        $playout_status = $scheduleSet->status instanceof Playing ? Playing::$name : Paused::$name;
        [$playout, $this->playingNow, $this->playingNext] = $this->processor->createNewPlayout(
            $scheduleSet,
            $creator,
            $playable_node,
            $starting,
            $playout_status,
            $this->playingNow,
            $this->playingNext
        );

        // find next -> if no valid siblings, then loop back to itself when done
        $next_status = $scheduleSet->status instanceof Playing ? Next::$name : Paused::$name;
        $next_playable_node = $this->findNextValidPlayableNode($playable_node, $playout->end) ?? $playable_node;
        [$nextPlayout, $this->playingNow, $this->playingNext] = $this->processor->createNewPlayout(
            $scheduleSet,
            $creator,
            $next_playable_node,
            $playout->end,
            $next_status,
            $this->playingNow,
            $this->playingNext
        );
        $playout->next()->associate($nextPlayout);
        $playout->save();
    }

    /**
     * @throws \Exception
     * @deprecated - move to Validator
     *
     * Find the next playable item in the node tree. The given `$node`'s full tree is loaded
     * and sorted according to `sort_order`, beginning with the first node that comes
     * after the given $node. Optionally, make sure the node's schedule is valid
     *
     */
    public function findNextPlayableNode(
        ?ScheduleListing          $node,
        \DateTimeInterface|string $starting = null,   // when the schedule check should start (i.e., now)
                                  $remainingNodes = null,
        bool                      $checkSchedule = false                   // whether the schedule rules should be checked
    ) : ?ScheduleListing
    {
        // ray(' --> --> Finding next playable item after "' . $node?->scheduleable?->name . '" (node ID: ' . $node->id . ')')->purple();

        // If $node is a layer, we need to make sure its own schedule is valid before even checking its children
        if ($node->scheduleable_type === ChannelLayer::class && ($checkSchedule && !$this->validator->validate($node, $starting))) {
            // the entire layer is not playable
            // ray("entire layer is not playable")->orange();
            return null;
        }

        $remainingNodes ??= $this->listingService->getSortOrderedTreeNodes($node);

        if (sizeof($remainingNodes) === 0) {
            // found nothing playable :(
            // ray("found nothing playable :( ")->orange();
            return null;
        }

        $nextNode = $remainingNodes->shift();
        // ray('remaining nodes to check for "'.$nextNode?->scheduleable?->name.' ('.$nextNode?->scheduleable_type.')": ', $remainingNodes->pluck('scheduleable')->pluck('name')->all(), )->blue();

        if ($nextNode->scheduleable === null) {

            // ray("scheduleable is null, move on to the next node")->orange();

            // move on to the next node
            return $this->findNextPlayableNode($nextNode, $starting, $remainingNodes, $checkSchedule);
        }

        // can't check using isLeaf() because we aren't ordering by _lft/_rgt
        if ($nextNode->children()->count() > 0) {
            // node belongs to a parent (playlist/layer/group/etc)

            // ray('$nextNode is NOT a leaf')->blue();

            if ($checkSchedule && !$this->validator->validate($nextNode, $starting)) {
                $potentialNodes = $remainingNodes->filter(function ($node) use ($nextNode) {
                    // ray($node->scheduleable->name.' isNotSelfOrDescendantOf($nextNode) ??? ', $node->isNotSelfOrDescendantOf($nextNode))->purple();
                    return $node->isNotSelfOrDescendantOf($nextNode);
                });

                // ray("schedule NOT valid, exclude node's ({$nextNode->scheduleable->name}, ID: {$nextNode->id}) descendants from the search, only use: ", $potentialNodes->pluck('scheduleable')->pluck('name')->all())->orange();

                // is parent & schedule NOT valid, so exclude node's children from the search
                return $this->findNextPlayableNode(
                    $nextNode,
                    $starting,
                    $potentialNodes,
                    $checkSchedule
                );
            }

            // ray("is parent & schedule was valid, include children in search: ", $nextNode->children->toBase()->merge($remainingNodes)->pluck('scheduleable')->pluck('name')->all())->blue();

            // playlist/layer schedule was valid, include children in search
            return $this->findNextPlayableNode($nextNode, $starting, $nextNode->children->toBase()
                                                                                        ->merge($remainingNodes), $checkSchedule);
        }

        // make sure it's a Page and not an empty parent
        if ($nextNode->scheduleable_type !== Page::class) {

            // ray("node is a leaf but not a page, move on to the next node")->orange();

            return $this->findNextPlayableNode($nextNode, $starting, $remainingNodes, $checkSchedule);
        }

        if ($checkSchedule && !$this->validator->validate($nextNode, $starting)) {

            // ray("schedule is invalid, move on to the next node")->orange();

            // node is not a Page, or schedule invalid, move on to the next node
            return $this->findNextPlayableNode($nextNode, $starting, $remainingNodes, $checkSchedule);
        }

        // ray("YAY, '{$nextNode?->scheduleable->name}' has valid schedule or no schedule, and is a leaf (Page)")->green();

        // YAY, valid schedule or no schedule, and a leaf (Page)
        return $nextNode;
    }

    /**
     * @throws \Exception
     * @deprecated - move to Validator
     *
     * Make sure schedule is valid when finding next playable node
     *
     */
    public function findNextValidPlayableNode(?ScheduleListing $node, \DateTimeInterface|string $starting, $remainingNodes = null) : ?ScheduleListing
    {
        return $this->findNextPlayableNode($node, $starting, $remainingNodes, true);
    }
}
