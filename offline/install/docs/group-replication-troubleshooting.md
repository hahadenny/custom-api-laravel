# Group Replication

Info for installing Porta on Prem with mysql's group replication. 


## QuickRef

For more useful commands and info on checking the database status, see [Support Cheatsheet](support-cheatsheet.html) in the "Porta Database" section.

_**NOTE**: Replace `<container-name>` (and remove the `<` `>`) with the name of the container you are accessing, i.e., `porta-db-2`_

- main machine database has container name `porta-db` and port `3306` (replication port `33061`)
- backup machine database has container name `porta-db-2` and port `3307` (replication port `33062`)
- arbiter machine database has container name `porta-db-3` and port `3308` (replication port `33063`)

### View database logs
```bash
docker logs <container-name>
```

### View database replication group members
```bash
docker exec -it <container-name> mysql -uroot -p -e "SELECT * FROM performance_schema.replication_group_members;"
```

#### Start Group Replication
```bash
docker exec -it <container-name> mysql -uroot -p -e "START GROUP_REPLICATION;"
```

#### Stop Group Replication
```bash
docker exec -it <container-name> mysql -uroot -p -e "STOP GROUP_REPLICATION;"
```

### Resetting a Database Container
1. Open cmd.exe
2. Run the command `wsl`
3. Using the `cd` command, navigate to the WSL directory containing the porta on prem installation files
    - i.e., `cd ~/px/porta/porta-onprem-2.x.x+XXXXX`
4. Run the command `sudo chown -R $USER:$USER . && chmod ug+x debug/debug-repl-change.sh && debug/debug-repl-change.sh` to run the database reset tool
5. Follow the prompts displayed in the terminal to reset the database container


## Success Messages

### Non-Primary Member
Example of successful log messages for a non-primary member:
```bash
# The database has found the group to join and it has a primary member
2024-01-24T14:44:29.647313Z 16 [System] [MY-011511] [Repl] Plugin group_replication reported: 'This server is working as secondary member with primary member address 10.100.100.176:3306.'

# If there are changes to be applied, the database will begin applying them
2024-01-29T14:42:36.696980Z 0 [System] [MY-013471] [Repl] Plugin group_replication reported: 'Distributed recovery will transfer data using: Incremental recovery from a group donor'

# The database has joined the group and began replicating
2024-01-24T14:44:29.647977Z 0 [System] [MY-011503] [Repl] Plugin group_replication reported: 'Group membership changed to 10.100.100.176:3306, 10.100.100.177:3307 on view 17061069903020065:2.'

# The database has officially successfully joined the group and its data is in sync with the other databases
2024-01-24T14:44:39.153344Z 0 [System] [MY-011490] [Repl] Plugin group_replication reported: 'This server was declared online within the replication group.'
```

### Primary Member
Example of successful log messages for a primary member:
```bash
# The replication group lost its primary member and has elected a new one
2024-01-29T15:13:13.328189Z 0 [System] [MY-011507] [Repl] Plugin group_replication reported: 'A new primary with address 192.168.50.42:3308 was elected. The new primary will execute all previous group transactions before allowing writes.'

# This database has been elected as the new primary
2024-01-29T15:13:13.634688Z 22 [System] [MY-011510] [Repl] Plugin group_replication reported: 'This server is working as primary member.'
```

# Troubleshooting Errors

## During install: `ERROR 3092 (HY000) at line 1: The server is not configured properly to be an active member of the group.`
The cause of this error truly is dependent on error log details of this machine (and often others). Check the error logs of this machine and look for those errors in this document. It may also help to check the logs fo the other machines in the group for errors around the same timestamp.

## Each Machine Only Sees Itself
If each machine only sees itself in the replication group, this likely means that there was no existing group to join. This can happen if the machines are not able to communicate with each other, or if the group replication process was not bootstrapped on any of the machines.

