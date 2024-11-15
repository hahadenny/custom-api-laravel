# Group Replication Setup Troubleshooting

For getting replication working in the first place.


## On start, secondary DBs are not connecting
Try restarting or stopping and starting the primary, then stopping and starting the secondaries and trying again.

Also try removing the 3rd node from the 2nd node's seed list if it's not being found and restarting.

Note: Fully stopping, then starting again may wipe the replication settings and need setup again when using the base+mysql dockerfiles.

## MySQL connection is slow using hostname but fast using IP

> https://serverfault.com/questions/408550/connecting-to-mysql-from-php-is-extremely-slow
> https://superuser.com/questions/436574/ipv4-vs-ipv6-priority-in-windows-7/436944#436944
> When you use a host name instead of an IP address, the MySQL client first runs an AAAA (IPv6) host lookup for the name, and tries this address first if it successfully resolves the name to an IPv6 address. If either step fails (name resolution or connection) it will fallback to IPv4, running an A lookup and trying this host instead.
> 
> What this means in practice is that if the IPv6 localhost lookup is successful but MySQL is not bound to the IPv6 loopback, you will need to wait for one connection timeout cycle before the IPv4 fallback occurs and the connection succeeds.

First test with a simple PHP script to ensure it is the connection and not the app:
```php
$hostname = 'localhost';
// $hostname = '127.0.0.1';
$username = 'porta';
$password = 'porta';
$database = 'porta';

try {
    // Create a new PDO instance
    $pdo = new PDO("mysql:host=$hostname;dbname=$database", $username, $password);

    // Set PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to the database successfully via '$hostname'<BR><BR>";

    try {
        // SQL query
        $sql = "SELECT * FROM porta.playlists";

        // Prepare the query
        $stmt = $pdo->prepare($sql);

        // Execute the query
        $stmt->execute();

        // Fetch data
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process the result
        foreach ($result as $row) {
            echo "ID: " . $row['id'] . ", Name: " . $row['name'] . "<br>";
        }
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }


} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

exit;
```

If one is slower (i.e., ~2s vs 16ms) then IPv6 lookup may be the problem. **The slowdown happens from the application and not pings or other checks because it is caused by the mysql client** 

Check the windows prefix policy table, this is similar to a routing table and will display the lookup priorities:
```shell
netsh int ipv6 show prefixpolicies
```

### Solution:
Easiest is to just use the IP for connections from PHP/Laravel


## var values are reset after server restart
Use `SET PERSIST` instead of `SET GLOBAL`


## ERROR 3096 (HY000): The `START GROUP_REPLICATION` command failed as there was an error when initializing the group communication layer.
```bash
[System] [MY-013587] [Repl] Plugin group_replication reported: 'Plugin 'group_replication' is starting.'
[System] [MY-011565] [Repl] Plugin group_replication reported: 'Setting super_read_only=ON.'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] There is no local IP address matching the one configured for the local node (192.168.50.163:3306).'
[ERROR] [MY-011674] [Repl] Plugin group_replication reported: 'Unable to initialize the group communication engine'
[ERROR] [MY-011637] [Repl] Plugin group_replication reported: 'Error on group communication engine initialization'
[System] [MY-011566] [Repl] Plugin group_replication reported: 'Setting super_read_only=OFF.'
```

Set `group_replication_local_address` hostname to container name
```mysql
SELECT * FROM performance_schema.replication_group_members;
```
```plain
+---------------------------+--------------------------------------+----------------+-------------+--------------+-------------+----------------+----------------------------+
| CHANNEL_NAME              | MEMBER_ID                            | MEMBER_HOST    | MEMBER_PORT | MEMBER_STATE | MEMBER_ROLE | MEMBER_VERSION | MEMBER_COMMUNICATION_STACK |
+---------------------------+--------------------------------------+----------------+-------------+--------------+-------------+----------------+----------------------------+
| group_replication_applier | 87e78883-72aa-11ee-b126-0242ac120003 | 192.168.50.163 |        3306 | ONLINE       | PRIMARY     | 8.0.32         | XCom                       |
+---------------------------+--------------------------------------+----------------+-------------+--------------+-------------+----------------+----------------------------+
1 row in set (0.00 sec)
```

> The `ONLINE` value for `MEMBER_STATE` indicates that this node is fully operational within the group


## data dir /var/mysql is unusable
Could be a permissions problem, but more likely a config problem, check for other errors occurring before this (invalid/unknown command option, etc.)

