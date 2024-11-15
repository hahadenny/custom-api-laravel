
# FROM SCRATCH -- for testing the setup. NOTE: These steps are outdated. See: `offline/mysql-repl/test-ubuntu-hardcode.sh` instead

> Instructions altered from [How To Configure MySQL Group Replication on Ubuntu 20.04](https://www.digitalocean.com/community/tutorials/how-to-configure-mysql-group-replication-on-ubuntu-20-04#step-4-configuring-replication-users-and-enabling-group-replication-plugin) to apply to our docker config

# Create containers

## Machine 1 
### Container A
```bash
docker create --name porta-db --network=porta-net -e MYSQL_ROOT_PASSWORD=porta -e MYSQL_DATABASE=porta -e MYSQL_USER=porta -e MYSQL_PASSWORD=porta \
-v /tmp/mysql:/var/lib/mysql \
-p 3306:3306 mysql/mysql-server \
--character-set-server=utf8mb4 \
--collation-server=utf8mb4_unicode_ci
```
### Container B
```bash
docker create --name porta-db-backup --network=porta-net -e MYSQL_ROOT_PASSWORD=porta -e MYSQL_DATABASE=porta -e MYSQL_USER=porta -e MYSQL_PASSWORD=porta \
 -v /tmp/mysql:/var/lib/mysql \
 -p 3307:3307 mysql/mysql-server \
 --character-set-server=utf8mb4 \
 --collation-server=utf8mb4_unicode_ci
```

# Generate UUID to identify the group
The `uuidgen` program creates (and prints) a new universally unique identifier (UUID) using the libuuid(3) library.  
The new UUID can reasonably be considered unique among all UUIDs created on the local system, and among UUIDs
created on other systems in the past and in the future.

```bash
uuidgen 
```
Ex: 45317831-5ff4-4d0c-be89-172c5b7a77df

# Create a mysql config file -- my.cnf
If it already exists, paste the contents in:
```bash
###############################################################################################
# CUSTOMIZED :
############################
# General replication settings
disabled_storage_engines="MyISAM,BLACKHOLE,FEDERATED,ARCHIVE,MEMORY"
gtid_mode = ON
enforce_gtid_consistency = ON
master_info_repository = TABLE
relay_log_info_repository = TABLE
binlog_checksum = NONE
log_slave_updates = ON
log_bin = binlog
binlog_format = ROW
transaction_write_set_extraction = XXHASH64
loose-group_replication_bootstrap_group = OFF
loose-group_replication_start_on_boot = OFF
#loose-group_replication_ssl_mode = REQUIRED
loose-group_replication_recovery_use_ssl = 1

# Shared replication group configuration
loose-group_replication_group_name = ""
loose-group_replication_ip_whitelist = ""
loose-group_replication_group_seeds = ""

# Single or Multi-primary mode? Uncomment these two lines
# for multi-primary mode, where any host can accept writes
loose-group_replication_single_primary_mode = OFF
loose-group_replication_enforce_update_everywhere_checks = ON

# Host specific replication configuration

# must be set to a unique number
server_id =
# respective server's IP address - address to bind to
#bind-address = ""
# respective server's IP address  - address to report to other members
report_host = ""
# the current server’s IP address with the group replication port appended
loose-group_replication_local_address = ""
```

## General replication settings
Leave unchanged

## Shared replication group configuration 
**These settings must be the same on each of your MySQL servers.**

### Set `loose-group_replication_group_name`
In the shared replication group config section:
```bash
loose-group_replication_group_name = "45317831-5ff4-4d0c-be89-172c5b7a77df"
```

### Set `loose-group_replication_ip_whitelist` to a list of all of your MySQL server IP addresses, separated by commas

```bash
loose-group_replication_ip_whitelist = "192.168.50.163, 192.168.50.183"
```

### Set `loose-group_replication_group_seeds` as IPs with ports 
This setting should be almost the same as the whitelist, but should append a designated group replication port to the end of each member:
```bash
loose-group_replication_group_seeds = "192.168.50.163:3306, 192.168.50.183:3307, 192.168.50.183:3306, 192.168.50.183:3307"
```

## Choose single or multi-primary
**These settings must be the same on each of your MySQL servers.**
Make sure both lines are uncommented:
```bash
loose-group_replication_single_primary_mode = OFF
loose-group_replication_enforce_update_everywhere_checks = ON
```

## Host specific replication configuration
Complete this process on each of your MySQL servers.

** NOTE: `bind-address` crashes container **

### Machine 1 - container A
```bash
# must be set to a unique number
server_id = 1
# respective server's IP address 
#bind-address = "192.168.50.163"
# respective server's IP address  - address to report to other members
report_host = "192.168.50.163"
# the current server’s IP address with the group replication port appended
loose-group_replication_local_address = "192.168.50.163:3306"
```

### Machine 1 - container B
```bash
# must be set to a unique number
server_id = 2
# respective server's IP address 
#bind-address = "192.168.50.163"
# respective server's IP address  - address to report to other members
report_host = "192.168.50.163"
# the current server’s IP address with the group replication port appended
loose-group_replication_local_address = "192.168.50.163:3307"
```

### Machine 2 - container A
```bash
# must be set to a unique number
server_id = 3
# respective server's IP address 
#bind-address = "192.168.50.183"
# address to report to other members
report_host = "192.168.50.183"
# local replication address and listening port
loose-group_replication_local_address = "192.168.50.183:3306"
```

### Machine 2 - container B
```bash
# must be set to a unique number
server_id = 4
# respective server's IP address 
#bind-address = "192.168.50.183"
# address to report to other members
report_host = "192.168.50.183"
# local replication address and listening port
loose-group_replication_local_address = "192.168.50.183:3307"
```

Save and close the file on each host when you’re finished

# Allow access between servers
Expose Docker ports?

# Configure Replication Users and Enable Group Replication Plugin
**Each MySQL instance must have a dedicated replication user**

## Created Dedicated Replication User on Each Server
On each of your MySQL servers, log into your MySQL instance with the administrative user. Access the container terminal nad then:
```bash
mysql -p
```
Turn off binary logging during the creation process
```mysql
SET SQL_LOG_BIN=0;
```

Create the replication user in mysql
```mysql
CREATE USER IF NOT EXISTS 'repl'@'%' IDENTIFIED BY 'password';
```

Grant the new user replication privileges on the server. This is required for making a distributed recovery connection to a donor to retrieve data
```mysql
GRANT REPLICATION SLAVE ON *.* TO 'repl'@'%';
```

Ensure that Group Replication connections are not terminated if one of the servers involved is placed in offline mode
```mysql
GRANT CONNECTION_ADMIN ON *.* TO repl@'%';
```

If the servers in the replication group are set up to support cloning, this privilege is required for 
a member to act as the donor in a cloning operation for distributed recovery
```mysql
GRANT BACKUP_ADMIN ON *.* TO repl@'%';
```

If the MySQL communication stack is in use for the replication group, this privilege is required for 
the user account to be able to establish and maintain connections for Group Replication using the 
MySQL communication stack
```mysql
GRANT GROUP_REPLICATION_STREAM ON *.* TO repl@'%';
```

Implement the changes:
```mysql
FLUSH PRIVILEGES;
```

Re-enable binary logging:
```mysql
SET SQL_LOG_BIN=1;
```

## Set recovery channel
Set the `group_replication_recovery` channel to use your new replication user and their associated password. Each server will then use these credentials to authenticate to the group for use with distributed recovery:
```mysql
CHANGE REPLICATION SOURCE TO SOURCE_USER='repl', SOURCE_PASSWORD='password' FOR CHANNEL 'group_replication_recovery';
```

## Enable the `group_replication` plugin
```mysql
INSTALL PLUGIN group_replication SONAME 'group_replication.so';
```
_Note: The container on the same machine may display error `ERROR 1125 (HY000): Function 'group_replication' already exists`_

Check the plugin is active:
It should appear at the bottom of the list
```mysql
SHOW PLUGINS;
```

# Starting Group Replication

## Bootstrapping the First Node
To start up the group, complete the following steps on a **single member of the group**

> Group members rely on existing members to send replication data, up-to-date membership lists, and other information when initially joining the group. Because of this, you need to **use a slightly different procedure to start up the initial group member so that it knows not to expect this information from other members in its seed list**

> If set, the `group_replication_bootstrap_group` variable tells a member that it shouldn’t expect to receive information from peers and should instead establish a new group and elect itself the primary member.

### On Machine 1 Container A 
Turn on `group_replication_bootstrap_group`
```mysql
SET GLOBAL group_replication_bootstrap_group=ON;
```

Start replication for the initial group member:
```mysql
START GROUP_REPLICATION;
```

Turn `group_replication_bootstrap_group` off again, since the only situation where this is appropriate is when there are no existing group members:
```mysql
SET GLOBAL group_replication_bootstrap_group=OFF;
```

Verify that this is the only member:
```mysql
SELECT * FROM performance_schema.replication_group_members;
```

### Create test data
```mysql
USE porta;
CREATE TABLE equipment (
id INT NOT NULL AUTO_INCREMENT,
type VARCHAR(50),
quant INT,
color VARCHAR(25),
PRIMARY KEY(id)
);

INSERT INTO equipment (type, quant, color) VALUES ('slide', 2, 'blue');

SELECT * FROM equipment;
```

After verifying that this server is a member of the group and that it has write capabilities, the other servers can join the group.

# Starting Up the Remaining Nodes
## Start group replication on node 2




---

# Troubleshooting

## \[InnoDB\] Unable to lock ./ibdata1 error: 11
Copy and replace, for all files this occurs
```bash
sudo mv /tmp/mysql/ibdata1 /tmp/mysql/ibdata1.bak
sudo cp -a /tmp/mysql/ibdata1.bak /tmp/mysql/ibdata1
```

Script: 
```bash
# Directory containing the files
directory="/tmp/mysql"

# Iterate over files in the directory
for file in "$directory"/*; do
    # Check if it's a regular file
    if [ -f "$file" ]; then
        # Check if the file name does not end with ".bak"
        if [[ "$file" != *.bak ]]; then
            # Generate the new file name by adding a ".bak" extension
            new_name="${file}.bak"
    
            # Rename the file
            sudo mv "$file" "$new_name"
    
            # Copy it back to the original name
            sudo cp -a "$new_name" "$file"
    
            # Optional: Verify the renaming and copying
            echo "Renamed and copied: $file -> $new_name -> $file"
        fi
    fi
done
```

### Delete files that end in multiple `.bak`
```bash
#!/bin/bash

# Directory containing the files
directory="/tmp/mysql"

# Use the find command to locate files ending with two or more ".bak" extensions
files_to_delete=$(find "$directory" -type f -name "*.bak.bak*")

# Check if any files match the criteria
if [ -n "$files_to_delete" ]; then
    # Loop through the files and delete each one
    for file in $files_to_delete; do
        rm "$file"
        echo "Deleted: $file"
    done
else
    echo "No files matching the criteria found."
fi
```

## Find used ports in windows or linux
```
netstat -a -t
```

```
netstat -a -t -n | grep 3306
```

## "ping" a port with telnet
```bash
telnet 192.168.50.163 3306
```

## var values are reset after server restart
Use `SET PERSIST` instead of `SET GLOBAL`

Check global variable values with `SHOW GLOBAL VARIABLES;` or `SHOW GLOBAL VARIABLES LIKE '%group_replication%';`

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


Check member status (on each server)
```mysql
 SELECT CHANNEL_NAME, GROUP_NAME, SOURCE_UUID, SERVICE_STATE, LAST_ERROR_NUMBER, LAST_ERROR_MESSAGE, LAST_ERROR_TIMESTAMP  FROM performance_schema.replication_connection_status;
```

Check aborted connections
```mysql
SHOW GLOBAL STATUS LIKE 'Aborted_connects';
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

[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] Timeout while waiting for the group communication engine to be ready!'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] The group communication engine is not ready for the member to join. Local port: 33062'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] The member was unable to join the group. Local port: 33062'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] Error on opening a connection to peer node porta-db-3:33063 when joining a group. My local port is: 33062.'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] Error on opening a connection to peer node porta-db-3:33063 when joining a group. My local port is: 33062.'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] Error on opening a connection to peer node porta-db-3:33063 when joining a group. My local port is: 33062.'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] Error on opening a connection to peer node porta-db-3:33063 when joining a group. My local port is: 33062.'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] Error on opening a connection to peer node porta-db-3:33063 when joining a group. My local port is: 33062.'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] Error on opening a connection to peer node porta-db-3:33063 when joining a group. My local port is: 33062.'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] Error on opening a connection to peer node porta-db-3:33063 when joining a group. My local port is: 33062.'
[ERROR] [MY-011640] [Repl] Plugin group_replication reported: 'Timeout on wait for view after joining group'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] The member is already leaving or joining a group.'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] Error connecting to all peers. Member join failed. Local port: 33062'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] The member was unable to join the group. Local port: 33062'


## Error joining existing group -- `Old incarnation found while trying to add node`

If another node, i.e., `porta-db-2` has already tried and failed to join the group and is listed as `OFFLINE`, when trying to `START GROUP_REPLICATION` again this error may display on the primary node.

Stopping each node didn't seem to work, but wiping and recreating the containers did. Obviously not viable in production. 

You're supposed to be able to bring the cluster down and then back up to fix it, TBD on how best to do this.
