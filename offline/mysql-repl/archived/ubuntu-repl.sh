#!/bin/bash

## VARS ##
source "$(dirname "${BASH_SOURCE[0]}")/../utils/vars.sh"

## FUNCTIONS ###
source "$(dirname "${BASH_SOURCE[0]}")/../utils/functions.sh"

################################################################

# generate UUID to ID the group
# The uuidgen program creates (and prints) a new universally unique identifier (UUID) using the libuuid(3) library.
# The new UUID can reasonably be considered unique among all UUIDs created on the local system, and among UUIDs
# created on other systems in the past and in the future
#GROUP_UUID=$(uuidgen)
GROUP_UUID="45317831-5ff4-4d0c-be89-172c5b7a77df"
echo "\n UUID: $GROUP_UUID \n";

# main container for this machine
docker create --name porta-db --network=porta-net -e MYSQL_ROOT_PASSWORD=porta -e MYSQL_DATABASE=porta -e MYSQL_USER=porta -e MYSQL_PASSWORD=porta \
-v /tmp/mysql:/var/lib/mysql \
-p 3306:3306 mysql/mysql-server \
--character-set-server=utf8mb4 \
--collation-server=utf8mb4_unicode_ci \
 --disabled-storage-engines='MyISAM,BLACKHOLE,FEDERATED,ARCHIVE,MEMORY' \
 --gtid-mode='ON' \
 --enforce-gtid-consistency='ON' \
 --master-info-repository='TABLE' \
 --relay-log-info-repository='TABLE' \
 --binlog-checksum='NONE' \
 --log-slave-updates='ON' \
 --log-bin='binlog' \
 --binlog-format='ROW' \
 --transaction-write-set-extraction='XXHASH64' \
 --loose-group-replication-bootstrap-group='OFF' \
 --loose-group-replication-start-on-boot='OFF' \
 --loose-group-replication-group-name='45317831-5ff4-4d0c-be89-172c5b7a77df' \
 --loose-group-replication-ip-whitelist='192.168.50.163, 192.168.50.183' \
 --loose-group-replication-group-seeds='192.168.50.163:3306, 192.168.50.183:3307, 192.168.50.183:3306, 192.168.50.183:3307' \
 --loose-group-replication-single-primary-mode='OFF' \
 --loose-group-replication-enforce-update-everywhere-checks='ON' \
 --server-id='1' \
 --report-host='192.168.50.163' \
 --loose-group-replication-local-address='192.168.50.163:3306'

 # this is crashing containers
# --bind-address='192.168.50.163' \

# backup container for this machine
docker create --name porta-db-backup --network=porta-net -e MYSQL_ROOT_PASSWORD=porta -e MYSQL_DATABASE=porta -e MYSQL_USER=porta -e MYSQL_PASSWORD=porta \
 -v /tmp/mysql:/var/lib/mysql \
 -p 3307:3307 mysql/mysql-server \
 --character-set-server=utf8mb4 \
 --collation-server=utf8mb4_unicode_ci \
 --disabled-storage-engines='MyISAM,BLACKHOLE,FEDERATED,ARCHIVE,MEMORY' \
 --gtid-mode='ON' \
 --enforce-gtid-consistency='ON' \
 --master-info-repository='TABLE' \
 --relay-log-info-repository='TABLE' \
 --binlog-checksum='NONE' \
 --log-slave-updates='ON' \
 --log-bin='binlog' \
 --binlog-format='ROW' \
 --transaction-write-set-extraction='XXHASH64' \
 --loose-group-replication-bootstrap-group='OFF' \
 --loose-group-replication-start-on-boot='OFF' \
 --loose-group-replication-group-name='45317831-5ff4-4d0c-be89-172c5b7a77df' \
 --loose-group-replication-ip-whitelist='192.168.50.163, 192.168.50.183' \
 --loose-group-replication-group-seeds='192.168.50.163:3306, 192.168.50.183:3307, 192.168.50.183:3306, 192.168.50.183:3307' \
 --loose-group-replication-single-primary-mode='OFF' \
 --loose-group-replication-enforce-update-everywhere-checks='ON' \
 --server-id='2' \
 --report-host='192.168.50.163' \
 --loose-group-replication-local-address='192.168.50.163:3307'
 # this is crashing containers
# --bind-address='192.168.50.163' \
