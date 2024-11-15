
# Build base image from Dockerfile-mysql-base
```bash
#docker build --no-cache -t portadisguise/porta-galera-db --progress=plain -f ./offline/build/docker/Dockerfile-mysql-base .
docker build -t portadisguise/porta-galera-db-base --progress=plain -f ./offline/build/docker/Dockerfile-mysql-base .
```

## Run the base image as container so it gets initialized 
```bash
docker stop galera1 && \
docker remove galera1 && \
sudo rm -rf /tmp/porta-onprem/galera1 && \
\
sudo mkdir -p /tmp/porta-onprem/galera1 && sudo chmod -R 777 /tmp/porta-onprem/galera1 && \
docker run --name galera1 --network=porta-net \
        -e MYSQL_ROOT_PASSWORD=Porta123 \
        -e MYSQL_ROOT_HOST=% \
        -e MYSQL_DATABASE=porta \
        -v /tmp/porta-onprem/galera1:/var/lib/mysql \
        -d portadisguise/porta-galera-db-base \
        --character-set-server=utf8mb4 \
        --collation-server=utf8mb4_unicode_ci\
        --user=root \
        --default-authentication-plugin=mysql_native_password
```


## Commit the changes to a new image
```bash
docker exec -it galera1 mysqladmin -pPorta123 shutdown && docker stop galera1 \
&& \
docker commit galera1 portadisguise/porta-galera-db-base-initialized
```

# Build image from Dockerfile-mysql
```bash
#docker build --no-cache -t portadisguise/porta-galera-db --progress=plain -f ./offline/build/docker/Dockerfile-mysql .
docker build -t portadisguise/porta-galera-db --progress=plain -f ./offline/build/docker/Dockerfile-mysql .
```

## Run container (first node)
```bash
docker stop galera1 && \
docker remove galera1 && \
docker run --name galera1 --network=porta-net \
        -e WSREP_NEW_CLUSTER=1 \
        -e MYSQL_ROOT_PASSWORD=Porta123 \
        -e MYSQL_ROOT_HOST=% \
        -e MYSQL_DATABASE=porta \
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
```

# chmod 777 on host volume datadir
This is to prevent errors initializing the server. Since mysql cannot be started as root and the host volume dir is created as root for some reason. 

For example, if docker command is   
`docker run --name galera1 --network=porta-net -v /tmp/porta-onprem/galera1:/var/lib/mysql`  

Then run:
```
sudo chmod -R 777 /tmp/porta-onprem/galera1
```

_The other option is to set up a consistent user id and group id that the container and host will both use_ 


# [Server] Failed to find valid data directory.
~~The mounted volume must be a subdir of /var/lib/mysql:~~
```bash
docker run --name galera1 --network=porta-net -v /tmp/porta-onprem/galera1:/var/lib/mysql/node-data
```

Initialize the data dir before startup (in entrypoint.sh or similar)
> https://dev.mysql.com/doc/refman/8.0/en/data-directory-initialization.html

Make sure the root password is set and permissions flushed during the init scripts
```
mysqld --initialize-insecure --init-file=/path/to/script.sh
mysqld --daemonize
```


# exec /entrypoint.sh: no such file or directory

Try running this script locally (from the porta-api dir)
```bash
chmod +x ./offline/build/docker/config/mysql-entrypoint.sh && ./offline/build/docker/config/mysql-entrypoint.sh
```

If the result is `-bash: ./offline/build/docker/config/mysql-entrypoint.sh: /bin/bash^M: bad interpreter: No such file or directory`, then the problem is actually that the file line endings are incorrect (CRLF instead of LF)

Close the files, `cd offline` in the `porta-api` dir, and run:
```bash
find . -type f -exec sed -i 's/\r$//' {} \;
```

Rebuild the image(s) and try running the container again.

## /usr//bin/wsrep_sst_rsync: line 127: ip: command not found
Add it to Dockerfile
```bash
# make sure `ip` command will work since SST method needs it
RUN apt-get install -y iproute2
```

## `The command 'docker' could not be found in this WSL 2 distro.` but docker is running
In the system tray, right-click the docker icon and choose restart. Then try the docker command again. Sometimes resource-saving mode prevents docker commands from registering in WSL

