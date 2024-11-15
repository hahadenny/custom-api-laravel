#!/bin/bash

###############################################################################################
## Scripts to always run before a script to determine the machine type and other variables
###############################################################################################

## VARS ##
source "$(dirname "${BASH_SOURCE[0]}")/../../vars.sh"

## FUNCTIONS ###
source "$(dirname "${BASH_SOURCE[0]}")/../../functions.sh"

#########################################################################

# Set up the custom error handler to run when errexit occurs
trap custom_error_handler ERR

# Enable errexit (exit on error)
set -e

# create porta WSL host logs dirs if it doesn't exist
mkdir -p "$PORTA_LOGS_PATH" && chmod -R ug+wr "$PORTA_LOGS_PATH"
mkdir -p "$PORTA_INSTALLER_LOGS" && chmod -R ug+wr "$PORTA_INSTALLER_LOGS"
mkdir -p "$PORTA_HELPER_LOGS" && chmod -R ug+wr "$PORTA_HELPER_LOGS"

# DETERMINE IF WE SHOULD SETUP DB REPLICATION
DB_REPLICATION_ENABLED=true;

# config files are located above `porta-onprem` dir
CONF_FILES_PATH="$(dirname "${BASH_SOURCE[0]}")/../../../.."

mkdir -p "$CONF_FILES_PATH/conf"

# INSTALLING FOR MAIN MACHINE OR BACKUP?
MACHINE_TYPE=$(get_machine_type "$CONF_FILES_PATH/$MACHINE_TYPE_CONF_FILE");

## GET MACHINE IP FROM USER
HOST_MACHINE=$(get_machine_ip "$CONF_FILES_PATH/$HOST_CONF_FILE");


print_terminal_message "Proceeding with host machine of '$HOST_MACHINE', this is the $MACHINE_TYPE machine."

database_user='porta'
database_name='porta'

if [ -z "$BOOTSTRAP" ]; then
    # if the calling script didn't set this then default to not bootstrap the node
    BOOTSTRAP='OFF'
fi


# database container name
DB_CONT_NAME='porta-db'
DB_CONT_PORT=3306
DB_REPL_PORT=33061

if [ "$MACHINE_TYPE" == "arbiter" ]; then
    # Arbiter machine
    DB_CONT_NAME='porta-db-3'
    DB_CONT_PORT=3308
    DB_REPL_PORT=33063
else
    if [ "$MACHINE_TYPE" == "backup" ]; then
        # Backup Machine
        DB_CONT_NAME='porta-db-2'
        DB_CONT_PORT=3307
        DB_REPL_PORT=33062
    fi
fi
