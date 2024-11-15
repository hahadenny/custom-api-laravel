<?php

namespace App\Enums;

use App\Models\Channel;
use App\Models\ChannelGroup;
use ValueError;

enum ChannelEntityType: string
{
    case Channel = 'channel';
    case ChannelGroup = 'channel_group';

    public static function getTableFrom(?string $value): ?string
    {
        if (is_null($value)) {
            return null;
        }

        try {
            $type = self::from($value);
        } catch (ValueError) {
            return null;
        }

        $class = match ($type) {
            self::Channel => Channel::class,
            self::ChannelGroup => ChannelGroup::class,
        };
        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = new $class();

        return $model->getTable();
    }
}
