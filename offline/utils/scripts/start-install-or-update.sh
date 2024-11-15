#!/bin/bash

#########################################################################
## Scripts to run for installing or updating on-prem
##  that are the same for both installing and updating
#########################################################################

# porta-bundle.tar.gz, .env, and other files must exist in the current directory where this script is being run

#SCRIPT_DIR="$(dirname "$0")" # -- dir of whoever called the initially executing script
#SCRIPT_DIR="$(dirname "${BASH_SOURCE[0]}")" # absolute path to THIS script

## VARS ##
#source "$(dirname "${BASH_SOURCE[0]}")/../vars.sh"

## FUNCTIONS ###
source "$(dirname "${BASH_SOURCE[0]}")/../functions.sh"

##################################################

print_terminal_billboard "Beginning $ACTION of Porta On Prem..."

# DETERMINE IF WE SHOULD SETUP DB REPLICATION
DB_REPLICATION_ENABLED=$(get_dbrepl_status);

if [ "$DB_REPLICATION_ENABLED" == "true" ]; then
    print_terminal_message "Continuing with database replication enabled..."
else
    print_terminal_message "Continuing without database replication..."
fi

# INSTALLING FOR MAIN MACHINE OR BACKUP?
MACHINE_TYPE=$(get_machine_type "$MACHINE_TYPE_CONF_FILE");

## GET MACHINE IP FROM USER
HOST_MACHINE=$(get_machine_ip "$HOST_CONF_FILE");

# Save to file and prompt for other machines if applicable
if [ "$MACHINE_TYPE" == "main" ]; then
    MAIN_MACHINE="$HOST_MACHINE"
    # save to file
    echo "$MAIN_MACHINE" > "$MAIN_CONF_FILE"
    if [ "$DB_REPLICATION_ENABLED" == "true" ]; then
        # get backup machine ip & save to file
        BACKUP_MACHINE=$(get_machine_ip "$BACKUP_CONF_FILE" "backup");
        # get arbiter machine ip & save to file
        ARBITER_MACHINE=$(get_machine_ip "$ARBITER_CONF_FILE" "arbiter");
    else
        print_terminal_message "Database Replication is not enabled, skipping backup & arbiter machine setup...";
    fi
else
    if [ "$MACHINE_TYPE" == "backup" ]; then
        BACKUP_MACHINE="$HOST_MACHINE"
        # save to file
        echo "$BACKUP_MACHINE" > "$BACKUP_CONF_FILE"
        if [ "$DB_REPLICATION_ENABLED" == "true" ]; then
            # get main machine ip & save to file
            MAIN_MACHINE=$(get_machine_ip "$MAIN_CONF_FILE" "main");
            # get arbiter machine ip & save to file
            ARBITER_MACHINE=$(get_machine_ip "$ARBITER_CONF_FILE" "arbiter");
        else
            print_terminal_message "Database Replication is not enabled, skipping main & arbiter machine setup...";
        fi
    else
        ARBITER_MACHINE="$HOST_MACHINE"
        # save to file
        echo "$ARBITER_MACHINE" > "$ARBITER_CONF_FILE"
        if [ "$DB_REPLICATION_ENABLED" == "true" ]; then
            # get main machine ip & save to file
            MAIN_MACHINE=$(get_machine_ip "$MAIN_CONF_FILE" "main");
            # get backup machine ip & save to file
            BACKUP_MACHINE=$(get_machine_ip "$BACKUP_CONF_FILE" "backup");
        else
            print_terminal_message "Database Replication is not enabled, skipping main & backup machine setup...";
        fi
    fi
fi

print_terminal_message "Proceeding with host machine of '$HOST_MACHINE', this is the $MACHINE_TYPE machine. \n\n Main machine: '$MAIN_MACHINE', \n Backup machine: '$BACKUP_MACHINE', \n Arbiter machine: '$ARBITER_MACHINE'..."

# Make sure we can run all the scripts (chmod utils/ dir)
print_terminal_message "Granting execute permissions to the install scripts..."
chmod -R ug+x "$(dirname "${BASH_SOURCE[0]}")/../"

# stop the containers, then delete them and their images
docker_soft_reset "$MACHINE_TYPE"

if [ "$ACTION" = "install" ]; then
    # prompt for database wipe
    wipe_db
fi

## If the images are already unzipped, don't extract just load
if [[ -e "$(dirname "${BASH_SOURCE[0]}")/../../$PORTA_IMAGES_TAR" ]]; then
    # Load Docker images
    print_terminal_message "Archive has already been extracted, loading Porta images... "
    docker load -i "$(dirname "${BASH_SOURCE[0]}")/../../$PORTA_IMAGES_TAR"
