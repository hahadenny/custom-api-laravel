<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class FileService
{
    const TYPE_IMAGE = 'image';
    const TYPE_VIDEO = 'video';
    const TYPE_AUDIO = 'audio';

    public function listing(User $user, array $params = [])
    {
        return $user->company
            ->media()
            ->where('h264_preview', 0)
            ->when(Arr::get($params, 'sort', 'id_desc'), function($query, $value) {
                $chunk = explode('_', $value, 2);
                $query->orderBy($chunk[0], $chunk[1]);
            })
            ->when(Arr::get($params, 'q', false), function($query, $value) {
                if (Str::length($value) > 2) {
                    $query->where(function($q) use ($value) {
                        $values = preg_split('/ +/', $value, -1, PREG_SPLIT_NO_EMPTY);
                        foreach ($values as $v) {
                            $q->where('name', 'like', '%' . $v . '%');
                        }
                    });
                }
            })
            ->when(Arr::get($params, 'tags', false), function ($query, $tags) {
                $query->where(function(Builder $q) use ($tags) {
                    foreach ($tags as $tag) {
                        $q->orWhereJsonContains('custom_properties->tags', $tag);
                    }
                });
            })
            ->when(Arr::get($params, 'type', false), function($query, $value) {
                if ($value === self::TYPE_IMAGE) {
                    $query->where('mime_type', 'like', 'image/%');
                } elseif ($value === self::TYPE_VIDEO) {
                    $query->where('mime_type', 'like', 'video/%');
                } elseif ($value === self::TYPE_AUDIO) {
                    $query->where('mime_type', 'like', 'audio/%');
                }
            })
            ->paginate(Arr::get($params, 'per_page', 10));
    }

    public function store(User $authUser, array $params = []): Media
    {
        return $authUser->company->addMedia($params['file'])->toMediaCollection(Company::MEDIA_COLLECTION_NAME);
    }

    public function update(Media $media, array $params = []): Media
    {
        foreach (['description', 'tags'] as $field) {
            if (array_key_exists($field, $params)) {
                $value = $params[$field];
                if ($value && $field === 'tags' && is_string($value)) {
                    $value = array_map('trim', explode(',', $value));
                }
                $media->setCustomProperty($field, $value);
            }
        }
        $media->name = $params['name'];
        $media->save();
        return $media;
    }

    public function destroy(User $authUser, $ids)
    {
        return $authUser->company->media()->findMany($ids)->each->delete();
    }

    public function getFile(User $authUser, $id)
    {
        return $authUser->company->media()->find($id);
    }
}
