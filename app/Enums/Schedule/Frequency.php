<?php

namespace App\Enums\Schedule;

use App\Traits\Enums\EnumTrait;

enum Frequency
{
    use EnumTrait;

    case YEARLY;
    case MONTHLY;
    case WEEKLY;
    case DAILY;
    case HOURLY;
    case MINUTELY;
    case SECONDLY;
    case NEVER;  // don't repeat
    case END;    // repeat when the loop ends
    case CUSTOM; // custom repeat data
}
