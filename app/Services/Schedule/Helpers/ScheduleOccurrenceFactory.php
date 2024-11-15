<?php

namespace App\Services\Schedule\Helpers;

use App\Contracts\Models\ScheduleableInterface;
use App\Contracts\Models\ScheduleableParentInterface;
use App\Enums\Schedule\ScheduleOrigin;
use App\Models\ChannelLayer;
use App\Models\Page;
use App\Models\Playlist;
use App\Models\Schedule\Schedule;
use App\Models\Schedule\ScheduleOccurrence;
use DateTimeInterface;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Methods to generate/destroy ScheduleOccurrences
 * @deprecated
 */
class ScheduleOccurrenceFactory
{
    public function __construct(
        protected ScheduleRulesetAdapter $rulesetAdapter,
        protected ScheduleDatetimeHelper $dateHelper,
        protected ScheduleFactory $scheduleFactory,
    ) {
    }

    /**
     * Generate occurrences from a Schedule's set of rules. If $regenerate=true,
     * delete the old occurrences after creating the new ones
     *
     * @param Schedule $schedule
     *
     * @return Schedule
     * @throws \Exception
     */
    public function generate(Schedule $schedule) : Schedule {
        // Log::debug(" *-- Generate for {$schedule->scheduleable->name} --*");

        // todo: maybe move this to an observer to do on rule creation?
        $this->generateFromRSet($schedule);

        // todo: add an observer to refresh the schedule monitor when occurrences created/deleted (?)

        return $schedule;
    }

    /**
     * Instantiate a ScheduleOccurrence model from a datetime string and
     * associate it with a schedule.
     * If $save is true, save model to DB
     *
     * @param Schedule               $schedule
     * @param DateTimeInterface      $start_datetime
     * @param DateTimeInterface|null $end_datetime
     * @param string                 $toFormat
     * @param bool                   $save
     *
     * @return ScheduleOccurrence
     */
    public function make(
        Schedule $schedule,
        DateTimeInterface $start_datetime,
        DateTimeInterface $end_datetime = null,
        bool $save = false,
        string $toFormat = "Y-m-d H:i:s",
    ) : ScheduleOccurrence {
        // Log::debug('params start: '.$start_datetime->format("Y-m-d H:i:s"));
        // Log::debug('params end: '.$end_datetime->format("Y-m-d H:i:s"));

        $attrs['start'] = $start_datetime->format($toFormat);
        if ( !empty($end_datetime)) {
            $attrs['end'] = $end_datetime->format($toFormat);
        }
        $occurrence = new ScheduleOccurrence($attrs);
        $occurrence->schedule()->associate($schedule);

        if ($save) {
            $occurrence->save();
        }

        return $occurrence;
    }

    /**
     * Calculate and create schedule occurrence dynamically from a
     * parent schedule instead of a schedule form.
     *
     * @param ScheduleableInterface            $scheduleable
     * @param                                  $user_timezone
     * @param null                             $priorEndOccurrence
     * @param ScheduleOccurrence|null          $parent
     * @param null                             $creator
     * @param ScheduleOrigin                   $origin
     * @param ScheduleableParentInterface|null $parentable
     *
     * @return ScheduleOccurrence
     * @throws \Exception
     */
    public function createLoopOccurrence(
        ScheduleableInterface $scheduleable,
        $user_timezone,
        $priorEndOccurrence = null,
        ScheduleOccurrence $parent = null,
        $creator = null,
        ScheduleOrigin $origin = ScheduleOrigin::Generated,
        // parent of the scheduleable param
        ScheduleableParentInterface $parentable = null,
    ) : ScheduleOccurrence {
        // set start time to now
        // parent's start time should be used if this is the first playable child in a loop
        $start = $priorEndOccurrence ?? $parent?->start ?? 'now';
        // Log::debug('prior end: '.$priorEndOccurrence);
        // Log::debug('parent start: '.$parent?->start);
        // Log::debug('parent start date class: '.get_class($parent?->start));
        // todo: get time as well (?)
        $start_datetime = $this->dateHelper->createDateTime($start);
        $duration       = $scheduleable->duration;
        $end_datetime   = $this->dateHelper->calculateDurationEnd($start_datetime, $duration);

        /** @var \App\Models\User $authUser */
        $creator ??= Auth::guard()->user();

        // timezone from browser
        $userTimezone = (new \DateTimeZone($user_timezone));

        Log::debug("Scheduleable '{$scheduleable->name}' (id: {$scheduleable->id}) schedule: ".$scheduleable->schedule);

        // get the related schedule or create it if it doesn't exist
        $schedule = $scheduleable->schedule ?? $this->scheduleFactory->make(
            $scheduleable->id,
            $scheduleable::class,
            $creator,
            $scheduleable,
            $userTimezone,
            $origin,
            $parentable?->id ?? null,
            isset($parentable) ? $parentable::class : null,
        );

        // todo: check to make sure no exclusion rules were broken (?)
        // todo: check to make sure no collision with existing rules/occurrences (?)
        $occurrence = $this->make(
            $schedule,
            $start_datetime,
            $end_datetime,
            save: true
        );

        $scheduleable->refresh();
        // $schedule->refresh();

        return $occurrence;
    }

