<?php

namespace App\Models;

use App\Traits\Models\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * @property int $id
 * @property int $company_id
 * @property string $uuid
 * @property string $source
 * @property array $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company|null $company
 */
class MediaMeta extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'data',
        'source',
        'uuid',
        'name',
        'file_name',
        'path',
        'mime_type',
        'size',
        'thumbnail',
        'd3_local_media_metas_id',
        'file_last_updated_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'data' => 'json',
        'file_last_updated_at' => 'datetime',
    ];

    // ?? may need to swap these around
    public function ingestedLocalFile()
    {
        $this->belongsTo(self::class, 'd3_local_media_metas_id');
    }

    public function shareFile()
    {
        $this->hasOne(self::class, 'd3_local_media_metas_id');
    }

    public function hasCustomProperty(string $propertyName): bool
    {
        return Arr::has($this->data, $propertyName);
    }

    /**
     * Get the value of custom property with the given name.
     *
     * @param string $propertyName
     * @param mixed $default
     *
     * @return mixed
     */
    public function getCustomProperty(string $propertyName, $default = null): mixed
    {
        return Arr::get($this->data, $propertyName, $default);
    }

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function setCustomProperty(string $name, $value): self
    {
        $isString = is_string($this->data);
        $customProperties = $isString ? json_decode($this->data, true) : $this->data;

        Arr::set($customProperties, $name, $value);

        $this->data = $isString ? json_encode($customProperties, JSON_INVALID_UTF8_IGNORE) : $customProperties;

        return $this;
    }

    public function forgetCustomProperty(string $name): self
    {
        $isString = is_string($this->data);
        $customProperties = $isString ? json_decode($this->data, true) : $this->data;

        Arr::forget($customProperties, $name);

        $this->data = $isString ? json_encode($customProperties, JSON_INVALID_UTF8_IGNORE) : $customProperties;

        return $this;
    }
}
