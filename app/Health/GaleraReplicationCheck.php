<?php

namespace App\Health;

use Spatie\Health\Checks\Result;

/**
 * Make sure a node is receiving and processing updates from the cluster write-sets
 */
class GaleraReplicationCheck extends DatabaseNodeCheck
{
    public function run() : Result
    {
        $this->initVars(
            $this->nodeService->checkGlobalVars(),
            $this->nodeService->checkNodeStatusVars()
        );

        $result = Result::make()
                        ->meta($this->dbVars);

        if(isset($this->dbVars["wsrep_local_recv_queue_avg"]) && round($this->dbVars["wsrep_local_recv_queue_avg"], 2) !== 0.0) {
            $this->warned++;
            $this->messages .= "[wsrep_local_recv_queue_avg] - Node can't apply write-sets as fast as it's receiving them. \n";
        }

        if(isset($this->dbVars["wsrep_flow_control_paused"]) && $this->dbVars['wsrep_flow_control_paused'] === 1) {
            $this->warned++;
            $this->messages .= "[wsrep_flow_control_paused] - Node flow control is paused. \n";
        } elseif(isset($this->dbVars["wsrep_flow_control_paused"]) && intval($this->dbVars['wsrep_flow_control_paused']) !== 0) {
            $this->warned++;
            $this->messages .= "[wsrep_flow_control_paused] - Node's replication health may be weak'. \n";
        }

        return $this->warned >= 1 ? $result->warning($this->messages) : $result->ok();
    }
}
