#!/bin/bash


################################################
## NODE
###################

docker stop porta-db-2 && \
docker remove porta-db-2 && \
sudo rm -rf /tmp/porta-onprem/mysql-2 && \
docker run --name porta-db-2 --network=porta-net -e MYSQL_ROOT_PASSWORD=Porta123 -e MYSQL_ROOT_HOST=% -e MYSQL_DATABASE=porta \
        -v /tmp/porta-onprem/mysql-2:/var/lib/mysql \
        -p 192.168.50.183:3308:3308 -p 192.168.50.183:33062:33062 -d mysql/mysql-server \
        --character-set-server=utf8mb4 \
        --collation-server=utf8mb4_unicode_ci \
        --port='3308' \
        --report-host='192.168.50.183' \
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

docker exec -i porta-db-2 mysql -uroot -pPorta123 -e "INSTALL PLUGIN group_replication SONAME 'group_replication.so'; \
SHOW PLUGINS; \
"

docker exec -i porta-db-2 mysql -uroot -pPorta123 -e "SET PERSIST group_replication_group_name='45317831-5ff4-4d0c-be89-172c5b7a77df'; \
SET PERSIST group_replication_local_address='porta-db-2:33062'; \
SET PERSIST group_replication_group_seeds='porta-db-1:33061,porta-db-2:33062,porta-db-3:33063'; \
SET PERSIST group_replication_ip_allowlist='AUTOMATIC'; \
SET PERSIST group_replication_start_on_boot='OFF'; \
SET PERSIST server_id=2; \
SET PERSIST group_replication_bootstrap_group=OFF; \
SET PERSIST group_replication_recovery_get_public_key=ON; \
SET PERSIST group_replication_single_primary_mode=OFF; \
SET PERSIST group_replication_enforce_update_everywhere_checks=ON; \
"

docker exec -i porta-db-2 mysql -uroot -pPorta123 -e "SET SQL_LOG_BIN=0; \
CREATE USER IF NOT EXISTS 'repl'@'%' IDENTIFIED BY 'password'; \
GRANT REPLICATION SLAVE ON *.* TO 'repl'@'%'; \
FLUSH PRIVILEGES; \
SET SQL_LOG_BIN=1; \
CHANGE REPLICATION SOURCE TO SOURCE_USER='repl', SOURCE_PASSWORD='password' FOR CHANNEL 'group_replication_recovery'; \
"


################################################
## NODE
###################
docker stop porta-db-3 && \
docker remove porta-db-3 && \
sudo rm -rf /tmp/porta-onprem/mysql-3 && \
docker run --name porta-db-3 --network=porta-net -e MYSQL_ROOT_PASSWORD=Porta123 -e MYSQL_ROOT_HOST=% -e MYSQL_DATABASE=porta \
        -v /tmp/porta-onprem/mysql-3:/var/lib/mysql \
        -p 192.168.50.183:3309:3309 -p 192.168.50.183:33063:33063 -d mysql/mysql-server \
        --character-set-server=utf8mb4 \
        --collation-server=utf8mb4_unicode_ci \
        --port='3309' \
        --report-host='192.168.50.183' \
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

docker exec -i porta-db-3 mysql -uroot  -pPorta123 -e "INSTALL PLUGIN group_replication SONAME 'group_replication.so'; \
SHOW PLUGINS; \
"

docker exec -i porta-db-3 mysql -uroot -pPorta123 -e \
"SET PERSIST group_replication_group_name='45317831-5ff4-4d0c-be89-172c5b7a77df'; \
SET PERSIST group_replication_local_address='porta-db-3:33063'; \
SET PERSIST group_replication_group_seeds='porta-db-1:33061,porta-db-2:33062,porta-db-3:33063'; \
SET PERSIST group_replication_ip_allowlist='AUTOMATIC'; \
SET PERSIST group_replication_start_on_boot='OFF'; \
SET PERSIST server_id=1; \
SET PERSIST group_replication_bootstrap_group=OFF;\
SET PERSIST group_replication_recovery_get_public_key=ON; \
SET PERSIST group_replication_single_primary_mode=OFF; \
SET PERSIST group_replication_enforce_update_everywhere_checks=ON; \
"

