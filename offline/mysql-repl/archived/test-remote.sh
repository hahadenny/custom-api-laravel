#!/bin/bash

## VARS ##
source "$(dirname "${BASH_SOURCE[0]}")/../utils/vars.sh"

## FUNCTIONS ###
source "$(dirname "${BASH_SOURCE[0]}")/../utils/functions.sh"

################################################################

# Set up the custom error handler to run when errexit occurs
trap custom_error_handler ERR

# Enable errexit (exit on error)
set -e

# generate UUID to ID the group
# The uuidgen program creates (and prints) a new universally unique identifier (UUID) using the libuuid(3) library.
# The new UUID can reasonably be considered unique among all UUIDs created on the local system, and among UUIDs
# created on other systems in the past and in the future
#GROUP_UUID=$(uuidgen)
GROUP_UUID="45317831-5ff4-4d0c-be89-172c5b7a77df"
MAIN_MACHINE_IP="192.168.50.163"
#BACKUP_MACHINE_IP="192.168.50.183"
BACKUP_MACHINE_IP="192.168.50.20"
NODE_1_PORT="3307"
NODE_2_PORT="3308"
NODE_3_PORT="3309"
NODE_4_PORT="3310"
NODE_1_REPL_PORT="33061"
NODE_2_REPL_PORT="33062"
NODE_3_REPL_PORT="33063"
NODE_4_REPL_PORT="33064"
DATA_DIR="/tmp/porta-onprem/mysql-"

print_terminal_message "Group UUID: $GROUP_UUID \n \n MAIN_MACHINE_IP: $MAIN_MACHINE_IP \n BACKUP_MACHINE_IP: $BACKUP_MACHINE_IP \n \n NODE_1_PORT: $NODE_1_PORT \n NODE_2_PORT: $NODE_2_PORT \n NODE_3_PORT: $NODE_3_PORT";


for N in 1 2; do
    NODE_PORT_VAR="NODE_${N}_PORT"
    NODE_PORT="${!NODE_PORT_VAR}"
    NODE_REPL_PORT_VAR="NODE_${N}_REPL_PORT"
    NODE_REPL_PORT="${!NODE_REPL_PORT_VAR}"
    CONTAINER_NAME="porta-db-$N"
    DATA_DIR="/tmp/porta-onprem/mysql-"

    CREATE_CMD="docker create --name $CONTAINER_NAME --network=porta-net \
                    -e MYSQL_ROOT_PASSWORD=Porta123 -e MYSQL_ROOT_HOST=% -e MYSQL_DATABASE=porta \
                    -v $DATA_DIR$N:/var/lib/mysql \
                    -p $MAIN_MACHINE_IP:$NODE_PORT:$NODE_PORT -p $MAIN_MACHINE_IP:$NODE_REPL_PORT:$NODE_REPL_PORT \
                    mysql/mysql-server \
                    --character-set-server=utf8mb4 \
                    --collation-server=utf8mb4_unicode_ci \
                    --port=$NODE_PORT \
                    --report-host=$MAIN_MACHINE_IP \
                    --report-port=$NODE_PORT \
                    --disabled-storage-engines='MyISAM,BLACKHOLE,FEDERATED,ARCHIVE,MEMORY' \
                    --gtid-mode=ON \
                    --enforce-gtid-consistency=ON \
                    --master-info-repository=TABLE \
                    --relay-log-info-repository=TABLE \
                    --binlog-checksum=NONE \
                    --log-replica-updates=ON \
                    --log-bin=binlog \
                    --binlog-format=ROW"


    stop_and_delete_container "$CONTAINER_NAME"
    print_terminal_message "Wiping container '$CONTAINER_NAME'"
    sudo rm -rf "$DATA_DIR$N"

    print_terminal_message "Creating and starting container for '$CONTAINER_NAME' on host '$MAIN_MACHINE_IP' port '$NODE_PORT' replication port: $NODE_REPL_PORT... \n \n \n MYSQL_ROOT_PASSWORD: Porta123 \n MYSQL_ROOT_HOST: % \n MYSQL_DATABASE: porta \n MYSQL_USER: porta \n MYSQL_PASSWORD: porta \n\n Volume: /tmp/porta-onprem/mysql-$N:/var/lib/mysql"
    create_container "$CONTAINER_NAME" "$CREATE_CMD"
    start_container "$CONTAINER_NAME"

    wait_for_container_ready "$CONTAINER_NAME"

    # Configure Replication Users and Enable Group Replication Plugin
    print_terminal_message "Installing group_replication plugin for '$CONTAINER_NAME'..."
    docker exec -i "$CONTAINER_NAME" mysql -uroot -pPorta123 -e "INSTALL PLUGIN group_replication SONAME 'group_replication.so'; \
            SHOW PLUGINS; "
