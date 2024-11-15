<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Events\CompanyCreated;
use App\Events\SocketServerCreated;
use App\Models\Cluster;
use App\Models\Company;
use App\Models\SocketServer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompanyService
{
    public function listing(array $params = []): LengthAwarePaginator
    {
        $query = Company::query();

        if (isset($params['is_active'])) {
            $query->where('is_active', $params['is_active']);
        }

        return $query
            ->with(['cluster'])
            ->orderByDesc('id')
            ->paginate(100)
            ->appends($params);
    }

    public function storeWithAdmin(array $params = []): Company
    {
        $company = new Company($params);

        DB::transaction(function () use ($company, $params) {
            /** @var \App\Models\User $admin */
            $admin = User::query()->where('email', $params['email'])->onlyTrashed()->first();

            if (! is_null($admin)) {
                $admin->fill($params)->restore();
            } else {
                $admin = new User($params);
            }

            $admin->role = UserRole::Admin;

            $needCreateServer = !empty($params['cluster_id']);
            $server = null;

            if ($needCreateServer) {
                $cluster = Cluster::findOrFail($params['cluster_id']);
                $company->ue_url = 'https://' . Str::slug($params['name']) . '.' . $cluster->settings['domain'];
                $company->cluster_id = $params['cluster_id'];

                $server = new SocketServer();
                $server->cluster_id = $params['cluster_id'];
                $server->url = $company->ue_url;
                $server->save();
            }

            $company->save();
            $company->users()->save($admin);

            /** @var \App\Services\ProjectService $projectService */
            $projectService = app(ProjectService::class);
            $project = $projectService->store($admin, ['name' => 'Default']);

            /** @var \App\Services\PlaylistService $playlistService */
            $playlistService = app(PlaylistService::class);
            $playlistService->store($admin, $project, ['name' => 'Default']);

            app(ChannelGroupService::class)->store($admin, ['name' => 'Default']);

            CompanyCreated::dispatch($company);
            if ($server) {
                SocketServerCreated::dispatch($server);
            }
        });

        return $company;
    }

    public function getServers()
    {
        return Company::active()->whereNotNull('cluster_id')->get()->map(function (Company $company) {
            return [
                'name' => Str::slug($company->name),
                'region' => $company->cluster->region,
            ];
        });
    }
}
