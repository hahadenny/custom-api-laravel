#!/bin/bash

#########################################################################
## Script to run for restarting replication nodes
#########################################################################

## ALWAYS RUN FIRST ##
source "$(dirname "${BASH_SOURCE[0]}")/../lib/head.sh"

# config files are located above `porta-onprem` dir

SHOULD_BOOTSTRAP="false"

# Save to file and prompt for other machine if applicable
if [ "$MACHINE_TYPE" == "main" ]; then
    MAIN_MACHINE="$HOST_MACHINE"
    # save to file
    echo "$MAIN_MACHINE" > "$CONF_FILES_PATH/$MAIN_CONF_FILE"
    if [ "$DB_REPLICATION_ENABLED" == "true" ]; then
        # get backup machine ip & save to file
        BACKUP_MACHINE=$(get_machine_ip "$CONF_FILES_PATH/$BACKUP_CONF_FILE" "backup");
        # get arbiter machine ip & save to file
        ARBITER_MACHINE=$(get_machine_ip "$CONF_FILES_PATH/$ARBITER_CONF_FILE" "arbiter");
    else
        print_terminal_message "Database Replication is not enabled, skipping backup & arbiter machine setup...";
    fi
else
    if [ "$MACHINE_TYPE" == "backup" ]; then
        BACKUP_MACHINE="$HOST_MACHINE"
        # save to file
        echo "$BACKUP_MACHINE" > "$CONF_FILES_PATH/$BACKUP_CONF_FILE"
        if [ "$DB_REPLICATION_ENABLED" == "true" ]; then
            # get main machine ip & save to file
            MAIN_MACHINE=$(get_machine_ip "$CONF_FILES_PATH/$MAIN_CONF_FILE" "main");
            # get arbiter machine ip & save to file
            ARBITER_MACHINE=$(get_machine_ip "$CONF_FILES_PATH/$ARBITER_CONF_FILE" "arbiter");
        else
            print_terminal_message "Database Replication is not enabled, skipping main & arbiter machine setup...";
        fi
    else
        ARBITER_MACHINE="$HOST_MACHINE"
        # save to file
        echo "$ARBITER_MACHINE" > "$CONF_FILES_PATH/$ARBITER_CONF_FILE"
        if [ "$DB_REPLICATION_ENABLED" == "true" ]; then
            # get main machine ip & save to file
            MAIN_MACHINE=$(get_machine_ip "$CONF_FILES_PATH/$MAIN_CONF_FILE" "main");
            # get backup machine ip & save to file
            BACKUP_MACHINE=$(get_machine_ip "$CONF_FILES_PATH/$BACKUP_CONF_FILE" "backup");
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
else
    if [ "$MACHINE_TYPE" = "arbiter" ]; then
        stop_and_delete_container "porta-db-3"
    else
        stop_and_delete_container "porta-db"
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
    update_my_cnf "$HOST_MACHINE" "$BACKUP_DB_NAME" "$(dirname "${BASH_SOURCE[0]}")/../../../install/config/my2.cnf"
    copy_file_to_container "$(dirname "${BASH_SOURCE[0]}")/../../../install/config/my2.cnf" /etc/my.cnf porta-db-2
    start_container "porta-db-2"
    wait_for_container_ready "porta-db-2"
    docker update --restart always "porta-db-2"
else
    if [ "$MACHINE_TYPE" = "arbiter" ]; then
        update_my_cnf "$HOST_MACHINE" "$ARBITER_DB_NAME" "$(dirname "${BASH_SOURCE[0]}")/../../../install/config/my3.cnf"
        copy_file_to_container "$(dirname "${BASH_SOURCE[0]}")/../../../install/config/my3.cnf" /etc/my.cnf porta-db-3
        start_container "porta-db-3"
        wait_for_container_ready "porta-db-3"
        docker update --restart always "porta-db-3"
    else
        update_my_cnf "$HOST_MACHINE" "$MAIN_DB_NAME" "$(dirname "${BASH_SOURCE[0]}")/../../../install/config/my.cnf"
        copy_file_to_container "$(dirname "${BASH_SOURCE[0]}")/../../../install/config/my.cnf" /etc/my.cnf porta-db
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


if [ "$SHOULD_BOOTSTRAP" == "true" ]; then
    # we ARE bootstrapping

    db_name=$(determine_db_name "$MACHINE_TYPE")

    # Wait for the database to be ready and migrate but DO NOT seed
    wait_for_db_and_migrate "$(dirname "${BASH_SOURCE[0]}")" 1 "$db_name" # bash boolean --> 0 is `true`, 1 is `false`
else
    # Don't migrate if not bootstrapping; machine will be "migrated" via replication
    print_terminal_message "Not bootstrapped; skipping migration...";
fi

# restart the laravel workers so that they pick up the new code
supervisor_restart_all_workers


## ALWAYS RUN LAST ##
source "$(dirname "${BASH_SOURCE[0]}")/../lib/foot.sh"
