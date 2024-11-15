<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use App\Models\UserProject;
use App\Models\Workspace;
use App\Traits\Services\UniqueNameTrait;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    use UniqueNameTrait;

    const PAGE_SIZE = 500;

    public function listing(User $authUser): LengthAwarePaginator
    {
        $with = [
            'createdBy:id,email',
            'userProjects' => function ($query) use ($authUser) {
                /** @var \Illuminate\Database\Eloquent\Relations\HasMany $query */
                $query->whereBelongsTo($authUser);
            },
        ];

        $projects = $authUser->company->projects()->with($with)->ordered()->paginate(self::PAGE_SIZE);

        foreach ($projects as $project) {
            /** @var \App\Models\Project $project */
            $project->setUserProjectFromMany($project->userProjects, $authUser);
            $project->unsetRelation('userProjects');
        }

        return $projects;
    }

    public function store(User $authUser, array $params = []): Project
    {
        $userProject = new UserProject($this->extractUserProjectParams($params));
        $userProject->user()->associate($authUser);

        $projectParams = $params;
        unset($projectParams['sort_order']);

        $project = new Project($projectParams);
        $project->createdBy()->associate($authUser);
        $project->company()->associate($authUser->company);

        DB::transaction(function () use ($project, $userProject, $params) {
            $project->save();

            if (isset($params['sort_order'])) {
                $project->moveToOrder($params['sort_order']);
            }

            $project->workspaces()->attach($project->activeWorkspace);
            $userProject->project()->associate($project)->save();
        });

        return $project->setWorkspace($project->activeWorkspace);
    }

    public function show(Project $project, User $authUser): Project
    {
        $workspace = null;

        DB::transaction(function () use ($project, $authUser, &$workspace) {
            $workspace = $this->resolveWorkspace($project, $authUser);
        });

        $project->setWorkspace($workspace);

        /** @var \App\Models\UserProject $userProject */
        $userProject = $project->userProjects()->whereBelongsTo($authUser)->first();
        $project->setUserProject($userProject);

        return $project;
    }

    public function update(Project $project, User $authUser, array $params = []): Project
    {
        $userProjectParams = $this->extractUserProjectParams($params);

        $workspaceId = null;

        if (array_key_exists('workspace_id', $params)) {
            $workspaceId = $params['workspace_id'];
            unset($params['workspace_id']);
        }

        $workspaceLayout = null;

        if (array_key_exists('workspace_layout', $params)) {
            $workspaceLayout = $params['workspace_layout'];
            unset($params['workspace_layout']);
        }

        $projectParams = $params;
        unset($projectParams['sort_order']);

        $project->fill($projectParams);
        $workspace = null;

        DB::transaction(function () use (
            $project, $authUser, $userProjectParams, $workspaceId, $workspaceLayout, &$workspace, $params
        ) {
            $project->save();

            if (isset($params['sort_order'])) {
                $project->moveToOrder($params['sort_order']);
            }

            if (! is_null($workspaceId)) {
                $workspace = Workspace::query()->find($workspaceId);

                $project->workspaces()->detach($project->workspaces()->whereBelongsTo($authUser)->get());
                $project->workspaces()->attach($workspace);

                $project->activeWorkspace()->associate($workspace)->save();
            } else {
                $workspace = $this->resolveWorkspace($project, $authUser);
            }

            if (! is_null($workspaceLayout)) {
                $workspace->update(['layout' => $workspaceLayout]);

                $project->activeWorkspace()->associate($workspace)->save();
            }

            $userProject = $project->userProjects()->whereBelongsTo($authUser)->first();

            if (is_null($userProject)) {
                $userProject = new UserProject();
                $userProject->user()->associate($authUser);
                $userProject->project()->associate($project);
            }

            $userProject->fill($userProjectParams);
            $userProject->save();
        });

        return $project->setWorkspace($workspace);
    }

    public function duplicate(Project $project, User $authUser, array $params = []): Project
    {
        $newProject = $project->replicate();
        $newProject->createdBy()->associate($authUser);
        $newProject->company()->associate($authUser->company);

        $newProject->name = $this->replicateUniqueName($newProject->company->projects(), $newProject->name);

        /** @var \App\Models\UserProject $userProject */
        $userProject = $project->userProjects()->whereBelongsTo($authUser)->first();
        $newUserProject = null;

        if (! is_null($userProject)) {
            $newUserProject = $userProject->replicate();
            $newUserProject->user()->associate($authUser);
        }

        $newProject->setUserProject($newUserProject);
        $newProject->setWorkspace($newProject->activeWorkspace);

        DB::transaction(function () use ($project, $authUser, $newProject, $newUserProject, $params) {
            $newProject->save();

            if (isset($params['sort_order'])) {
                $newProject->moveToOrder($params['sort_order']);
            } else {
                $newProject->moveToOrder($project->sort_order + 1);
            }

            $newProject->workspaces()->attach($newProject->activeWorkspace);

            if (! is_null($newUserProject)) {
                $newUserProject->project()->associate($newProject)->save();
            }

            $groupIdNewGroup = (new PlaylistGroupService())
                ->setProject($newProject)
                ->replicateMany($project->playlistGroups, $authUser);

            (new PlaylistService())
                ->setProject($newProject)
                ->replicateMany($project->playlists, $authUser, $groupIdNewGroup);
        });

        return $newProject;
    }

    protected function resolveWorkspace(Project $project, User $authUser): ?Workspace
    {
        $workspace = $project->workspaces()->whereBelongsTo($authUser)->first();

        if (! is_null($workspace)) {
            return $workspace;
        }

        if (! is_null($project->activeWorkspace)) {
            $workspace = new Workspace([
                'layout' => $project->activeWorkspace->layout,
            ]);

            $workspace->user()->associate($authUser);
            $workspace->company()->associate($authUser->company);

            $workspace->name = $this->replicateUniqueName(
                $authUser->workspaces(), $project->activeWorkspace->name
            );

            $workspace->save();
        } else {
            $workspace = $authUser->workspaces->firstWhere('is_active')
                ?? (clone $authUser->workspaces)->sortByDesc('updated_at')->first();
        }

        if (! is_null($workspace)) {
            $project->workspaces()->attach($workspace);
        }

        return $workspace;
    }

    protected function extractUserProjectParams(array &$params): array
    {
        $userProjectParams = [];

        if (array_key_exists('user_project_state', $params)) {
            $userProjectParams['state'] = $params['user_project_state'];
            unset($params['user_project_state']);
        }
        if (array_key_exists('user_project_is_active', $params)) {
            if (! is_null($params['user_project_is_active'])) {
                $userProjectParams['is_active'] = $params['user_project_is_active'];
            }
            unset($params['user_project_is_active']);
        }

        return $userProjectParams;
    }
}
