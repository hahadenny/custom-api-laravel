<?php

namespace App\Services\Schedule\Helpers;

use App\Jobs\SendSchedulerPlayoutJob;
use App\Models\Channel;
use App\Models\Channel as PortaChannel;
use App\Models\Page;
use App\Models\Schedule\ScheduleChannelPlayout;
use App\Models\Schedule\ScheduleListing;
use App\Models\Schedule\ScheduleSet;
use App\Models\Schedule\States\Next;
use App\Models\Schedule\States\Playing;
use App\Models\User;
use App\Services\Engines\Avalanche\AvalancheEvent;
use App\Services\Engines\D3\D3Event;
use App\Services\Engines\Unreal\UnrealEvent;
use App\Services\Schedule\ScheduleListingService;
use Illuminate\Support\Facades\Log;
use JetBrains\PhpStorm\ArrayShape;

class PlayoutProcessor
{
    public function __construct(
        protected ScheduleRulesetAdapter $rulesetAdapter,
        protected ScheduleListingService $listingService,
    )
    {
        // Log::debug("in PlayoutProcessor constructor");
    }

    /**
     * Create a new playout object and its accompanying status update data
     *
     * @throws \Exception
     */
    #[ArrayShape([
        0 => 'ScheduleChannelPlayout',
        1 => 'array', // playingNow data
        2 => 'array', // playingNext data
    ])]
    public function createNewPlayout(
        ScheduleSet     $scheduleSet,
        ?User           $creator,
        ScheduleListing $playable_node,
                        $starting,
                        $status,
                        $playingNow=null,
                        $playingNext=null
    ) : array
    {
        $duration = $playable_node->duration;
        $start = isset($starting) ? ScheduleDatetimeHelper::createDateTime($starting->format("Y-m-d"), $starting->format("H:i:s")) : null;
        $end = isset($start) ? ScheduleDatetimeHelper::calculateDurationEnd($start, $duration) : null;
        /** @var Page $page */
        $page = $playable_node->scheduleable;

        $playout = new ScheduleChannelPlayout([
            'schedule_set_id'  => $scheduleSet->id,
            // for now grig wants to play to the page channel
            'playout_channel_id'  => $page->channel_id,
            'schedule_listing_id' => $playable_node->id,
            'start'               => $start?->format("Y-m-d H:i:s"),
            'end'                 => $end?->format("Y-m-d H:i:s"),
            'status'              => $status,
            'remaining'           => $duration,
        ]);
        $playout->createdBy()->associate($creator);
        $playout->save();

        if ($scheduleSet->status instanceof Playing) {
            if($playout->status instanceof Playing) {
                // send playout to engine
                // optimize: (after commit? - might be too delayed, should transaction be moved inside loop? or batched?)

                // @optimize: dispatch playable node instead of page? seems redundant but check what Porta is looking for
                SendSchedulerPlayoutJob::dispatch($page, $playout->playoutChannel)->afterCommit();

                if(isset($playingNow)){
                    $playingNow [$playout->schedule_set_id][$playout->id]= ['page' => $page, 'playout' => $playout];
                } else {
                    // $this->playingNow [$playout->schedule_set_id][]= ['page' => $page, 'playout' => $playout];
                }
            } elseif ($playout->status instanceof Next){
                if(isset($playingNext)){
                    $playingNext [$playout->schedule_set_id][$playout->id]= ['page' => $page, 'playout' => $playout];
                } else {
                    // $this->playingNext [$playout->schedule_set_id][]= ['page' => $page, 'playout' => $playout];
                }
            }
        }

        return [$playout, $playingNow, $playingNext];
    }

    /**
     * @throws \Exception
     */
    public function calculateElapsed(ScheduleListing $listing, \DateTimeInterface $started, string|\DateTimeInterface $pausedAt=null)
    {
        // ray('duration: ', $listing->duration);

        $pausedAt ??= now();

        $originalStart = ScheduleDatetimeHelper::createDateTime($started->format("Y-m-d"), $started->format("H:i:s"));
        $pausedAt = ScheduleDatetimeHelper::createDateTime($pausedAt->format("Y-m-d"), $pausedAt->format("H:i:s"));
        $elapsed = abs($pausedAt->getTimestamp() - $originalStart->getTimestamp());

        // if elapsed was somehow way too long, set elapsed to duration instead
        return min($elapsed, $listing->duration);
    }

    public function calculateRemaining(ScheduleChannelPlayout $playout, ScheduleListing $listing) : ScheduleChannelPlayout
    {
        $remaining = ($playout->remaining ?? $listing->duration) - $playout->elapsed;
        $playout->remaining = $remaining < 0 ? null : $remaining;
        return $playout;
    }

    // modification of PageListing.js > onSend()
    public function prepareBroadcastData(Page $page, Channel $channel) : array|null
    {
        // ray('prepareBroadcastData -- '.$channel->name)->blue()->label('prepareBroadcastData');
        Log::debug("PlayoutProcessor -- preparing broadcast data for page: $page->name, channel: $channel->name");

        $page->loadMissing(['template', 'channel']);
        // channel type format isn't very consistent, so just remove the prepended slash if it exists
        $engineEvent = match (ltrim($channel->type, "/")) {
            ltrim(PortaChannel::TYPE_DISGUISE, "/")  => new D3Event(),
            ltrim(PortaChannel::TYPE_AVALANCHE, "/") => new AvalancheEvent(),
            default => new UnrealEvent()
        };

        // there may not be an authenticated user within this process, so check creator access instead (??)
        $hasChannelAccess = $page->createdBy->hasChannelAccess($channel);

        if ( !($channel->name && $page->template?->preset
            && ($hasChannelAccess || $page->template?->preset === 'd3')
        )) {
            return [
                'message' => 'no access or data is missing... channel:' . $channel->name
                    . ' -- preset: ' . $page->template?->preset . '  -- access: ' . $hasChannelAccess,
            ];
        }

        if ($page->type === 'Numeric') {
            $page->name = $page->page_name;
        }

        if (
            mb_strtolower($page->template->preset) === 'd3'
            || mb_strtolower($page->template->engine) === 'd3'
            || mb_strtolower($page->template->engine) === 'disguise'
        ) {
            // handle D3

            Log::debug("PlayoutProcessor -- D3 EngineEvent --> calling D3Event->buildSubmission() for page: $page->name, channel: $channel->name");

            return $engineEvent->buildSubmission(
                $page->channel?->name ?? $page->channelEntity->name,  // channel
                $page->data,           // data
                $page->template->data // schema
            );
        }
        if (mb_strtolower($page->template->engine) === 'avalanche') {
            // handle avalanche
            return $engineEvent->buildSubmission(
                $page->channel?->parent?->name ?? $page->channelEntity?->parent?->name,   // channel - send on the unreal channel & namespace
                $page->data,                    // data
                $page->template->data,          // schema
                $page->template->preset,        // asset (preset)
                $page->channel?->name ?? $page->channelEntity->name,           // avalancheChannel
            );
        } else {
            // handle unreal
            return $engineEvent->buildSubmission(
                $page->channel?->name ?? $page->channelEntity->name,   // channel
                $page->data,            // data
                $page->template->data,  // schema
                $page->template->preset // preset
            );
        }
    }
}
