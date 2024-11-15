<?php

namespace App\Health;

use Spatie\Health\Checks\Result;

/**
 * Make sure a node is connected and synced to the cluster
 */
class GaleraNodeStatusCheck extends DatabaseNodeCheck
{
    public function run(): Result
    {
        $this->initVars(
            $this->nodeService->checkGlobalVars(),
            $this->nodeService->checkNodeStatusVars()
        );

        $result = Result::make()
                        ->meta($this->dbVars);

        if($this->dbVars['wsrep_connected'] === 'OFF'){
            $this->failed++;
            $this->messages .= "[wsrep_connected] - Node is not connected to cluster. \n";
        }

        if($this->dbVars["wsrep_local_state_comment"] !== "Synced"){
            $this->warned++;
            $this->messages .= "[wsrep_local_state_comment] - Node is not synced. \n";
        }

        return $this->failed >= 1
            ? $result->failed($this->messages)
            : ($this->warned >= 1 ? $result->warning($this->messages) : $result->ok());
    }
}
