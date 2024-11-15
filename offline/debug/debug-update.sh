#!/bin/bash

#########################################################################
## Scripts to run for updating without gzipping images
# TODO -- NOTE: NEEDS UPDATING FOR RESUMING GROUP REPLICATION
# features/dev-PN-1019-third-db-machine
#########################################################################

## VARS ##
source "$(dirname "${BASH_SOURCE[0]}")/../utils/vars.sh"

## FUNCTIONS ###
source "$(dirname "${BASH_SOURCE[0]}")/../utils/functions.sh"

##################################################

# Set up the custom error handler to run when errexit occurs
trap custom_error_handler ERR

# Enable errexit (exit on error)
set -e

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

# Save to file and prompt for other machine if applicable
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

print_terminal_message "Proceeding with host machine of '$HOST_MACHINE', this is the $MACHINE_TYPE machine. \n Main machine: '$MAIN_MACHINE', \n Backup machine: '$BACKUP_MACHINE', \n Arbiter machine: '$ARBITER_MACHINE'..."

# Make sure we can run all the scripts (chmod utils/ dir)
print_terminal_message "Granting execute permissions to the install scripts..."
chmod -R ug+x "$(dirname "${BASH_SOURCE[0]}")/../"

# stop the containers, then delete them (leave images)
print_terminal_message "Stopping and removing containers..."
if [ "$MACHINE_TYPE" = "backup" ]; then
    stop_and_delete_container "porta"
    stop_and_delete_container "porta-socket"
    stop_and_delete_container "porta-redis"
    stop_and_delete_container "porta-db-2"
    # using vanilla command can result in error if container doesn't exist
#    docker stop porta porta-socket porta-redis porta-db-2 && docker rm porta porta-socket porta-redis porta-db-2
else
    if [ "$MACHINE_TYPE" = "arbiter" ]; then
        stop_and_delete_container "porta"
#        stop_and_delete_container "porta-socket"
#        stop_and_delete_container "porta-redis"
        stop_and_delete_container "porta-db-3"
        # using vanilla command can result in error if container doesn't exist
        #docker stop porta porta-socket porta-redis porta-db-3 && docker rm porta porta-socket porta-redis porta-db-3
#        docker stop porta porta-db-3 && docker rm porta porta-db-3
    else
        # using vanilla command can result in error if container doesn't exist
#        docker stop porta porta-socket porta-redis porta-db && docker rm porta porta-socket porta-redis porta-db
        stop_and_delete_container "porta"
        stop_and_delete_container "porta-socket"
        stop_and_delete_container "porta-redis"
        stop_and_delete_container "porta-db"
    fi
fi

# prompt to wipe db data (remove docker volumes)
wipe_db

# Create the containers from images
docker_create "$MACHINE_TYPE"

sed -i "s|DB_ONPREM_REPLICATION_ENABLED=.*|DB_ONPREM_REPLICATION_ENABLED='$DB_REPLICATION_ENABLED'|" "$(dirname "${BASH_SOURCE[0]}")/../install/config/.env-api";
# Make sure .env Bridge info is correct
sed -i "s|PORTA_BRIDGE_HOST=.*|PORTA_BRIDGE_HOST=$HOST_MACHINE|" "$(dirname "${BASH_SOURCE[0]}")/../install/config/.env-api";
# Make sure .env file has machine type set
sed -i "s|APP_MACHINE_TYPE=.*|APP_MACHINE_TYPE='$MACHINE_TYPE'|" "$(dirname "${BASH_SOURCE[0]}")/../install/config/.env-api";
sed -i "s|REACT_APP_MACHINE_TYPE=.*|REACT_APP_MACHINE_TYPE='$MACHINE_TYPE'|" "$(dirname "${BASH_SOURCE[0]}")/../install/config/.env-app";
sed -i "s|APP_MACHINE_TYPE=.*|APP_MACHINE_TYPE='$MACHINE_TYPE'|" "$(dirname "${BASH_SOURCE[0]}")/../install/config/.env-socket";

