<?php

namespace App\Models;

use App\Traits\Models\BelongsToCompany;
use ArrayAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as DbCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Tag extends Model
{
    use BelongsToCompany;
    use HasFactory;

    public $guarded = [];

    protected static function boot()
    {
        parent::boot();

        static::saving(function (self $tag) {
            if (empty($tag->sort_order)) {
                $tag->sort_order = 1;
            }
        });
    }

    public function scopeWithType(Builder $query, string $type = null): Builder
    {
        if (is_null($type)) {
            return $query;
        }

        return $query->where('type', $type)->ordered();
    }

    public function scopeContaining(Builder $query, string $name): Builder
    {
        return $query->whereRaw('lower(' . $this->getQuery()->getGrammar()->wrap('name') . ') like ?', ['%' . mb_strtolower($name) . '%']);
    }

    public static function findOrCreate(
        Company $company,
        string | array | ArrayAccess $values,
        string | null $type = null,
    ): Collection | Tag | static {
        $tags = collect($values)->map(function ($value) use ($type, $company) {
            if ($value instanceof self) {
                return $value;
            }

            return static::findOrCreateFromString($company, $value, $type);
        });

        return is_string($values) ? $tags->first() : $tags;
    }

    public static function getWithType(string $type): DbCollection
    {
        return static::withType($type)->get();
    }

    public static function findFromString(Company $company, string $name, string $type = null)
    {
        return $company->tags()
            ->where('type', $type)
            ->where(function ($query) use ($name) {
                $query->where('name', $name);
            })
            ->first();
    }

    public static function findFromStringOfAnyType(Company $company, string $name)
    {
        return $company->tags()
            ->where("name", $name)
            ->get();
    }

    public static function findOrCreateFromString(Company $company, string $name, string $type = null)
    {
        $tag = static::findFromString($company, $name, $type);

        if (!$tag) {
            $tag = $company->tags()->create([
                'name' => $name,
                'type' => $type,
            ]);
        }

        return $tag;
    }

    public static function getTypes(Company $company): Collection
    {
        return $company->tags()->groupBy('type')->pluck('type');
    }

    public function scopeOrdered(Builder $builder)
    {
        return $builder->orderBy('sort_order');
    }

}
