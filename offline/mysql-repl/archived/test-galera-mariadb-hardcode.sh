#!/bin/bash

docker network create --driver bridge app-tier

################################################
## NODE 1
###################

docker stop mariadb-galera-1 && \
docker remove mariadb-galera-1 && \
sudo rm -rf /tmp/porta-onprem/galera1 && \
docker run -d --name mariadb-galera-1 --network app-tier \
    -e MARIADB_CHARACTER_SET=utf8mb4 \
    -e MARIADB_COLLATE=utf8_general_ci \
    -e MARIADB_DATABASE=porta \
    -e MARIADB_REPLICATION_USER=my_replication_user \
    -e MARIADB_REPLICATION_PASSWORD=my_replication_password \
    -e MARIADB_USER=my_user \
    -e MARIADB_PASSWORD=my_password \
    -e MARIADB_GALERA_CLUSTER_NAME=my_galera \
    -e MARIADB_GALERA_MARIABACKUP_USER=my_mariabackup_user \
    -e MARIADB_GALERA_MARIABACKUP_PASSWORD=my_mariabackup_password \
    -e MARIADB_ROOT_USER=porta \
    -e MARIADB_ROOT_PASSWORD=Porta123 \
    -e MARIADB_ROOT_HOST=% \
    -v /tmp/porta-onprem/galera1:/bitnami/mariadb \
    -p 3306:3306 \
    -p 4444:4444 \
    -p 4567:4567 \
    -p 4568:4568 \
    bitnami/mariadb-galera:latest

#    -e MARIADB_GALERA_CLUSTER_BOOTSTRAP=yes \


################################################
## NODE 2
###################

docker stop porta-db-2 && \
docker remove porta-db-2 && \
sudo rm -rf /tmp/porta-onprem/galera2 && \

docker run -d --name mariadb-galera2 \
  -e MARIADB_GALERA_CLUSTER_NAME=my_galera \
  -e MARIADB_GALERA_CLUSTER_ADDRESS=gcomm://mariadb-galera-1:4567,0.0.0.0:4567 \
    -e MARIADB_GALERA_MARIABACKUP_USER=my_mariabackup_user \
    -e MARIADB_GALERA_MARIABACKUP_PASSWORD=my_mariabackup_password \
  -e MARIADB_ROOT_PASSWORD=my_root_password \
  -e MARIADB_REPLICATION_USER=my_replication_user \
  -e MARIADB_REPLICATION_PASSWORD=my_replication_password \
  -v /tmp/porta-onprem/galera2:/bitnami/mariadb \
  -p 192.168.50.163:3307:3307 \
  -p 192.168.50.163:4444:4444 \
  -p 192.168.50.163:4567:4567 \
  -p 192.168.50.163:4568:4568 \
  bitnami/mariadb-galera:latest \
  --character-set-server=utf8mb4 \
  --collation-server=utf8mb4_unicode_ci \
  --port=3307 \

################################################
## NODE 2
###################
# Removing container porta-db-2
# Wiping volume for porta-db-2
docker stop porta-db-2 && \
docker remove porta-db-2 && \
sudo rm -rf /tmp/porta-onprem/mysql-2 && \
docker run --name porta-db-2 --network=porta-net -e MYSQL_ROOT_PASSWORD=Porta123 -e MYSQL_ROOT_HOST=% -e MYSQL_DATABASE=porta \
        -v /tmp/porta-onprem/mysql-2:/bitnami/mariadb \
        -p 192.168.50.163:3308:3308 -p 192.168.50.163:33062:33062 -d mysql/mysql-server \
        --character-set-server=utf8mb4 \
        --collation-server=utf8mb4_unicode_ci \
        --port='3308' \
        --report-host='192.168.50.163' \
        --report-port='3308' \
        --disabled-storage-engines='MyISAM,BLACKHOLE,FEDERATED,ARCHIVE,MEMORY' \
        --gtid-mode=ON \
        --enforce-gtid-consistency=ON \
        --master-info-repository=TABLE \
        --relay-log-info-repository=TABLE \
        --binlog-checksum=NONE \
        --log-replica-updates=ON \
        --log-bin=binlog \
        --binlog-format=ROW

# install plugin so vars work
docker exec -i porta-db-2 mysql -uroot -pPorta123 -e "INSTALL PLUGIN group_replication SONAME 'group_replication.so'; \
SHOW PLUGINS; \
"

# set repl vars for porta-db-2
docker exec -i porta-db-2 mysql -uroot -pPorta123 -e "SET PERSIST group_replication_group_name='45317831-5ff4-4d0c-be89-172c5b7a77df'; \
SET PERSIST group_replication_local_address='porta-db-2:33062'; \
SET PERSIST group_replication_group_seeds='galera1:33061,porta-db-2:33062,porta-db-3:33063'; \
SET PERSIST group_replication_ip_allowlist='AUTOMATIC'; \
SET PERSIST group_replication_start_on_boot='OFF'; \
SET PERSIST server_id=2; \
SET PERSIST group_replication_bootstrap_group=OFF; \
SET PERSIST group_replication_recovery_get_public_key=ON; \
SET PERSIST group_replication_single_primary_mode=OFF; \
SET PERSIST group_replication_enforce_update_everywhere_checks=ON; \
"

