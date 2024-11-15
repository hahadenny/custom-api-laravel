<?php

namespace App\Services\Monitoring;

use App\Exceptions\SocketConnectionException;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * For use with on-prem socket servers
 */
class SocketConnectionService extends ConnectionService
{
    /** Name of the socket server connections that reside on each machine type */
    protected array $connectionNamesMap = [
        'main' => 'socket-1',
        'backup' => 'socket-2',
    ];

    /** The name of the connection for the socket server that resides on this machine */
    protected string $currentMachineConnName;

    /** All connections setup in config/services.php */
    protected array $allConfigConnections;

    public function __construct() {

        parent::__construct();

        $this->currentMachineConnName   = $this->connectionNamesMap[$this->currentMachineType] ?? '';
        $this->allConfigConnections     = config('services.socketio.on-prem.connections') ?? [];
    }

    /**
     * See if we can connect to $connectionName's host:port
     * Default connection timeout of 1 second
     *
     * @param string $connectionName
     * @param int    $timeout - seconds
     *
     * @return bool
     */
    public function checkConnection(string $connectionName, int $timeout=1) : bool
    {
        $connectionData = config('services.socketio.on-prem.connections.'.$connectionName);

        if(!isset($connectionData)) {
            Log::warning("SocketConnectionService::checkConnection() - connection details not found for connection name: '$connectionName'");
            return false;
        }

        $host = $connectionData['host'];
        $port = $connectionData['port'];

        return $this->check($host, $port, $timeout);
    }

    /**
     * Attempt to set the default socket server connection to a working connection.
     *
     * @param string|null $failedConnectionName - current default and name to exclude when looking for new primary
     * @param string|null $workingConnectionName - the new connection to set as default
     *
     * @return string - name of the new default connection
     * @throws Exception
     */
    public function recoverConnection(string $failedConnectionName=null, string $workingConnectionName=null) : string
    {
        $goodConnectionName = $workingConnectionName ?? $this->findGoodConnection($this->allConfigConnections, [$failedConnectionName]);

        $newDefaultConnName = $this->setNewDefaultConnection(
            $goodConnectionName,
            $this->allConfigConnections[$goodConnectionName]['host'],
            $this->allConfigConnections[$goodConnectionName]['port'],
            'ONPREM_SOCKET_CONNECTION',
            'ONPREM_SOCKET_HOST',
            'ONPREM_SOCKET_PORT'
        );

        $defaultDbConn = config('database.default');
        try {
            // set the ue_url in DB for the socket server
            // TODO - build socket URL properly
            $socketUrl = 'http://'.$this->allConfigConnections[$newDefaultConnName]['host'].':'.$this->allConfigConnections[$newDefaultConnName]['port'];
            $results = DB::connection($defaultDbConn)
                         ->table('companies')
                         ->update(['ue_url' => $socketUrl]);
            DB::connection($defaultDbConn)->disconnect();
            Log::info("Socket connection recovered successfully. New default connection is '".$socketUrl."'.");
        } catch(\Exception $e){
            DB::connection($defaultDbConn)->disconnect();
            Log::debug(" ++ Updating socket URL in use to '".$this->allConfigConnections[$newDefaultConnName]['host']."' failed. ++ ");
            throw new SocketConnectionException("Could not  (using db connection '$defaultDbConn') -- ".$e->getMessage());
        }

        return $newDefaultConnName;
    }
}
