<?php

namespace App\Services;

use App\Models\UePresetAsset;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UePresetAssetService
{
    public function store(User $authUser, array $params = []): UePresetAsset
    {
        $uePresetAsset = new UePresetAsset($params);

        $uePresetAsset->company()->associate($authUser->company);

        $uePresetAsset->save();

        return $uePresetAsset;
    }

    public function batchDelete(array $params = [])
    {
        DB::transaction(function () use ($params) {
            UePresetAsset::destroy($params['ids']);
        });
    }
}
