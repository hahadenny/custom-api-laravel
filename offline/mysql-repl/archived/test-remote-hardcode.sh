#!/bin/bash

# setup proxy container for swarm mode
netsh interface portproxy add v4tov4 listenport=2378 listenaddress=0.0.0.0 connectport=2378 connectaddress=<MAIN WSL IP>

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
        -p 192.168.50.163:3307:3307 -p 192.168.50.163:33061:33061 -d mysql/mysql-server \
        --character-set-server=utf8mb4 \
        --collation-server=utf8mb4_unicode_ci \
        --port='3307' \
        --report-host='192.168.50.163' \
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

docker exec -i porta-db-1 mysql -uroot  -pPorta123 -e "INSTALL PLUGIN group_replication SONAME 'group_replication.so'; \
SHOW PLUGINS; \
";

docker exec -i porta-db-1 mysql -uroot  -pPorta123 -e "
SET PERSIST group_replication_group_name='45317831-5ff4-4d0c-be89-172c5b7a77df'; \
SET PERSIST group_replication_local_address='127.0.0.1:33061'; \
SET PERSIST group_replication_group_seeds='192.168.50.163:33061,192.168.50.163:33062,192.168.50.163:33063'; \
SET PERSIST group_replication_ip_allowlist='AUTOMATIC'; \
SET PERSIST group_replication_start_on_boot='OFF'; \
SET PERSIST server_id=1; \
SET PERSIST group_replication_bootstrap_group=OFF;\
SET PERSIST group_replication_recovery_get_public_key=ON; \
SET SQL_LOG_BIN=0; \
CREATE USER IF NOT EXISTS 'repl'@'%' IDENTIFIED BY 'password'; \
GRANT REPLICATION SLAVE ON *.* TO 'repl'@'%'; \
FLUSH PRIVILEGES; \
SET SQL_LOG_BIN=1; \
CHANGE REPLICATION SOURCE TO SOURCE_USER='repl', SOURCE_PASSWORD='password' FOR CHANNEL 'group_replication_recovery'; \
"

#SET PERSIST group_replication_ip_allowlist='192.168.50.163,192.168.50.183,porta-db-1,porta-db-2,porta-db-3'; \


################################################
## NODE 2
###################
# Removing container porta-db-2
# Wiping volume for porta-db-2
docker stop porta-db-2 && \
docker remove porta-db-2 && \
sudo rm -rf /tmp/porta-onprem/mysql-2 && \
docker run --name porta-db-2 --network=porta-net -e MYSQL_ROOT_PASSWORD=Porta123 -e MYSQL_ROOT_HOST=% -e MYSQL_DATABASE=porta \
        -v /tmp/porta-onprem/mysql-2:/var/lib/mysql \
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

docker exec -i porta-db-2 mysql -uroot -pPorta123 -e "SET PERSIST group_replication_group_name='45317831-5ff4-4d0c-be89-172c5b7a77df'; \
SET PERSIST group_replication_group_seeds='192.168.50.163:33061,192.168.50.163:33062,192.168.50.163:33063'; \
SET PERSIST group_replication_ip_allowlist='AUTOMATIC'; \
SET PERSIST group_replication_start_on_boot='OFF'; \
SET PERSIST server_id=2; \
SET PERSIST group_replication_local_address='porta-db-2:33062'; \
SET PERSIST group_replication_bootstrap_group=OFF; \
SET PERSIST group_replication_recovery_get_public_key=ON; \
SET SQL_LOG_BIN=0; \
CREATE USER IF NOT EXISTS 'repl'@'%' IDENTIFIED BY 'password'; \
GRANT REPLICATION SLAVE ON *.* TO 'repl'@'%'; \
FLUSH PRIVILEGES; \
SET SQL_LOG_BIN=1; \
CHANGE REPLICATION SOURCE TO SOURCE_USER='repl', SOURCE_PASSWORD='password' FOR CHANNEL 'group_replication_recovery'; \
"


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


# CONNECT: mysql -h 192.168.50.163 -P 3307 --protocol=tcp -u root

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


## START REPLICATION ON NODE 2
docker exec -i porta-db-2 mysql -uroot -pPorta123 -e "START GROUP_REPLICATION; \
SELECT * FROM performance_schema.replication_group_members; \
"

## CHECK REPLICATION ON NODE 2
docker exec -i porta-db-2 mysql -uroot -pPorta123 -e \
"SELECT * FROM porta.equipment; \
"

