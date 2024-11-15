<?php

namespace App\Enums\Schedule;

enum FrequencyType
{
    case bysetpos;
    case bymonth;
    case bymonthday;
    case bynmonthday;
    case byyearday;
    case byweekno;
    case byweekday;
    case byhour;
    case byminute;
    case bysecond;
}