#    docker exec -i "$CONTAINER_NAME" mysql -uroot -pPorta123 -e \
#        "SELECT COUNT(*) INTO @plugin_exists FROM mysql.plugin WHERE name = 'group_replication'; \
#        -- If the plugin does not exist, install it \
#        IF @plugin_exists = 0 THEN \
#            INSTALL PLUGIN group_replication SONAME 'group_replication.so'; \
#            SELECT 'Plugin installed.'; \
#        ELSE \
#            SELECT 'Plugin already exists.'; \
#        END IF; \
#        SHOW PLUGINS; "

    print_terminal_message "Configuring group_replication plugin for '$CONTAINER_NAME'..."
    docker exec -i "$CONTAINER_NAME" mysql -uroot -pPorta123 -e \
        "SET PERSIST group_replication_group_name='$GROUP_UUID'; \
        SET PERSIST group_replication_local_address='$CONTAINER_NAME:$NODE_REPL_PORT'; \
        SET PERSIST group_replication_group_seeds='$MAIN_MACHINE_IP:$NODE_1_REPL_PORT,$MAIN_MACHINE_IP:$NODE_2_REPL_PORT,$MAIN_MACHINE_IP:$NODE_3_REPL_PORT'; \
        SET PERSIST group_replication_ip_allowlist='AUTOMATIC'; \
        SET PERSIST group_replication_start_on_boot='OFF'; \
        SET PERSIST server_id=$N; \
        SET PERSIST group_replication_bootstrap_group=OFF;\
        SET PERSIST group_replication_recovery_get_public_key=ON;"

        echo -e "SET PERSIST group_replication_group_name='$GROUP_UUID'; \n  SET PERSIST group_replication_local_address='$CONTAINER_NAME:$NODE_REPL_PORT'; \n SET PERSIST group_replication_group_seeds='$MAIN_MACHINE_IP:$NODE_1_REPL_PORT, $MAIN_MACHINE_IP:$NODE_2_REPL_PORT, $MAIN_MACHINE_IP:$NODE_3_REPL_PORT'; \n SET PERSIST group_replication_ip_allowlist='AUTOMATIC'; \n SET PERSIST group_replication_start_on_boot='OFF'; \n SET PERSIST server_id=$N; \n SET PERSIST group_replication_bootstrap_group=OFF; \n SET PERSIST group_replication_recovery_get_public_key=ON;"

        # SET PERSIST group_replication_ip_allowlist='$MAIN_MACHINE_IP,$BACKUP_MACHINE_IP,localhost,127.0.0.1,porta-db-1,porta-db-2,porta-db-3,porta-db-4'; \
        # SET PERSIST group_replication_group_seeds='$MAIN_MACHINE_IP:$NODE_1_REPL_PORT,$MAIN_MACHINE_IP:$NODE_2_REPL_PORT,$BACKUP_MACHINE_IP:$NODE_3_REPL_PORT,$BACKUP_MACHINE_IP:$NODE_4_REPL_PORT'; \

    print_terminal_message "Setting up group_replication user for '$CONTAINER_NAME'..."
    docker exec -i "$CONTAINER_NAME" mysql -uroot -pPorta123 -e \
        "SET SQL_LOG_BIN=0; \
        CREATE USER IF NOT EXISTS 'repl'@'%' IDENTIFIED BY 'password'; \
        GRANT REPLICATION SLAVE ON *.* TO 'repl'@'%'; \
        FLUSH PRIVILEGES; \
        SET SQL_LOG_BIN=1; \
        CHANGE REPLICATION SOURCE TO SOURCE_USER='repl', SOURCE_PASSWORD='password' FOR CHANNEL 'group_replication_recovery'; \
        "
done

################################################################################################
## ONLY RUN ON FIRST SERVER - porta-db-1
################################################################################################
# Bootstrap first node & start replication
print_terminal_message "Bootstrapping first member of the group..."
docker exec -i porta-db-1 mysql -uroot -pPorta123 -e \
"SET PERSIST group_replication_bootstrap_group=ON; \
START GROUP_REPLICATION; \
SET PERSIST group_replication_bootstrap_group=OFF; \
SELECT * FROM performance_schema.replication_group_members;\
"

# add test data
print_terminal_message "Adding test data to the first member..."
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
 print_terminal_message "Starting replication for node 2..."
 docker exec -i porta-db-2 mysql -uroot -pPorta123 -e "START GROUP_REPLICATION; \
 SELECT * FROM performance_schema.replication_group_members; \
 "

 ## CHECK REPLICATION ON NODE 2
 print_terminal_message "Checking replication for node 2..."
 docker exec -i porta-db-2 mysql -uroot -pPorta123 -e "SELECT * FROM porta.equipment;"


# ## START REPLICATION ON NODE 3
# print_terminal_message "Starting replication for node 3..."
# docker exec -i porta-db-3 mysql -uroot -pPorta123 -e "START GROUP_REPLICATION; \
# SELECT * FROM performance_schema.replication_group_members; \
# "
#
# ## CHECK REPLICATION ON NODE 3
# print_terminal_message "Checking replication for node 3..."
# docker exec -i porta-db-3 mysql -uroot -pPorta123 -e "SELECT * FROM porta.equipment;"
#
# ## START REPLICATION ON NODE 4
# print_terminal_message "Starting replication for node 4..."
# docker exec -i porta-db-4 mysql -uroot -pPorta123 -e "START GROUP_REPLICATION; \
# SELECT * FROM performance_schema.replication_group_members; \
# "
#
# ## CHECK REPLICATION ON NODE 4
# print_terminal_message "Checking replication for node 4..."
# docker exec -i porta-db-4 mysql -uroot -pPorta123 -e "SELECT * FROM porta.equipment;"

# Disable errexit (optional, if you want to continue with the script)
set +e
