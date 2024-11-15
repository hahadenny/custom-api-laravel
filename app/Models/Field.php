<?php

namespace App\Models;

use App\Enums\FieldType;
use App\Traits\Models\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $company_id
 * @property int $page_id
 * @property string $name
 * @property string $ue_preset_display_name
 * @property \App\Enums\FieldType $type
 * @property string|null $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\Page $page
 */
class Field extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'page_id',
        'name',
        'ue_preset_display_name',
        'type',
        'value',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'type' => FieldType::class,
    ];

    public function page()
    {
        return $this->belongsTo(Page::class);
    }
}
