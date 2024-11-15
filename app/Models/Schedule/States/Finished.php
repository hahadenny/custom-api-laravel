<?php

namespace App\Models\Schedule\States;

/**
 * Entity has finished playing
 */
class Finished extends PlayoutState
{
    /* Keep PlayoutState parent class in the same dir to
    auto-resolve $name from Finished::class for DB */
    public static string $name = 'finished';
}
