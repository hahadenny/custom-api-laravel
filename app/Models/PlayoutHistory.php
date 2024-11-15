<?php

namespace App\Models;

use App\Models\Schedule\States\PlayoutState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayoutHistory extends Model
{
    protected $table = 'playout_history';

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'status' => PlayoutState::class,
    ];

    public function channel() : BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    // inverse of
    // PlaylistListing::playoutHistory()
    // ScheduleListing::playoutHistory()
    public function listing()
    {
        return $this->morphTo();
    }
}
