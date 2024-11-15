#!/bin/bash

#########################################################################
## Script to run for manually creating a database backup
#########################################################################

## ALWAYS RUN FIRST ##
source "$(dirname "${BASH_SOURCE[0]}")/../lib/head.sh"

BACKUP_MACHINE=$(get_machine_ip "$CONF_FILES_PATH/$BACKUP_CONF_FILE" "backup");

# backup first
docker exec -it porta bash -c "php artisan backup:run --only-db --disable-notifications"

## transfer to machine
DB_SRC_HOST="$HOST_MACHINE"
DB_SRC_PORT=3306
DB_DEST_HOST="$BACKUP_MACHINE"
DB_DEST_PORT=3307

# check machine type to determine hosts & ports
if [ "$MACHINE_TYPE" == "main" ]; then
    # transfer to backup machine
    DB_SRC_PORT=3306
    DB_DEST_HOST="$BACKUP_MACHINE"
    DB_DEST_PORT=3307
else
    if [ "$MACHINE_TYPE" == "backup" ]; then
        # transfer to main machine
        DB_SRC_PORT=3307
        DB_DEST_HOST="$MAIN_MACHINE"
        DB_DEST_PORT=3306
    fi
fi






# mysqldump directly to machine
#print_terminal_message "mysqldump -u$database_user -pporta -h $DB_SRC_HOST -P $DB_SRC_PORT --no-tablespaces --all-databases | mysql -u$database_user -pporta -h $DB_DEST_HOST -P $DB_DEST_PORT"
## mysqldump -h 10.10.10.104 -P 3306 -uporta -pporta --no-tablespaces --all-databases | mysql -h 10.10.10.106 -P 3307 -uporta -pporta
## mysqldump -h 192.168.50.163 -P 3306 -uporta -pporta --no-tablespaces --all-databases | mysql -h 192.168.50.42 -P 3307 -uporta -pporta
#
docker exec -it porta bash -c "mysqldump -h $DB_SRC_HOST -P $DB_SRC_PORT -uporta -pporta --no-tablespaces --all-databases | mysql -h $DB_DEST_HOST -P $DB_DEST_PORT -uporta -pporta"


print_terminal_message "Data transferred from $DB_SRC_HOST to $DB_DEST_HOST."
print_terminal_message "Fixing socket server URL in destination database..."
# update socket server URL in destination database
docker exec -it porta mysql -uporta -pporta -h "$DB_DEST_HOST" -P "$DB_DEST_PORT" -e "UPDATE companies SET ue_url='http://$DB_DEST_HOST:6001';"


## ALWAYS RUN LAST ##
source "$(dirname "${BASH_SOURCE[0]}")/../lib/foot.sh"
