<?php

namespace App\Enums\Schedule;

use App\Traits\Enums\BackedEnumTrait;
use App\Traits\Enums\EnumTrait;

/**
 * RFC compliant day codes, as well as custom form codes
 */
enum Day : string
{
    use EnumTrait, BackedEnumTrait;

    case MO = 'Monday';
    case TU = 'Tuesday';
    case WE = 'Wednesday';
    case TH = 'Thursday';
    case FR = 'Friday';
    case SA = 'Saturday';
    case SU = 'Sunday';
    case WEEKDAYS = 'Weekdays';
    case EVERYDAY = 'Everyday';
}
