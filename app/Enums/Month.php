<?php

namespace App\Enums;

use App\Traits\Enums\EnumTrait;

enum Month
{
    use EnumTrait;

    case January;
    case February;
    case March;
    case April;
    case May;
    case June;
    case July;
    case August;
    case September;
    case October;
    case November;
    case December;
}