    /**
     * Create ScheduleOccurrence models from RRule\RSet.
     * If the number of occurrences is infinite or over the limit, only create a subset of them.
     * If $save is true, save the new model to the DB
     *
     * @see https://github.com/rlanvin/php-rrule/wiki/Rset#iteration
     * > An instance of RSet can be used directly in a foreach loop to obtain occurrences.
     * > This is the most efficient way to use this class, as the occurrences are only
     * > computed one at the time, as you need them.
     *
     * @param Schedule $schedule
     * @param bool     $save
     *
     * @return SupportCollection
     * @throws \Exception
     */
    public function generateFromRSet(Schedule $schedule, bool $save = true) : SupportCollection
    {
        $occurrences = collect();

        $set = $schedule->getRSet() ?? $this->rulesetAdapter->createRecurrenceDataForSchedule($schedule);
        // determine how many occurrences to generate
        $limit        = ($set->isInfinite() || count($set) > ScheduleOccurrence::DEFAULT_LIMIT)
            ? ScheduleOccurrence::DEFAULT_LIMIT
            : null;
        $scheduleable = $schedule->scheduleable;
        $duration     = $scheduleable?->getDuration();

        ray($scheduleable);

        if ($scheduleable instanceof ScheduleableParentInterface) {
            if( $scheduleable instanceof ChannelLayer ){
                /** @var ChannelLayer $scheduleable */
                $scheduleable->loadMissing(['schedulePagesHavingAnyChannel', 'schedulePlaylists.scheduleableChildren']);
                // todo: THIS MERGE MAY OVERWRITE PLAYLISTS WITH SAME ID AS DIRECT PAGES
                $children = $scheduleable->schedulePlaylists->merge($scheduleable->schedulePagesHavingAnyChannel);
            } else {
                /** @var Playlist $scheduleable */
                $children = $scheduleable->scheduleableChildren;
            }
            $children = is_array($children) ? collect($children) : $children;

            ray('scheduleables children');
            ray($children);

            // On first run, wipe out any existing occurrences in case this scheduleable was a looped child some time in the past
            // todo: may need to check origin before wiping?
            $this->deleteRelatedOccurrences($children->pluck('schedule')->pluck('id')->all());
        }

        /** @var \DateTime $datetime */
        foreach ($set as $i => $datetime) {
            if (isset($children)) {
                // don't create an occurrence directly for a parent,
                // instead, occurrences are created for the parent's pages
                $save = false;
            }

            $occurrence = $this->make(
                $schedule,
                start_datetime: $datetime,
                end_datetime: $this->dateHelper->calculateDurationEnd($datetime, $duration),
                save: $save
            );
            $occurrences->push($occurrence);

            if (isset($children)) {
                // It's a playlist or channel layer

                /** @var ScheduleOccurrence $priorEndOccurrence  - end of the previous occurrence */
                $priorEndOccurrence = null;

                $this->createOccurrencesForChildren($children, $occurrence, $schedule, $priorEndOccurrence, $scheduleable);
            }

            if (isset($limit) && $i >= $limit) {
                break;
            }
        }
        return $occurrences;
    }