Example (on each machine):
```sql
SELECT * FROM performance_schema.replication_group_members;
+---------------------------+--------------------------------------+----------------+-------------+--------------+-------------+----------------+----------------------------+
| CHANNEL_NAME              | MEMBER_ID                            | MEMBER_HOST    | MEMBER_PORT | MEMBER_STATE | MEMBER_ROLE | MEMBER_VERSION | MEMBER_COMMUNICATION_STACK |
+---------------------------+--------------------------------------+----------------+-------------+--------------+-------------+----------------+----------------------------+
| group_replication_applier | 3059e925-bc43-11ee-a26c-0242ac120002 | 10.100.100.177 |        3307 | OFFLINE      |             |                | XCom                       |
+---------------------------+--------------------------------------+----------------+-------------+--------------+-------------+----------------+----------------------------+
```

To fix this, group replication needs to be restarted **on each machine**: See [Bootstrapping Group Replication](#bootstrapping-group-replication) below.

## A Joining Member Creates Its Own Group
If a joining member creates its own group, this likely means that the group_replication_bootstrap_group setting was not set to OFF on the joining member. 

## data dir /var/mysql is unusable
Could be a permissions problem, but more likely a config problem, check for other errors occurring before this (invalid/unknown command option, etc.)


## `ERROR 1045 (28000): Access denied for user 'root'@'localhost' (using password: YES)`
## Or `ERROR 2002 (HY000): Can't connect to local MySQL server through socket '/var/lib/mysql/mysql.sock' (2)`
Sometimes this happens before container status = `healthy` and connection is (wrongly) attempted via socket protocol and not TCP. 

Simply waiting for container status to be `healthy` and then trying again should work.


## Error joining existing group 

### `Timeout while waiting for the group communication engine to be ready` / `Error connecting to all peers`

Another node's logs might also display `Old incarnation found while trying to add node`

Error log example (for the node that is failing to join):
```bash
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] Timeout while waiting for the group communication engine to be ready!'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] The group communication engine is not ready for the member to join. Local port: 33062'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] The member was unable to join the group. Local port: 33062'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] Error on opening a connection to peer node porta-db-3:33063 when joining a group. My local port is: 33062.'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] Error on opening a connection to peer node porta-db-3:33063 when joining a group. My local port is: 33062.'
[ERROR] [MY-011640] [Repl] Plugin group_replication reported: 'Timeout on wait for view after joining group'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] The member is already leaving or joining a group.'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] Error connecting to all peers. Member join failed. Local port: 33062'
[ERROR] [MY-011735] [Repl] Plugin group_replication reported: '[GCS] The member was unable to join the group. Local port: 33062'
```

#### Happening On First Join
Try:
- pinging 
  - `ping <MACHINE_IP>`)
- telnet (from Powershell)
  - `telnet <MACHINE_IP> <DATABASE_PORT>`
- remote mysql access 
  - `mysql -hMACHINE_IP -P MACHINE_MYSQL_PORT -uroot -p`  

If these all work, **check the hosts file** and ensure the IP to container name mapping is correct on both machines. (This was the issue the last time this error was seen during setup).

#### Happening On Re-join
This error often occurs if there is no bootstrapped node. Run this check on each machine to get a sense of the state of the cluster:
- _Replace `<container-name>` with the name of the container you are accessing, i.e., `porta-db`_
```bash
docker exec -it <container-name> mysql -uroot -p -e "SELECT * FROM performance_schema.replication_group_members;"
``` 

