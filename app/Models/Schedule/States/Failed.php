<?php

namespace App\Models\Schedule\States;

/**
 * Entity should have played but did not, or entity's status change failed unexpectedly
 */
class Failed extends PlayoutState
{
    /* Keep PlayoutState parent class in the same dir to
    auto-resolve $name from Failed::class for DB */
    public static string $name = 'failed';
}
