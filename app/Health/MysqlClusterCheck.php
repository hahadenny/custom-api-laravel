<?php

namespace App\Health;

use Spatie\Health\Checks\Result;

/**
 * Make sure a node is registering the correct number of connected nodes
 */
class MysqlClusterCheck extends DatabaseNodeCheck
{
    public const COMMAND_ALIAS = 'mysql_cluster';
    public const NAME = 'MysqlCluster';

    public function run() : Result
    {
        // customize display name
        $this->label = "MySQL Status of the replication group";

        $this->initVars(
            $this->nodeService->checkClusterStatusVars()
        );

        $result = Result::make()->meta($this->dbVars);

        $this->parseClusterStatus($this->dbVars);

        return $this->failed >= 1
            ? $result->failed($this->messages)
            : ($this->warned >= 1 ? $result->warning($this->messages) : $result->ok());
    }

    /**
     * @param array $vars - rows from `performance_schema.replication_group_members`
     *
     * @return void
     */
    public function parseClusterStatus(array $vars) : void
    {
        if(sizeof($vars) < config('database.cluster.expected_size')) {
            $this->warned++;
            $this->messages .= "There may be a network problem, or `mysql` may be down on 1+ node(s). \n";
        }

        $hasPrimary = false;
        foreach($vars as $var){
            if(!isset($var['member_role'])){
                // likely total database failure
                $this->failed++;
                $this->messages .= "Group member status could not be determined from query; the database may have encountered total failure. \n";
                break;
            }

            if($var['member_role'] == 'PRIMARY'){
                $hasPrimary = true;
            }
            if($var['member_state'] == 'OFFLINE'){
                if($var['member_role'] == 'PRIMARY') {
                    $hasPrimary = false;
                }
                $this->warned++;
                $this->messages .= "A member in the group is OFFLINE (".$var['member_host']."). \n";
            } elseif($var['member_state'] == 'UNREACHABLE'){
                if($var['member_role'] == 'PRIMARY'){
                    $hasPrimary = false;
                    $this->failed++;
                    $this->messages .= "The primary is UNREACHABLE (".$var['member_host']."). \n";
                } else {
                    $this->warned++;
                    $this->messages .= "A member in the group is UNREACHABLE (".$var['member_host']."). \n";
                }
            }
        }

        // if no primary ONLINE, then fail
        if(!$hasPrimary) {
            $this->failed++;
            $this->messages .= "There is no primary available. \n";
        }
    }
}
