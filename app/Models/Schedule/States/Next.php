<?php

namespace App\Models\Schedule\States;

/**
 * Entity is playing next
 */
class Next extends PlayoutState
{
    /* Keep PlayoutState parent class in the same dir to
    auto-resolve $name from Next::class for DB */
    public static string $name = 'next';
}
