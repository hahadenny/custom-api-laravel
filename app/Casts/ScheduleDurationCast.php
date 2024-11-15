<?php

namespace App\Casts;

use App\Services\Schedule\Helpers\ScheduleDatetimeHelper;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * Always store as integer
 */
class ScheduleDurationCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string                              $key
     * @param mixed                               $value
     * @param array                               $attributes
     *
     * @return string
     * @throws \Exception
     */
    public function get($model, string $key, mixed $value, array $attributes)
    {
        return $value;
    }

    /**
     * Prepare the given value for storage.
     * Always store duration as seconds integer
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string                              $key
     * @param array                               $value
     * @param array                               $attributes
     *
     * @return string
     * @throws \Exception
     */
    public function set($model, string $key, mixed $value, array $attributes)
    {
        return str_contains($value, ':') ? ScheduleDatetimeHelper::timeToSeconds($value) : $value;
    }
}
