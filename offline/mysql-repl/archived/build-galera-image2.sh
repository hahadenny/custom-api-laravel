#!/bin/bash

## REMOTE BACKUP MACHINE

docker build --no-cache -t portadisguise/porta-galera-db --progress=plain -f ./offline/build/docker/Dockerfile-mysql .

## !! NEEDS THE EXISTING INITIALIZED VOLUME FROM RUNNING THE BASE IMAGE
docker stop galera2 && \
docker remove galera2 && \
docker run --name galera2 --network=porta-net \
        -e WSREP_NEW_CLUSTER=0 \
        -e MYSQL_ROOT_PASSWORD=Porta123 \
        -e MYSQL_ROOT_HOST=% \
        -e MYSQL_DATABASE=porta \
        -e WSREP_CLUSTER_NAME="nice_cluster" \
        -e WSREP_CLUSTER_ADDR="gcomm://galera1" \
        -e WSREP_NODE_NAME="Galera-2" \
        -e WSREP_NODE_ADDR="192.168.50.20" \
        -e WSREP_SST_RECV_ADDR="192.168.50.20:4444" \
        -e WSREP_PROV_OPTS="ist.recv_addr=192.168.50.20:4568;base_host=192.168.50.20;debug=yes" \
        -v /tmp/porta-onprem/galera2:/var/lib/mysql \
        -p 192.168.50.20:3306:3306 \
        -p 192.168.50.20:4567:4567 -p 192.168.50.20:4444:4444 -p 192.168.50.20:4568:4568 \
        -d portadisguise/porta-galera-db \
        --character-set-server=utf8mb4 \
        --collation-server=utf8mb4_unicode_ci\
        --user=root \
        --default-authentication-plugin=mysql_native_password
