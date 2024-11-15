<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\DB;

class GaleraNodeService extends ClusterChecksService
{
    protected array $defaultStatusVars = [
        // Clear & simple status value. Changes quickly and generally won’t take long to get to `Synced`
        'wsrep_local_state_comment',
        // `OFF` = node doesn't have a connection to any other nodes or cluster components
        'wsrep_connected',
        // Value greater than 0 indicates that the node can’t apply write-sets as fast as it’s receiving them
        'wsrep_local_recv_queue_avg',
        // Value greater than 0 means the node’s replication health may be weak. A value of 1 means the node was paused 100% of the time
        'wsrep_flow_control_paused',
    ];

    protected array $defaultClusterStatusVars = [
        'wsrep_cluster_size',
        // Different conf_id values across nodes indicates that the cluster is partitioned
        'wsrep_cluster_conf_id',
        'wsrep_cluster_state_uuid',
        'wsrep_cluster_status',
    ];


    /**
     * @see https://galeracluster.com/library/documentation/mysql-wsrep-options.html
     *
     * Return an array of `null`s if none found so we can still deconstruct the result
     */
    public function checkGlobalVars(string $connectionName='mysql') : array
    {
        try {
            $wsrep_results = DB::select("SELECT @@GLOBAL.wsrep_cluster_name AS wsrep_cluster_name");
            if (isset($wsrep_results[0])) {
                return json_decode(json_encode($wsrep_results[0]), true);
            }
        } catch (\Exception $e) {
            return ['wsrep_cluster_name' => $e->getMessage()];
        }

        return ['wsrep_cluster_name' => null];
    }

    /**
     * @see https://galeracluster.com/library/documentation/galera-status-variables.html
     *
     * @param array  $vars - array of status vars to query
     *                          NOTE: this is not currently escaped
     *
     * @return array - If none found, return an array of `null`s so we can still deconstruct the result
     */
    protected function checkStatusVars(array $vars) : array
    {
        try {
            $query = "SELECT variable_name, variable_value
                        FROM performance_schema.global_status
                        WHERE variable_name IN (" . ':' . implode(', :', $vars) . ")";
            $results = DB::select($query, $vars);

            if (empty($results)) {
                return array_fill_keys($vars, null);
            }

            foreach ($results as $i => $result) {
                $results[$result->variable_name] = $result->variable_value;
                unset($results[$i]);
            }

            return $results;

        } catch (\Exception $e) {
            return [$vars[0] => $e->getMessage()];
        }
    }
}
