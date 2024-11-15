# Specific settings
> See: https://dev.mysql.com/doc/refman/8.1/en/group-replication-configuring-instances.html

## Engines
```
disabled_storage_engines="MyISAM,BLACKHOLE,FEDERATED,ARCHIVE,MEMORY"
```
Using storage engines other than InnoDB could cause errors

## Replication Requirements
```bash
# unique identifier 
server_id=1
# enable replication with global transaction identifiers
gtid_mode=ON
# allow execution of only statements that can be safely logged using a GTID
enforce_gtid_consistency=ON

# DEFAULTS -- on by default in 8.1
# *** disabled if --initialize-insecure is specified ***
log_bin=ON
log_replica_updates=ON
binlog_format=ROW 
lower_case_table_names=1 # 1 for InnoDB, must be same for all group members
xa_detach_on_prepare=ON # recommended

# default_table_encryption=OFF - can be ON or OFF just must be the same on all group members
```
> [Section 13.3.8.2, “XA Transaction States”](https://dev.mysql.com/doc/refman/8.1/en/xa-states.html)

## Group Replication Settings
Ensure that the server is configured and instructed to instantiate the replication infrastructure:
### plugin_load_add
```bash
# adds the Group Replication plugin to the list of plugins which the server loads at startup
plugin_load_add='group_replication.so'
```

### group_replication_group_name
```bash
# tells the plugin the name of the group that it is joining, or being created
# must be a valid UUID
# forms part of the data written to the binary log for:
#   - GTIDs that are used when transactions received by group members from clients
#   - view change events that are generated internally by the group members
group_replication_group_name="aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"
```

### group_replication_start_on_boot
```bash
# `group_replication_start_on_boot=off` instructs the plugin to not start operations automatically when the server starts.
# This is important when setting up Group Replication as it ensures you can configure 
# the server before manually starting the plugin. 
# Once the member is configured you can set `group_replication_start_on_boot` to `on` 
# so that Group Replication starts automatically upon server boot
group_replication_start_on_boot=off
```

### group_replication_local_address
Sets the network address and port which the member uses for **internal** communication with other members in the group.

_**Important**
The **group replication local address must be different to the host name and port used for SQL client connections**, which are defined by MySQL Server's `hostname` and `port` system variables. It must not be used for client applications. It must be **only be used for internal communication between the members of the group** while running Group Replication._

> The network address configured by `group_replication_local_address` must be resolvable by all group members. For example, if each server instance is on a different machine with a fixed network address, you could use the IP address of the machine, such as `10.0.0.1`. If you use a host name, you must use a fully qualified name, and ensure it is resolvable through DNS, correctly configured `/etc/hosts` files, or other name resolution processes.

**The recommended port for `group_replication_local_address` is `33061`**

> This is used by Group Replication as the unique identifier for a group member within the replication group.  
> You can use the same port for all members of a replication group as long as the host names or IP addresses are all different, as demonstrated in this tutorial.  
> Alternatively you can use the same host name or IP address for all members as long as the ports are all different
> See: https://dev.mysql.com/doc/refman/8.1/en/group-replication-deploying-locally.html

The **connection that an existing member offers to a joining member** for Group Replication's distributed recovery process **is not the network address configured by `group_replication_local_address`**.   
Group members offer their standard SQL client connection to joining members for distributed recovery, as specified by MySQL Server's `hostname` and `port`; they may (also) advertise an alternative list of distributed recovery endpoints as dedicated client connections for joining members.

_**Important**
Distributed recovery can fail if a joining member cannot correctly identify the other members using the host name as defined by MySQL Server's hostname system variable.  
It is recommended that operating systems running MySQL have a properly configured unique host name, either using DNS or local settings.  
The host name that the server is using for SQL client connections can be verified in the `Member_host` column of the Performance Schema table `replication_group_members`._

_If multiple group members externalize a default host name set by the operating system, there is a chance of the joining member not resolving it to the correct member address and not being able to connect for distributed recovery.  
**In this situation you can use MySQL Server's `report_host` system variable to configure a unique host name to be externalized by each of the servers**._

```bash
# Group Replication uses this address for internal member-to-member connections 
# involving remote instances of the group communication engine. 
group_replication_local_address= "s1:33061"
```

### group_replication_group_seeds

Sets the hostname and port of the group members which are used by the new member to establish its connection to the group. Once the connection is established, the group membership information is listed in the Performance Schema table `replication_group_members`.

**Usually the group_replication_group_seeds list contains the `hostname`:`port` of each of the group member's `group_replication_local_address`.**

_**Important**  
The `hostname`:`port` listed in `group_replication_group_seeds` is the seed member's internal network address, configured by `group_replication_local_address` and not the `hostname`:`port` used for SQL client connections_

> The server that starts the group does not make use of this option, since it is the initial server and as such, it is in charge of bootstrapping the group.  
> In other words, **any existing data which is on the server bootstrapping the group is what is used as the data for the next joining member**.  
> The second server joining asks the one and only member in the group to join, any missing data on the second server is replicated from the donor data on the bootstrapping member, and then the group expands.  
> The third server joining can ask any of these two to join, data is synchronized to the new member, and then the group expands again.  
> Subsequent servers repeat this procedure when joining.

_**Warning**
When joining multiple servers at the same time, **make sure that they point to seed members that are already in the group**. Do not use members that are also joining the group as seeds, because they might not yet be in the group when contacted._

_It is good practice to start the bootstrap member first, and let it create the group. **Then make it the seed member for the rest of the members that are joining**. This ensures that there is a group formed when joining the rest of the members._

_Creating a group and joining multiple members at the same time is not supported. It might work, but chances are that the operations race and then the act of joining the group ends up in an error or a timeout._

> See more: https://dev.mysql.com/doc/refman/8.1/en/group-replication-ip-address-permissions.html

```bash
# the `hostname`:`port` of each of the group member's `group_replication_local_address`
group_replication_group_seeds= "s1:33061,s2:33061,s3:33061"
```

### group_replication_bootstrap_group

Instructs the plugin whether to bootstrap the group or not.

We set this variable to `off` in the options. Instead we configure `group_replication_bootstrap_group` when the instance is running, to ensure that only one member actually bootstraps the group.

_**Important**
`group_replication_bootstrap_group` must only be enabled on one server instance belonging to a group at any time, usually the first time you bootstrap the group (or in case the entire group is brought down and back up again).  
If you bootstrap the group multiple times, for example when multiple server instances have this option set, then they could create an artificial split brain scenario, in which two distinct groups with the same name exist.  
**Always set `group_replication_bootstrap_group=off` after the first server instance comes online**._

```bash
group_replication_bootstrap_group=off
```

### group_replication_ip_allowlist

**The allowlist must contain the IP address or host name that is specified in each member's `group_replication_local_address` system variable.**  
This address is not the same as the MySQL server SQL protocol `host` and `port`, and is not specified in the `bind_address` system variable for the server instance.

> If a host name used as the Group Replication local address for a server instance resolves to both an IPv4 and an IPv6 address, the IPv4 address is preferred for Group Replication connections.

IP addresses specified as distributed recovery endpoints, and the IP address for the member's standard SQL client connection if that is used for distributed recovery (which is the default), do not need to be added to the allowlist.

**The allowlist is only for the address specified by `group_replication_local_address` for each member.** A joining member must have its initial connection to the group permitted by the allowlist in order to retrieve the address or addresses for distributed recovery.

> When a connection attempt from an IP address is refused because the address is not in the allowlist, the refusal message always prints the IP address in IPv6 format. IPv4 addresses are preceded by `::ffff:` in this format (an IPV4-mapped IPv6 address). **You do not need to use this format to specify IPv4 addresses in the allowlist**; use the standard IPv4 format for them.

**Important**
_**The automatic allowlist of private addresses cannot be used for connections from servers outside the private network**_, so a server, even if it has interfaces on public IPs, does not by default allow Group Replication connections from external hosts.

For Group Replication **connections between server instances that are on different machines, you must provide public IP addresses and specify these as an explicit allowlist**. If you specify any entries for the allowlist, **the private and localhost addresses are not added automatically**, so if you use any of these, you must specify them explicitly.

> To join a replication group, a server needs to be permitted on the seed member to which it makes the request to join the group. Typically, this would be the bootstrap member for the replication group, but it can be any of the servers listed by the group_replication_group_seeds option in the configuration for the server joining the group.

For host names, name resolution takes place only when a connection request is made by another server. A host name that cannot be resolved is not considered for allowlist validation, and a warning message is written to the error log. Forward-confirmed reverse DNS (FCrDNS) verification is carried out for resolved host names.

> You can also implement name resolution locally using the hosts file, to avoid the use of external components.

- A comma must separate each entry in the allowlist
- specify the same allowlist for all servers that are members of the replication group
```mysql
SET GLOBAL group_replication_ip_allowlist="192.0.2.21/24,198.51.100.44,203.0.113.0/24,2001:db8:85a3:8d3:1319:8a2e:370:7348,example.org,www.example.com/24";
```

# User Credentials

The same replication user must be used for distributed recovery on every group member.

> Group Replication uses a distributed recovery process to synchronize group members when joining them to the group. Distributed recovery involves transferring transactions from a donor's binary log to a joining member using a replication channel named group_replication_recovery. You must therefore set up a replication user with the correct permissions so that Group Replication can establish direct member-to-member replication channels.
> See: https://dev.mysql.com/doc/refman/8.1/en/group-replication-distributed-recovery.html

> If distributed recovery connections for your group use SSL, the replication user must be created on each server before the joining member connects to the donor. For instructions to set up SSL for distributed recovery connections and create a replication user that requires SSL, see [Section 18.6.3, “Securing Distributed Recovery Connections”](https://dev.mysql.com/doc/refman/8.1/en/group-replication-distributed-recovery-securing.html)

_**Important**
By default, users created in MySQL 8 use [Section 6.4.1.2, “Caching SHA-2 Pluggable Authentication”](https://dev.mysql.com/doc/refman/8.1/en/caching-sha2-pluggable-authentication.html)._

_If the replication user for distributed recovery uses the caching SHA-2 authentication plugin, and you are _**not**_ using SSL for distributed recovery connections, **RSA key-pairs are used for password exchange**._

_You can either copy the public key of the replication user to the joining member, or configure the donors to provide the public key when requested. For instructions to do this, see [Section 18.6.3.1, “Secure User Credentials for Distributed Recovery”](https://dev.mysql.com/doc/refman/8.1/en/group-replication-secure-user.html)._

To create the replication user for distributed recovery:
1. Start server
2. Disable binary logging in order to create the replication user separately on each instance
    ```mysql
    SET SQL_LOG_BIN=0;
    ```
3. Create a MySQL user with the following privileges:
    ```mysql
    CREATE USER IF NOT EXISTS rpl_user@'%' IDENTIFIED BY 'password';
    # required for making a distributed recovery connection to a donor to retrieve data
    GRANT REPLICATION SLAVE ON *.* TO rpl_user@'%';
    # ensures that Group Replication connections are not terminated if one of the 
    # servers involved is placed in offline mode
    GRANT CONNECTION_ADMIN ON *.* TO rpl_user@'%';
    # if the servers in the replication group are set up to support cloning 
    # This privilege is required for a member to act as the donor in a cloning operation for distributed recovery
    GRANT BACKUP_ADMIN ON *.* TO rpl_user@'%';
    # if the MySQL communication stack is in use for the replication group. 
    # This privilege is required for the user account to be able to establish and 
    # maintain connections for Group Replication using the MySQL communication stack
    GRANT GROUP_REPLICATION_STREAM ON *.* TO rpl_user@'%';
    FLUSH PRIVILEGES;
    ```
4. Re-enable binary logging
    ```mysql
    SET SQL_LOG_BIN=1;
    ```
5. Supply the user credentials to the server for use with distributed recovery by setting the user credentials as the credentials for the `group_replication_recovery` channel:
    ```mysql
    CHANGE REPLICATION SOURCE TO SOURCE_USER='rpl_user', 
       SOURCE_PASSWORD='password'
       FOR CHANNEL 'group_replication_recovery';
    ```
   > These user credentials are stored in plain text in the replication metadata repositories on the server. They are applied whenever Group Replication is started, including automatic starts.


> [Section 18.6.3.1.3, “Providing Replication User Credentials Securely”](https://dev.mysql.com/doc/refman/8.1/en/group-replication-secure-user.html#group-replication-secure-user-provide)
> [Section 18.5.4.2, “Cloning for Distributed Recovery”](https://dev.mysql.com/doc/refman/8.1/en/group-replication-cloning.html)
> [Section 18.6.1, “Communication Stack for Connection Security Management”](https://dev.mysql.com/doc/refman/8.1/en/group-replication-connection-security.html)

# Launching Group Replication
Ensure group replication plugin is installed successfully
```mysql
SHOW PLUGINS;
```

# Bootstrapping the Group (Start group for the first time)
```mysql
SET GLOBAL group_replication_bootstrap_group=ON;
START GROUP_REPLICATION;
SET GLOBAL group_replication_bootstrap_group=OFF;
```

Check that the group is now created and that there is one member in it:
```mysql
SELECT * FROM performance_schema.replication_group_members;
```

Create some test data:
```mysql
CREATE DATABASE test;
USE test;
CREATE TABLE t1 (c1 INT PRIMARY KEY, c2 TEXT NOT NULL);
INSERT INTO t1 VALUES (1, 'Luis');
SELECT * FROM t1;
```

Check the binary log:
```mysql
SHOW BINLOG EVENTS;
```

# Add Another Instance to the Group
## Config
Example config file for server #2:
```bash
[mysqld]
#
# Disable other storage engines
#
disabled_storage_engines="MyISAM,BLACKHOLE,FEDERATED,ARCHIVE,MEMORY"
#
# Replication configuration parameters
#
server_id=2
gtid_mode=ON
enforce_gtid_consistency=ON
#
# Group Replication configuration
#
plugin_load_add='group_replication.so'
group_replication_group_name="aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"
group_replication_start_on_boot=off
group_replication_local_address= "s2:33061"
group_replication_group_seeds= "s1:33061,s2:33061,s3:33061"
group_replication_bootstrap_group= off
```

## User Creds
The commands are the same as used when setting up server s1 as the user is shared within the group.  
**This member needs to have the same replication user**
```mysql
SET SQL_LOG_BIN=0;
CREATE USER rpl_user@'%' IDENTIFIED BY 'password';
GRANT REPLICATION SLAVE ON *.* TO rpl_user@'%';
GRANT CONNECTION_ADMIN ON *.* TO rpl_user@'%';
GRANT BACKUP_ADMIN ON *.* TO rpl_user@'%';
GRANT GROUP_REPLICATION_STREAM ON *.* TO rpl_user@'%';
FLUSH PRIVILEGES;
SET SQL_LOG_BIN=1;
```

```mysql
CHANGE REPLICATION SOURCE TO SOURCE_USER='rpl_user', SOURCE_PASSWORD='password' \
	FOR CHANNEL 'group_replication_recovery';
```

## Start Group Replication
You do not need to bootstrap the group because the group already exists. At this point server s2 only needs to be added to the already existing group.
```mysql
START GROUP_REPLICATION;
```

Check the `replication_group_members` table:
```mysql
SELECT * FROM performance_schema.replication_group_members;
+---------------------------+--------------------------------------+-------------+-------------+--------------+-------------+----------------+----------------------------+
| CHANNEL_NAME              | MEMBER_ID                            | MEMBER_HOST | MEMBER_PORT | MEMBER_STATE | MEMBER_ROLE | MEMBER_VERSION | MEMBER_COMMUNICATION_STACK |
+---------------------------+--------------------------------------+-------------+-------------+--------------+-------------+----------------+----------------------------+
| group_replication_applier | 395409e1-6dfa-11e6-970b-00212844f856 |   s1        |        3306 | ONLINE       | PRIMARY     | 8.1.0          | XCom                       |
| group_replication_applier | ac39f1e6-6dfa-11e6-a69d-00212844f856 |   s2        |        3306 | ONLINE       | SECONDARY   | 8.1.0          | XCom                       |
+---------------------------+--------------------------------------+-------------+-------------+--------------+-------------+----------------+----------------------------+
```

> When s2 attempted to join the group, Section 18.5.4, “Distributed Recovery” ensured that s2 applied the same transactions which s1 had applied. Once this process completed, s2 could join the group as a member, and at this point it is marked as ONLINE. In other words it must have already caught up with server s1 automatically.

## Check Replication Success
Once s2 is ONLINE, it then begins to process transactions with the group. Verify that s2 has indeed synchronized with server s1 as follows:
```mysql
SHOW DATABASES LIKE 'test';

SELECT * FROM test.t1;

SHOW BINLOG EVENTS;
+---------------+------+----------------+-----------+-------------+--------------------------------------------------------------------+
| Log_name      | Pos  | Event_type     | Server_id | End_log_pos | Info                                                               |
+---------------+------+----------------+-----------+-------------+--------------------------------------------------------------------+
| binlog.000001 |    4 | Format_desc    |         2 |         123 | Server ver: 8.1.0-log, Binlog ver: 4                              |
| binlog.000001 |  123 | Previous_gtids |         2 |         150 |                                                                    |
| binlog.000001 |  150 | Gtid           |         1 |         211 | SET @@SESSION.GTID_NEXT= 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa:1'  |
| binlog.000001 |  211 | Query          |         1 |         270 | BEGIN                                                              |
| binlog.000001 |  270 | View_change    |         1 |         369 | view_id=14724832985483517:1                                        |
| binlog.000001 |  369 | Query          |         1 |         434 | COMMIT                                                             |
| binlog.000001 |  434 | Gtid           |         1 |         495 | SET @@SESSION.GTID_NEXT= 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa:2'  |
| binlog.000001 |  495 | Query          |         1 |         585 | CREATE DATABASE test                                               |
| binlog.000001 |  585 | Gtid           |         1 |         646 | SET @@SESSION.GTID_NEXT= 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa:3'  |
| binlog.000001 |  646 | Query          |         1 |         770 | use `test`; CREATE TABLE t1 (c1 INT PRIMARY KEY, c2 TEXT NOT NULL) |
| binlog.000001 |  770 | Gtid           |         1 |         831 | SET @@SESSION.GTID_NEXT= 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa:4'  |
| binlog.000001 |  831 | Query          |         1 |         890 | BEGIN                                                              |
| binlog.000001 |  890 | Table_map      |         1 |         933 | table_id: 108 (test.t1)                                            |
| binlog.000001 |  933 | Write_rows     |         1 |         975 | table_id: 108 flags: STMT_END_F                                    |
| binlog.000001 |  975 | Xid            |         1 |        1002 | COMMIT /* xid=30 */                                                |
| binlog.000001 | 1002 | Gtid           |         1 |        1063 | SET @@SESSION.GTID_NEXT= 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa:5'  |
| binlog.000001 | 1063 | Query          |         1 |        1122 | BEGIN                                                              |
| binlog.000001 | 1122 | View_change    |         1 |        1261 | view_id=14724832985483517:2                                        |
| binlog.000001 | 1261 | Query          |         1 |        1326 | COMMIT                                                             |
+---------------+------+----------------+-----------+-------------+--------------------------------------------------------------------+
```

The second server has been added to the group and it has replicated the changes from server s1 automatically. In other words, the transactions applied on s1 up to the point in time that s2 joined the group have been replicated to s2

# Add Additional Instances to the Group
Adding additional instances to the group is essentially the same sequence of steps as adding the second server, except that the configuration has to be changed as it had to be for server s2.

## Config
```bash
[mysqld]
#
# Disable other storage engines
#
disabled_storage_engines="MyISAM,BLACKHOLE,FEDERATED,ARCHIVE,MEMORY"
#
# Replication configuration parameters
#
server_id=3
gtid_mode=ON
enforce_gtid_consistency=ON
#
# Group Replication configuration
#
plugin_load_add='group_replication.so'
group_replication_group_name="aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"
group_replication_start_on_boot=off
group_replication_local_address= "s3:33061"
group_replication_group_seeds= "s1:33061,s2:33061,s3:33061"
group_replication_bootstrap_group= off
```

## User Creds
```mysql
SET SQL_LOG_BIN=0;
CREATE USER rpl_user@'%' IDENTIFIED BY 'password';
GRANT REPLICATION SLAVE ON *.* TO rpl_user@'%';
GRANT CONNECTION_ADMIN ON *.* TO rpl_user@'%';
GRANT BACKUP_ADMIN ON *.* TO rpl_user@'%';
GRANT GROUP_REPLICATION_STREAM ON *.* TO rpl_user@'%';
FLUSH PRIVILEGES;
SET SQL_LOG_BIN=1;
```

```mysql
CHANGE REPLICATION SOURCE TO SOURCE_USER='rpl_user', 
   SOURCE_PASSWORD='password'
   FOR CHANNEL 'group_replication_recovery';
```

## Start Group Replication
```mysql
START GROUP_REPLICATION;
```

Check the `replication_group_members` table:
```mysql
SELECT * FROM performance_schema.replication_group_members;
+---------------------------+--------------------------------------+-------------+-------------+--------------+-------------+----------------+----------------------------+
| CHANNEL_NAME              | MEMBER_ID                            | MEMBER_HOST | MEMBER_PORT | MEMBER_STATE | MEMBER_ROLE | MEMBER_VERSION | MEMBER_COMMUNICATION_STACK |
+---------------------------+--------------------------------------+-------------+-------------+--------------+-------------+----------------+----------------------------+
| group_replication_applier | 395409e1-6dfa-11e6-970b-00212844f856 |   s1        |        3306 | ONLINE       | PRIMARY     | 8.1.0          | XCom                       |
| group_replication_applier | 7eb217ff-6df3-11e6-966c-00212844f856 |   s3        |        3306 | ONLINE       | SECONDARY   | 8.1.0          | XCom                       |
| group_replication_applier | ac39f1e6-6dfa-11e6-a69d-00212844f856 |   s2        |        3306 | ONLINE       | SECONDARY   | 8.1.0          | XCom                       |
+---------------------------+--------------------------------------+-------------+-------------+--------------+-------------+----------------+----------------------------+
```

## Check Replication Success
```mysql
SHOW DATABASES LIKE 'test';

SELECT * FROM test.t1;

SHOW BINLOG EVENTS;
```

# Initializing on same machine
> https://dev.mysql.com/doc/refman/8.1/en/group-replication-deploying-locally.html

Create a group with three MySQL Server instances on one physical machine. This means that **three data directories are needed**, one per server instance, and that you need to configure each instance independently.

These settings configure MySQL server to use the data directory created earlier and which port the server should open and start listening for incoming connections:
```bash
[mysqld]

# server configuration
datadir=<full_path_to_data>/data/s1
#basedir=<full_path_to_bin>/mysql-8.1/

port=24801
#socket=<full_path_to_sock_dir>/s1.sock
```
_The non-default port of 24801 is used because in this tutorial the three server instances use the same hostname. In a setup with three different machines this would not be required._

## Network Connection
Group Replication requires a network connection between the members, which means that each member must be able to resolve the network address of all of the other members.

For example in this tutorial all three instances run on one machine, so **to ensure that the members can contact each other** you could add a line to the option file such as `report_host=127.0.0.1`

Then each member needs to be able to connect to the other members on their `group_replication_local_address`. For example in the option file of member s1 add:
```bash
group_replication_local_address= "127.0.0.1:24901"
group_replication_group_seeds= "127.0.0.1:24901,127.0.0.1:24902,127.0.0.1:24903"
```

This configures s1 to use port 24901 for internal group communication with seed members.

> For each server instance you want to add to the group, make these changes in the option file of the member. For each member you must ensure a unique address is specified, so use a unique port per instance for `group_replication_local_address`.  
> Usually you want all members to be able to serve as seeds for members that are joining the group and have not got the transactions processed by the group. In this case, add all of the ports to `group_replication_group_seeds` as shown above.

---

# Limitations
## Table Locks
The certification process does not take into account table locks

## Foreign Keys with Cascading Constraints
**Multi-primary mode groups** (members all configured with group_replication_single_primary_mode=OFF) **do not support tables** with multi-level foreign key dependencies, specifically tables **that have defined CASCADING foreign key constraints**.  
This is because foreign key constraints that result in cascading operations executed by a multi-primary mode group can result in undetected conflicts and lead to inconsistent data across the members of the group.  
Therefore we recommend setting `group_replication_enforce_update_everywhere_checks=ON` on server instances used in multi-primary mode groups to avoid undetected conflicts.
**In single-primary mode this is not a problem as it does not allow concurrent writes to multiple members of the group and thus there is no risk of undetected conflicts.**

## Multi-primary Mode Deadlock
When a group is operating in multi-primary mode, SELECT .. FOR UPDATE statements can result in a deadlock. This is because the lock is not shared across the members of the group, therefore the expectation for such a statement might not be reached.

## Group Size
The maximum number of MySQL servers that can be members of a single replication group is 9.

## Transaction Size
If an individual transaction results in message contents which are large enough that the message cannot be copied between group members over the network within a 5-second window, members can be suspected of having failed, and then expelled, just because they are busy processing the transaction.

Large transactions can also cause the system to slow due to problems with memory allocation.

To avoid these issues use the following mitigations: [See limitation docs under "Limits on Transaction Size"](https://dev.mysql.com/doc/refman/8.1/en/group-replication-limitations.html)

---

# Monitoring
> https://dev.mysql.com/doc/refman/8.1/en/group-replication-monitoring.html

Use `performance_schema` tables:
- `replication_group_member_stats`
- `replication_group_members`
- `replication_group_communication_information`
- `replication_connection_status`
- `replication_applier_status`
- `group_replication_recovery`
- `group_replication_applier`

Messages relating to **Group Replication lifecycle events** other than errors are classified as system messages; these are always **written to the replication group member' error log**. You can use this information to review the history of a given server's membership in a replication group.

Some **lifecycle events that affect the whole group are logged on every group member**, such as a new member entering ONLINE status in the group or a primary election.  
**Other events are logged only on the member where they take place**, such as super read only mode being enabled or disabled on the member, or the member leaving the group.

---

# GTIDs (global transaction identifiers)
> https://dev.mysql.com/doc/refman/8.1/en/group-replication-gtids.html

Group Replication uses GTIDs to track exactly which transactions have been committed on every server instance. Incoming transactions from clients are assigned a GTID by the group member that receives them.  
Any replicated transactions that are received by group members on asynchronous replication channels from source servers outside the group retain the GTIDs that they have when they arrive on the group member.

## Extra Transactions
If a joining member has transactions in its GTID set that are not present on the existing members of the group, it is not allowed to complete the distributed recovery process, and cannot join the group.

Extra transactions might be present on a member if an administrative transaction is carried out on the instance while Group Replication is stopped. To avoid introducing new transactions in that way, always set the value of the sql_log_bin system variable to OFF before issuing administrative statements, and back to ON afterwards:
```mysql
SET SQL_LOG_BIN=0;
<administrator action>
SET SQL_LOG_BIN=1;
```

Setting this system variable to OFF means that the transactions that occur from that point until you set it back to ON are not written to the binary log and do not have GTIDs assigned to them.

If an extra transaction is present on a joining member, check the binary log for the affected server to see what the extra transaction actually contains. **The safest method to reconcile the joining member’s data and GTID set with the members currently in the group is to use MySQL's cloning functionality to transfer the content from a server in the group to the affected server**.  
For instructions to do this, see [Section 5.6.7.3, “Cloning Remote Data”](https://dev.mysql.com/doc/refman/8.1/en/clone-plugin-remote.html). If the transaction is required, rerun it after the member has successfully rejoined.

---

# Server States
> https://dev.mysql.com/doc/refman/8.1/en/group-replication-server-states.html

- `ONLINE` : The server is an active member of a group and in a fully functioning state.
    - Other group members can connect to it, as can clients if applicable.
    - A member is only fully synchronized with the group, and participating in it, when it is in the `ONLINE` state.
- `RECOVERING` : The server has joined a group and is in the process of becoming an active member.
    - Distributed recovery is currently taking place, where the member is receiving state transfer from a donor using a remote cloning operation or the donor's binary log.
- `OFFLINE` : The Group Replication plugin is loaded but the member does not belong to any group.
    - This status may briefly occur while a member is joining or rejoining a group.
- `ERROR` : The member is in an error state and is not functioning correctly as a group member.
    - A member can enter error state either while applying transactions or during the recovery phase.
    - A member in this state does not participate in the group's transactions.
- `UNREACHABLE` : The local failure detector suspects that the member cannot be contacted, because the group's messages are timing out.
    - This can happen if a member is disconnected involuntarily, for example.
        - If you see this status for other servers, it can also mean that the member where you query this table is part of a partition, where a subset of the group's servers can contact each other but cannot contact the other servers in the group.

---

# Tables
## `replication_group_members`
Used for monitoring the status of the different server instances that are members of the group.
> The information in the table is updated whenever there is a view change, for example when the configuration of the group is dynamically changed when a new member joins.

## `replication_group_member_stats`
Provides group-level information related to the certification process, and also statistics for the transactions received and originated by each individual member of the replication group.

_**Note that refreshing of statistics for remote members is controlled by the message period specified in the `group_replication_flow_control_period` option, so these can differ slightly from the locally collected statistics for the member where the query is made.**_

> Each member in a replication group certifies and applies transactions received by the group. Statistics regarding the certifier and applier procedures are useful to understand how the applier queue is growing, how many conflicts have been found, how many transactions were checked, which transactions are committed everywhere, and so on.

---

# Managing Groups
> https://dev.mysql.com/doc/refman/8.1/en/group-replication-operations.html

## In case a full group shutdown
> SEE: https://dev.mysql.com/doc/refman/8.1/en/group-replication-restarting-group.html

**The replication group must be restarted beginning with the most up to date member**, that is, the member that has the most transactions executed and certified. The members with fewer transactions can then join and catch up with the transactions they are missing through distributed recovery.

**It is not correct to assume that the last known primary member of the group is the most up to date** member of the group, because a member that was shut down later than the primary might have more transactions.

PLEASE SEE LINK FOR CORRECT STEPS TO RESTART THE GROUP:
https://dev.mysql.com/doc/refman/8.1/en/group-replication-restarting-group.html

## Transaction Consistency
> https://dev.mysql.com/doc/refman/8.1/en/group-replication-consistency-guarantees.html

## Distributed Recovery
> https://dev.mysql.com/doc/refman/8.1/en/group-replication-distributed-recovery.html

## Security
> https://dev.mysql.com/doc/refman/8.1/en/group-replication-security.html

### IP Address Permissions
> PLEASE SEE: https://dev.mysql.com/doc/refman/8.1/en/group-replication-ip-address-permissions.html

The Group Replication plugin lets you specify an `allowlist` of hosts from which an incoming Group Communication System connection can be accepted. If you specify an `allowlist` on a server s1:
- when server s2 is establishing a connection to s1 for the purpose of engaging group communication,
- s1 first checks the `allowlist` before accepting the connection from s2.
- If s2 is in the `allowlist`, then s1 accepts the connection

#### Default
If you do not specify an allowlist explicitly, the group communication engine (XCom) automatically scans active interfaces on the host, and identifies those with addresses on private subnetworks, together with the subnet mask that is configured for each interface.

These addresses, and the localhost IP address for IPv4 and IPv6 are used to create an automatic Group Replication allowlist.

**The automatic allowlist therefore includes any IP addresses that are found for the host in the following ranges after the appropriate subnet mask has been applied**:
```bash
IPv4 (as defined in RFC 1918)
10/8 prefix       (10.0.0.0 - 10.255.255.255) - Class A
172.16/12 prefix  (172.16.0.0 - 172.31.255.255) - Class B
192.168/16 prefix (192.168.0.0 - 192.168.255.255) - Class C

IPv6 (as defined in RFC 4193 and RFC 5156)
fc00:/7 prefix    - unique-local addresses
fe80::/10 prefix  - link-local unicast addresses

127.0.0.1 - localhost for IPv4
::1       - localhost for IPv6
```

An entry is added to the error log stating the addresses that have been allowed automatically for the host.

**Important**
_**The automatic allowlist of private addresses cannot be used for connections from servers outside the private network**_, so a server, even if it has interfaces on public IPs, does not by default allow Group Replication connections from external hosts.

For Group Replication **connections between server instances that are on different machines, you must provide public IP addresses and specify these as an explicit allowlist**. If you specify any entries for the allowlist, **the private and localhost addresses are not added automatically**, so if you use any of these, you must specify them explicitly.

---

# Official Troubleshooting Docs
> https://dev.mysql.com/doc/refman/8.1/en/group-replication-performance.html
