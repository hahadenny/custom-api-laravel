<?php

namespace App\Listeners;

use App\Events\TerraformSocketRunCreated;
use App\Models\SocketServer;
use App\Models\User;
use App\Notifications\CreateOhDearSiteFailedNotification;
use App\Services\Monitoring\OhDearService;
use App\Services\TerraformService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class CreateOhDearSite implements ShouldQueue
{
    use InteractsWithQueue;
    use SerializesModels;

    private int $minutes_to_check = 15;

    public function __construct(protected TerraformService $terraform, protected OhDearService $ohDearService)
    {
    }

    /**
     * Wait for the terraform run to finish, then send request to OhDear API to create a site entry for it
     *
     * NOTE: there is no endpoint for updating the Uptime response text verification, this must be
     *       added manually in the site's Uptime check settings in https://ohdear.app/sites
     */
    public function handle(TerraformSocketRunCreated $event) : void
    {
        $i = 0;
        $notifyUsers = User::whereIn('email', explode(',', config('services.oh-dear.failure_emails')))->get();;
        $max_sites = config('services.oh-dear.max_sites');
        $site_count = $this->ohDearService->getSitesCount();

        if (!is_int($site_count)){
            $this->notifyOfFailure($notifyUsers, $event->server, 'HTTP Request failed', $site_count);
            return;
        }

        if ($site_count >= $max_sites) {
            Log::warning('Maximum number ('.$max_sites.') of allowed Oh Dear sites reached.');
            $this->notifyOfFailure($notifyUsers, $event->server, 'Too many sites (max '.$max_sites.')');
            return;
        }

        do {
            $rsp = $this->terraform->getRun($event->run_id)->json();

            if(isset($rsp['errors'])) {
                Log::warning('Terraform HTTP request for run "' . $event->run_id . '" failed.', $rsp);
                $this->notifyOfFailure($notifyUsers, $event->server, 'Terraform request failed for run '.$event->run_id, json_encode($rsp));
                return;
            }

            if (isset($rsp['data']['attributes']['status'])) {

                if(in_array($rsp['data']['attributes']['status'], ['discarded', 'errored', 'canceled', 'force_canceled'])) {
                    Log::warning('Terraform run "' . $event->run_id . '" failed with status "'.$rsp['data']['attributes']['status'].'".', $rsp);
                    $this->notifyOfFailure($notifyUsers, $event->server, 'Terraform run "'.$event->run_id.'" failed', json_encode($rsp));
                    return;
                }

                if($rsp['data']['attributes']['status'] === 'applied') {

                    Log::info('Terraform run "' . $event->run_id . '" finished.', $rsp);

                    $socketUrl = $event->server->url . config('services.socketio.ping_path');

                    $response = $this->ohDearService->createSite([
                        'team_id'    => $this->ohDearService->getFirstTeamId(),
                        'url'        => $socketUrl,
                        "uses_https" => true,
                        'label'      => $event->server->url . ' SIO',
                        'group_name' => "Sockets - Production",
                        'tags'       => [
                            'socket', 'production', 'terraform', Str::slug($event->server->cluster->region),
                        ],
                        'checks'     => [
                            'uptime',
                            'performance',
                            'certificate_health',
                            'certificate_transparency',
                            'dns',
                            'domain',
                        ],
                    ]);

                    if ($response->successful()) {
                        Log::info('Oh Dear Site for "' . $event->server->url . '" was added successfully');

                        // NOTE: there is no endpoint for updating the Uptime response text verification, this must be added manually in the site's Uptime check settings in https://ohdear.app/sites

                    } else {
                        Log::warning('Adding Oh Dear Site for "' . $event->server->url . '" failed.', $response->json());

                        $this->notifyOfFailure($notifyUsers, $event->server, 'Error', $response->json());
                    }

                    return;
                }
            }
            ++$i;
            sleep(60);
        } while ($i < $this->minutes_to_check);

        Log::warning('Terraform run "' . $event->run_id . '" for "'.$event->server->url.'" took too long; aborted site create check.');

        $this->notifyOfFailure($notifyUsers, $event->server, 'Terraform run not applied in time');
    }

    protected function notifyOfFailure(array|Collection $users, SocketServer $server, $error_message = 'Error', string $response = null) : void
    {
        Notification::send(
            $users,
            new CreateOhDearSiteFailedNotification(
                $error_message,
                $server,
                $response
            )
        );
    }
}
