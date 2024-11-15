<?php

namespace App\Health;

use App\Services\Monitoring\ClusterChecksService;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Make sure a node is connected and synced to the cluster
 */
abstract class DatabaseNodeCheck extends Check
{
    protected array  $dbVars;
    protected int    $failed   = 0;
    protected int    $warned   = 0;
    protected string $messages = '';

    public function __construct(protected ClusterChecksService $nodeService)
    {
        parent::__construct();
    }

    protected function initVars(array ...$varsArrays) : void
    {
        $this->dbVars = array_merge(...$varsArrays);

        foreach($this->dbVars as $varName => $var) {
            if( !isset($var)) {
                if($this->warned === 0){
                    $this->messages .= "One or more MySQL vars are not available: ";
                }
                $this->warned++;
                $this->messages .= "`$varName`, ";
            }
        }

        if($this->warned > 0){
            $this->messages = rtrim($this->messages, ', ') . ". \n ";
        }
    }

    abstract public function run() : Result;
}
