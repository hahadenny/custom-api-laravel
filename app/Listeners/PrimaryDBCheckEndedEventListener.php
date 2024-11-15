<?php

namespace App\Listeners;

use App\Exceptions\PrimaryConnectionException;
use App\Health\PrimaryDatabaseConnectionCheck;
use App\Services\Monitoring\DatabaseConnectionService;
use Illuminate\Support\Facades\Log;
use Spatie\Health\Events\CheckEndedEvent;

/**
 * After a primary connection check, if the check failed, attempt to recover
 * the primary connection and set it as the default connection.
 *
 * ?? -- needs clarification --> NOTE: CheckEndedEvent is only triggered from the package's
 * default Artisan check command, not anytime a check is done within the application
 */
class PrimaryDBCheckEndedEventListener extends SingleCheckEndedEventListener
{

    public function __construct(protected DatabaseConnectionService $connService) {}

    /**
     * @throws \Exception
     */
    public function handle(CheckEndedEvent $event) : void
    {
        if($this->handleEvent($event, PrimaryDatabaseConnectionCheck::class)) {
            // Doesn't need further handling
            return;
        }

        // database connection to primary failed
        try{
            Log::warning(" !! Attempting to recover primary connection... ");
            $this->connService->recoverPrimaryConnection(
                $this->result->meta['connection_name'] ?? null,
                $this->result->meta['online_primary_name'] ?? null
            );
        } catch (\Exception $e) {
            $queryResult = isset($this->result->meta['query_result']) ? json_encode($this->result->meta['query_result']) : '';

            Log::error(" !! DB CONNECTION '{$this->result->meta['connection_name']}' FAILED the '{$this->checkClassBaseName}' check. Recovery failed with query_result: ".$queryResult);

            throw new PrimaryConnectionException("Could not switch to Primary database connection ({$e->getMessage()})", 0404, $e);
        }

    }
}

/*
         // Database check
            {
                "name": "Database",
                "label": "Database",
                "notificationMessage": "",
                "shortSummary": "Connected to '192.168.50.163'",
                "status": "ok",
                "meta": {
                    "connection_name": "mysql",
                    "host_name": "192.168.50.163"
                }
            },
        */
/*
        // DatabaseNodeStatus
            {
                "name": "DatabaseNodeStatus",
                "label": "Database Node Status",
                "notificationMessage": "One or more MySQL vars are not available: `wsrep_local_state_comment`, `wsrep_connected`, `wsrep_local_recv_queue_avg`, `wsrep_flow_control_paused`. \n [wsrep_local_state_comment] - Node is not synced. \n",
                "shortSummary": "Warning",
                "status": "warning",
                "meta": {
                    "wsrep_cluster_name": "SQLSTATE[HY000]: General error: 1193 Unknown system variable 'wsrep_cluster_name' (SQL: SELECT @@GLOBAL.wsrep_cluster_name AS wsrep_cluster_name)",
                    "wsrep_local_state_comment": null,
                    "wsrep_connected": null,
                    "wsrep_local_recv_queue_avg": null,
                    "wsrep_flow_control_paused": null
                }
            },
        // DatabaseReplication
            {
                "name": "DatabaseReplication",
                "label": "Database Replication",
                "notificationMessage": "One or more MySQL vars are not available: `wsrep_local_state_comment`, `wsrep_connected`, `wsrep_local_recv_queue_avg`, `wsrep_flow_control_paused`. \n ",
                "shortSummary": "Warning",
                "status": "warning",
                "meta": {
                    "wsrep_cluster_name": "SQLSTATE[HY000]: General error: 1193 Unknown system variable 'wsrep_cluster_name' (SQL: SELECT @@GLOBAL.wsrep_cluster_name AS wsrep_cluster_name)",
                    "wsrep_local_state_comment": null,
                    "wsrep_connected": null,
                    "wsrep_local_recv_queue_avg": null,
                    "wsrep_flow_control_paused": null
                }
            },
        // DatabaseCluster
            {
                "name": "DatabaseCluster",
                "label": "Database Cluster",
                "notificationMessage": "One or more MySQL vars are not available: `wsrep_cluster_size`, `wsrep_cluster_conf_id`, `wsrep_cluster_state_uuid`, `wsrep_cluster_status`. \n ",
                "shortSummary": "Warning",
                "status": "warning",
                "meta": {
                    "wsrep_cluster_name": "SQLSTATE[HY000]: General error: 1193 Unknown system variable 'wsrep_cluster_name' (SQL: SELECT @@GLOBAL.wsrep_cluster_name AS wsrep_cluster_name)",
                    "wsrep_cluster_size": null,
                    "wsrep_cluster_conf_id": null,
                    "wsrep_cluster_state_uuid": null,
                    "wsrep_cluster_status": null
                }
            }
         */
