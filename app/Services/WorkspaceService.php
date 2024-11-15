<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;

class WorkspaceService
{
    public function store(User $authUser, array $params = []): Workspace
    {
        $workspace = new Workspace($params);
        $workspace->user()->associate($authUser);
        $workspace->company()->associate($authUser->company);
        $workspace->save();

        return $workspace;
    }

    public function update(Workspace $workspace, array $params = []): Workspace
    {
        return tap($workspace)->update($params);
    }
}
