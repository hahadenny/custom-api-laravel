#!/bin/bash

#EXEC_DIR="$(dirname $0)" # -- dir of whoever called the initially executing script
#SCRIPT_DIR="$(dirname "${BASH_SOURCE[0]}")" # absolute path to THIS script

## VARS ##
#source "$(dirname "${BASH_SOURCE[0]}")/../vars.sh"

## FUNCTIONS ###
source "$(dirname "${BASH_SOURCE[0]}")/../functions.sh"

####################################################################################################


print_terminal_message "Fixing .env & storage permissions... "
docker exec -u 0 -it porta bash -c "chown www-data:www-data .env && chown www-data:www-data frontend/.env && chown -R www-data:www-data storage && chmod -R ug+rwx storage"
