<?php

namespace App\Models;

use App\Traits\Models\BelongsToCompany;
use App\Traits\Models\SetsRelationAlias;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $company_id
 * @property int $ue_preset_group_id
 * @property string $name
 * @property string $display_name
 * @property string $ue_id
 * @property string $type
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\UePresetGroup $group
 */
class UePreset extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes, SetsRelationAlias;

    // keys and values must also be defined as relation methods on this model
    protected const RELATION_MAP = ['uePresetGroup' => 'group'];

    protected $fillable = [
        'ue_preset_group_id',
        'name',
        'display_name',
        'ue_id',
        'type',
        'description',
    ];

    public function uePresetGroup()
    {
        return $this->belongsTo(UePresetGroup::class);
    }

    public function group()
    {
        return $this->uePresetGroup();
    }
}
