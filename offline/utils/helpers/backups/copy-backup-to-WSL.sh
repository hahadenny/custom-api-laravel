#!/bin/bash

#########################################################################
## Scripts to run for copying an existing backup from container to host machine filesystem
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
## Prompt the user to choose the file
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
print_terminal_message "Copying $DB_BACKUP_ARCHIVE to host..."

# Copy backup file from API to host to DB container
docker cp -a "porta:$DB_BACKUPS_PATH/$DB_DUMPS_RELPATH/$DB_BACKUP_SQL_FILE" "/home/$USER/"

print_terminal_message "Backup copied to WSL filesystem at: /home/$USER/$DB_BACKUP_SQL_FILE"

## ALWAYS RUN LAST ##
source "$(dirname "${BASH_SOURCE[0]}")/../lib/foot.sh"
