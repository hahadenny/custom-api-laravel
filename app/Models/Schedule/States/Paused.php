<?php

namespace App\Models\Schedule\States;

/**
 * Entity is currently paused
 */
class Paused extends PlayoutState
{
    /* Keep PlayoutState parent class in the same dir to
    auto-resolve $name from Paused::class for DB */
    public static string $name = 'paused';
}