If each machine only sees itself, then you need to bootstrap the group replication process on one of the machines. See [Bootstrapping Group Replication](#bootstrapping-group-replication) below.

If a machine is listed as `UNREACHABLE`, check the details in its logs (`docker logs <container-name>`) and see [Old incarnation found while trying to add node](#old-incarnation-found-while-trying-to-add-node) below.


### `Old incarnation found while trying to add node`

If another node, i.e., `porta-db-2` has already tried and failed to join the group and is listed as `OFFLINE`, when trying to `START GROUP_REPLICATION` again this error may display on the existing node.

(Wiping and recreating the containers worked, but obviously not viable in production.)

Run this check on each machine to get a sense of the state of the cluster:
- _Replace `<container-name>` with the name of the container you are accessing, i.e., `porta-db`_
```bash
docker exec -it <container-name> mysql -uroot -p -e "SELECT * FROM performance_schema.replication_group_members;"
```
One of the machines will likely display one of the member's `MEMBER_STATE` as `UNREACHABLE`:
```
+---------------------------+--------------------------------------+----------------+-------------+--------------+-------------+----------------+----------------------------+
| CHANNEL_NAME              | MEMBER_ID                            | MEMBER_HOST    | MEMBER_PORT | MEMBER_STATE | MEMBER_ROLE | MEMBER_VERSION | MEMBER_COMMUNICATION_STACK |
+---------------------------+--------------------------------------+----------------+-------------+--------------+-------------+----------------+----------------------------+
| group_replication_applier | e0ff1ffa-bac5-11ee-9fc8-0242ac120002 | 10.100.100.176 |        3306 | UNREACHABLE  | PRIMARY     | 8.0.32         | XCom                       |
| group_replication_applier | faa25e2f-bac6-11ee-a05b-0242ac120005 | 10.100.100.177 |        3307 | ONLINE       | SECONDARY   | 8.0.32         | XCom                       |
+---------------------------+--------------------------------------+----------------+-------------+--------------+-------------+----------------+----------------------------+
```

**NOTE: When the `UNREACHABLE` member also has a `MEMBER_ROLE` of `PRIMARY`, no new primary will be elected until the unreachable member has been kicked out.**  If there is a majority remaining, the member should be kicked out automatically after a timeout period. ([Based on some testing done](https://d3technologies.atlassian.net/browse/PN-1018) by killing primary containers, this appears to be designated by `group_replication_components_stop_timeout`, which defaults to 5 minutes)

If there is no majority remaining to vote the member out, or you would like to remove to member quickly, group replication needs to be restarted **on each machine**: See [Bootstrapping Group Replication](#bootstrapping-group-replication) below.


### `This member has more executed transactions than those present in the group`

Sometimes this can occur when attempting to join a group that the member is already a part of. Check the group members from the failed joiner to confirm that it is not already a member of the group. If all members are present in the result, then the member is already a part of the group.
- _Replace `<container-name>` with the name of the container you are accessing, i.e., `porta-db`_
```bash
docker exec -it <container-name> mysql -uroot -p -e "SELECT * FROM performance_schema.replication_group_members;"
```

If the member is not a part of the group, this error can also occur if a member has a higher `gtid_executed` value than the existing members. This can happen if the member was previously part of a group, or was the primary of its own group, and is now being added to a different group.

Run this check on each machine to view the `gtid_executed` value for each member:
- _Replace `<container-name>` with the name of the container you are accessing, i.e., `porta-db`_
```bash
docker exec -it <container-name> mysql -uroot -p -e "SELECT @@global.gtid_executed;"
```


# Bootstrapping Group Replication 
- _Replace `<container-name>` with the name of the container you are accessing, i.e., `porta-db`_

1. Stop group replication on each machine:
```bash
docker exec -it <container-name> mysql -uroot -p -e "STOP GROUP_REPLICATION;"
```
2. On one machine, turn bootstrapping on in the database:
```bash
docker exec -it <container-name> mysql -uroot -p -e "SET PERSIST group_replication_bootstrap_group=ON;"
```
3. Start group replication on the bootstrapping database only:
```bash
docker exec -it <container-name> mysql -uroot -p -e "START GROUP_REPLICATION"
```
4. Once the bootstrapped database is running and its `MEMBER_STATE` is `ONLINE`, turn bootstrapping off:
```bash
docker exec -it <container-name> mysql -uroot -p -e "SET PERSIST group_replication_bootstrap_group=OFF;"
```
5. Start group replication on the other machines:
```bash
docker exec -it <container-name> mysql -uroot -p -e "START GROUP_REPLICATION"
```
6. Once all machines are running, check the replication group members again:
```bash
docker exec -it <container-name> mysql -uroot -p -e "SELECT * FROM performance_schema.replication_group_members;"
```

All members should be running correctly and `ONLINE`.
