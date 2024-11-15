#!/bin/bash

#########################################################################
## Scripts to run for data restoration using an existing backup
#########################################################################

## ALWAYS RUN FIRST ##
source "$(dirname "${BASH_SOURCE[0]}")/../lib/head.sh"

# Get a list of the latest backup file names from the API container
# The name will look something like `machine_type-machine-connection_name-backup-Y-m-d-H-i-s.zip`

# Run the script inside the container
# set max number of files to show
list_max=15
zip_files=($(docker exec porta find "$DB_BACKUPS_PATH" -maxdepth 1 -type f -name "*.zip" -exec basename {} \; | sort -r | head -n $list_max))

# Check if there are any zip files
if [ ${#zip_files[@]} -eq 0 ]; then
    echo "No backups found in $DB_BACKUPS_PATH."
    exit 1
fi

##
## Prompt the user to choose the file to restore from
##
selected_option=""
while true; do
    # Display options
    echo "" # newline
    echo "Choose a zip file (from the $list_max most recent backups):"
    for ((i=0; i<${#zip_files[@]}; i++)); do
        echo "$((i+1))) ${zip_files[i]}"
    done
    read -p "Enter the number of your choice: " selection
    if [[ "$selection" =~ ^[0-9]+$ && $selection -ge 1 && $selection -le ${#zip_files[@]} ]]; then
        selected_option="${zip_files[$((selection-1))]}"
        read -p "*** You selected $selected_option, restore with this backup? (y/n) " choice
        # Convert the user's input to lowercase for easier comparison
        choice=${choice,,}
        if [[ "$choice" == "y" ]]; then
            # break out of the loop and continue with the script
            DB_BACKUP_ARCHIVE="$DB_BACKUPS_PATH/$selected_option"
            break
        fi
    else
        echo "Invalid selection, please enter a valid number."
    fi
done

# Use the selected option
print_terminal_message "Restoring with $DB_BACKUP_ARCHIVE..."


# porta-db does not have unzip installed, so we need to unzip the file in the API container
# Unzip the backup file (it becomes db_dumps/mysql-porta.sql.gz)
print_terminal_message "Unzipping backup archive..."
echo "unzip $DB_BACKUP_ARCHIVE -d $DB_BACKUPS_PATH"
docker exec -it porta bash -c "unzip $DB_BACKUP_ARCHIVE -d $DB_BACKUPS_PATH"

# Unzip the sql file (and keep original)
print_terminal_message "Unzipping SQL archive..."
echo "gunzip -k $DB_BACKUPS_PATH/$DB_DUMPS_RELPATH/$DB_BACKUP_SQL_ARCHIVE"
docker exec -it porta  bash -c "gunzip -k $DB_BACKUPS_PATH/$DB_DUMPS_RELPATH/$DB_BACKUP_SQL_ARCHIVE"

# Copy backup file from API to host to DB container
print_terminal_message "Copying SQL file to database container (via host machine)..."
docker cp -a "porta:$DB_BACKUPS_PATH/$DB_DUMPS_RELPATH/$DB_BACKUP_SQL_FILE" ~/
docker cp -a ~/"$DB_BACKUP_SQL_FILE" "$DB_CONT_NAME":/
# Remove backup file from host
rm -rf ~/"$DB_BACKUP_SQL_FILE"

# pipe the sql to the machine's database to restore
database_user='porta'
database_name='porta'
print_terminal_message "Restoring database, (the next password prompt will be for the database user '$database_user')..."
docker exec -it "$DB_CONT_NAME" bash -c "cat $DB_BACKUP_SQL_FILE | mysql -u$database_user -p -h $DB_CONT_NAME -P $DB_CONT_PORT $database_name"


## ALWAYS RUN LAST ##
source "$(dirname "${BASH_SOURCE[0]}")/../lib/foot.sh"
