
# Monitoring
> https://dev.mysql.com/doc/refman/8.0/en/group-replication-monitoring.html

## `replication_group_members` Table
> https://dev.mysql.com/doc/refman/8.0/en/group-replication-replication-group-members.html

For monitoring the status of the different server instances that are members of the group. Information in the table is updated whenever there is a view change (member join).

```sql
SELECT * FROM performance_schema.replication_group_members;
```

## `replication_group_member_stats` Table
> https://dev.mysql.com/doc/refman/8.0/en/group-replication-replication-group-member-stats.html

Each member in a replication group certifies and applies transactions received by the group. Statistics regarding the certifier and applier procedures are useful to understand:
- how the applier queue is growing, 
- how many conflicts have been found, 
- how many transactions were checked, 
- which transactions are committed everywhere, 
- and so on

```sql 
SELECT * FROM performance_schema.replication_group_member_stats\G
```

## `replication_group_communication_information` Table
> https://dev.mysql.com/doc/refman/8.0/en/performance-schema-replication-group-communication-information-table.html

Shows group configuration options for the whole replication group.


## Replication Channels
`group_replication_recovery`: Used for replication changes related to distributed recovery.

`group_replication_applier`: Used for the incoming changes from the group, to apply transactions coming directly from the group.


## Group Replication System Variables 
> https://dev.mysql.com/doc/refman/8.0/en/group-replication-system-variables.html

### Group-wide Configuration Settings
Group-wide configuration settings cannot be changed by the usual methods while Group Replication is running.

