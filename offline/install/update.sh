#!/bin/bash

# porta-bundle.tar.gz, .env, and other files must exist in the current directory where this script is being run

#SCRIPT_DIR="$(dirname "$0")" # -- dir of whoever called the initially executing script
#SCRIPT_DIR="$(dirname "${BASH_SOURCE[0]}")" # absolute path to THIS script

## VARS ##
source "$(dirname "${BASH_SOURCE[0]}")/../utils/vars.sh"

## FUNCTIONS ###
source "$(dirname "${BASH_SOURCE[0]}")/../utils/functions.sh"

##################################################

# Set up the custom error handler to run when errexit occurs
trap custom_error_handler ERR

# Enable errexit (exit on error)
set -e

ACTION="update"
SHOULD_BOOTSTRAP="false"

print_terminal_message "Removing monitoring tools..."
docker compose -f "$(dirname "${BASH_SOURCE[0]}")/config/docker-compose-monitoring.yml" down

# Run all update relevant scripts
source "$(dirname "${BASH_SOURCE[0]}")/../utils/scripts/start-install-or-update.sh"

#
## NOTE: if we're updating, replication should already be setup. running setup again will crash the update process
#

# Disable errexit so that database issues don't prevent the workers from starting
set +e

if [ "$DB_REPLICATION_ENABLED" == "true" ]; then
    # Begin setting up replication
    setup_machine_db_replication "$MACHINE_TYPE"

    db_name=$(determine_db_name "$MACHINE_TYPE")

    if [ "$SHOULD_BOOTSTRAP" == "true" ]; then
        # ARE bootstrapping
        # Wait for the database to be ready and migrate but DO NOT seed
        wait_for_db_and_migrate "$(dirname "${BASH_SOURCE[0]}")" 1 "$db_name" # bash boolean --> 0 is `true`, 1 is `false`
    else
        # Don't migrate if not bootstrapping; machine will be "migrated" via replication
        print_terminal_message "Not bootstrapped.";

        ask_migrate_or_seed "$db_name"
    fi
else
    print_terminal_message "Database Replication is not enabled, skipping replication setup...";
    # Wait for the database to be ready and migrate + seed
    wait_for_db_and_migrate "$(dirname "${BASH_SOURCE[0]}")" 0 "$db_name" # bash boolean --> 0 is `true`, 1 is `false`
fi

# Make sure config line endings are correct
#fix_logs_crlf

# restart the laravel workers so that they pick up the new code
supervisor_restart_all_workers

# clear cache so db status update is sent to UI
docker exec -i porta php artisan cache:clear
docker exec -i porta php artisan config:cache

# copy old mount volume files to new docker volume
source "$(dirname "${BASH_SOURCE[0]}")/../utils/helpers/migrate-volumes.sh"

#docker compose -f "$(dirname "${BASH_SOURCE[0]}")/config/docker-compose-monitoring.yml" up -d


print_terminal_billboard "Update installation has completed!"
