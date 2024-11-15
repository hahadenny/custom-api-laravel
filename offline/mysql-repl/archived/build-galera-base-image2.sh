#!/bin/bash

docker build -t portadisguise/porta-galera-db-base --progress=plain -f ./offline/build/docker/Dockerfile-mysql-base . \
&& \
docker stop galera2 && \
docker remove galera2 && \
sudo rm -rf /tmp/porta-onprem/galera2 && \
\
sudo mkdir -p /tmp/porta-onprem/galera2 && sudo chmod -R 777 /tmp/porta-onprem/galera2 && \
docker run --name galera2 --network=porta-net \
        -e MYSQL_ROOT_PASSWORD=Porta123 \
        -e MYSQL_ROOT_HOST=% \
        -e MYSQL_DATABASE=porta \
        -v /tmp/porta-onprem/galera2:/var/lib/mysql \
        -d portadisguise/porta-galera-db-base \
        --character-set-server=utf8mb4 \
        --collation-server=utf8mb4_unicode_ci\
        --user=root \
        --default-authentication-plugin=mysql_native_password


# run shutdown here because it doesn't work in entrypoint.sh
# running it as && concat to the above command does not work because it runs before the server is ready
    # --> could maybe check `docker logs galera2` for `mysqld: ready for connections`
docker exec -it galera2 mysqladmin -pPorta123 shutdown && docker stop galera2 \
&& docker commit galera2 portadisguise/porta-galera-db-base-initialized
