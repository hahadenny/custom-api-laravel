#!/bin/bash

## MAIN MACHINE

### FIRST BOOT

docker stop galera1 && \
docker remove galera1 && \
sudo rm -rf /tmp/porta-onprem/galera1 && \
\
sudo mkdir -p /tmp/porta-onprem/galera1 && sudo chmod -R 777 /tmp/porta-onprem/galera1 && \
docker run --name galera1 --network=porta-net \
        -e WSREP_ON=OFF \
        -e MYSQL_ROOT_PASSWORD=Porta123 \
        -e MYSQL_ROOT_HOST=% \
        -e MYSQL_DATABASE=porta \
        -e BIND_ADDR=0.0.0.0 \
        -v /tmp/porta-onprem/galera1:/var/lib/mysql \
        -p 192.168.50.163:3306:3306 \
        -p 192.168.50.163:4567:4567 -p 192.168.50.163:4444:4444 -p 192.168.50.163:4568:4568 \
        -d portadisguise/porta-galera-db \
        --character-set-server=utf8mb4 \
        --collation-server=utf8mb4_unicode_ci\
        --user=root \
        --default-authentication-plugin=mysql_native_password


# run shutdown here because it doesn't work in entrypoint.sh
# running it as && concat to the above command does not work because it runs before the server is ready
    # --> could maybe check `docker logs galera1` for `mysqld: ready for connections`
docker exec -it galera1 mysqladmin -pPorta123 shutdown && docker stop galera1


### GALERA BOOT
docker run --name galera1 --network=porta-net \
        -e WSREP_ON=ON \
        -e WSREP_NEW_CLUSTER=1 \
        -e MYSQL_ROOT_PASSWORD=Porta123 \
        -e MYSQL_ROOT_HOST=% \
        -e MYSQL_DATABASE=porta \
        -e BIND_ADDR=0.0.0.0 \
        -e WSREP_CLUSTER_NAME="nice_cluster" \
        -e WSREP_CLUSTER_ADDR="gcomm://" \
        -e WSREP_NODE_NAME="Galera-1" \
        -e WSREP_NODE_ADDR="192.168.50.163" \
        -e WSREP_SST_RECV_ADDR="192.168.50.163:4444" \
        -e WSREP_PROV_OPTS="ist.recv_addr=192.168.50.163:4568;base_host=192.168.50.163;debug=yes" \
        -v /tmp/porta-onprem/galera1:/var/lib/mysql \
        -p 192.168.50.163:3306:3306 \
        -p 192.168.50.163:4567:4567 -p 192.168.50.163:4444:4444 -p 192.168.50.163:4568:4568 \
        -d portadisguise/porta-galera-db \
        --character-set-server=utf8mb4 \
        --collation-server=utf8mb4_unicode_ci\
        --user=root \
        --default-authentication-plugin=mysql_native_password




## REMOTE BACKUP MACHINE
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

