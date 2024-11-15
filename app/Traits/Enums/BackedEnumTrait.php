<?php

namespace App\Traits\Enums;

/**
 * Helper methods for backed enums
 */
trait BackedEnumTrait
{
    /**
     * Return enum case values in an array
     */
    public static function values() : array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Return enum cases as associative array: [name => value]
     */
    public static function toArray() : array
    {
      return array_combine(self::names(), self::values());
    }
}