# Always copy the .env file into the porta containers
copy_env_to_container "$(dirname "${BASH_SOURCE[0]}")/../install/config/.env-api" /var/www/
if [ "$MACHINE_TYPE" != "arbiter" ]; then
    copy_env_to_container "$(dirname "${BASH_SOURCE[0]}")/../install/config/.env-app" /var/www/frontend
    copy_env_to_container "$(dirname "${BASH_SOURCE[0]}")/../install/config/.env-socket" /usr/src/app/ porta-socket
fi

# NOTE: this is really just to avoid having to create a separate Dockerfile for mysql...
if [ "$MACHINE_TYPE" = "backup" ]; then
    # make sure replication connection values use correct machine addresses
    update_my_cnf "$HOST_MACHINE" "$BACKUP_DB_NAME" "$(dirname "${BASH_SOURCE[0]}")/../install/config/my2.cnf"
    copy_file_to_container "$(dirname "${BASH_SOURCE[0]}")/../install/config/my2.cnf" /etc/my.cnf porta-db-2
else
    if [ "$MACHINE_TYPE" = "arbiter" ]; then
        # make sure replication connection values use correct machine addresses
        update_my_cnf "$HOST_MACHINE" "$ARBITER_DB_NAME" "$(dirname "${BASH_SOURCE[0]}")/../install/config/my3.cnf"
        copy_file_to_container "$(dirname "${BASH_SOURCE[0]}")/../install/config/my3.cnf" /etc/my.cnf porta-db-3
    else
        # make sure replication connection values use correct machine addresses
        update_my_cnf "$HOST_MACHINE" "$MAIN_DB_NAME" "$(dirname "${BASH_SOURCE[0]}")/../install/config/my.cnf"
        copy_file_to_container "$(dirname "${BASH_SOURCE[0]}")/../install/config/my.cnf" /etc/my.cnf porta-db
    fi
fi

# Start the containers
docker_start "$MACHINE_TYPE"

# stop the laravel workers until we're done with the setup
supervisor_stop_all_workers

# fix .env and storage permissions
source "$(dirname "${BASH_SOURCE[0]}")/../utils/scripts/fix-env-storage-perms.sh"

## Main or Backup machines:
if [ "$MACHINE_TYPE" != "arbiter" ]; then
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
fi

######################
## API ##

# CHANGE API ENV VALUES TO MACHINE IP
set_api_host "$HOST_MACHINE"

# Change API .env values for database connection info
# -- Get backup machine IP from user
set_db_hosts "$MACHINE_TYPE" "$MAIN_MACHINE" "$BACKUP_MACHINE" "$ARBITER_MACHINE"


# Access the porta container and run artisan commands for laravel configuration
source "$(dirname "${BASH_SOURCE[0]}")/../utils/scripts/init-laravel.sh"

# Disable errexit so that database issues don't prevent the workers from starting
set +e

# if we're updating, replication should already be setup, but running setup again will
# just set the same values again or skip
if [ "$DB_REPLICATION_ENABLED" == "true" ]; then
    # Begin setting up replication
    setup_machine_db_replication "$MACHINE_TYPE"
else
    print_terminal_message "Database Replication is not enabled, skipping replication setup...";
fi

db_name=$(determine_db_name "$MACHINE_TYPE")

# Don't migrate if replication is enabled; machine will be "migrated" via replication
if [ "$DB_REPLICATION_ENABLED" == "false" ]; then
    # Wait for the database to be ready and DO NOT seed
    wait_for_db_and_migrate "$(dirname "${BASH_SOURCE[0]}")" 1 "$db_name" # bash boolean --> 0 is `true`, 1 is `false`
else
    ask_migrate_or_seed "$db_name"
fi

# restart the laravel workers so that they pick up the new code
supervisor_start_all_workers
