<?php

namespace App\Traits;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

trait CachesCheckResults {
    protected function cacheCheckMeta(string $cacheKey, string $metaKey, bool $finished=true, Carbon $when=null) : bool
    {
        $when ??= now();
        $cacheStructure = [
            'startedCheckingAt' => null,
            'lastFinishedAt' => null,
            'lastSuccessfulAt' => null,
            'lastFailedAt' => null,
        ];

        $cacheData = Cache::get($cacheKey, $cacheStructure);
        $cacheData = is_string($cacheData) ? json_decode($cacheData, true) : $cacheData;
        if($finished){
            $cacheData['lastFinishedAt'] = $when;
        }
        $cacheData[$metaKey] = $when;

        return Cache::put($cacheKey, $cacheData);
    }

    protected function cacheCheckStartNowIfEmpty($cacheKey) : bool
    {
        return $this->cacheCheckMeta($cacheKey, 'startedCheckingAt', false);
    }

    protected function cacheCheckSuccessNow($cacheKey) : bool
    {
        return $this->cacheCheckMeta($cacheKey, 'lastSuccessfulAt');
    }

    protected function cacheCheckFailureNow($cacheKey) : bool
    {
        return $this->cacheCheckMeta($cacheKey, 'lastFailedAt');
    }
}
