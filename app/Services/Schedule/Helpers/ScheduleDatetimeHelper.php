<?php

namespace App\Services\Schedule\Helpers;

use App\Models\Schedule\Schedule;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ScheduleDatetimeHelper
{
    /**
     * Create a DateTime object from datetime strings (or objects) and adjust the timezone
     *
     * @throws \Exception
     */
    public static function standardizeDateTimezone(
        string $date,
        string $time,
        string|DateTimeZone $fromTimezone,
        string|DateTimeZone $toTimezone = Schedule::DEFAULT_TIMEZONE,
        string $fromFormat = 'Y-m-d H:i:s',
        bool $end = false, // whether to calculate as though this is a start time or an end time
    ) : DateTimeInterface
    {
        // ray('-- STANDARDIZE TIMEZONE --')->purple();
        // ray('date: ', $date)->purple();
        // ray('time: ', $time)->purple();
        // ray('from timezone: ', $fromTimezone)->purple();
        // ray('TO timezone: ', $toTimezone)->purple();

        $fromTimezone = $fromTimezone instanceOf DateTimeZone ? $fromTimezone : new DateTimeZone($fromTimezone);
        $toTimezone = $toTimezone instanceOf DateTimeZone ? $toTimezone : new DateTimeZone($toTimezone);

        return static::createDateTime($date, $time, $fromFormat, $fromTimezone, $end)->setTimezone($toTimezone);
    }

    /**
     * @throws \Exception
     */
    public static function standardizeEndDateTimezone(
        string|DateTimeInterface $date,
        string $time,
        string|DateTimeZone $fromTimezone,
        string|DateTimeZone $toTimezone = Schedule::DEFAULT_TIMEZONE,
        string $fromFormat = 'Y-m-d H:i:s',
    ) : DateTimeInterface
    {
        return static::standardizeDateTimezone($date, $time, $fromTimezone, $toTimezone, $fromFormat, true);
    }

    /**
     * Create a DateTime obj from strings of the given timezone and convert to UTC timezone
     * @throws \Exception
     */
    public static function createDateTime(
        string $date,
        ?string $time = null,
        string $fromFormat = 'Y-m-d H:i:s',
        string|DateTimeZone $fromTimezone = Schedule::DEFAULT_TIMEZONE,
        bool $end = false,
    ) : DateTimeInterface {

        // ray('date: ', $date)->label('-- createDateTime --');
        // ray('time: ', $time)->label('-- createDateTime --');

        $fromTimezone = $fromTimezone instanceOf DateTimeZone ? $fromTimezone : new DateTimeZone($fromTimezone);

        // if time was not given we want to start/end at the first/last possible time window
        $carbonDate = Carbon::parse($date, $fromTimezone);
        $time ??= $end ? $carbonDate->endOfDay() : $carbonDate->startOfDay();

        return DateTimeImmutable::createFromFormat($fromFormat, $date . " " . $time, $fromTimezone);
    }

    /**
     * Create a DateTime obj with time of end of day
     * Shortcut for createDateTime() with $time=null and $end=true
     * @throws \Exception
     */
    public static function createEndDateTime(
        string $date,
        string $fromFormat = 'Y-m-d H:i:s',
        string|DateTimeZone $fromTimezone = Schedule::DEFAULT_TIMEZONE,
    ) : DateTimeInterface {
        return static::createDateTime($date, null, $fromFormat, $fromTimezone, true);
    }

    /**
     * Convert a datetime to specific string format
     *
     * @param string|\DateTimeInterface $datetime
     * @param string                    $format
     *
     * @return string
     */
    public static function formatDatetime(string|\DateTimeInterface $datetime, $format = "Y-m-d H:i:s") : string
    {
        return is_string($datetime) ? $datetime : $datetime->format($format);
    }

    /**
     * Calculate end datetime via duration
     *
     * @param \DateTimeInterface $start_datetime
     * @param int                $duration
     *
     * @return \DateTimeInterface
     */
    public static function calculateDurationEnd(
        \DateTimeInterface $start_datetime,
        int $duration,
    ) : \DateTimeInterface {
        // clone so we can ->modify() without altering the original datetime
        $clone = clone $start_datetime;
        return $clone->modify("+ $duration seconds");
    }

    /**
     * Convert a formatted time string into seconds
     * NOTE: if hour is > 24, will need to parse differently
     *
     * @param string $time - 'H:i:s'
     *
     * @return int
     * @throws \Exception
     */
    public static function timeToSeconds(string $time) : int
    {
        $time = date_parse($time);
        if($time['hour'] > 24){
            throw new \Exception("Time longer than 24 hours cannot be converted to seconds");
        }
        return ($time['hour'] * 3600) + ($time['minute'] * 60) + $time['second'];
    }

    /**
     * Convert seconds integer to formatted time string
     * NOTE: if seconds total more than 24hrs, will need to parse differently
     *
     * @throws \Exception
     */
    public static function secondsToTime(int $seconds, string $format="H:i:s") : string
    {
        if($seconds >= 86400 ){
            throw new \Exception("Seconds longer than 24 hours (86,400) cannot be converted to time");
        }

        return gmdate($format, $seconds);
    }

    /**
     * @throws \Exception
     */
    public static function formatDurationOutput(string|int $duration, string $format="H:i:s") : int|string
    {
        return str_contains($duration, ':')
            ? $duration
            : static::secondsToTime($duration, $format);
    }

    /**
     * Make sure all descendants have their duration formatted properly (H:i:s)
     *
     * @param array  $dataArray           - the array to recurse through
     * @param string $descendantIndexName - name of the index for the entry that contains the descendants
     * @param string $durationField       - name of the index for the entry that contains the duration
     *
     * @return array
     * @throws \Exception
     */
    public static function recursivelyFormatDuration(
        array  $dataArray,
        string $descendantIndexName='children',
        string $durationField='duration'
    ) : array
    {
        if(!empty($dataArray[$durationField])){
            $dataArray[$durationField] = static::formatDurationOutput($dataArray[$durationField]);
        }

        if(!empty($dataArray[$descendantIndexName])){
            foreach($dataArray[$descendantIndexName] as $i => $child){
                $childArray = $child instanceof Model ? $child->toArray() : $child;
                $dataArray[$descendantIndexName][$i] = static::recursivelyFormatDuration($childArray, $descendantIndexName, $durationField);
            }
        }

        return $dataArray;
    }
}
