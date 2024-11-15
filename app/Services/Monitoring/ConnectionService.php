<?php

namespace App\Services\Monitoring;

use App\Exceptions\PrimaryConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * For use with on-prem
 */
abstract class ConnectionService
{
    /** The name of this machine's type; "main", "backup", "arbiter" */
    protected ?string $currentMachineType;

    public function __construct() {
        $this->currentMachineType = config('app.versioning.machine');
    }

    public function getCurrentMachineType() : string
    {
        return $this->currentMachineType;
    }

    public function getCurrentMachineConnectionName() : string
    {
        return $this->currentMachineConnName;
    }

    public function getConnectionNames() : array
    {
        return $this->connectionNamesMap;
    }

    /**
     * See if we can connect to $connectionName's host:port
     * Default connection timeout of 2 seconds
     *
     * @param string $connectionName
     * @param int    $timeout - seconds
     *
     * @return bool
     */
    abstract public function checkConnection(string $connectionName, int $timeout=2) : bool;

    /**
     * See if we can connect to a specific $host:$port
     * Default connection timeout of 2 seconds
     *
     * NOTE: This check makes detecting database down state much faster than if we were to attempt to
     *       connect to the DB
     *
     * @param string        $host
     * @param int|string    $port
     * @param int           $timeout - seconds
     *
     * @return bool
     */
    protected function check(string $host, int|string $port, int $timeout=2) : bool
    {
        try {
            $connection = fsockopen($host, $port, $errCode, $errMsg, $timeout);
            if($connection){
                fclose($connection);
                return true;
            }
        } catch (\Exception $e){
            Log::error("EXCEPTION OCCURRED when trying to reach the host machine at host: '$host' on port: '$port' -- Exception: {$e->getMessage()}");
            if(isset($connection) && $connection !== false) {
                fclose($connection);
            }
            return false;
        }

        Log::error("Could not reach the host machine at host: '$host' on port: '$port' -- Error Code: $errCode, Error Message: $errMsg");

        return false;
    }

    /**
     * Ping the given host
     *
     * @param string $host
     * @param int    $timeout
     *
     * @return bool
     */
    protected function pingHost(string $host, int $timeout=2) : bool
    {
        exec("ping -c 1 -W $timeout $host", $output, $status);
        return $status === 0;
    }

    /**
     * Find a working connection from the given list of connections
     *
     * @param array     $connections
     * @param string[]  $excludeConnections - connection names to exclude from the search
     * @param string    $exceptionClass - exception to throw if no good connections are found
     *
     * @return string
     */
    protected function findGoodConnection(array $connections, array $excludeConnections=[], string $exceptionClass=PrimaryConnectionException::class) : string
    {
        $connectionNames = '';
        foreach($connections as $connectionName => $connection) {
            if(in_array($connectionName, $excludeConnections)) {
                continue;
            }

            $connectionNames .= $connectionName . ', ';

            if($this->checkConnection($connectionName)) {
                return $connectionName;
            }
        }

        throw new $exceptionClass("No good connections could be found within the list of available connections (".(rtrim($connectionNames, ', ')).").");
    }

    /**
     * Cache the new default connection for use by subsequent requests.
     *
     * NOTE: we can't simply update the config with config() because the config:cache command
     * disregards any in-app value changes and recreates the config from scratch using
     * the values for the .env file. So instead, we will manually update the default
     * connection info that is set in the .env file.
     *
     * @param string $name
     * @param string $host
     * @param string $port
     * @param string $nameKey - env key for the connection name
     * @param string $hostKey - env key for the connection host
     * @param string $portKey - env key for the connection port
     *
     * @return string - name of new default connection
     */
    protected function setNewDefaultConnection(string $name, string $host, string $port, string $nameKey, string $hostKey, string $portKey) : string
    {
        Log::info("Setting new default connection to use -- '$name' -- host: $host, port: $port");

        $this->setEnvironmentValue($nameKey, $name);
        $this->setEnvironmentValue($hostKey, $host);
        $this->setEnvironmentValue($portKey, $port);
        Artisan::call('config:cache');

        return $name;
    }

    /**
     * Change the value of an env var from within the .env file itself
     *
     * @param $envKey
     * @param $envValue
     *
     * @return void
     * @throws \Exception
     * @link https://stackoverflow.com/a/52548022
     */
    protected function setEnvironmentValue($envKey, $envValue) : void
    {
        // get the current .env file contents
        $envFile = app()->environmentFilePath();
        $envContents = file_get_contents($envFile);
        // create a backup copy of the .env file in case something goes wrong
        copy($envFile, $envFile . '.bak');
        // create a copy of the .env file for us to write to
        $envNew = copy($envFile, $envFile . '.new');

        // We'll be searching for the key,value pair using newlines characters, so append
        // a newline in case the searched variable is in the last line without one
        $envContents .= PHP_EOL;
        $keyPosition = strpos($envContents, "$envKey=");
        // Search for the first newline character after the `$keyPosition` to find the end of the value for that key.
        // Use '/\R/' to match all line endings regardless of OS (same as using: `\r\n|\n|\r|\f|\x0b|\x85|\x{2028}|\x{2029}`)
        // @see https://www.npopov.com/2011/12/10/PCRE-and-newlines.html
        preg_match('/\R/', $envContents, $matches, PREG_OFFSET_CAPTURE, $keyPosition);
        $endOfLinePosition = isset($matches[0]) ? ($matches[0][1] ?? null) : null;
        if(!isset($endOfLinePosition)) {
            throw new \Exception("!! The env var was not found in the .env file contents; the original .env file will not be changed (Tried to change '$envKey' to '$envValue').");
        }

        // Replace the old value with the new value by replacing the entire line
        $oldLine = substr($envContents, $keyPosition, $endOfLinePosition - $keyPosition);
        $envContents = str_replace($oldLine, "$envKey=$envValue", $envContents);
        $envContents = substr($envContents, 0, -1);

        // Write the change to the new .env file
        $fp = fopen($envNew, 'w');
        fwrite($fp, $envContents);
        fclose($fp);

        // Make sure the new .env file is not empty
        $newEnvContents = file_get_contents($envNew);
        if(empty($newEnvContents)) {
            throw new \Exception("!! The new .env file is empty; the original .env file will not be changed (Tried to change '$envKey' to '$envValue').");
        }

        // Replace the original .env file with the new one
        copy($envNew, $envFile);
    }
}
