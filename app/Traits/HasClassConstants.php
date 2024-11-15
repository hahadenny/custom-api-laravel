<?php

namespace App\Traits;

trait HasClassConstants
{
    /**
     * Helper for using class constants/Enums dynamically
     */
    public static function fromName(string $name)
    {
        return defined(self::class."::$name") ? constant(self::class."::$name") : null;
    }
}
