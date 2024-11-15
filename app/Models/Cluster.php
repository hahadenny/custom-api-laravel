<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $region
 * @property string $name
 * @property array|null $settings
 * @property string $token
 * @property boolean $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Company> $companies
 */
class Cluster extends Model
{
    use HasFactory;

    protected $casts = [
        'settings' => 'json',
        'is_active' => 'boolean',
    ];

    public function companies() : HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function scopeActive(Builder $query)
    {
        $query->where(['is_active' => true]);
    }
}