else
    # Extract the porta images archive, keep the zipped archive intact
    print_terminal_message "Extracting the Porta images archive '$PORTA_IMAGES_ARCHIVE', this may take a few minutes... "
    gunzip -k "$(dirname "${BASH_SOURCE[0]}")/../../$PORTA_IMAGES_ARCHIVE"

    # Load Docker images from the extracted archive
    print_terminal_message "Loading Porta images from the extracted archive... "
    docker load -i "$(dirname "${BASH_SOURCE[0]}")/../../$PORTA_IMAGES_TAR"
fi

# Create the containers from images
docker_create "$MACHINE_TYPE"

# Make sure .env file knows if repl is enabled
sed -i "s|DB_ONPREM_REPLICATION_ENABLED=.*|DB_ONPREM_REPLICATION_ENABLED='$DB_REPLICATION_ENABLED'|" "$(dirname "${BASH_SOURCE[0]}")/../../install/config/.env-api";
# Make sure .env Bridge info is correct
sed -i "s|PORTA_BRIDGE_HOST=.*|PORTA_BRIDGE_HOST=$HOST_MACHINE|" "$(dirname "${BASH_SOURCE[0]}")/../../install/config/.env-api";
# Make sure .env file has machine type set
sed -i "s|APP_MACHINE_TYPE=.*|APP_MACHINE_TYPE='$MACHINE_TYPE'|" "$(dirname "${BASH_SOURCE[0]}")/../../install/config/.env-api";
sed -i "s|REACT_APP_MACHINE_TYPE=.*|REACT_APP_MACHINE_TYPE='$MACHINE_TYPE'|" "$(dirname "${BASH_SOURCE[0]}")/../../install/config/.env-app";
sed -i "s|APP_MACHINE_TYPE=.*|APP_MACHINE_TYPE='$MACHINE_TYPE'|" "$(dirname "${BASH_SOURCE[0]}")/../../install/config/.env-socket";
# Make sure .env socket info is correct
# all machines should point to main machine's socket server
sed -i "s|ONPREM_SOCKET_HOST=.*|ONPREM_SOCKET_HOST=$MAIN_MACHINE|" "$(dirname "${BASH_SOURCE[0]}")/../../install/config/.env-api";

# Always copy the .env file into the porta containers
copy_env_to_container "$(dirname "${BASH_SOURCE[0]}")/../../install/config/.env-api" /var/www/
copy_env_to_container "$(dirname "${BASH_SOURCE[0]}")/../../install/config/.env-app" /var/www/frontend
copy_env_to_container "$(dirname "${BASH_SOURCE[0]}")/../../install/config/.env-socket" /usr/src/app porta-socket

# NOTE: this is really just to avoid having to create a separate Dockerfile for mysql...
if [ "$MACHINE_TYPE" = "backup" ]; then
    # make sure replication connection values use correct machine addresses
    update_my_cnf "$HOST_MACHINE" "$BACKUP_DB_NAME" "$(dirname "${BASH_SOURCE[0]}")/../../install/config/my2.cnf"
    copy_file_to_container "$(dirname "${BASH_SOURCE[0]}")/../../install/config/my2.cnf" /etc/my.cnf porta-db-2
else
    if [ "$MACHINE_TYPE" = "arbiter" ]; then
        # make sure replication connection values use correct machine addresses
        update_my_cnf "$HOST_MACHINE" "$ARBITER_DB_NAME" "$(dirname "${BASH_SOURCE[0]}")/../../install/config/my3.cnf"
        copy_file_to_container "$(dirname "${BASH_SOURCE[0]}")/../../install/config/my3.cnf" /etc/my.cnf porta-db-3
    else
        # make sure replication connection values use correct machine addresses
        update_my_cnf "$HOST_MACHINE" "$MAIN_DB_NAME" "$(dirname "${BASH_SOURCE[0]}")/../../install/config/my.cnf"
        copy_file_to_container "$(dirname "${BASH_SOURCE[0]}")/../../install/config/my.cnf" /etc/my.cnf porta-db
    fi
fi

# Start the containers
docker_start "$MACHINE_TYPE"

# stop the laravel workers until we're done with the setup
supervisor_stop_all_workers

# fix .env and storage permissions
source "$(dirname "${BASH_SOURCE[0]}")/fix-env-storage-perms.sh"

######################
## SOCKET ##
set_socket_host "$HOST_MACHINE"


######################
## FRONTEND ##
# Use input IP for .env values
set_frontend_host "$HOST_MACHINE"

# Build front end app with correct machine IP
print_terminal_message "Building Porta Front End, this may take several minutes..."

docker exec -it porta bash -c "REACT_APP_BASE_URL='http://$HOST_MACHINE:8000' npm --prefix /var/www/frontend run build"


######################
## API ##

# CHANGE API ENV VALUES TO MACHINE IP
set_api_host "$HOST_MACHINE"

# Change API .env values for database connection info
set_db_hosts "$MACHINE_TYPE" "$MAIN_MACHINE" "$BACKUP_MACHINE" "$ARBITER_MACHINE"

# Access the porta container and run artisan commands for laravel configuration
source "$(dirname "${BASH_SOURCE[0]}")/init-laravel.sh"
