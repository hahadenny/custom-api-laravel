<?php

namespace App\Models\Schedule\States;

/**
 * Entity is currently playing
 */
class Playing extends PlayoutState
{
    /* Keep PlayoutState parent class in the same dir to
    auto-resolve $name from Playing::class for DB */
    public static string $name = 'playing';
}
