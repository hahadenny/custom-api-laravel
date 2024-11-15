#!/bin/bash

#EXEC_DIR="$(dirname $0)" # -- dir of whoever called the initially executing script
#SCRIPT_DIR="$(dirname "${BASH_SOURCE[0]}")" # absolute path to THIS script

## VARS ##
#source "$(dirname "${BASH_SOURCE[0]}")/../vars.sh"

## FUNCTIONS ###
source "$(dirname "${BASH_SOURCE[0]}")/../functions.sh"

####################################################################################################

# Access the porta container and run artisan commands for laravel configuration
print_terminal_message "Clearing and setting Laravel cache, linking storage dir..."
docker exec -it porta bash -c "php artisan key:generate && php artisan cache:clear && php artisan config:cache && php artisan storage:link && php artisan horizon:publish"

fix_laravel_perms
