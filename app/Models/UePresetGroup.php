<?php

namespace App\Models;

use App\Contracts\Models\GroupInterface;
use App\Traits\Models\BelongsToCompany;
use App\Traits\Models\SetsRelationAlias;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kalnoy\Nestedset\NodeTrait;

/**
 * @property int $id
 * @property int $company_id
 * @property int $ue_preset_asset_id
 * @property string $name
 * @property int $sort_order
 * @property int|null $parent_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\UePresetAsset $uePresetAsset
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\UePreset> $uePresets
 * @property-read \App\Models\UePresetGroup|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\UePresetGroup> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\UePresetGroup> $ancestors
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\UePresetGroup> $descendants
 */
class UePresetGroup extends Model implements GroupInterface
{
    use BelongsToCompany, HasFactory, NodeTrait, SoftDeletes, SetsRelationAlias;

    // keys and values must also be defined as relation methods on this model
    protected const RELATION_MAP = ['uePresets' => 'items'];

    protected $fillable = [
        'ue_preset_asset_id',
        'name',
        'sort_order',
        'parent_id',
    ];

    public function uePresetAsset()
    {
        return $this->belongsTo(UePresetAsset::class);
    }

    public function uePresets()
    {
        return $this->hasMany(UePreset::class);
    }

    public function items(): HasMany
    {
        return $this->uePresets();
    }

    public function setSortOrder(?int $value = null): static
    {
        if (is_null($value)) {
            $value = $this->company->uePresetGroups()->whereKeyNot($this)->max('sort_order') + 1;
        }

        $this->sort_order = $value;

        return $this;
    }
}
