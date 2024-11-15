<?php

namespace App\Models;

use App\Enums\WorkflowType;
use App\Services\Workflow\Steps\FilterStep;
use App\Services\Workflow\Steps\GoogleSheetImport;
use App\Services\Workflow\Steps\SaveStep;
use App\Services\Workflow\Steps\SetDataStep;
use App\Services\Workflow\Steps\SpreadsheetImport;
use App\Services\Workflow\WorkflowException;
use App\Traits\Models\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property string $name
 * @property \App\Enums\WorkflowType $type
 * @property string|null $description
 * @property array $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\WorkflowRunLog> $workflowRunLogs
 * @property-read \App\Models\WorkflowRunLog $latestWorkflowRunLog
 */
class Workflow extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'description',
        'data',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    protected $casts = [
        'type' => WorkflowType::class,
        'data' => 'array',
    ];

    protected static array $stepClasses = [
        'filter_step' => FilterStep::class,
        'set_data_step' => SetDataStep::class,
        'save_step' => SaveStep::class,
        'spreadsheet_import' => SpreadsheetImport::class,
        'google_sheet_import' => GoogleSheetImport::class,
    ];

    /**
     * @param string $stepType
     * @return string
     * @throws WorkflowException
     */
    public static function stepClass(string $stepType): string
    {
        if (!isset(self::$stepClasses[$stepType])) {
            throw new WorkflowException('Unsupported "' . $stepType . '" step type.');
        }
        return self::$stepClasses[$stepType];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workflowRunLogs(): HasMany
    {
        return $this->hasMany(WorkflowRunLog::class);
    }

    public function latestWorkflowRunLog(): HasOne
    {
        return $this->hasOne(WorkflowRunLog::class)->latestOfMany();
    }
}