## `ERROR 1045 (28000): Access denied for user 'root'@'localhost' (using password: YES)`
## Or `ERROR 2002 (HY000): Can't connect to local MySQL server through socket '/var/lib/mysql/mysql.sock' (2)`
Sometimes this happens before container status = `healthy` and connection is (wrongly) attempted via socket and not TCP


## [GCS] Connection attempt from IP address ::ffff:172.18.0.4 refused. Address is not in the IP allowlist.
Set `group_replication_ip_allowlist` to default

> NOTE: this setting will not work for connections from servers outside the private network

```mysql
SET PERSIST group_replication_ip_allowlist='AUTOMATIC';
```

## Primary port is wrong -- `Slave I/O for channel 'group_replication_recovery': error connecting to master 'repl@192.168.50.163:3306'`
```bash
[ERROR] [MY-010584] [Repl] Slave I/O for channel 'group_replication_recovery': error connecting to master 'repl@192.168.50.163:3306' - retry-time: 60 retries: 1 message: Authentication plugin 'caching_sha2_password' reported error: Authentication requires secure connection. Error_code: MY-002061
[ERROR] [MY-011582] [Repl] Plugin group_replication reported: 'There was an error when connecting to the donor server. Please check that group_replication_recovery channel credentials and all MEMBER_HOST column values of performance_schema.replication_group_members table are correct and DNS resolvable.'
[ERROR] [MY-011583] [Repl] Plugin group_replication reported: 'For details please check performance_schema.replication_connection_status table and error log messages of Slave I/O for channel group_replication_recovery.'
```

Check the group members' values:
```mysql
SELECT * FROM performance_schema.replication_group_members;
+---------------------------+--------------------------------------+----------------+-------------+--------------+-------------+----------------+----------------------------+
| CHANNEL_NAME              | MEMBER_ID                            | MEMBER_HOST    | MEMBER_PORT | MEMBER_STATE | MEMBER_ROLE | MEMBER_VERSION | MEMBER_COMMUNICATION_STACK |
+---------------------------+--------------------------------------+----------------+-------------+--------------+-------------+----------------+----------------------------+
| group_replication_applier | b6099205-72b4-11ee-b096-0242ac120003 | 192.168.50.163 |        3306 | ONLINE       | PRIMARY     | 8.0.32         | XCom                       |
| group_replication_applier | efc3cde1-72b4-11ee-b1a8-0242ac120004 | 192.168.50.163 |        3306 | RECOVERING   | SECONDARY   | 8.0.32         | XCom                       |
+---------------------------+--------------------------------------+----------------+-------------+--------------+-------------+----------------+----------------------------+
```

If port is wrong, set it with `--report-port='XXXX` option on container creation, then check again:

```mysql
SELECT * FROM performance_schema.replication_group_members;
+---------------------------+--------------------------------------+----------------+-------------+--------------+-------------+----------------+----------------------------+
| CHANNEL_NAME              | MEMBER_ID                            | MEMBER_HOST    | MEMBER_PORT | MEMBER_STATE | MEMBER_ROLE | MEMBER_VERSION | MEMBER_COMMUNICATION_STACK |
+---------------------------+--------------------------------------+----------------+-------------+--------------+-------------+----------------+----------------------------+
| group_replication_applier | b6099205-72b4-11ee-b096-0242ac120003 | 192.168.50.163 |        3307 | ONLINE       | PRIMARY     | 8.0.32         | XCom                       |
| group_replication_applier | efc3cde1-72b4-11ee-b1a8-0242ac120004 | 192.168.50.163 |        3308 | RECOVERING   | SECONDARY   | 8.0.32         | XCom                       |
+---------------------------+--------------------------------------+----------------+-------------+--------------+-------------+----------------+----------------------------+
```

## error connecting to master 'repl@192.168.50.163:3307' - retry-time: 60 retries: 1 message: Lost connection to MySQL server at 'reading initial communication packet', system error: 0

Set mysql port at server create with `--port=####`


## error connecting to master 'repl@192.168.50.163:3307' - retry-time: 60 retries: 1 message: Authentication plugin 'caching_sha2_password' reported error: Authentication requires secure connection.

`SET PERSIST group_replication_recovery_get_public_key=ON;` for each server so that the public key can be shared (less secure, only use if you are sure there is no risk of server identity being compromised, for example by a man-in-the-middle attack)

