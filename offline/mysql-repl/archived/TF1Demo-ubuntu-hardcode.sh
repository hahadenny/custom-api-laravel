#!/bin/bash

################################################
## NODE 1
###################
# Removing container porta-db-3
# Wiping volume for porta-db-3
docker stop porta-db-3 && \
docker remove porta-db-3 && \
sudo rm -rf /tmp/porta-onprem/mysql-3 && \
docker run --name porta-db-3 --network=porta-net -e MYSQL_ROOT_PASSWORD=Porta123 -e MYSQL_ROOT_HOST=% -e MYSQL_DATABASE=porta \
        -v /tmp/porta-onprem/mysql-3:/var/lib/mysql \
        -p 64.49.71.20:3307:3307 -p 64.49.71.20:33063:33063 -d mysql/mysql-server \
        --character-set-server=utf8mb4 \
        --collation-server=utf8mb4_unicode_ci \
        --port='3307' \
        --report-host='64.49.71.20' \
        --report-port='3307' \
        --disabled-storage-engines='MyISAM,BLACKHOLE,FEDERATED,ARCHIVE,MEMORY' \
        --gtid-mode=ON \
        --enforce-gtid-consistency=ON \
        --master-info-repository=TABLE \
        --relay-log-info-repository=TABLE \
        --binlog-checksum=NONE \
        --log-replica-updates=ON \
        --log-bin=binlog \
        --binlog-format=ROW

# --hostname=porta-db-3 ??
# --hostname=64.49.71.20 ??

# crashes
#        --bind-address='64.49.71.20,172.28.48.1,127.0.0.1' \


# install plugin so vars work
#docker exec -i porta-db-3 mysql -h64.49.71.20 -P 3307 -uroot -pPorta123 -e "INSTALL PLUGIN group_replication SONAME 'group_replication.so';SHOW PLUGINS;"
docker exec -i porta-db-3 mysql -uroot  -pPorta123 -e "INSTALL PLUGIN group_replication SONAME 'group_replication.so'; \
SHOW PLUGINS; \
"

#        --protocol=tcp \


# set repl vars for porta-db-3
docker exec -i porta-db-3 mysql -uroot -pPorta123 -e \
"SET PERSIST group_replication_group_name='45317831-5ff4-4d0c-be89-172c5b7a77df'; \
SET PERSIST group_replication_local_address='porta-db-3:33063'; \
SET PERSIST group_replication_group_seeds='porta-db-1:33061,porta-db-2:33062,porta-db-3:33063,porta-db-4:33064'; \
SET PERSIST group_replication_ip_allowlist='AUTOMATIC'; \
SET PERSIST group_replication_start_on_boot='OFF'; \
SET PERSIST server_id=1; \
SET PERSIST group_replication_bootstrap_group=OFF;\
SET PERSIST group_replication_recovery_get_public_key=ON; \
"

#SET PERSIST group_replication_ip_allowlist='64.49.71.20,192.168.50.183,porta-db-3,porta-db-4,porta-db-3'; \


# Configure Replication Users and Enable Group Replication Plugin for porta-db-3
docker exec -i porta-db-3 mysql -uroot -pPorta123 -e "SET SQL_LOG_BIN=0; \
CREATE USER IF NOT EXISTS 'repl'@'%' IDENTIFIED BY 'password'; \
GRANT REPLICATION SLAVE ON *.* TO 'repl'@'%'; \
FLUSH PRIVILEGES; \
SET SQL_LOG_BIN=1; \
CHANGE REPLICATION SOURCE TO SOURCE_USER='repl', SOURCE_PASSWORD='password' FOR CHANNEL 'group_replication_recovery'; \
"

## !! NEED RELAY LOG? --relay-log=1601e9a2d010-relay-bin
## `[Warning] [MY-010604] [Repl] Neither --relay-log nor --relay-log-index were used; so replication may break when this MySQL server acts as a slave and has his hostname changed!! Please use '--relay-log=1601e9a2d010-relay-bin' to avoid this problem.`

################################################
## NODE 2
###################
# Removing container porta-db-4
# Wiping volume for porta-db-4
docker stop porta-db-4 && \
docker remove porta-db-4 && \
sudo rm -rf /tmp/porta-onprem/mysql-4 && \
docker run --name porta-db-4 --network=porta-net -e MYSQL_ROOT_PASSWORD=Porta123 -e MYSQL_ROOT_HOST=% -e MYSQL_DATABASE=porta \
        -v /tmp/porta-onprem/mysql-4:/var/lib/mysql \
        -p 64.49.71.20:3308:3308 -p 64.49.71.20:33064:33064 -d mysql/mysql-server \
        --character-set-server=utf8mb4 \
        --collation-server=utf8mb4_unicode_ci \
        --port='3308' \
        --report-host='64.49.71.20' \
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
docker exec -i porta-db-4 mysql -uroot -pPorta123 -e "INSTALL PLUGIN group_replication SONAME 'group_replication.so'; \
SHOW PLUGINS; \
"

# set repl vars for porta-db-4
docker exec -i porta-db-4 mysql -uroot -pPorta123 -e "SET PERSIST group_replication_group_name='45317831-5ff4-4d0c-be89-172c5b7a77df'; \
SET PERSIST group_replication_local_address='porta-db-4:33064'; \
SET PERSIST group_replication_group_seeds='porta-db-1:33061,porta-db-2:33062,porta-db-3:33063,porta-db-4:33064'; \
SET PERSIST group_replication_ip_allowlist='AUTOMATIC'; \
SET PERSIST group_replication_start_on_boot='OFF'; \
SET PERSIST server_id=2; \
SET PERSIST group_replication_bootstrap_group=OFF; \
SET PERSIST group_replication_recovery_get_public_key=ON; \
"

# Configure Replication Users and Enable Group Replication Plugin for porta-db-4
docker exec -i porta-db-4 mysql -uroot -pPorta123 -e "SET SQL_LOG_BIN=0; \
CREATE USER IF NOT EXISTS 'repl'@'%' IDENTIFIED BY 'password'; \
GRANT REPLICATION SLAVE ON *.* TO 'repl'@'%'; \
FLUSH PRIVILEGES; \
SET SQL_LOG_BIN=1; \
CHANGE REPLICATION SOURCE TO SOURCE_USER='repl', SOURCE_PASSWORD='password' FOR CHANNEL 'group_replication_recovery'; \
"

## START REPLICATION ON NODE 1
docker exec -i porta-db-3 mysql -uroot -pPorta123 -e "START GROUP_REPLICATION; \
SELECT * FROM performance_schema.replication_group_members; \
"

## CHECK REPLICATION ON NODE 1
docker exec -i porta-db-3 mysql -uroot -pPorta123 -e \
"SELECT * FROM porta.equipment; \
"

## START REPLICATION ON NODE 2
docker exec -i porta-db-4 mysql -uroot -pPorta123 -e "START GROUP_REPLICATION; \
SELECT * FROM performance_schema.replication_group_members; \
"

## CHECK REPLICATION ON NODE 2
docker exec -i porta-db-4 mysql -uroot -pPorta123 -e \
"SELECT * FROM porta.equipment; \
"