- group_replication_single_primary_mode
- group_replication_enforce_update_everywhere_checks
- group_replication_gtid_assignment_block_size
- group_replication_view_change_uuid
- group_replication_paxos_single_leader
- group_replication_communication_stack (a special case not policed by Group Replication's own checks; see the system variable description for details)
- default_table_encryption
- lower_case_table_names
- transaction_write_set_extraction (deprecated from MySQL 8.0.26)

### Same Value on All Members
Set the same value on all members of a group in order to avoid unnecessary rollback of transactions, failure of message delivery, or failure of message recovery

- group_replication_auto_increment_increment
- group_replication_communication_max_message_size
- group_replication_compression_threshold
- group_replication_message_cache_size
- group_replication_transaction_size_limit


### Variables That Need a Group Replication START/STOP to Apply Changes

- group_replication_advertise_recovery_endpoints
- group_replication_autorejoin_tries
- group_replication_consistency
- group_replication_exit_state_action
- group_replication_flow_control_applier_threshold
- group_replication_flow_control_certifier_threshold
- group_replication_flow_control_hold_percent
- group_replication_flow_control_max_quota
- group_replication_flow_control_member_quota_percent
- group_replication_flow_control_min_quota
- group_replication_flow_control_min_recovery_quota
- group_replication_flow_control_mode
- group_replication_flow_control_period
- group_replication_flow_control_release_percent
- group_replication_force_members
- group_replication_ip_allowlist
- group_replication_ip_whitelist
- group_replication_member_expel_timeout
- group_replication_member_weight
- group_replication_transaction_size_limit
- group_replication_unreachable_majority_timeout


## Log Error Verbosity

| log_error_verbosity Value | 	Permitted Message Priorities |
|:--------------------------|:------------------------------|
| 1                         | 	ERROR                        |
| 2                         | 	ERROR, WARNING               |
| 3                         | 	ERROR, WARNING, INFORMATION  |


--- 


# Verify Sync on Join
When a new node joins and is displaying its state as ONLINE in `performance_schema.replication_group_members`, verify that the new node is in sync with the other nodes by checking the binlog events on each node:
```sql
SHOW BINLOG EVENTS;
```

# GTIDs
> https://dev.mysql.com/doc/refman/8.0/en/replication-gtids.html
> https://dev.mysql.com/doc/refman/8.0/en/replication-gtids-lifecycle.html
> https://dev.mysql.com/doc/refman/8.0/en/replication-options-gtids.html

The UUID portion of the GTID is the `group_replication_group_name`, regardless of the member that originally received them. 

A new member joining the group (a `View_change_log_event`) also generates a GTID when recorded to the binary log. 
> This can be customized with `group_replication_view_change_uuid` in order to distinguish between view change vent GTIDs and client transaction GTIDs.

## `gtid_purged`
The following categories of GTIDs are in gtid_purged:

- GTIDs of replicated transactions that were committed with binary logging disabled on the replica.
- GTIDs of transactions that were written to a binary log file that has now been purged.
- GTIDs that were added explicitly to the set by the statement SET @@GLOBAL.gtid_purged. 

You can change the value of `gtid_purged` in order to record on the server that the transactions in a certain GTID set have been applied, although they do not exist in any binary log on the server. When you add GTIDs to `gtid_purged`, they are also added to `gtid_executed`.

An example use case for this action is when you are restoring a backup of one or more databases on a server, but you do not have the relevant binary logs containing the transactions on the server.

**`gtid_executed`** -->  is computed as the union of the GTIDs in Previous_gtids_log_event in the most recent binary log file, the GTIDs of transactions in that binary log file, and the GTIDs stored in the mysql.gtid_executed table. This GTID set contains all the GTIDs that have been used (or added explicitly to gtid_purged) on the server, whether or not they are currently in a binary log file on the server. It does not include the GTIDs for transactions that are currently being processed on the server (@@GLOBAL.gtid_owned).

```bash
SELECT @@global.gtid_executed;
```

**`gtid_purged`** --> is computed by first adding the GTIDs in Previous_gtids_log_event in the most recent binary log file and the GTIDs of transactions in that binary log file. This step gives the set of GTIDs that are currently, or were once, recorded in a binary log on the server (gtids_in_binlog). Next, the GTIDs in Previous_gtids_log_event in the oldest binary log file are subtracted from gtids_in_binlog. This step gives the set of GTIDs that are currently recorded in a binary log on the server (gtids_in_binlog_not_purged). Finally, gtids_in_binlog_not_purged is subtracted from gtid_executed. The result is the set of GTIDs that have been used on the server, but are not currently recorded in a binary log file on the server, and this result is used to initialize gtid_purged.

## Resetting the GTID Execution History

To reset the GTID execution history on a server, use the `RESET MASTER` statement. 

For example, you might need to do this:
- after carrying out test queries to verify a replication setup on new GTID-enabled servers, 
- when you want to join a new server to a replication group but it contains some unwanted local transactions that are not accepted by Group Replication

When you issue `RESET MASTER`, the following reset operations are carried out:

- The value of the gtid_purged system variable is set to an empty string ('').
- The global value (but not the session value) of the gtid_executed system variable is set to an empty string.
- The mysql.gtid_executed table is cleared (see mysql.gtid_executed Table).
- If the server has binary logging enabled, the existing binary log files are deleted and the binary log index file is cleared.



# [ERROR] [MY-011522] [Repl] Plugin group_replication reported: 'The member contains transactions not present in the group. The member will now exit the group.'

Extra transactions might be present on a member if an administrative transaction is carried out on the instance while Group Replication is stopped.

**To avoid introducing new transactions, always set the value of the `sql_log_bin` system variable to OFF before issuing administrative statements, and back to ON afterward**:

```sql
SET SQL_LOG_BIN=0;

#  transactions that occur here are not written to the binary log and do not have GTIDs assigned to them

SET SQL_LOG_BIN=1;
```

If an extra transaction is present on a joining member, check the binary log for the affected server to see what the extra transaction actually contains. The safest method to reconcile the joining member’s data and GTID set with the members currently in the group is to **use MySQL's cloning functionality to transfer the content from a server in the group to the affected server**. --> https://dev.mysql.com/doc/refman/8.0/en/clone-plugin-remote.html

If the transaction is required, rerun it after the member has successfully rejoined.

Check on all nodes:
```sql
SELECT @@global.gtid_executed;
```

Details of any error and the last successfully applied transaction are recorded in the Performance Schema table `replication_applier_status_by_worker`.

```sql
select * from performance_schema.replication_applier_status_by_worker\G
```

```sql
SHOW BINARY LOGS;
```


## Cloning to Affected Server

To clone data from a remote MySQL server instance (the donor) and transfer it to the MySQL instance where the cloning operation was initiated (the recipient):

```sql
CLONE INSTANCE FROM 'user'@'host':port
IDENTIFIED BY 'password'
[DATA DIRECTORY [=] 'clone_dir']
```

- `user` is the clone user on the donor MySQL server instance.
- `password` is the user password.
- `host` is the hostname address of the **donor** MySQL server instance
- `port` is the port number of the **donor** MySQL server instance
- `clone_dir` [optionla] is the directory on the **recipient** MySQL server instance where the data is to be stored. 
  - If not specified, the default is the data directory of the recipient MySQL server instance.
- `[REQUIRE [NO] SSL]` explicitly specifies whether an encrypted connection is to be used or not when transferring cloned data over the network. 
  - An error is returned if the explicit specification cannot be satisfied


Example: 
```sql
CLONE INSTANCE FROM 'repl'@'porta-db-2':3307
IDENTIFIED BY 'password'
```

Result: ERROR 3869 (HY000): Clone system configuration: porta-db-2:3307 is not found in clone_valid_donor_list:

### flushing and purging the replica's binary logs, as in this example:

FLUSH LOGS;
PURGE BINARY LOGS TO 'binlog.000146'; # of Y-m-d H:i:s
