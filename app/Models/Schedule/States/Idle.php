<?php

namespace App\Models\Schedule\States;

/**
 * Neutral; entity has no current status or pending status
 */
class Idle extends PlayoutState
{
    /* Keep PlayoutState parent class in the same dir to
    auto-resolve $name from Idle::class for DB */
    public static string $name = 'idle';
}
