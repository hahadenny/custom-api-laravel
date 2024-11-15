<?php

namespace App\Traits\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait UniqueNameTrait
{
    public function replicateUniqueName(Builder|Relation $query, string $name, string $attribute = 'name'): string
    {
        if ($query->clone()->where($attribute, $name)->doesntExist()) {
            return $name;
        }

        // Example of name: "Some Name (1)".
        // "(.+)" -> "Some Name" -> group 1, $matches[1].
        // " \((\d+)\)" -> " (1)".
        // "(\d+)" -> "1" -> group 2, $matches[2].
        $namePattern = '/^(.+) \((\d+)\)$/';

        $matches = [];
        preg_match($namePattern, $name, $matches);
        $baseName = $matches[1] ?? $name;

        $likeEscapedBaseName = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $baseName);

        $existingName = $query->clone()
            ->where($attribute, 'LIKE', $likeEscapedBaseName.' (%)')
            ->orderBy(DB::raw("length($attribute)"), 'desc')
            ->orderBy($attribute, 'desc')
            ->value($attribute);

        $counter = 1;

        if (! is_null($existingName)) {
            $matches = [];
            preg_match($namePattern, $existingName, $matches);

            $counter = (int) $matches[2] + 1;
        }

        $suffix = ' ('.$counter.')';

        return Str::substr($baseName, 0, 255 - Str::length($suffix)).$suffix;
    }

    public function replicateUniquePageNum(Builder|Relation $query, $page_number, $playlist_id, string $attribute = 'page_number'): string
    {
        $pivot_playlist_id = $this->getParentModel()?->id;

        if (!$pivot_playlist_id) {
            // NR Plugin has no playlist, and therefore no parent model
            $usedPageNums = $query->where($attribute, '>', $page_number)
                                  ->pluck('page_number')
                                  ->toArray();
        } else {
            $usedPageNums = $query->clone()
                                  ->join('playlist_listings', 'playlist_listings.playlistable_id', '=', 'pages.id')
                                  ->where([['playlist_id', $pivot_playlist_id]])
                                  ->where($attribute, '>', $page_number)
                                  ->pluck('page_number')->toArray();
        }


        $newPageNum = $page_number;
        while(1) {
            $newPageNum = $newPageNum + 1;
            if (!in_array($newPageNum, $usedPageNums)) {
                return $newPageNum;
            }
        }
    }
}
