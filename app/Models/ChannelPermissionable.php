<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Permission\Traits\HasPermissions;

class ChannelPermissionable extends Model
{
    use HasPermissions;

    protected $fillable = ['channel_id', 'targetable_type', 'targetable_id'];

    public function targetable(): MorphTo
    {
        return $this->morphTo();
    }

    public function guardName()
    {
        return ['api'];
    }
}