> See: https://dev.mysql.com/doc/refman/8.2/en/group-replication-secure-user.html

```mysql 
SELECT * FROM performance_schema.replication_group_members;
+---------------------------+--------------------------------------+----------------+-------------+--------------+-------------+----------------+----------------------------+
| CHANNEL_NAME              | MEMBER_ID                            | MEMBER_HOST    | MEMBER_PORT | MEMBER_STATE | MEMBER_ROLE | MEMBER_VERSION | MEMBER_COMMUNICATION_STACK |
+---------------------------+--------------------------------------+----------------+-------------+--------------+-------------+----------------+----------------------------+
| group_replication_applier | 8e480008-7370-11ee-b162-0242ac120002 | 192.168.50.163 |        3307 | ONLINE       | PRIMARY     | 8.0.32         | XCom                       |
| group_replication_applier | a7ed68c7-7370-11ee-b1d9-0242ac120003 | 192.168.50.163 |        3308 | ONLINE       | SECONDARY   | 8.0.32         | XCom                       |
+---------------------------+--------------------------------------+----------------+-------------+--------------+-------------+----------------+----------------------------+
```


## Error joining existing group -- `Timeout while waiting for the group communication engine to be ready`

The primary node's logs may also display `Old incarnation found while trying to add node`

Try pinging and telnet and remote mysql access (`mysql -h10.17.2.33 -P 3307 -uroot -pPorta123`).  
If these all work, check the hosts file and ensure the IP to container name mapping is correct on both machines. This was the issue the last time this error was seen.

### On Re-join
If the whole cluster that was formerly running successfully has gone down and needs to come back up, this error occurs if there is no bootstrapped node. On the first node, or the node that will be the primary, run: 
```mysql
SET PERSIST group_replication_bootstrap_group=ON;
```

If this node is already running but has a state of `OFFLINE`, restart the container and it should start the group replication process and become the primary node. Once it's ready, remember to turn of bootstrapping with:
```mysql
SET PERSIST group_replication_bootstrap_group=OFF;
```

If there are other nodes running with a state of `OFFLINE`, once the bootstrapped primary is running, either restart their containers or run `START GROUP_REPLICATION` again and they should join the group.

Error log example:
```bash
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] Timeout while waiting for the group communication engine to be ready!'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] The group communication engine is not ready for the member to join. Local port: 33062'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] The member was unable to join the group. Local port: 33062'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] Error on opening a connection to peer node porta-db-3:33063 when joining a group. My local port is: 33062.'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] Error on opening a connection to peer node porta-db-3:33063 when joining a group. My local port is: 33062.'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] Error on opening a connection to peer node porta-db-3:33063 when joining a group. My local port is: 33062.'
[ERROR] [MY-011640] [Repl] Plugin group_replication reported: 'Timeout on wait for view after joining group'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] The member is already leaving or joining a group.'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] Error connecting to all peers. Member join failed. Local port: 33062'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] The member was unable to join the group. Local port: 33062'
```

## Error joining existing group -- `Old incarnation found while trying to add node`

If another node, i.e., `porta-db-2` has already tried and failed to join the group and is listed as `OFFLINE`, when trying to `START GROUP_REPLICATION` again this error may display on the existing node.

(Wiping and recreating the containers worked, but obviously not viable in production.)

Bring the cluster down with `STOP GROUP_REPLICATION` on each node. Then on the first node, set `SET PERSIST group_replication_bootstrap_group=ON;` and `START GROUP_REPLICATION`. 

Once the first node is running as primary member, `START GROUP_REPLICATION` on the other machines


## During setup/install --> `Plugin group_replication reported: The member contains transactions not present in the group`
Make sure you turn off bin logging with `SET SQL_LOG_BIN=0;` when running admin commands during setup, then turn it back on with `SET SQL_LOG_BIN=1;`

> Note: you can view the transaction history with `SELECT @@global.gtid_executed;`

Example: 
```bash
docker exec -i "$container_name" mysql -uroot -p"$db_pswd" -e \
    "SET SQL_LOG_BIN=0; \
    GRANT SELECT ON performance_schema.replication_group_members TO '$db_user'@'%'; \
    GRANT SELECT ON performance_schema.replication_connection_status TO '$db_user'@'%'; \
    GRANT SELECT ON performance_schema.replication_group_member_stats TO '$db_user'@'%'; \
    FLUSH PRIVILEGES; \
    SET SQL_LOG_BIN=1;";
```