    private function createOccurrencesForChildren(SupportCollection $children, &$occurrence, &$schedule, &$priorEndOccurrence, $scheduleable) : void
    {
        $children->map(function ($child) use (&$occurrence, &$schedule, &$priorEndOccurrence, $scheduleable) {
            if ($child instanceof Page) {
                // ray('===== PAGE CHILD =====')->green();
                // ray('parent schedule')->green();
                // ray($schedule)->green();
                $priorEndOccurrence = $this->createLoopOccurrence(
                    $child,
                    $schedule->timezone,
                    $priorEndOccurrence?->end,
                    parent: $occurrence,
                    origin: ScheduleOrigin::Generated,
                    parentable: $scheduleable
                );
            } elseif ($child instanceof ScheduleableParentInterface) {
                // ray('~~~~~ PLAYLIST CHILD ~~~~~')->blue();
                // ray('parent schedule')->blue();
                // ray($schedule)->blue();
                $this->createOccurrencesForChildren($child->scheduleableChildren, $occurrence, $schedule, $priorEndOccurrence, $scheduleable);
            }
        });
    }

    /**
     * Regenerate occurrences belonging to the given Schedule.
     * (create new occurrences & delete old occurrences)
     *
     * NOTE: `deleting` and `deleted` model events will not be dispatched via delete()
     *
     * todo: move this to observer/queue on rule creation or playout?
     */
    public function regenerate(Schedule $schedule) : Schedule
    {
        Log::debug(" -- REGENERATE {$schedule->scheduleable->name} --");

        DB::transaction(function () use ($schedule) {

            $this->deleteRelatedOccurrences($schedule);

            $this->generate($schedule);
        });

        // $schedule->refresh();

        return $schedule;
    }

    /**
     * Delete occurrences where it matches the given Schedule(s), or where
     * the given Schedule(s) belong to a parent/grandparent.
     */
    public function deleteRelatedOccurrences(int|Schedule|array $schedule) : void
    {
        $schedule_id = is_int($schedule) ? $schedule : (is_array($schedule) ? $schedule : $schedule->id);
        // NOTE: does not fire model events
        ScheduleOccurrence::whereHasSchedule($schedule_id)
                          ->orWhereHasAncestralSchedule($schedule_id)
                          ->delete();
    }

    /*
    protected function isValidForChildSchedule(
        ScheduleableInterface $scheduleable,
        DateTimeInterface           $start_datetime,
        DateTimeInterface           $end_datetime,
        // parent of the scheduleable param
        ScheduleableParentInterface $parentable = null,
    ) : bool
    {
        if (!isset($parentable) || $scheduleable->schedule?->origin !== ScheduleOrigin::Scheduled) {
            return false;
        }

        // This scheduleable has a parent AND has been scheduled separately from that parent
        /** @var Schedule $childSchedule *
        $childSchedule = $scheduleable->schedule;

        /**
         * Does the occurrence we are generating occur(start?) within $childSchedule?
         *
         * @see https://github.com/rlanvin/php-rrule/wiki/RRuleInterface#getoccurrencesbetweenbegin-end-limit
         * > this method will return the events that start between `$begin` and `$end`,
         * > but not the events that started before `$begin` and might still be ongoing due to their duration.
         * > If you want the latter behavior, you should first subtract duration from `$begin`,
         * > to get the occurrences between "begin - duration" and "end"
         *

        /*
        // Is filtering the rules via query before checking occurrences helpful or not?
        //       Performance may be worse --> cache instead
        $childScheduleRules = $childSchedule->rules()
                                    // is $start_datetime >= the schedule's rules' `start_date`s?
                                            ->whereDate('start_date', '>=', $start_datetime->format('Y-m-d'))
                                            ->whereTime('start_time', '>=', $start_datetime->format('H:i:s'))
                                    // is $end_datetime < the schedule's rules' `end_date`s?
                                            ->whereDate('end_date', '<=', $end_datetime->format('Y-m-d'))
                                            ->whereTime('end_time', '<', $end_datetime->format('H:i:s'));
        //*

        // todo: make sure this is effective, may need to cache the child's occurrences instead
        // get the RSet for the rules of this child
        // use cache if exists, set cache if not
        $cache_key = 'schedule-'.$childSchedule->id.'-rset';
        /** @var RSet $childRSet *
        $childRSet = Cache::remember($cache_key, 300 , function () use ($childSchedule) {
            return $childSchedule->getRSet($this->rulesetAdapter);
        });

        // is this occurrence within any of the child's schedule's occurrences?
        $matching_occurrences = $childRSet->getOccurrencesBetween($start_datetime, $end_datetime);

        if (sizeof($matching_occurrences) < 1) {
            // this child should not be played during this occurrence of its parent
            return false;
        }

        return true;
    } //*/

}
