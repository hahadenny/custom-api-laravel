<?php

namespace App\Services\Monitoring;

use App\Exceptions\PrimaryConnectionException;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * For use with on-prem mysql group replication nodes
 */
class DatabaseConnectionService extends ConnectionService
{
    /** Name of the database connections that reside on each machine type */
    protected array $connectionNamesMap = [
        'main' => 'mysql-1',
        'backup' => 'mysql-2',
        'arbiter' => 'mysql-3',
    ];

    /** The name of the connection for the database that resides on this machine */
    protected string $currentMachineConnName;

    public function __construct() {

        parent::__construct();

        $this->currentMachineConnName = $this->connectionNamesMap[$this->currentMachineType] ?? '';
    }

    /**
     * Get the primary connection data. Query for it using the given $usingConnectionName & cache the result.
     *
     * NOTE: We query every time because there is a chance that the cached primary connection is no longer
     * the primary in the case in which it were to go down & come back up before being detected as down
     * by this instance of Porta.
     *
     * @param string|null $usingConnectionName
     *
     * @return string
     * @throws Exception
     */
    public function getPrimaryConnectionName(string $usingConnectionName=null) : string
    {
        $usingConnectionName ??= config('database.default');
        return $this->fetchPrimaryConnectionName($usingConnectionName);
    }

    /**
     * See if the given `$connectionName` host and port are the primary connection.
     * If a `$primaryConnectionName` is passed, we simply compare strings and
     * don't query the database for a primary.
     *
     * @param string      $connectionName
     * @param string|null $primaryConnectionName
     *
     * @return bool
     * @throws Exception
     */
    public function isPrimaryConnection(string $connectionName, string $primaryConnectionName=null) : bool
    {
        $connectionData = config('database.connections.'.$connectionName);
        $primaryConnectionName ??= $this->getPrimaryConnectionName($connectionName);
        $primaryConnectionData = config('database.connections.'.$primaryConnectionName);

        return $connectionData['host'] === $primaryConnectionData['host']
            && $connectionData['port'] === $primaryConnectionData['port'];
    }

    /**
     * See if we can connect to $connectionName's database.
     * Default connection timeout of 2 seconds
     *
     * NOTE: This check makes detecting down state much faster than if we were to attempt to
     *       connect to the DB
     *
     * @param string $connectionName
     * @param int    $timeout - seconds
     *
     * @return bool
     */
    public function checkConnection(string $connectionName, int $timeout=2) : bool
    {
        $connectionData = config('database.connections.'.$connectionName);
        $host = $connectionData['host'];
        $port = $connectionData['port'];

        return $this->check($host, $port, $timeout);
    }

    /**
     * Set the default database connection to the primary connection.
     *
     * @param string|null $currentConnectionName - current default and name to exclude when looking for new primary
     * @param string|null $knownPrimaryConnectionName - the new primary connection to set as default
     *
     * @return string - name of the new default connection
     * @throws Exception
     */
    public function recoverPrimaryConnection(string $currentConnectionName=null, string $knownPrimaryConnectionName=null) : string
    {
        if($knownPrimaryConnectionName){
            $primHost = config('database.connections.' . $knownPrimaryConnectionName . '.host');
            $primPort = config('database.connections.' . $knownPrimaryConnectionName . '.port');
            return $this->setNewDefaultConnection($knownPrimaryConnectionName, $primHost, $primPort, 'DB_CONNECTION', 'DB_HOST', 'DB_PORT');
        }

        $primaryConnectionName = $this->getPrimaryConnectionName($currentConnectionName);
        $primHost = config('database.connections.' . $primaryConnectionName . '.host');
        $primPort = config('database.connections.' . $primaryConnectionName . '.port');
        return $this->setNewDefaultConnection($primaryConnectionName, $primHost, $primPort, 'DB_CONNECTION', 'DB_HOST', 'DB_PORT');
    }

    /**
     * Run backup if this isn't the primary db (and is ideally the arbiter)
     *
     * NOTE: Because the default connection (should) always gets switched to Primary, checking
     * if "this" connection is primary will always be true. Instead, check machine type
     * against the connection info so we can run the backup on the machine that
     * doesn't have the primary database
     *
     * @throws Exception
     */
    public function shouldRunBackup() : bool
    {
        try{
            $primaryConnName = $this->getPrimaryConnectionName('mysql');
        } catch (PrimaryConnectionException $e){
            report($e);
            Log::error("EXCEPTION!! -- Something went wrong while getting the Primary connection; database was not backed up here. (" . now()->format('Y-m-d H:i:s T') . ")");
            return false;
        }

        // this prevents backups from ever running if only one node is left in the cluster
        /*if($this->isPrimaryConnection($this->connectionNamesMap[$this->currentMachineType], $primaryConnName)){
            // this machine is the primary db
            Log::info("This is the primary machine, machine's database instance was not backed up. (" . now()->format('Y-m-d H:i:s T') . ")");
            return false;
        }*/

        if($this->currentMachineType === 'arbiter'){
            // This machine is the arbiter & not the primary db
            Log::info("This is the arbiter; beginning database backup at " . now()->format('Y-m-d H:i:s T'));
            return true;
        }

        if($this->arbiterWillNotBackup($primaryConnName, $this->connectionNamesMap) && $this->currentMachineType === 'backup'){
            // The arbiter is primary or down & this machine is the backup machine
            Log::info("The arbiter is primary or offline; beginning database backup at " . now()->format('Y-m-d H:i:s T'));
            return true;
        }

        if( ! $this->checkConnection($this->connectionNamesMap['backup'])) {
            // Even though this is the main machine, the backup is down & arbiter is not running backups, so we want to run the backup here
            Log::info("The backup machine is inaccessible, beginning database backup at " . now()->format('Y-m-d H:i:s T'));
            return true;
        }

        // Otherwise don't back it up on the main machine; we only want to do backups on one machine at a time
        Log::info("Database was not backed up here! This may be the main machine. (" . now()->format('Y-m-d H:i:s T') . ")");
        return false;
    }

