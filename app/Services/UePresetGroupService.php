<?php

namespace App\Services;

use App\Models\UePresetGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UePresetGroupService
{
    public function store(User $authUser, array $params = []): UePresetGroup
    {
        $group = new UePresetGroup($params);

        $group->company()->associate($authUser->company);

        if (is_null($group->sort_order)) {
            $group->setSortOrder();
        }

        DB::transaction(function () use ($group) {
            $group->save();
        });

        return $group;
    }

    public function update(UePresetGroup $group, array $params = []): UePresetGroup
    {
        DB::transaction(function () use ($group, $params) {
            $group->update($params);
        });

        return $group;
    }

    public function batchUpdate(array $params = []): Collection
    {
        $groups = null;

        DB::transaction(function () use (&$groups, $params) {
            $groups = UePresetGroup::query()->findMany($params['ids']);

            foreach ($groups as $group) {
                /** @var \App\Models\UePresetGroup $group */
                $group->parent()->associate($params['parent_id'])->save();
            }
        });

        return $groups;
    }

    public function delete(UePresetGroup $group)
    {
        DB::transaction(function () use ($group) {
            $group->delete();
        });
    }

    public function batchDelete(array $params = [])
    {
        DB::transaction(function () use ($params) {
            UePresetGroup::destroy($params['ids']);
        });
    }
}
