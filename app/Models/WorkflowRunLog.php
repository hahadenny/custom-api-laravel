<?php

namespace App\Models;

use App\Enums\WorkflowType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $workflow_id
 * @property int|null $run_by_user_id
 * @property \App\Enums\WorkflowType $workflow_type
 * @property array $workflow_data
 * @property array $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Workflow $workflow
 * @property-read \App\Models\User|null $runByUser
 */
class WorkflowRunLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $casts = [
        'workflow_type' => WorkflowType::class,
        'workflow_data' => 'array',
        'data' => 'array',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function runByUser(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
