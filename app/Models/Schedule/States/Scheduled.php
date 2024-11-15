<?php

namespace App\Models\Schedule\States;

/**
 * Entity is scheduled to play at some point in the future and is not “next”
 */
class Scheduled extends PlayoutState
{
    /* Keep PlayoutState parent class in the same dir to
    auto-resolve $name from Scheduled::class for DB */
    public static string $name = 'scheduled';
}
