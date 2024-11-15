<?php

namespace App\Listeners;

use App\Events\SocketServerCreated;
use App\Events\TerraformSocketRunCreated;
use App\Services\TerraformService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateSocketServer implements ShouldQueue
{
    use InteractsWithQueue;
    use SerializesModels;


    public function handle(SocketServerCreated $event)
    {
        $config = config('services.terraform');
        if (!$config['enabled']) {
            return;
        }

        /* @var $terraform TerraformService */
        $terraform = app(TerraformService::class);
        $server = $event->server;
        $cluster = $server->cluster;
        $clusterSettings = $cluster->settings;

        $data = [
            'attributes' => [
                'message' => 'Create ' . $server->url . ' server.',
                'auto-apply' => true,
//                'variables' => [
//                    [
//                        'key' => 'servers',
//                        'value' => json_encode(['server' => app(CompanyService::class)->getServers()])
//                    ],
//                ]
            ],
            'type' => 'runs',
            'relationships' => [
                'workspace' => [
                    'data' => [
                        'type' => 'workspaces',
                        'id' => $config['workspace']
                    ]
                ]
            ]
        ];
        if (!empty($clusterSettings['module'])) {
            $data['attributes']['target-addrs'] = ['module.' . $clusterSettings['module']];
        }
        $i = 0;
        do {
            $rsp = $terraform->createRun($data)->json();
            Log::info('Create new server', $rsp);
            if (!empty($rsp['data']['id'])) {
                $server->params = array_merge($server->params ?? [], ['terraform_run_id' => $rsp['data']['id']]);
                $server->save();
                TerraformSocketRunCreated::dispatch($rsp['data']['id'], $server);
                return;
            }
            ++$i;
            sleep(10);
        } while ($i < 3);
    }
}