    /**
     * Determine if the arbiter should NOT run the backup
     * (if the arbiter is the primary or down, it should not run the backup)
     *
     * @param string $primaryConnName
     * @param array  $connectionNames
     *
     * @return bool
     * @throws Exception
     */
    protected function arbiterWillNotBackup(string $primaryConnName, array $connectionNames) : bool
    {
        return $this->isPrimaryConnection('mysql-3', $primaryConnName)
            || ! $this->checkConnection($connectionNames['arbiter']);
    }

    /**
     * Get the list of potential new primary connections from the list of matching $driver connections.
     * Optionally exclude the given `$exclConnectionName`.
     *
     * @param string|null $exclConnectionName
     * @param string      $driver
     *
     * @return array
     */
    protected function getNewPotentialConnections(string $exclConnectionName=null, string $driver='mysql') : array
    {
        return Arr::where(config('database.connections'), function($val, $key) use ($driver, $exclConnectionName) {
            return $val['driver'] === $driver
                && (!isset($exclConnectionName) || $key !== $exclConnectionName);
        });
    }

    /**
     * Get info about the online primary by using the given connection to run the query.
     * If the given connection is down, the valid list of configured connections will
     * be queried for a working connection to use.
     *
     * @param string $connectionName
     *
     * @return \stdClass
     * @throws Exception
     */
    protected function queryOnlinePrimary(string $connectionName) : \stdClass
    {
        // check this connection before trying to use it so we avoid the long connection timeout if it is down
        if(!$this->checkConnection($connectionName)){
            Log::warning("$connectionName is down, finding new connection to use...");
            $connectionName = $this->findGoodConnection($this->getNewPotentialConnections($connectionName));
            Log::warning("Using new connection: $connectionName");
        }

        // We need higher permissions to check this, ensure the DB_USER has the following permissions:
        // GRANT SELECT ON performance_schema.replication_group_members TO 'porta'@'%';
        // For on-prem installations, this is done in the install/update step during replication setup.
        $membersQuery = "SELECT member_host, member_port, member_state, member_role
                         FROM performance_schema.replication_group_members";
                         // We don't need the primary yet; we just want a working connection to use
                         // to determine the primary
                            /* WHERE member_state='ONLINE'
                           AND member_role = 'PRIMARY'*/
        try {
            $results = DB::connection($connectionName)->select($membersQuery);
            DB::connection($connectionName)->disconnect();
        } catch(\Exception $e){
            DB::connection($connectionName)->disconnect();
            Log::debug(" ++ Good connection '$connectionName' failed, disconnected. ++ ");
            throw new PrimaryConnectionException("Could not query for online primary members (using good connection '$connectionName') -- ".$e->getMessage());
        }

        // look for online primary
        $onlinePrimaries = Arr::where($results, function($val, $key) {
            return $val->member_state === 'ONLINE'
                && $val->member_role === 'PRIMARY';
        });

        if(sizeof($onlinePrimaries) > 1){
            throw new PrimaryConnectionException("Query found more than one ONLINE primary member (using connection '$connectionName') -- ".json_encode($onlinePrimaries));
        }

        if(sizeof($onlinePrimaries) === 1){
            // first element may not always have index 0
            return reset($onlinePrimaries);
        }

        // look for unreachable/other primary
        $otherPrimaries = Arr::where($results, function($val, $key) {
            return $val->member_state === 'UNREACHABLE'
                && $val->member_role === 'PRIMARY';
        });
        if(sizeof($otherPrimaries) > 0){
            throw new PrimaryConnectionException("Query found UNREACHABLE primary member(s) (using connection '$connectionName') -- ".json_encode($otherPrimaries));
        }

        throw new PrimaryConnectionException("Query found no online primary members (using connection '$connectionName') -- ".json_encode($results));
    }

    /**
     * Query for the online primary connection and return the connection's name (from config/database.php).
     * Optionally exclude the given $exclConnectionName.
     *
     * @param string      $connectionName
     * @param string|null $exclConnectionName
     * @param string      $driver
     *
     * @return string - primary connection name
     * @throws Exception
     */
    protected function fetchPrimaryConnectionName(string $connectionName, string $exclConnectionName=null, string $driver='mysql') : string
    {
        $onlinePrimary = $this->queryOnlinePrimary($connectionName);
        $mysqlConnections = Arr::where(config('database.connections'), function($val, $key) { return $val['driver'] === 'mysql'; });
        $primaryConnection = Arr::where($mysqlConnections, function($val, $key) use ($onlinePrimary) {
            return
                (isset($val['host']) && (string)$val['host'] === (string)$onlinePrimary->member_host)
                && (isset($val['port']) && (string)$val['port'] === (string)$onlinePrimary->member_port);
        });

        if(empty($primaryConnection)){
            throw new \Exception("Could not find the primary connection name in list of valid connections, using the online primary query result. -- query: ".json_encode($onlinePrimary)." -- config: ".json_encode($mysqlConnections));
        }

        return array_key_first($primaryConnection);
    }
}
