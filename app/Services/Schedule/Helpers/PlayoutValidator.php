<?php

namespace App\Services\Schedule\Helpers;

use App\Models\Schedule\ScheduleListing;
use App\Models\Schedule\ScheduleRule;
use RRule\RRule;

/**
 * Validate the entire rule set of a ScheduleListing's Schedule to see if it is allowed to play
 */
class PlayoutValidator
{
    public function __construct(
        protected ScheduleRulesetAdapter $rulesetAdapter,
    )
    {
    }

    /**
     * Validate that a playout's schedule would allow it to play,
     * also taking its ancestors' schedules into account
     *
     * @throws \Exception
     */
    public function validate(ScheduleListing $node, \DateTimeInterface|string $starting) : bool
    {
        // ray(' ~~~~~ CHECKING SCHEDULE OF: "'.$node->scheduleable->name.'" ~~~~~ ')->purple()->label('PlayoutValidator');

        // we need to make sure the layer's rules have still not been broken. we have to worry
        // about the max number of occurrences each time we begin the loop again because this
        // is not a static rule like checking the date window.
        //
        // CHECK SHOULD HAPPEN WHEN: a loop finishes and returns to the beginning
        $this->validateAncestors($node, $starting);

        if ( !isset($node->schedule) || $node->schedule->rules?->count() == 0) {
            // ray('no schedule and/or rules found --> valid!');
            return true;
        }

        return $this->validateRules($node, $starting);
    }

    /**
     * Make sure the layer's/playlist's/etc.'s rules have still not been broken.
     *
     * We have to worry about the max number of occurrences each time
     * we begin the loop again because this is not a static rule
     * like checking the date window is.
     *
     * @throws \Exception
     */
    public function validateAncestors(ScheduleListing $node, \DateTimeInterface|string $starting) : bool
    {
        // optimize:
        //  - caching may be helpful here.
        //  - we also may not need to run the whole validate(), just the max_occurrences check

        $ancestors = $node->ancestors;
        // ray('ancestors of "'.$node->scheduleable->name.'" --> ', $ancestors?->all())->purple()->label('rules');
        if (isset($ancestors) && sizeof($ancestors) > 0) {
            // ray('===== CHECKING ANCESTORS OF "'.$node->scheduleable->name.'"')->purple()->label('rules');
            // ray($node)->purple()->label('rules');
            // ray(' =====')->purple()->label('rules');
            foreach ($ancestors as $ancestor) {
                if ( !$this->validate($ancestor, $starting)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Validate that a playout's schedule set of rules would allow it to play.
     *
     * FOR LOOP MODE:
     *    We'll check each rule individually instead of as a set, because we need to take the
     *    "event duration" (allowed playout window) into account. We do this because the
     *    occurrences generated by `RSet` are too precise to be useful, and even if we
     *    were to use them as a starting point to check based on "event duration", we
     *    don't know which rule's duration should be applied to which occurrence.
     *
     * @throws \Exception
     */
    protected function validateRules(ScheduleListing $node, \DateTimeInterface|string $starting) : bool
    {
        $playout_duration = $node->getTotalDuration();
        $playout_start = ScheduleDatetimeHelper::createDateTime($starting->format("Y-m-d"), $starting->format("H:i:s"));
        $playout_end = ScheduleDatetimeHelper::calculateDurationEnd($playout_start, $playout_duration);

        $rules = $node->schedule->rules;
        foreach ($rules as $rule) {
            if ( !$this->validateRule($node, $rule, $playout_start, $playout_end, $playout_duration)) {
                // if any rule fails, the entire schedule is invalid
                return false;
            }
        }
        return true;
    }

    /**
     * @throws \Exception
     */
    protected function validateRule(
        ScheduleListing $node,
        ScheduleRule $rule,
        \DateTimeInterface $playout_start,
        \DateTimeInterface $playout_end,
        int $playout_duration
    ) : bool
    {
        if(!$this->validateWindow($rule, $playout_start, $playout_end)){
            return false;
        }
        return empty($rule->max_occurrences) || !$this->hitMaxOccurrences($node, $rule->max_occurrences);
    }

    /**
     * Determine whether the playout falls within the window in which it is allowed to play.
     * This method can be used effectively whether the rule repeats or not.
     *
     * @throws \Exception
     */
    protected function validateWindow(
        ScheduleRule $rule,
        \DateTimeInterface $playout_start,
        \DateTimeInterface $playout_end,
    ) : bool
    {
        // ray('validateWindow -- max occ for rule: '.$rule->max_occurrences)->purple();
        // ray('initial playout start: '.$playout_start->format("Y-m-d H:i:s"))->label('PlayoutValidator');
        $rRule = new RRule($rule->rfc_string);

        // To take event duration into account when generating occurrences, we
        // should check btwn `playout_start - duration` and `playout_end`
        $playout_start = $playout_start->sub($rule->getDuration());
        $matching_occurrences = $rRule->getOccurrencesBetween($playout_start, $playout_end);

        // ray('rule duration interval: '.$rule->getDuration()->format('%d days %h hours %i minutes'))->label('PlayoutValidator')->purple();
        // ray('new start to check between --> '. $playout_start->format("Y-m-d H:i:s"))->label('PlayoutValidator')->green();
        //
        // ray('"ALL" occurrences')->purple();
        // foreach($rRule->getOccurrences(10) as $occ){
        //     ray($occ->format("Y-m-d H:i:s"))->purple();
        // }
        //
        // ray('checking for occurrences between "'.$playout_start->format("Y-m-d H:i:s").'" and "'.$playout_end->format("Y-m-d H:i:s").'" ')->blue()->label('PlayoutValidator');

        if (sizeof($matching_occurrences) >= 1) {
            // ray('occurrences match --> valid!')->green();
            return true;
        }
        // ray('NO OCC MATCH --> INVALID')->red();
        return false;
    }

    /**
     * Check number of occurrences that have already past against the max number allowed by the rule
     */
    protected function hitMaxOccurrences(ScheduleListing $node, int $max_occurrences) : bool
    {
        // ray('hit max occurrences? -- count: '.$node->finishedPlayoutHistory()->count())->purple();

        if ($node->finishedPlayoutHistory()->count() >= $max_occurrences) {
            // we've already passed all of the datetimes that would have (probably) played out
            ray('we hit the max occurrences, --> INVALID')->red();
            return true;
        }

        return false;
    }
}
