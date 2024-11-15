<?php

namespace App\Health;

use Spatie\Health\Checks\Result;

/**
 * Make sure a node is connected and synced to the cluster
 */
class MysqlNodeStatusCheck extends DatabaseNodeCheck
{
    public function run(): Result
    {
        // customize display name
        $this->label = "MySQL Status of this machine";

        $this->initVars(
            $this->nodeService->checkOwnGlobalVars(),
            $this->nodeService->checkOwnMemberVars()
        );

        $result = Result::make()->meta($this->dbVars);

        if(isset($this->dbVars['group_replication_bootstrap_group']) && $this->dbVars['group_replication_bootstrap_group'] === 'ON'){
            $this->warned++;
            $this->messages .= "[group_replication_bootstrap_group] - Node will be bootstrapped when restarted. \n";
        }

        if(!isset($this->dbVars["member_state"]) || $this->dbVars["member_state"] !== "ONLINE"){
            $this->warned++;
            $this->messages .= "[member_state] - Node is not online in the group \n";
        }
        $summary = "";
        if(!isset($this->dbVars["member_role"])){
            $this->failed++;
            $this->messages .= "[member_role] - Node role could not be determined. \n";
        } else {
            $summary .= "Role: " . $this->dbVars["member_role"] . " -- ";
        }
        if(!isset($this->dbVars["member_role"])){
            $this->failed++;
            $this->messages .= "[member_role] - Node role could not be determined. \n";
        } else {
            $summary .= "State: " . $this->dbVars["member_state"];
        }

        $summary = $summary === "" ? "Node status could not be determined" : $summary;
        $result->shortSummary($summary);

        return $this->failed >= 1
            ? $result->failed($this->messages)
            : ($this->warned >= 1 ? $result->warning($this->messages) : $result->ok());
    }
}
