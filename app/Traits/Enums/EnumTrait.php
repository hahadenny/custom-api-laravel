<?php

namespace App\Traits\Enums;

/**
 * Helper methods for enums
 */
trait EnumTrait
{
    /**
     * Return all case names in an array
     */
    public static function names() : array
    {
        return array_column(self::cases(), 'name');
    }
}
