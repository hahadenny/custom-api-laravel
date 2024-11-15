<?php

namespace App\Services;

use App\Models\UePreset;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UePresetService
{
    public function store(User $authUser, array $params = []): UePreset
    {
        $uePreset = new UePreset($params);

        $uePreset->company()->associate($authUser->company);

        $uePreset->save();

        return $uePreset;
    }

    public function batchUpdate(array $params = []): Collection
    {
        $uePresets = null;

        DB::transaction(function () use (&$uePresets, $params) {
            $uePresets = UePreset::query()->findMany($params['ids']);

            foreach ($uePresets as $uePreset) {
                /** @var \App\Models\UePreset $uePreset */
                $uePreset->group()->associate($params['ue_preset_group_id']);
                $uePreset->save();
            }
        });

        return $uePresets;
    }

    public function batchDelete(array $params = [])
    {
        DB::transaction(function () use ($params) {
            UePreset::destroy($params['ids']);
        });
    }
}
