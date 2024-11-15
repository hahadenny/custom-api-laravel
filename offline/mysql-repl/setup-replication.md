
> Majority of these instructions are customized steps from [How To Configure MySQL Group Replication on Ubuntu 20.04](https://www.digitalocean.com/community/tutorials/how-to-configure-mysql-group-replication-on-ubuntu-20-04#step-4-configuring-replication-users-and-enabling-group-replication-plugin)

**_NOTE: This document is mainly for low level dev testing of initial setup and configuration of group replication and is likely outdated compared to the existing scripts in `utils/scripts/start-install-or-update.sh`._**

# Setup Machines' Configuration
## 1) Determine IPs of Primary and Backup Machines
**On BOTH machines**:
1. In Windows Powershell, run `ipconfig`
2. Note the ipv4 address for use in upcoming steps


## 2) Update hosts file of Primary and Backup Machines
**On BOTH machines**:
We want the IP of the machine to point to the relevant container names. 
1. Open notepad as administrator
2. In notepad, open `C:\Windows\System32\drivers\etc\hosts`
3. Add the following section:
```
# Docker DB Replication -- IP -> container name
<PRIMARY MACHINE IP> porta-db
<BACKUP MACHINE IP> porta-db-2
<BACKUP MACHINE IP> porta-db-3
```
4. Save these hosts file changes 


## 3) Add inbound rules to open mysql ports between Primary and Backup Machines
**On BOTH machines**:
1. In Windows Security → Firewall & network protection → Advanced Settings → Inbound Rules → Add Rule  
2. Choose "Ports" and add paste these as local ports: `3306,3307,3308,3309,33060,33061,33062,33063`
3. Finish up the wizard steps


# Configure Group Replication

## Primary Machine
### Create and run the container

### Generate a UUID for the group
Note this for use on the backup machine

### Install group replication plugin (each container)
```bash
docker exec -i porta-db mysql -uroot -pporta -e "INSTALL PLUGIN group_replication SONAME 'group_replication.so'; \
SHOW PLUGINS;"
```

### Set the replication vars
Use the group UUID you generated
```bash
docker exec -i porta-db mysql -uroot -pporta -e "SET PERSIST group_replication_group_name='a5c236d2-2c38-46ce-a15f-ebe5d5560e9c'; \
SET PERSIST group_replication_local_address='porta-db:33061'; \
SET PERSIST group_replication_group_seeds='porta-db:33061,porta-db-2:33062,porta-db-3:33063'; \
SET PERSIST group_replication_ip_allowlist='AUTOMATIC'; \
SET PERSIST group_replication_start_on_boot='OFF'; \
SET PERSIST server_id=3; \
SET PERSIST group_replication_bootstrap_group=OFF; \
SET PERSIST group_replication_recovery_get_public_key=ON; \
SET PERSIST group_replication_enforce_update_everywhere_checks=OFF; \
SET PERSIST group_replication_single_primary_mode=ON; \
"
```

### Create and config the replication user
```bash
docker exec -i porta-db mysql -uroot -pporta -e "SET SQL_LOG_BIN=0; \
CREATE USER IF NOT EXISTS 'repl'@'%' IDENTIFIED BY 'password'; \
GRANT REPLICATION SLAVE ON *.* TO 'repl'@'%'; \
GRANT CLONE_ADMIN ON *.* TO 'repl'@'%'; \
GRANT CONNECTION_ADMIN ON *.* TO 'repl'@'%'; \
GRANT SELECT ON performance_schema.replication_group_members TO 'porta'@'%'; \
GRANT SELECT ON performance_schema.replication_connection_status TO 'porta'@'%'; \
GRANT SELECT ON performance_schema.replication_group_member_stats TO 'porta'@'%'; \
FLUSH PRIVILEGES; \
SET SQL_LOG_BIN=1; \
CHANGE REPLICATION SOURCE TO SOURCE_USER='repl', SOURCE_PASSWORD='password' FOR CHANNEL 'group_replication_recovery'; \
"
```

### Bootstrap the Primary Group member and set to start repl on boot
```bash
docker exec -i porta-db mysql -uroot -pporta -e \
"SET PERSIST group_replication_bootstrap_group=ON; \
START GROUP_REPLICATION; \
SET PERSIST group_replication_bootstrap_group=OFF; \
SET PERSIST group_replication_start_on_boot=ON; \
SELECT * FROM performance_schema.replication_group_members;\
"
```

##################################################################################
### Continue to the next section to configure the backup machine


## Backup Machine
### Create and run the containers

### Install group replication plugin (each container)
```bash
docker exec -i porta-db-2 mysql -uroot -pporta -e "INSTALL PLUGIN group_replication SONAME 'group_replication.so'; \
SHOW PLUGINS;" && \
docker exec -i porta-db-3 mysql -uroot -pporta -e "INSTALL PLUGIN group_replication SONAME 'group_replication.so'; \
SHOW PLUGINS;"
```

### Set the replication vars (each container)
Use the group UUID you generated

> NOTE: 3rd db removed from 2nd DB seeds

```bash
docker exec -i porta-db-2 mysql -uroot -pporta -e "SET PERSIST group_replication_group_name='a5c236d2-2c38-46ce-a15f-ebe5d5560e9c'; \
SET PERSIST group_replication_local_address='porta-db-2:33062'; \
SET PERSIST group_replication_group_seeds='porta-db:33061,porta-db-2:33062'; \
SET PERSIST group_replication_ip_allowlist='AUTOMATIC'; \
SET PERSIST group_replication_start_on_boot='OFF'; \
SET PERSIST server_id=2; \
SET PERSIST group_replication_bootstrap_group=OFF; \
SET PERSIST group_replication_recovery_get_public_key=ON; \
SET PERSIST group_replication_enforce_update_everywhere_checks=OFF; \
SET PERSIST group_replication_single_primary_mode=ON; \
" && \
docker exec -i porta-db-3 mysql -uroot -pporta -e "SET PERSIST group_replication_group_name='a5c236d2-2c38-46ce-a15f-ebe5d5560e9c'; \
SET PERSIST group_replication_local_address='porta-db-3:33063'; \
SET PERSIST group_replication_group_seeds='porta-db:33061,porta-db-2:33062,porta-db-3:33063'; \
SET PERSIST group_replication_ip_allowlist='AUTOMATIC'; \
SET PERSIST group_replication_start_on_boot='OFF'; \
SET PERSIST server_id=3; \
SET PERSIST group_replication_bootstrap_group=OFF; \
SET PERSIST group_replication_recovery_get_public_key=ON; \
SET PERSIST group_replication_enforce_update_everywhere_checks=OFF; \
SET PERSIST group_replication_single_primary_mode=ON; \
"
```


### Create and config the replication user for each container
```bash
docker exec -i porta-db-2 mysql -uroot -pporta -e "SET SQL_LOG_BIN=0; \
CREATE USER IF NOT EXISTS 'repl'@'%' IDENTIFIED BY 'password'; \
GRANT REPLICATION SLAVE ON *.* TO 'repl'@'%'; \
GRANT CLONE_ADMIN ON *.* TO 'repl'@'%'; \
GRANT CONNECTION_ADMIN ON *.* TO 'repl'@'%'; \
GRANT SELECT ON performance_schema.replication_group_members TO 'porta'@'%'; \
GRANT SELECT ON performance_schema.replication_connection_status TO 'porta'@'%'; \
GRANT SELECT ON performance_schema.replication_group_member_stats TO 'porta'@'%'; \
FLUSH PRIVILEGES; \
SET SQL_LOG_BIN=1; \
CHANGE REPLICATION SOURCE TO SOURCE_USER='repl', SOURCE_PASSWORD='password' FOR CHANNEL 'group_replication_recovery'; \
" && \
docker exec -i porta-db-3 mysql -uroot -pporta -e "SET SQL_LOG_BIN=0; \
CREATE USER IF NOT EXISTS 'repl'@'%' IDENTIFIED BY 'password'; \
GRANT REPLICATION SLAVE ON *.* TO 'repl'@'%'; \
GRANT CLONE_ADMIN ON *.* TO 'repl'@'%'; \
GRANT CONNECTION_ADMIN ON *.* TO 'repl'@'%'; \
GRANT SELECT ON performance_schema.replication_group_members TO 'porta'@'%'; \
GRANT SELECT ON performance_schema.replication_connection_status TO 'porta'@'%'; \
GRANT SELECT ON performance_schema.replication_group_member_stats TO 'porta'@'%'; \
FLUSH PRIVILEGES; \
SET SQL_LOG_BIN=1; \
CHANGE REPLICATION SOURCE TO SOURCE_USER='repl', SOURCE_PASSWORD='password' FOR CHANNEL 'group_replication_recovery'; \
"
```


### Join the replication group from each container and set to start repl on boot
```bash
docker exec -i porta-db-2 mysql -uroot -pporta -e "START GROUP_REPLICATION; \
SET PERSIST group_replication_start_on_boot=ON; \
SELECT * FROM performance_schema.replication_group_members; \
" && \
docker exec -i porta-db-3 mysql -uroot -pporta -e "START GROUP_REPLICATION; \
SET PERSIST group_replication_start_on_boot=ON; \
SELECT * FROM performance_schema.replication_group_members; \
"
```

## Check replication 
Select from the DB and see what has copied over


---

# Quick commands

## Find used ports in windows or linux
```
netstat -a -t
```

```
netstat -a -t -n | grep 3306
```

## traceroute / tracepath (linux)
```bash
tracepath hostname

tracepath ip
```

## View windows prefix policy table
Similar to a routing table: https://superuser.com/questions/436574/ipv4-vs-ipv6-priority-in-windows-7/436944#436944
```shell
netsh int ipv6 show prefixpolicies
```

## "ping" a port with telnet (linux only)
```bash
telnet 192.168.50.163 3306
```

## View DNS info (linux)
```bash
dig hostname
```

## Check global variable values 
`SHOW GLOBAL VARIABLES;` or `SHOW GLOBAL VARIABLES LIKE '%group_replication%';`

## Check group members
```mysql
SELECT * FROM performance_schema.replication_group_members;
```

## Check member status (on each server)
```mysql
 SELECT CHANNEL_NAME, GROUP_NAME, SOURCE_UUID, SERVICE_STATE, LAST_ERROR_NUMBER, LAST_ERROR_MESSAGE, LAST_ERROR_TIMESTAMP  FROM performance_schema.replication_connection_status;
```

### Primary
```mysql
SHOW MASTER STATUS\G
```

### Secondary
```mysql
SHOW REPLICA STATUS\G
```


## Check member stats
```mysql
SELECT * FROM performance_schema.replication_group_member_stats\G
```

## Check aborted connections
```mysql
SHOW GLOBAL STATUS LIKE 'Aborted_connects';
```

## ** TO AVOID REPLICATING A STATEMENT
Setting this system variable to OFF means that the transactions that occur from that point until you set it back to ON are not written to the binary log and do not have GTIDs assigned to them.
```mysql
SET SQL_LOG_BIN=0;
# <administrator action>
SET SQL_LOG_BIN=1;
```