# Configure Replication Users and Enable Group Replication Plugin for porta-db-2
docker exec -i porta-db-2 mysql -uroot -pPorta123 -e "SET SQL_LOG_BIN=0; \
CREATE USER IF NOT EXISTS 'repl'@'%' IDENTIFIED BY 'password'; \
GRANT REPLICATION SLAVE ON *.* TO 'repl'@'%'; \
FLUSH PRIVILEGES; \
SET SQL_LOG_BIN=1; \
CHANGE REPLICATION SOURCE TO SOURCE_USER='repl', SOURCE_PASSWORD='password' FOR CHANNEL 'group_replication_recovery'; \
"

################################################
## NODE 3
###################
# Removing container porta-db-3
# Wiping volume for porta-db-3
docker stop porta-db-3 && \
docker remove porta-db-3 && \
sudo rm -rf /tmp/porta-onprem/mysql-3 && \
docker run --name porta-db-3 --network=porta-net -e MYSQL_ROOT_PASSWORD=Porta123 -e MYSQL_ROOT_HOST=% -e MYSQL_DATABASE=porta \
        -v /tmp/porta-onprem/mysql-3:/bitnami/mariadb \
        -p 192.168.50.163:3309:3309 -p 192.168.50.163:33063:33063 -d mysql/mysql-server \
        --character-set-server=utf8mb4 \
        --collation-server=utf8mb4_unicode_ci \
        --port='3309' \
        --report-host='192.168.50.163' \
        --report-port='3309' \
        --disabled-storage-engines='MyISAM,BLACKHOLE,FEDERATED,ARCHIVE,MEMORY' \
        --gtid-mode=ON \
        --enforce-gtid-consistency=ON \
        --master-info-repository=TABLE \
        --relay-log-info-repository=TABLE \
        --binlog-checksum=NONE \
        --log-replica-updates=ON \
        --log-bin=binlog \
        --binlog-format=ROW

# install plugin so vars work
docker exec -i porta-db-3 mysql -uroot -pPorta123 -e "INSTALL PLUGIN group_replication SONAME 'group_replication.so'; \
SHOW PLUGINS;"

# set repl vars for porta-db-3
docker exec -i porta-db-3 mysql -uroot -pPorta123 -e "SET PERSIST group_replication_group_name='45317831-5ff4-4d0c-be89-172c5b7a77df'; \
SET PERSIST group_replication_local_address='porta-db-3:33063'; \
SET PERSIST group_replication_group_seeds='galera1:33061,porta-db-2:33062,porta-db-3:33063'; \
SET PERSIST group_replication_ip_allowlist='AUTOMATIC'; \
SET PERSIST group_replication_start_on_boot='OFF'; \
SET PERSIST server_id=3; \
SET PERSIST group_replication_bootstrap_group=OFF; \
SET PERSIST group_replication_recovery_get_public_key=ON; \
SET PERSIST group_replication_single_primary_mode=OFF; \
SET PERSIST group_replication_enforce_update_everywhere_checks=ON; \
"

# Configure Replication Users and Enable Group Replication Plugin for porta-db-3
docker exec -i porta-db-3 mysql -uroot -pPorta123 -e "SET SQL_LOG_BIN=0; \
CREATE USER IF NOT EXISTS 'repl'@'%' IDENTIFIED BY 'password'; \
GRANT REPLICATION SLAVE ON *.* TO 'repl'@'%'; \
FLUSH PRIVILEGES; \
SET SQL_LOG_BIN=1; \
CHANGE REPLICATION SOURCE TO SOURCE_USER='repl', SOURCE_PASSWORD='password' FOR CHANNEL 'group_replication_recovery'; \
"


################################################################################################
## ONLY RUN ON FIRST SERVER - galera1
################################################################################################
# Bootstrap first node & start replication
docker exec -i galera1 mysql -uroot -pPorta123 -e \
"SET PERSIST group_replication_bootstrap_group=ON; \
START GROUP_REPLICATION; \
SET PERSIST group_replication_bootstrap_group=OFF; \
SELECT * FROM performance_schema.replication_group_members;\
"


# CONNECT: mysql -h 192.168.50.163 -P 3307 --protocol=tcp -u root

# add test data
docker exec -i galera1 mysql -uroot -pPorta123 -e "USE porta; \
 CREATE TABLE equipment ( \
 id INT NOT NULL AUTO_INCREMENT, \
 type VARCHAR(50), \
 quant INT, \
 color VARCHAR(25), \
 PRIMARY KEY(id) \
 ); \
 INSERT INTO equipment (type, quant, color) VALUES ('slide', 2, 'blue'); \
 SELECT * FROM equipment; \
 "


