<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserGroup;
use App\Traits\Services\UniqueNameTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UserGroupService
{
    use UniqueNameTrait;

    public function store(User $authUser, array $params = []): UserGroup
    {
        $group = new UserGroup($params);

        $group->company()->associate($authUser->company);

        DB::transaction(function () use ($group) {
            $group->save();
        });

        return $group;
    }

    public function update(UserGroup $group, array $params = []): UserGroup
    {
        DB::transaction(function () use ($group, $params) {
            $group->update($params);
            $group->refresh();
        });

        return $group;
    }

    public function batchUpdate(array $params = []): Collection
    {
        return DB::transaction(function () use ($params) {
            $groups = new Collection();

            if (isset($params['ids'])) {
                $groups = UserGroup::query()->findMany($params['ids']);

                foreach ($groups as $group) {
                    /** @var \App\Models\UserGroup $group */

                    if ($group->parent_id === $params['parent_id']) {
                        continue;
                    }

                    $parent = ! is_null($params['parent_id'])
                        ? UserGroup::query()->find($params['parent_id']) : null;

                    $group->parent()->associate($parent);

                    /** @var \Kalnoy\Nestedset\QueryBuilder $query */
                    $query = UserGroup::query()
                        ->where('parent_id', $group->parent_id)
                        ->whereKeyNot($group);

                    $group->name = $this->replicateUniqueName($query, $group->name);
                    $group->save();
                }
            }

            if (isset($params['item_ids'])) {
                $users = User::query()->findMany($params['item_ids']);

                foreach ($users as $user) {
                    /** @var \App\Models\User $user */

                    $user->group()->associate($params['parent_id'])->save();
                    $user->refresh();
                }
            }

            return $groups;
        });
    }

    public function batchUngroup(array $params = []): void
    {
        DB::transaction(function () use ($params) {
            // Among ids in the $params['ids'] can be ids of groups that are nested into each other.
            // To properly handle this case we do not use with(['parent', 'children', 'playlists']).
            // Each foreach iteration of groups queries data, including those saved in the previous iterations.
            $groups = UserGroup::query()->findMany($params['ids']);

            foreach ($groups as $group) {
                /** @var \App\Models\UserGroup $group */

                foreach ($group->children as $child) {
                    /** @var \App\Models\UserGroup $child */
                    $child->parent()->associate($group->parent)->save();
                }

                foreach ($group->users as $user) {
                    /** @var \App\Models\User $user */
                    $user->group()->associate($group->parent)->save();
                }

                $group->delete();
            }
        });
    }

    public function delete(UserGroup $group): void
    {
        DB::transaction(function () use ($group) {
            $this->deleteGroup($group);
        });
    }

    public function batchDelete(array $params = []): void
    {
        DB::transaction(function () use ($params) {
            foreach ($params['ids'] as $id) {
                // Query each group separately to handle nested groups properly.
                /** @var \App\Models\UserGroup|null $group */
                $group = UserGroup::query()->find($id);

                if (! is_null($group)) {
                    $this->deleteGroup($group);
                }
            }

            if (isset($params['item_ids'])) {
                $users = User::query()->findMany($params['item_ids']);

                foreach ($users as $user) {
                    /** @var \App\Models\User $user */
                    $user->delete();
                }
            }
        });
    }

    protected function deleteGroup(UserGroup $group): void
    {
        foreach ($group->users as $user) {
            /** @var \App\Models\User $user */
            $user->delete();
        }

        foreach ($group->descendants as $descendant) {
            /** @var \App\Models\UserGroup $descendant */
            foreach ($descendant->users as $user) {
                /** @var \App\Models\User $user */
                $user->delete();
            }

            $descendant->delete();
        }

        $group->delete();
    }
}