docker exec -i porta-db-3 mysql -uroot -pPorta123 -e "SET SQL_LOG_BIN=0; \
CREATE USER IF NOT EXISTS 'repl'@'%' IDENTIFIED BY 'password'; \
GRANT REPLICATION SLAVE ON *.* TO 'repl'@'%'; \
FLUSH PRIVILEGES; \
SET SQL_LOG_BIN=1; \
CHANGE REPLICATION SOURCE TO SOURCE_USER='repl', SOURCE_PASSWORD='password' FOR CHANNEL 'group_replication_recovery'; \
"



#
#
#docker stop porta-db-4 && \
#docker remove porta-db-4 && \
#sudo rm -rf /tmp/porta-onprem/mysql-4 && \
#docker run --name porta-db-4 --network=porta-net -e MYSQL_ROOT_PASSWORD=Porta123 -e MYSQL_ROOT_HOST=% -e MYSQL_DATABASE=porta \
#        -v /tmp/porta-onprem/mysql-4:/var/lib/mysql \
#        -p 192.168.50.183:3310:3310 -p 192.168.50.183:33064:33064 -d mysql/mysql-server \
#        --character-set-server=utf8mb4 \
#        --collation-server=utf8mb4_unicode_ci \
#        --port='3310' \
#        --report-host='192.168.50.183' \
#        --report-port='3310' \
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
#docker exec -i porta-db-4 mysql -uroot -pPorta123 -e "INSTALL PLUGIN group_replication SONAME 'group_replication.so'; \
#SHOW PLUGINS; \
#"
#
#docker exec -i porta-db-4 mysql -uroot -pPorta123 -e "SET PERSIST group_replication_group_name='45317831-5ff4-4d0c-be89-172c5b7a77df'; \
#SET PERSIST group_replication_local_address='porta-db-4:33064'; \
#SET PERSIST group_replication_group_seeds='porta-db-1:33061,porta-db-2:33062,porta-db-3:33063'; \
#SET PERSIST group_replication_ip_allowlist='AUTOMATIC'; \
#SET PERSIST group_replication_start_on_boot='OFF'; \
#SET PERSIST server_id=2; \
#SET PERSIST group_replication_bootstrap_group=OFF; \
#SET PERSIST group_replication_recovery_get_public_key=ON; \
#"
#
#docker exec -i porta-db-4 mysql -uroot -pPorta123 -e "SET SQL_LOG_BIN=0; \
#CREATE USER IF NOT EXISTS 'repl'@'%' IDENTIFIED BY 'password'; \
#GRANT REPLICATION SLAVE ON *.* TO 'repl'@'%'; \
#FLUSH PRIVILEGES; \
#SET SQL_LOG_BIN=1; \
#CHANGE REPLICATION SOURCE TO SOURCE_USER='repl', SOURCE_PASSWORD='password' FOR CHANNEL 'group_replication_recovery'; \
#"




docker exec -i porta-db-2 mysql -uroot -pPorta123 -e "START GROUP_REPLICATION; \
SELECT * FROM performance_schema.replication_group_members; \
"

docker exec -i porta-db-2 mysql -uroot -pPorta123 -e \
"SELECT * FROM porta.equipment; \
"



docker exec -i porta-db-3 mysql -uroot -pPorta123 -e "START GROUP_REPLICATION; \
SELECT * FROM performance_schema.replication_group_members; \
"

docker exec -i porta-db-3 mysql -uroot -pPorta123 -e \
"SELECT * FROM porta.equipment; \
"



docker exec -i porta-db-2 mysql -uroot -pPorta123 -e \
"STOP GROUP_REPLICATION; \
SELECT * FROM performance_schema.replication_group_members;\
"

docker exec -i porta-db-3 mysql -uroot -pPorta123 -e \
"STOP GROUP_REPLICATION; \
SELECT * FROM performance_schema.replication_group_members;\
"



### START REPLICATION ON NODE 2
#docker exec -i porta-db-4 mysql -uroot -pPorta123 -e "START GROUP_REPLICATION; \
#SELECT * FROM performance_schema.replication_group_members; \
#"
#
### CHECK REPLICATION ON NODE 2
#docker exec -i porta-db-4 mysql -uroot -pPorta123 -e \
#"SELECT * FROM porta.equipment; \
#"

