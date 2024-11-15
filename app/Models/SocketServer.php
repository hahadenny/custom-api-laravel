<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $url
 * @property int $cluster_id
 * @property array|null $params
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Cluster $cluster
 */
class SocketServer extends Model
{
    use SoftDeletes;

    protected $casts = [
        'params' => 'json'
    ];

    public function cluster() : BelongsTo
    {
        return $this->belongsTo(Cluster::class);
    }
}
