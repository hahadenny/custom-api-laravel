#!/bin/bash

## VARS ##
source "$(dirname "${BASH_SOURCE[0]}")/../vars.sh"

## FUNCTIONS ###
source "$(dirname "${BASH_SOURCE[0]}")/../functions.sh"

##################################################

if [ -d "$OLD_API_STORAGE_MOUNT" ] && [ "$(ls -A "$OLD_API_STORAGE_MOUNT")" ]; then
    print_terminal_message "The directory '$OLD_API_STORAGE_MOUNT' exists and is not empty. Migrating files to new storage volume..."

    # copy old volume to new volume
    docker stop porta
    if docker image inspect "$DOCKER_REPO/porta-api:configured" >/dev/null 2>&1; then
        print_terminal_message "Removing old image 'portadisguise/porta-api:configured'..."
        docker rmi portadisguise/porta-api:configured
    fi

    # commit the current state of the porta container to a new image
    # date format: `Y-m-d.HMS.timezone`
    docker commit --message="after install $(date +'%Y-%m-%d.%H%M%S.%z')" porta portadisguise/porta-api:configured
    # restart the container
    docker start porta

    # create a new, temp container from the newly committed image so we have all the proper env/configs
    # and can mount both the old and new storage volumes for copying
    docker run --rm --name porta-tmp -d --network=porta-net \
        -v /tmp/storage/app:/var/www/old_storage \
        --mount source="$API_APP_STORAGE_VOLUME",target=/var/www/storage/app \
        portadisguise/porta-api:configured

    # copy the old storage to the new storage
    # this will "copy" the old files to the real porta container since the real one is
    # also using the `$API_APP_STORAGE_VOLUME` that this `/var/www/storage/app` is mounted to
    print_terminal_message "Copying files from host's '$OLD_API_STORAGE_MOUNT' to new volume '$API_APP_STORAGE_VOLUME'..."
    docker exec -it porta-tmp bash -c "cp -r /var/www/old_storage/* /var/www/storage/app"
    # fix permissions
    print_terminal_message "Fixing permissions on new volume '$API_APP_STORAGE_VOLUME'..."
    docker exec -it -u 0 porta-tmp bash -c "chown -R www-data:www-data /var/www/storage/app"
    docker exec -it porta-tmp bash -c "chmod -R 775 /var/www/storage/app"

    print_terminal_message "Removing temporary container..."
    docker stop porta-tmp
    # remove the temp container's image
    docker rmi portadisguise/porta-api:configured

    print_terminal_message "The directory '$OLD_API_STORAGE_MOUNT' has been migrated to the new storage volume '$API_APP_STORAGE_VOLUME'. \n A sample of the new volume contents is as follows, please review for accuracy:"

    echo ""
    docker exec -it porta bash -c "ls -l /var/www/storage/app"

    # list the 5 most recently modified files

    # shows the most info
    #docker exec -it porta bash -c "find /var/www/storage/app/public -type f -exec ls -lt {} + | sort -k 6,7 | head -n 5"

    # shows only filenames not the date
    docker exec -it porta bash -c "find /var/www/storage/app/public -type f -exec  stat --format '%Y %n' {} + | sort -n | cut -d' ' -f2- | head -n 5"

    # the date is displayed weirdly
    # docker exec -it porta bash -c "find /var/www/storage/app/public -type f -exec stat --format '%y %n' {} + | sort | head -n 5"

    # cuts out the file name, which we don't want to do
    # docker exec -it porta bash -c "find /var/www/storage/app/public -type f -exec stat --format '%y %n' {} + | sort | head -n 5 | cut -d'.' -f1"

    while true; do

        read -p "*** Do these files look correct? (y/n): " confirm_volume

        if [ "$confirm_volume" == "y" ]; then
            while true; do
                # Prompt to remove the old storage directory
                read -p "*** Would you like to delete the old storage directory '$OLD_API_STORAGE_MOUNT'? (y/n): " confirm_delete
                if [ "$confirm_delete" == "y" ]; then
                    print_terminal_message "Deleting old storage '$OLD_API_STORAGE_MOUNT'..."
                    rm -rf /tmp/storage/app
                    break
                else
                    if [ "$confirm_delete" == "n" ]; then
                        print_terminal_message "The old storage directory '$OLD_API_STORAGE_MOUNT' has NOT been deleted."
                        break
                    fi
                fi
            done
            break
        else
            if [ "$confirm_volume" == "n" ]; then
                print_terminal_message "Please contact support regarding Porta's volume transfer."
                break
            fi
        fi
    done

else
    print_terminal_message "The directory '$OLD_API_STORAGE_MOUNT' does not exist or is empty. Skipping files migration."
fi
