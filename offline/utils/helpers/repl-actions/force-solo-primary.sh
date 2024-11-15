#!/bin/bash

#######################################################################################
## Script to run to force this node to be the primary node and continue replication
#######################################################################################

## ALWAYS RUN FIRST ##
source "$(dirname "${BASH_SOURCE[0]}")/../lib/head.sh"

print_terminal_billboard "!! WARNING !! Running this script while other database nodes are healthy can result in data loss."

read -e -p "Are you sure you want to continue? (y/n): " -i "n" confirm

if [ "$confirm" == "y" ]; then
    print_terminal_message "Forcing this node to be the only group member and primary node..."
else
    print_terminal_message "Exiting without changes..."
    exit 1
fi


# NOTE: REMOVED TO AVOID DELAYING FAILOVER

## Create backup before forcing primary
#
## USING THE COMMAND CAUSES ERROR when the machine is not primary
##   --> must be able to specify connection to THIS current machine
##docker exec -it porta bash -c "php artisan backup:run --only-db --disable-notifications"
#
#DUMP_DIR="/var/www/manual_db_dumps"
#DB_FILE="${DUMP_DIR}/${MACHINE_TYPE}_machine_all_database_$(date +'%Y%m%d.%H%M%S.%z').sql.gz"
## mysqldump -h porta-db -P 3306 -uporta -pporta --no-tablespaces --all-databases | gzip > "/var/www/manual_db_dumps/main_machine_all_database_$(date +'%Y%m%d.%H%M%S.%z').sql.gz"
#
#print_terminal_message "Creating database backup in 'porta' container at '$DB_FILE'..."
#docker exec -it porta bash -c "mkdir -p $DUMP_DIR && mysqldump -h $DB_CONT_NAME -P $DB_CONT_PORT -uporta -pporta --no-tablespaces --all-databases | gzip > $DB_FILE"

docker exec -it porta bash -c "cp /var/www/.env /var/www/.env.bak && supervisorctl stop porta-task-worker:* && chmod ugo-w /var/www/.env"
docker exec -it porta bash -c "cp /var/www/.env /var/www/.env.bak && supervisorctl stop porta-task-worker:*"

# Force this node to be the primary node
docker exec -it "$DB_CONT_NAME" mysql -uporta -pporta -e "SET GLOBAL group_replication_force_members='$DB_CONT_NAME:$DB_REPL_PORT';"

# Clear the value of `group_replication_force_members` after the command completes
docker exec -it "$DB_CONT_NAME" mysql -uporta -pporta -e "SET GLOBAL group_replication_force_members='';"

docker exec -it porta bash -c "cp /var/www/.env.bak /var/www/.env && php artisan config:cache && supervisorctl start porta-task-worker:*"

## ALWAYS RUN LAST ##
source "$(dirname "${BASH_SOURCE[0]}")/../lib/foot.sh"
