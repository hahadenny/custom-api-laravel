<?php

namespace App\Models\Schedule\States;

/**
 * Entity was stopped - essentially a reset
 */
class Stopped extends PlayoutState
{
    /* Keep PlayoutState parent class in the same dir to
    auto-resolve $name from Paused::class for DB */
    public static string $name = 'stopped';
}