## START REPLICATION ON NODE 2
docker exec -i porta-db-2 mysql -uroot -pPorta123 -e "START GROUP_REPLICATION; \
SELECT * FROM performance_schema.replication_group_members; \
"

## CHECK REPLICATION ON NODE 2
docker exec -i porta-db-2 mysql -uroot -pPorta123 -e \
"SELECT * FROM porta.equipment; \
"


## START REPLICATION ON NODE 3
docker exec -i porta-db-3 mysql -uroot -pPorta123 -e "START GROUP_REPLICATION; \
SELECT * FROM performance_schema.replication_group_members; \
"

## CHECK PRIMARY FOR NODE 2
docker exec -i porta-db-2 mysql -uroot -pPorta123 -e "USE porta; \
INSERT INTO equipment (type, quant, color) VALUES ('swings', 4, 'red'); \
SELECT * FROM equipment; \
"

## CHECK PRIMARY FOR NODE 3
docker exec -i porta-db-3 mysql -uroot -pPorta123 -e "USE porta; \
INSERT INTO equipment (type, quant, color) VALUES ('monkey bars', 10, 'yellow'); \
SELECT * FROM equipment; \
"

## CHECK REPLICATION ON NODE 3
docker exec -i porta-db-3 mysql -uroot -pPorta123 -e \
"SELECT * FROM porta.equipment; \
"


## CHECK REPLICATION ON NODE 2
docker exec -i porta-db-2 mysql -uroot -pPorta123 -e \
"SELECT * FROM porta.equipment; \
"

## CHECK REPLICATION NODE 1
docker exec -i galera1 mysql -uroot -pPorta123 -e \
"SELECT * FROM porta.equipment; \
"





##############################################################################
## NOTES
##############################################################################

## REPL LOCAL ADDRESS
# group replication local address must be different to the host name and port used for SQL client connections.
# only be used for internal communication between the members of the group.

# if each server instance is on a different machine with a fixed network address, you could use the IP address of the machine.

# If you use a host name, you must use a fully qualified name, and ensure it is resolvable through DNS, correctly configured `/etc/hosts` files, or other name resolution processes
# you can use the same host name or IP address for all members as long as the ports are all different

####################
### EXAMPLE
## Group Replication uses this address for internal member-to-member connections
## involving remote instances of the group communication engine.
# group_replication_local_address= "s1:33061"


## JOINING GROUP
# The connection that an existing member offers to a joining member is NOT the network address configured by `group_replication_local_address`
# It is the address as specified by MySQL Server's `hostname` and `port`

# If multiple group members externalize a default host name set by the operating system, there is a chance of the joining member not resolving it to the correct member address and not being able to connect for distributed recovery.
# In this situation you can use MySQL Server's `report_host` system variable to configure a unique host name to be externalized by each of the servers


## GROUP SEEDS
# The `hostname`:`port` listed in `group_replication_group_seeds` is the seed member's internal network address, configured by `group_replication_local_address` and not the `hostname`:`port` used for SQL client connections

####################
### EXAMPLE
## the `hostname`:`port` of each of the group member's `group_replication_local_address`
# group_replication_group_seeds= "s1:33061,s2:33061,s3:33061"


## IP ALLOWLIST
# The allowlist must contain the IP address or host name that is specified in each member's `group_replication_local_address`
# This address is NOT the same as the MySQL server SQL protocol `host` and `port`,
# and is not specified in the `bind_address` system variable for the server instance.

# ** The automatic allowlist of private addresses CANNOT be used for connections from servers outside the private network **

# !! For Group Replication **connections between server instances that are on different machines, you must provide public IP addresses and specify these as an explicit allowlist**. If you specify any entries for the allowlist, **the private and localhost addresses are not added automatically**, so if you use any of these, you must specify them explicitly.

# You can also implement name resolution locally using the hosts file, to avoid the use of external components.

####################
### EXAMPLE
##- A comma must separate each entry in the allowlist
##- specify the same allowlist for all servers that are members of the replication group
# SET GLOBAL group_replication_ip_allowlist="192.0.2.21/24,198.51.100.44,203.0.113.0/24,2001:db8:85a3:8d3:1319:8a2e:370:7348,example.org,www.example.com/24";

########################################################################################################

# use defaults instead
#        --log-bin=ON #binlog
#        --binlog-format=ROW
#        --log-replica-updates=ON

# not specified by docs
#        --binlog-checksum=NONE \
#        --relay-log-info-repository=TABLE \
#        --master-info-repository=TABLE \


########################################

# CONNECT: mysql -h 192.168.50.163 -P 3307 --protocol=tcp -u root

## docker opts
# --hostname=galera1 ??
# --hostname=192.168.50.163 ??
## mysql opts
# --protocol=tcp ??

# crashes
#        --bind-address='192.168.50.163,172.28.48.1,127.0.0.1' \

# docker exec -i galera1 mysql -h192.168.50.163 -P 3307 -uroot -pPorta123 -e "INSTALL PLUGIN group_replication SONAME 'group_replication.so';SHOW PLUGINS;"
########################################
