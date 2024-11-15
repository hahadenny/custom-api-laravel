<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Events\PasswordWasChanged;
use App\Models\ChannelPermissionable;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UserService
{
    const PAGE_SIZE = 500;

    public function listing(User $authUser)
    {
        $query = match ($authUser->role) {
            UserRole::SuperAdmin => User::query()
                ->with(['company'])
                ->whereIn('company_id', Company::query()->select(['id'])),
            default => $authUser->company->users(),
        };

        return $query->with([
            'channelGroups:id,name',
            'group:id,name'
        ])->orderByDesc('id')->paginate(self::PAGE_SIZE);
    }

    public function store(User $authUser, array $params = []): User
    {
        return DB::transaction(function () use ($authUser, $params) {
            /** @var \App\Models\User $user */
            $user = User::query()->where('email', $params['email'])->onlyTrashed()->first();

            if (! is_null($user)) {
                $user->fill($params)->restore();
            } else {
                $user = new User($params);
            }

            if ($authUser->isSuperAdmin()) {
                $user->save();
            } else {
                $authUser->company->users()->save($user);
            }

            if (array_key_exists('settings', $params)) {
                $user->profile->settings = $params['settings'];
                $user->profile->save();
            }
            if (array_key_exists('channel_group_ids', $params)) {
                $user->channelGroups()->sync($params['channel_group_ids']);
            }

            return $user;
        });
    }

    public function update(User $user, array $params = []): User
    {
        $newPassword = $params['password'] ?? false;
        $res = DB::transaction(function () use ($user, $params) {
            if (empty($params['password'])) {
                unset($params['password']);
            }

            $user->fill($params);

            if ($user->isSuperAdmin()) {
                $user->company()->dissociate();
                $user->userGroup()->dissociate();
            }

            $user->save();
            $user->refresh();

            if (array_key_exists('settings', $params)) {
                $user->profile->settings = $params['settings'];
                $user->profile->save();
            }

            if (array_key_exists('channel_group_ids', $params)) {
                $user->channelGroups()->sync($params['channel_group_ids']);
            }

            if (array_key_exists('permissions', $params)) {
                $user->syncPermissions($params['permissions']);
            }

            return $user;
        });
        if ($newPassword) {
            event(new PasswordWasChanged($user, $newPassword));
        }
        return $res;
    }

    public function batchUpdate(array $params = []): Collection
    {
        return DB::transaction(function () use ($params) {
            $users = User::query()->findMany($params['ids']);

            foreach ($users as $user) {
                /** @var \App\Models\User $user */

                $user->group()->associate($params['user_group_id'])->save();
                $user->refresh();
            }

            return $users;
        });
    }

    public function delete(User $user)
    {
        DB::transaction(function () use ($user) {
            $user->delete();
        });
    }

    public function batchDelete(array $params = [])
    {
        DB::transaction(function () use ($params) {
            $users = User::query()->findMany($params['ids']);

            foreach ($users as $user) {
                /** @var \App\Models\User $user */
                $user->delete();
            }
        });
    }

    /**
     * Loop the user groups from the request to find a match with existing user groups in the database.
     * If a match is found, loop the channels of the database group to add the requested
     * channel permissions to that user group.
     *
     * @param User $user
     * @param      $data
     *
     * @return void
     */
    public function storeGroupPermissions(User $user, $data)
    {
        $groups = $user->company->userGroups()->with(['channelPermissionables'])->get()->keyBy('id');
        DB::transaction(function () use ($groups, $data) {
            foreach ($data['groups'] as $id => $channels) {
                $group = $groups[$id] ?? null;
                if (!$group) {
                    continue;
                }

                /* Example $group->channelPermissionables array:
                [{
                    "id": 1,
                    "channel_id": 33,
                    "permissions": [
                        "play_page"
                    ]
                },...] */
                $permissionables = $group->channelPermissionables->keyBy('channel_id');
                foreach ($channels as $channelId => $permissions) {
                    // has permissions
                    $channelPermission = $permissionables[$channelId] ?? null;
                    if (!$channelPermission) {
                        $channelPermission = new ChannelPermissionable([
                            'channel_id' => $channelId
                        ]);
                        $channelPermission->targetable()->associate($group);
                        $channelPermission->save();
                    }
                    $channelPermission->syncPermissions($permissions);
                }
            }
        });
    }
}
