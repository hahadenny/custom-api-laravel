<?php

namespace App\Services;

use App\Events\SocketServerCreated;
use App\Models\Cluster;
use App\Models\SocketServer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SocketServerService
{

    public function listing(array $params = []): LengthAwarePaginator
    {
        $query = SocketServer::query();

        return $query
            ->with(['cluster'])
            ->orderByDesc('id')
            ->paginate(100)
            ->appends($params);
    }

    public function store(array $params = []): SocketServer
    {
        $server = new SocketServer();
        $cluster = Cluster::findOrFail($params['cluster_id']);
        $server->url = 'https://' . Str::slug($params['url']) . '.' . $cluster->settings['domain'];
        $server->cluster_id = $params['cluster_id'];

        return DB::transaction(function () use ($server, $params) {
            $server->save();
            SocketServerCreated::dispatch($server);
            return $server;
        });
    }

    public function getServers()
    {
        return SocketServer::all()->map(function (SocketServer $server) {
            return [
                'name' => Str::slug($server->url),
                'region' => $server->cluster->region,
            ];
        });
    }
}
