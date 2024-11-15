#!/bin/bash

# @see: https://dev.mysql.com/blog-archive/setting-up-mysql-group-replication-with-mysql-docker-images/

# create docker network
docker network create groupnet

# run 3 databases in multi primary mode
for N in 1 2 3
do docker run -d --name=node$N --net=groupnet --hostname=node$N \
  -v $PWD/d$N:/var/lib/mysql -e MYSQL_ROOT_PASSWORD=porta \
  mysql/mysql-server \
  --datadir='/var/lib/mysql' \
  --gtid-mode='ON' \
  --enforce-gtid-consistency='ON' \
  --master-info-repository='TABLE' \
  --relay-log-info-repository='TABLE' \
  --binlog-checksum='NONE' \
  --log-slave-updates='ON' \
  --log-bin='binlog' \
  --transaction-write-set-extraction='XXHASH64' \
  --group-replication-start-on-boot='OFF' \
  --group-replication-group-name='aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee' \
  --group-replication-group-seeds='node1:33061,node2:33061,node3:33061' \
  --loose-group-replication-single-primary-mode='OFF' \
  --loose-group-replication-enforce-update-everywhere-checks='ON' \
  --server-id=$N \
  --group-replication-local-address="node$N:33061" \
  --plugin-load='group_replication.so' \
  --relay-log-recovery='ON'
#  --binlog-format='ROW' \
#  --report-host='192.168.50.163' \
#  --loose-group-replication-ip-whitelist='192.168.50.163, 192.168.50.183'
#  --bind-address='192.168.50.163' \
done

# bootstrap the group with node1
docker exec -it node1 mysql -pmypass \
  -e "SET @@GLOBAL.group_replication_bootstrap_group=1;" \
  -e "CREATE USER 'repl'@'%' IDENTIFIED BY 'password';" \
  -e "GRANT REPLICATION SLAVE ON *.* TO repl@'%';" \
  -e "flush privileges;" \
  -e "change master to master_user='repl' for channel 'group_replication_recovery';" \
  -e "START GROUP_REPLICATION;" \
  -e "SET @@GLOBAL.group_replication_bootstrap_group=0;" \
  -e "SELECT * FROM performance_schema.replication_group_members;"

# set for other nodes
for N in 2 3
do docker exec -it node$N mysql -uroot -pmypass \
  -e "change master to master_user='repl' for channel 'group_replication_recovery';" \
  -e "START GROUP_REPLICATION;"
done

# verify
docker exec -it node1 mysql -uroot -pmypass \
  -e "SELECT * FROM performance_schema.replication_group_members;"
