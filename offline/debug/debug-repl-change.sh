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

#########################################################################

# Set up the custom error handler to run when errexit occurs
trap custom_error_handler ERR

# Enable errexit (exit on error)
set -e

# DETERMINE IF WE SHOULD SETUP DB REPLICATION
DB_REPLICATION_ENABLED=true;

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

# stop the laravel workers until we're done with the setup
supervisor_stop_all_workers

# stop the database, then delete them (leave images)
print_terminal_message "Stopping and removing $MACHINE_TYPE database..."
if [ "$MACHINE_TYPE" = "backup" ]; then
    stop_and_delete_container "porta-db-2"
    # using vanilla command can result in error if container doesn't exist
    # docker stop porta-db-2 && docker rm porta-db-2
else
    if [ "$MACHINE_TYPE" = "arbiter" ]; then
        stop_and_delete_container "porta-db-3"
        # using vanilla command can result in error if container doesn't exist
        # docker stop porta-db-3 && docker rm porta-db-3
    else
        stop_and_delete_container "porta-db"
        # using vanilla command can result in error if container doesn't exist
        # docker stop porta-db && docker rm porta-db
    fi
fi

# prompt to wipe db data (remove docker volumes)
wipe_db

# Create the database containers from image
print_terminal_message "Creating database..."
if [ "$MACHINE_TYPE" = "backup" ]; then
    create_db_container "$HOST_MACHINE" "$BACKUP_DB_NAME" "$BACKUP_DB_PORT" "$BACKUP_DB_REPL_PORT" "$BACKUP_DB_VOLUME" "my2.cnf"
else
    if [ "$MACHINE_TYPE" = "arbiter" ]; then
        create_db_container "$HOST_MACHINE" "$ARBITER_DB_NAME" "$ARBITER_DB_PORT" "$ARBITER_DB_REPL_PORT" "$ARBITER_DB_VOLUME" "my3.cnf"
    else
        create_db_container "$HOST_MACHINE" "$MAIN_DB_NAME" "$MAIN_DB_PORT" "$MAIN_DB_REPL_PORT" "$MAIN_DB_VOLUME" "my.cnf"
    fi
fi

# NOTE: this is really just to avoid having to create a separate Dockerfile for mysql...
# make sure replication connection values use correct machine addresses
#copy mysql config to container
if [ "$MACHINE_TYPE" = "backup" ]; then
    update_my_cnf "$HOST_MACHINE" "$BACKUP_DB_NAME" "$(dirname "${BASH_SOURCE[0]}")/../install/config/my2.cnf"
    copy_file_to_container "$(dirname "${BASH_SOURCE[0]}")/../install/config/my2.cnf" /etc/my.cnf porta-db-2
    start_container "porta-db-2"
    wait_for_container_ready "porta-db-2"
    docker update --restart always "porta-db-2"
else
    if [ "$MACHINE_TYPE" = "arbiter" ]; then
        update_my_cnf "$HOST_MACHINE" "$ARBITER_DB_NAME" "$(dirname "${BASH_SOURCE[0]}")/../install/config/my3.cnf"
        copy_file_to_container "$(dirname "${BASH_SOURCE[0]}")/../install/config/my3.cnf" /etc/my.cnf porta-db-3
        start_container "porta-db-3"
        wait_for_container_ready "porta-db-3"
        docker update --restart always "porta-db-3"
    else
        update_my_cnf "$HOST_MACHINE" "$MAIN_DB_NAME" "$(dirname "${BASH_SOURCE[0]}")/../install/config/my.cnf"
        copy_file_to_container "$(dirname "${BASH_SOURCE[0]}")/../install/config/my.cnf" /etc/my.cnf porta-db
        start_container "porta-db"
        wait_for_container_ready "porta-db"
        docker update --restart always "porta-db"
    fi
fi


if [ "$DB_REPLICATION_ENABLED" == "true" ]; then
    # Begin setting up replication
    setup_machine_db_replication "$MACHINE_TYPE"
else
    print_terminal_message "Database Replication is not enabled, skipping replication setup...";
fi

db_name=$(determine_db_name "$MACHINE_TYPE")

ask_migrate_or_seed "$db_name"

# restart the laravel workers so that they pick up the new code
supervisor_restart_all_workers

print_terminal_message "Finished."

# Disable errexit (optional, if you want to continue with the script)
set +e
