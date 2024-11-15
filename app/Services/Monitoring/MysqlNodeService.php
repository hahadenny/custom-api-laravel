<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MysqlNodeService extends ClusterChecksService
{
    /*
    // prob want dynamic
    group_replication_bootstrap_group

    // status related
    server_id
    gtid_executed
    ?? group_replication_recovery_complete_at


    // maybe want dynamic
    group_replication_autorejoin_tries
    group_replication_exit_state_action
    group_replication_member_expel_timeout
    group_replication_components_stop_timeout
    group_replication_unreachable_majority_timeout

    // important configs
    group_replication_group_name
    group_replication_group_seeds
    group_replication_local_address
    group_replication_start_on_boot
    group_replication_member_weight
    group_replication_single_primary_mode
    group_replication_ip_allowlist

    // less important
    group_replication_consistency
    group_replication_enforce_update_everywhere_checks
    group_replication_force_members
    group_replication_recovery_reconnect_interval
    group_replication_recovery_retry_count

    // queries
    SHOW GLOBAL VARIABLES WHERE variable_name LIKE '%replication%' OR variable_name = 'server_id' OR variable_name LIKE 'gtid_executed';
    SELECT variable_name, variable_value FROM performance_schema.global_variables WHERE variable_name LIKE '%replication%' OR variable_name = 'server_id' OR VARIABLE_NAME LIKE 'gtid_executed' ORDER BY variable_name;
    SELECT * FROM performance_schema.replication_applier_status_by_worker\G
    SELECT * FROM performance_schema.replication_group_members;

    ~~> don't bother, group_replication_primary_member is deprecated
        ~~SELECT variable_name, variable_value FROM performance_schema.global_status WHERE variable_name LIKE '%replication%' ORDER BY variable_name;~~
    */

    public const CONNECTION_NAMES = [
        'main' => 'mysql-1',
        'backup' => 'mysql-2',
        'arbiter' => 'mysql-3',
    ];

    protected array $defaultStatusVars = [
        'group_replication_bootstrap_group',
        'server_id',
        'gtid_executed',
        'group_replication_local_address',
        'group_replication_member_weight',
        'group_replication_group_name',
    ];

    // used for performance_schema.replication_group_members instead of global_status vars
    // since mysql group replication has no status vars
    protected array $defaultClusterStatusVars = [
        'channel_name',
        'member_id',
        'member_host',
        'member_port',
        'member_state',
        'member_role'
    ];

    /**
     * @var string the machine type of this machine (main, backup, arbiter)
     */
    protected string $machineType;

    /**
     * @var string the connection name for the database running on this machine
     */
    protected string $connectionName;

    public function __construct() {
        $this->machineType = config('app.versioning.machine') ?? 'main';
        $this->connectionName = $this->getDbConnNameByMachine() ?? self::CONNECTION_NAMES['main'];
    }

    /**
     * @inheritDoc
     */
    public function checkGlobalVars(string $connectionName='mysql') : array
    {
        try {
            $query = "SELECT variable_name, variable_value
                        FROM performance_schema.global_variables
                        WHERE variable_name IN (" . ':' . implode(', :', $this->defaultStatusVars) . ")";
            $results = DB::connection($connectionName)->select($query, $this->defaultStatusVars);

            if (empty($results)) {
                return array_fill_keys($this->defaultStatusVars, null);
            }

            foreach ($results as $i => $result) {
                $results[$result->variable_name] = $result->variable_value;
                unset($results[$i]);
            }
            return $results;
        } catch (\Exception $e) {
            return [$this->defaultStatusVars[0] => $e->getMessage()];
        }
    }

    /**
     * Get the global vars for the database on the current machine
     */
    public function checkOwnGlobalVars() : array
    {
        return $this->checkGlobalVars($this->connectionName);
    }

    /**
     * @param array  $vars - array of status vars to query
     *
     * @return array - If none found, return an array of `null`s so we can still deconstruct the result
     */
    protected function checkStatusVars(array $vars, bool $selfOnly=false) : array
    {
        $thisHost = $selfOnly
            ? $this->getDbHostByMachine()
            : '%'; // query all

        try {
            $query = "SELECT " . implode(', ', $vars) . " FROM performance_schema.replication_group_members";
            $query .= $selfOnly ? " WHERE member_host = ?" : "";
            $rows = $selfOnly
                // use THIS machine's connection
                ? DB::connection(self::CONNECTION_NAMES[$this->machineType])->select($query, [$thisHost])
                // use default connection
                // (if the local DB is down, get the cluster status using a functional connection)
                : DB::select($query);

            if (empty($rows)) {
                return array_fill_keys($vars, null);
            }

            if($selfOnly){
                return get_object_vars($rows[0]);
            }

            foreach($rows as $i => $row) {
                foreach ($row as $colName => $colVal) {
                    $rows[$i] = get_object_vars($row);
                }
            }

            return $rows;

        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return [$vars[0] => $e->getMessage()];
        }
    }

    /**
     * Query the `performance_schema.replication_group_members` table only for this member
     *
     * @param array|null $vars
     *
     * @return array
     */
    public function checkOwnMemberVars(array $vars=null) : array
    {
        $vars ??= $this->defaultClusterStatusVars;
        return $this->checkStatusVars($vars, true);
    }

    /**
     * Get the database host for a given machine type.
     * Defaults to the current machine's type.
     * (main, backup, arbiter)
     *
     * @param string|null $machineType
     *
     * @return string
     */
    public function getDbHostByMachine(string $machineType=null) : string
    {
        return $this->getDbConfigByMachineType('host', $machineType);
    }

    /**
     * Get the database connection name for a given machine type.
     *
     * @param string|null $machineType
     *
     * @return string|null
     */
    public function getDbConnNameByMachine(string $machineType=null) : ?string
    {
        $machineType ??= $this->machineType;
        return self::CONNECTION_NAMES[$machineType] ?? null;
    }

    /**
     * Get a given database connection config value for a given machine type.
     * Defaults to the current machine's type. (main, backup, arbiter)
     *
     * @param string|null $machineType
     * @param string      $dbConfigField
     *
     * @return string
     */
    protected function getDbConfigByMachineType(string $dbConfigField, string $machineType=null) : string
    {
        $machineType ??= $this->machineType;
        return match($machineType){
            'main' => config('database.connections.'.self::CONNECTION_NAMES['main'].'.'.$dbConfigField),
            'backup' => config('database.connections.'.self::CONNECTION_NAMES['backup'].'.'.$dbConfigField),
            'arbiter' => config('database.connections.'.self::CONNECTION_NAMES['arbiter'].'.'.$dbConfigField),
        };
    }
}
