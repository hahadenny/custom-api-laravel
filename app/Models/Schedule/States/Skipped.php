<?php

namespace App\Models\Schedule\States;

/**
 * Entity was pending play out but was not played due to manual override
 */
class Skipped extends PlayoutState
{
    /* Keep PlayoutState parent class in the same dir to
    auto-resolve $name from Skipped::class for DB */
    public static string $name = 'skipped';
}
