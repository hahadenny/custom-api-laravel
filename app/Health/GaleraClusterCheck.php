<?php

namespace App\Health;

use Spatie\Health\Checks\Result;

/**
 * Make sure a node is registering the correct number of connected nodes
 */
class GaleraClusterCheck extends DatabaseNodeCheck
{
    public function run() : Result
    {
        $this->initVars(
            $this->nodeService->checkGlobalVars(),
            $this->nodeService->checkClusterStatusVars()
        );

        $result = Result::make()
                        ->meta($this->dbVars);

        if(isset($this->dbVars["wsrep_cluster_size"]) && intval($this->dbVars["wsrep_cluster_size"]) != config('database.cluster.expected_size')) {
            $this->failed++;
            $this->messages .= "[wsrep_cluster_size] - There may be a network problem, or `mysql` may be down on 1+ node(s), or the expected cluster size .env value may simply be outdated. \n";
        }

        return $this->failed >= 1
            ? $result->failed($this->messages)
            : ($this->warned >= 1 ? $result->warning($this->messages) : $result->ok());
    }
}
