<?php

namespace App\Services;

use App\Models\MediaMeta;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FileMetaService
{
    const TYPE_IMAGE = 'image';
    const TYPE_VIDEO = 'video';
    const TYPE_AUDIO = 'audio';

    public function listing(User $user, array $params = [])
    {
        return $user->company
            ->mediaMetas()
            ->when(Arr::get($params, 'source'), function($query, $value) {
                $query->where('source', $value);
            })
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
                        $q->whereRaw(
                            "JSON_SEARCH(data->'$.tags', 'one', ?) IS NOT NULL",
                            [$tag]
                        );
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

    /*public function store(User $authUser, array $params = []): MediaMeta
    {
        $meta = MediaMeta::firstOrNew([
                'company_id' => $authUser->company_id,
                'file_name' => $params['file_name'],
                'source' => $params['source'],
            ],
            [
                'id' => $params['id'] ?? null,

                // always need -- came from bridge or edited the file data in porta
                'file_name' => $params['file_name'],
                'company_id' => $authUser->company_id,
                'uuid' => $params['uuid'] ?? (string) Str::uuid(),

                // -- only present if request came from bridge
                'name' => $params['file_name'] ?? null,
                'source' => $params['source'],
                'mime_type' => $params['mime_type'] ?? null,
                'thumbnail' => $params['thumbnail'] ?? null,
                'file_last_updated_at' => $params['file_last_updated_at'] ?? null,
                'size' => $params['size'],
        ]);
        $meta->data = [
            'description' => $params['description'] ?? null,
            'tags' => $params['tags'] ?? [],
        ];
        $meta->save();
        return $meta;
    }*/

    public function update(MediaMeta $mediaMeta, array $params = []): MediaMeta
    {
        foreach (['description', 'tags'] as $field) {
            if (array_key_exists($field, $params)) {
                $value = $params[$field];
                if ($value && $field === 'tags' && is_string($value)) {
                    $value = array_map('trim', explode(',', $value));
                }
                $mediaMeta->setCustomProperty($field, $value);
            }
        }
        $mediaMeta->name = $params['name'];
        $mediaMeta->save();
        return $mediaMeta;
    }

    public function upsert(User $authUser, array $params = [])
    {
        // loop through each file and store/update
        $chunkSize = 100;
        $chunks = array_chunk($params, $chunkSize);

        foreach ($chunks as $chunk) {
            // Process each associative array in the chunk
            $upsert = [];
            foreach ($chunk as $fileData) {
                // shared: 2023-11-16T17:42:44.8690276Z --> ERROR
                // local: 2023-04-24T10:20:10.864-04:00
                try {
                    $fileData['file_last_updated_at'] = new \DateTime($fileData['file_last_updated_at']);
                } catch (\Exception $e) {
                    Log::error("Error parsing file_last_updated_at: {$fileData['file_last_updated_at']}");
                    $fileData['file_last_updated_at'] = null;
                }

                $hasBackslash = mb_strpos($fileData['path'], '\\') !== false;
                $separator = $hasBackslash ? '\\' : '/';

                $filepath = $fileData['filepath'] ?? null;
                if(empty($filepath)){
                    $pieces = explode($separator, $fileData['path']);
                    $file = array_pop($pieces);

                    if(mb_strpos($file, '.') !== false){
                        // `path` had filename attached, so we should remove it
                        $fileData['path'] = implode($separator, $pieces);
                    }

                    $filepath = $fileData['path'] . $separator . $fileData['file_name'];
                }

                // if item already exists, make sure we retain the existing customizations
                $existingItem = MediaMeta::where('filepath', $filepath)->first() ?? null;
                $existingData = $existingItem?->data ?? [];
                $existingTags = $existingData['tags'] ?? [];
                $existingDescription = $existingData['description'] ?? [];

                // create default tags from path
                if(!empty($fileData['path'])) {
                    $tags = array_filter(explode($separator, $fileData['path']));
                    $fileData['tags'] = array_merge($tags, ($fileData['tags'] ?? []), $existingTags);
                } else {
                    Log::warning("There was no 'path' provided for file: '{$fileData['file_name']}', no tags were created.");
                }

                $upsert []= [
                    'company_id' => $authUser->company_id,
                    'file_name' => $fileData['file_name'],
                    'path' => $fileData['path'], // may not have filename
                    'filepath' => $filepath, // both path and filename
                    'source' => $fileData['source'],

                    // always need -- came from bridge or edited the file data in porta
                    'uuid' => $fileData['uuid'] ?? (string) Str::uuid(),

                    // -- only present if request came from bridge
                    'name' => $existingItem?->name ?? $fileData['file_name'] ?? null,
                    'mime_type' => $fileData['mime_type'] ?? null,
                    'thumbnail' => $fileData['thumbnail'] ?? null,
                    'file_last_updated_at' => $fileData['file_last_updated_at'] ?? null,
                    'size' => $fileData['size'],
                    'data' => json_encode([
                        'description' => $existingDescription ?? $fileData['description'] ?? null,
                        'tags' => array_unique(array_map('mb_strtolower', $fileData['tags'])),
                    ], JSON_INVALID_UTF8_IGNORE|JSON_THROW_ON_ERROR)
                ];
            }

            $upsertResult = MediaMeta::upsert(
                // values to insert or update
                $upsert,
                // column(s) that uniquely identify records
                ['filepath'],
                // columns that should be updated if a matching record already exists in the database
                ['file_last_updated_at', 'name', 'data', 'thumbnail', 'size', 'mime_type']);
        }
    }

    public function destroy(User $authUser, $ids)
    {
        return $authUser->company->mediaMetas()->findMany($ids)->each->delete();
    }

    public function getFile(User $authUser, $id)
    {
        return $authUser->company->mediaMetas()->find($id);
    }

    public function bridgeDelete(User $authUser, array $params = [])
    {
        /* Example: [
          "\\DISGUISEPUGET\sharedTF1\penguinbabies.jpg"
          "\\DISGUISEPUGET\sharedTF1\penguinflappyboi.jpg"
        ] */
        $metas = MediaMeta::whereIn('filepath', $params)->get();
        $ids = $metas->pluck('id');
        return $this->destroy($authUser, $ids);
    }
}
