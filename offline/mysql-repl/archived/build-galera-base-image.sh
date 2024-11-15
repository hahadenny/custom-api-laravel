#!/bin/bash

docker build -t portadisguise/porta-galera-db-base --progress=plain -f ./offline/build/docker/Dockerfile-mysql-base . \
&& \
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


# run shutdown here because it doesn't work in entrypoint.sh
# running it as && concat to the above command does not work because it runs before the server is ready
    # --> could maybe check `docker logs galera1` for `mysqld: ready for connections`
docker exec -it galera1 mysqladmin -pPorta123 shutdown && docker stop galera1 \
&& docker commit galera1 portadisguise/porta-galera-db-base-initialized
