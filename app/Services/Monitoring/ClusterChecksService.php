<?php

namespace App\Services\Monitoring;

use App\Models\User;
use Illuminate\Support\Benchmark;

abstract class ClusterChecksService
{
    protected array $defaultStatusVars;

    protected array $defaultClusterStatusVars;

    public function getDefaultStatusVars() : array
    {
        return $this->defaultStatusVars;
    }

    public function getDefaultClusterStatusVars() : array
    {
        return $this->defaultClusterStatusVars;
    }

    /**
     * Check both node status and global vars
     */
    public function getNodeInfo() : array
    {
        return array_merge(
            $this->benchmark(),
            ['node_host' => config('database.connections.mysql.host'),],
            $this->checkGlobalVars(),
            $this->checkNodeStatusVars(),
            $this->checkClusterStatusVars()
        );
    }

    public function benchmarkInMs() : float
    {
        return $this->benchmark()['db_benchmark'];
    }

    public function benchmark() : array
    {
        return Benchmark::measure([
            'db_benchmark' => fn() => User::take(10)->get(), // result in milliseconds
        ]);
    }

    /**
     * @see https://galeracluster.com/library/documentation/galera-status-variables.html
     *
     * @param array $vars - array of status vars to query
     *
     * @return array - If none found, return an array of `null`s so we can still deconstruct the result
     */
    public function checkNodeStatusVars(array $vars = []) : array
    {
        $vars = array_merge($this->defaultStatusVars, $vars);

        return $this->checkStatusVars($vars);
    }

    /**
     * @see https://galeracluster.com/library/documentation/galera-status-variables.html
     *
     * @param array $vars - array of status vars to query
     *                      ['db_var_name' => 'nice_name']
     *
     * Return an array of `null`s if none found so we can still deconstruct the result
     */
    public function checkClusterStatusVars(array $vars = []) : array
    {
        $vars = array_merge($this->defaultClusterStatusVars, $vars);

        return $this->checkStatusVars($vars);
    }

    /**
     * Return an array of `null`s if none found so we can still deconstruct the result.
     * Defaults to checking on the default database connection.
     */
    abstract public function checkGlobalVars(string $connectionName='mysql') : array;

    /**
     * @see https://galeracluster.com/library/documentation/galera-status-variables.html
     *
     * @param array  $vars - array of status vars to query
     *
     * @return array - If none found, return an array of `null`s so we can still deconstruct the result
     */
    abstract protected function checkStatusVars(array $vars) : array;
}
