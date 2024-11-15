#!/bin/bash

################################################
## NODE 1
###################
# Removing container porta-db-1
# Wiping volume for porta-db-1

docker stop porta-db-1 && \
docker remove porta-db-1 && \
sudo rm -rf /tmp/porta-onprem/mysql-1 && \
docker run --name porta-db-1 --network=porta-net -e MYSQL_ROOT_PASSWORD=Porta123 -e MYSQL_ROOT_HOST=% -e MYSQL_DATABASE=porta \
        -v /tmp/porta-onprem/mysql-1:/var/lib/mysql \
        -p 10.17.2.33:3307:3307 -p 10.17.2.33:33061:33061 -d mysql/mysql-server \
        --character-set-server=utf8mb4 \
        --collation-server=utf8mb4_unicode_ci \
        --port='3307' \
        --report-host='10.17.2.33' \
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

# --hostname=porta-db-1 ??
# --hostname=10.17.2.33 ??

# crashes
#        --bind-address='10.17.2.33,172.28.48.1,127.0.0.1' \


# install plugin so vars work
#docker exec -i porta-db-1 mysql -h10.17.2.33 -P 3307 -uroot -pPorta123 -e "INSTALL PLUGIN group_replication SONAME 'group_replication.so';SHOW PLUGINS;"
docker exec -i porta-db-1 mysql -uroot  -pPorta123 -e "INSTALL PLUGIN group_replication SONAME 'group_replication.so'; \
SHOW PLUGINS; \
"

#        --protocol=tcp \


# set repl vars for porta-db-1
docker exec -i porta-db-1 mysql -uroot -pPorta123 -e \
"SET PERSIST group_replication_group_name='45317831-5ff4-4d0c-be89-172c5b7a77df'; \
SET PERSIST group_replication_local_address='porta-db-1:33061'; \
SET PERSIST group_replication_group_seeds='porta-db-1:33061,porta-db-2:33062,porta-db-3:33063'; \
SET PERSIST group_replication_ip_allowlist='AUTOMATIC'; \
SET PERSIST group_replication_start_on_boot='OFF'; \
SET PERSIST server_id=1; \
SET PERSIST group_replication_bootstrap_group=OFF;\
SET PERSIST group_replication_recovery_get_public_key=ON; \
"

#SET PERSIST group_replication_ip_allowlist='10.17.2.33,192.168.50.183,porta-db-1,porta-db-2,porta-db-3'; \


# Configure Replication Users and Enable Group Replication Plugin for porta-db-1
docker exec -i porta-db-1 mysql -uroot -pPorta123 -e "SET SQL_LOG_BIN=0; \
CREATE USER IF NOT EXISTS 'repl'@'%' IDENTIFIED BY 'password'; \
GRANT REPLICATION SLAVE ON *.* TO 'repl'@'%'; \
FLUSH PRIVILEGES; \
SET SQL_LOG_BIN=1; \
CHANGE REPLICATION SOURCE TO SOURCE_USER='repl', SOURCE_PASSWORD='password' FOR CHANNEL 'group_replication_recovery'; \
"


#
### !! NEED RELAY LOG? --relay-log=1601e9a2d010-relay-bin
### `[Warning] [MY-010604] [Repl] Neither --relay-log nor --relay-log-index were used; so replication may break when this MySQL server acts as a slave and has his hostname changed!! Please use '--relay-log=1601e9a2d010-relay-bin' to avoid this problem.`
#
#################################################
### NODE 2
####################
## Removing container porta-db-2
## Wiping volume for porta-db-2
#docker stop porta-db-2 && \
#docker remove porta-db-2 && \
#sudo rm -rf /tmp/porta-onprem/mysql-2 && \
#docker run --name porta-db-2 --network=porta-net -e MYSQL_ROOT_PASSWORD=Porta123 -e MYSQL_ROOT_HOST=% -e MYSQL_DATABASE=porta \
#        -v /tmp/porta-onprem/mysql-2:/var/lib/mysql \
#        -p 10.17.2.33:3308:3308 -p 10.17.2.33:33062:33062 -d mysql/mysql-server \
#        --character-set-server=utf8mb4 \
#        --collation-server=utf8mb4_unicode_ci \
#        --port='3308' \
#        --report-host='10.17.2.33' \
#        --report-port='3308' \
#        --disabled-storage-engines='MyISAM,BLACKHOLE,FEDERATED,ARCHIVE,MEMORY' \
#        --gtid-mode=ON \
#        --enforce-gtid-consistency=ON \
#        --master-info-repository=TABLE \
#        --relay-log-info-repository=TABLE \
#        --binlog-checksum=NONE \
#        --log-replica-updates=ON \
#        --log-bin=binlog \
#        --binlog-format=ROW
#
## install plugin so vars work
#docker exec -i porta-db-2 mysql -uroot -pPorta123 -e "INSTALL PLUGIN group_replication SONAME 'group_replication.so'; \
#SHOW PLUGINS; \
#"
#
## set repl vars for porta-db-2
#docker exec -i porta-db-2 mysql -uroot -pPorta123 -e "SET PERSIST group_replication_group_name='45317831-5ff4-4d0c-be89-172c5b7a77df'; \
#SET PERSIST group_replication_local_address='porta-db-2:33062'; \
#SET PERSIST group_replication_group_seeds='porta-db-1:33061,porta-db-2:33062,porta-db-3:33063'; \
#SET PERSIST group_replication_ip_allowlist='AUTOMATIC'; \
#SET PERSIST group_replication_start_on_boot='OFF'; \
#SET PERSIST server_id=2; \
#SET PERSIST group_replication_bootstrap_group=OFF; \
#SET PERSIST group_replication_recovery_get_public_key=ON; \
#"
#
## Configure Replication Users and Enable Group Replication Plugin for porta-db-2
#docker exec -i porta-db-2 mysql -uroot -pPorta123 -e "SET SQL_LOG_BIN=0; \
#CREATE USER IF NOT EXISTS 'repl'@'%' IDENTIFIED BY 'password'; \
#GRANT REPLICATION SLAVE ON *.* TO 'repl'@'%'; \
#FLUSH PRIVILEGES; \
#SET SQL_LOG_BIN=1; \
#CHANGE REPLICATION SOURCE TO SOURCE_USER='repl', SOURCE_PASSWORD='password' FOR CHANNEL 'group_replication_recovery'; \
#"

################################################################################################
## ONLY RUN ON FIRST SERVER - porta-db-1
################################################################################################
# Bootstrap first node & start replication
docker exec -i porta-db-1 mysql -uroot -pPorta123 -e \
"SET PERSIST group_replication_bootstrap_group=ON; \
START GROUP_REPLICATION; \
SET PERSIST group_replication_bootstrap_group=OFF; \
SELECT * FROM performance_schema.replication_group_members;\
"


# CONNECT: mysql -h 10.17.2.33 -P 3307 --protocol=tcp -u root

# add test data
docker exec -i porta-db-1 mysql -uroot -pPorta123 -e "USE porta; \
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



docker exec -i porta-db-1 mysql -uroot -pPorta123 -e \
 "STOP GROUP_REPLICATION; \
 SELECT * FROM performance_schema.replication_group_members;\
 "


### START REPLICATION ON NODE 2
#docker exec -i porta-db-2 mysql -uroot -pPorta123 -e "START GROUP_REPLICATION; \
#SELECT * FROM performance_schema.replication_group_members; \
#"
#
### CHECK REPLICATION ON NODE 2
#docker exec -i porta-db-2 mysql -uroot -pPorta123 -e \
#"SELECT * FROM porta.equipment; \
#"
