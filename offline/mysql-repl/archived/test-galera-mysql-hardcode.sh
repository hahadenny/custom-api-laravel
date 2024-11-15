#!/bin/bash

docker stop galera1 && \
docker remove galera1 && \
sudo rm -rf /tmp/porta-onprem/galera1 && \
\
sudo mkdir -p /tmp/porta-onprem/galera1 && sudo chmod -R 777 /tmp/porta-onprem/galera1 && \
docker run --name galera1 --network=porta-net \
        -e MYSQL_ROOT_PASSWORD=Porta123 \
        -e MYSQL_ROOT_HOST=% \
        -e MYSQL_DATABASE=porta \
        -e BIND_ADDR=0.0.0.0 \
        -e WSREP_CLUSTER_NAME="nice_cluster" \
        -e WSREP_CLUSTER_ADDR="gcomm://" \
        -e WSREP_NODE_NAME="Galera-1" \
        -e WSREP_NODE_ADDR="192.168.50.163" \
        -e WSREP_SST_RECV_ADDR="192.168.50.163:4444" \
        -e WSREP_PROV_OPTS="ist.recv_addr=192.168.50.163:4568;base_host=192.168.50.163;" \
        -v /tmp/porta-onprem/galera1:/var/lib/mysql \
        -p 192.168.50.163:3306:3306 \
        -p 192.168.50.163:4567:4567 -p 192.168.50.163:4444:4444 -p 192.168.50.163:4568:4568 \
        -d portadisguise/porta-galera-db \
        --character-set-server=utf8mb4 \
        --collation-server=utf8mb4_unicode_ci\
        --user=root \
        --default-authentication-plugin=mysql_native_password


## SAME MACHINE
docker stop galera2 && \
docker remove galera2 && \
sudo rm -rf /tmp/porta-onprem/galera2 && \
\
sudo mkdir -p /tmp/porta-onprem/galera2 && sudo chmod -R 777 /tmp/porta-onprem/galera2 && \
docker run --name galera2 --network=porta-net \
        -e MYSQL_ROOT_PASSWORD=Porta123 \
        -e MYSQL_ROOT_HOST=% \
        -e MYSQL_DATABASE=porta \
        -e BIND_ADDR=0.0.0.0 \
        -e WSREP_CLUSTER_NAME="nice_cluster" \
        -e WSREP_CLUSTER_ADDR="gcomm://galera1" \
        -e WSREP_NODE_NAME="Galera-1" \
        -e WSREP_NODE_ADDR="192.168.50.163" \
        -e WSREP_SST_RECV_ADDR="192.168.50.163:5444" \
        -e WSREP_PROV_OPTS="ist.recv_addr=192.168.50.163:5568;base_host=192.168.50.163;base_port=4306;" \
        -v /tmp/porta-onprem/galera2:/var/lib/mysql \
        -p 192.168.50.163:4306:4306 \
        -p 192.168.50.163:5567:5567 -p 192.168.50.163:5444:5444 -p 192.168.50.163:5568:5568 \
        -d portadisguise/porta-galera-db \
        --character-set-server=utf8mb4 \
        --collation-server=utf8mb4_unicode_ci\
        --user=root \
        --default-authentication-plugin=mysql_native_password \
        --port=4306

