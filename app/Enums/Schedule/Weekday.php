<?php

namespace App\Enums\Schedule;

use App\Traits\Enums\BackedEnumTrait;
use App\Traits\Enums\EnumTrait;

/**
 * RFC compliant weekday codes (no weekends)
 */
enum Weekday : string
{
    use EnumTrait, BackedEnumTrait;

    case MO = 'Sunday';
    case TU = 'Monday';
    case WE = 'Tuesday';
    case TH = 'Wednesday';
    case FR = 'Thursday';
}
