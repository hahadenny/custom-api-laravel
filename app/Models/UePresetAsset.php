<?php

namespace App\Models;

use App\Traits\Models\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $ue_id
 * @property string $ue_project
 * @property string $ue_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Company $company
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\UePresetGroup> $uePresetGroups
 */
class UePresetAsset extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'ue_id',
        'ue_project',
        'ue_path',
    ];

    public function uePresetGroups()
    {
        return $this->hasMany(UePresetGroup::class);
    }
}
