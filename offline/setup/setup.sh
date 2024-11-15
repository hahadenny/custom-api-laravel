#!/bin/bash

## /////////////////////////////////////////////////////////////////////////////////////////////
## // SETUP FOR INSTALL //
## /////////////////////////////////////////////////////////////////////////////////////////////

#EXEC_DIR="$(dirname $0)" # -- dir of whoever called the initially executing script
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

## Make a pretty tree of available helpers and save it to file at `install/docs/helper-tree.txt`
tree_doc_path=$(realpath "$(dirname "${BASH_SOURCE[0]}")/../install/docs")
rm -f "$tree_doc_path/helper-tree.txt"
touch "$tree_doc_path/helper-tree.txt"
offline_path="$(realpath "$(dirname "${BASH_SOURCE[0]}")/../")"
cd "$offline_path" || exit
tree -a porta-helpers >& "$tree_doc_path/helper-tree.txt"
cd - || exit


#####################################
## Update or Build Images ##

# Update Images
#docker pull portadisguise/porta-api
#docker pull mysql/mysql-server
#docker pull portadisguise/porta-socket
#docker pull redis

#####################################
## Save & Compress Images ##

# append buildnumber to build path dir (trims trailing `/`)
BUNDLE_BUILD_PATH="${BUNDLE_BUILD_PATH}$(get_build_num "$VERSION_FILEPATH")"

if [[ -e "$BUNDLE_BUILD_PATH" ]]; then
    # remove old build if it exists
    print_terminal_message "Removing old build from '$BUNDLE_BUILD_PATH'..."

    # Remove all files in the directory except the files tracking builds/versions
    find "$BUNDLE_BUILD_PATH" -mindepth 1 -type f ! -name "*.txt" -exec rm -f {} +

    # Remove all empty subdirectories
    find "$BUNDLE_BUILD_PATH" -mindepth 1 -type d -empty -delete
fi

# setup subfolder
ONPREM_BUILD_PATH="$BUNDLE_BUILD_PATH/porta-onprem"
BATCH_BUILD_PATH="$BUNDLE_BUILD_PATH/porta-helpers"

# make and move to build dir
print_terminal_message "Creating build dir at '$ONPREM_BUILD_PATH', '$BATCH_BUILD_PATH'..."
mkdir -p "$BUNDLE_BUILD_PATH"
mkdir -p "$BATCH_BUILD_PATH"
mkdir -p "$ONPREM_BUILD_PATH"


# allow exec of scripts
print_terminal_message "Allowing install script execution..."
chmod -R u+x "$(dirname "${BASH_SOURCE[0]}")/../utils"
chmod -R u+x "$(dirname "${BASH_SOURCE[0]}")/../debug"
chmod u+x "$(dirname "${BASH_SOURCE[0]}")/../install/install.sh"
chmod u+x "$(dirname "${BASH_SOURCE[0]}")/../install/update.sh"

# NOTE: this is really just to avoid having to create a separate Dockerfile for mysql...
# copy `my.cnf` files to `install/config`
mkdir -p "$(dirname "${BASH_SOURCE[0]}")/../install/config"
cp -r "$(dirname "${BASH_SOURCE[0]}")/../build/docker/config/my.cnf" "$(dirname "${BASH_SOURCE[0]}")/../install/config"
cp -r "$(dirname "${BASH_SOURCE[0]}")/../build/docker/config/my2.cnf" "$(dirname "${BASH_SOURCE[0]}")/../install/config"
cp -r "$(dirname "${BASH_SOURCE[0]}")/../build/docker/config/my3.cnf" "$(dirname "${BASH_SOURCE[0]}")/../install/config"

# copy dirs to the build dir
print_terminal_message "Copying install scripts to the build dir..."
cp -r "$(dirname "${BASH_SOURCE[0]}")/../install" "$ONPREM_BUILD_PATH"
cp -r "$(dirname "${BASH_SOURCE[0]}")/../utils" "$ONPREM_BUILD_PATH"
cp -r "$(dirname "${BASH_SOURCE[0]}")/../debug" "$ONPREM_BUILD_PATH"
# Batch files dir
cd "$(dirname "${BASH_SOURCE[0]}")/../porta-helpers" || exit
# copy porta-helpers dir contents to build dir
cp -r * "$BATCH_BUILD_PATH"
cd - || exit

# move the help guides up to the bundle dir so users have access before unzipping porta-onprem
mkdir -p "$BUNDLE_BUILD_PATH/docs"
# move HTML and TXT files to the docs dir
find "$ONPREM_BUILD_PATH/install/docs" -type f \( -name "*.html" -o -name "*.txt" \) -exec mv {} "$BUNDLE_BUILD_PATH/docs" \;
mv "$ONPREM_BUILD_PATH/install/README.html" "$BUNDLE_BUILD_PATH/README.html"
mv "$ONPREM_BUILD_PATH/install/INSTALL.html" "$BUNDLE_BUILD_PATH/INSTALL.html"
mv "$ONPREM_BUILD_PATH/install/installation-troubleshooting.html" "$BUNDLE_BUILD_PATH/installation-troubleshooting.html"

# copy version file to dir we'll be zipping (parent bundle dir)
cp -r "$VERSION_FILEPATH" "$BUNDLE_BUILD_PATH"
# also copy to onprem build dir
cp -r "$VERSION_FILEPATH" "$ONPREM_BUILD_PATH"

print_terminal_message "Saving & compressing Docker images, this may take several minutes..."

#docker save redis:7.4.0 mysql/mysql-server:8.0.32 portadisguise/porta-api:latest portadisguise/porta-socket:latest portadisguise/porta-glances:latest portadisguise/porta-prometheus:latest portadisguise/porta-grafana:latest prom/node-exporter:v1.8.2 gcr.io/cadvisor/cadvisor:v0.49.1 portainer/portainer-ce:2.20.3 | gzip > "$ONPREM_BUILD_PATH/$PORTA_IMAGES_ARCHIVE"
docker save redis:7.4.0 mysql/mysql-server:8.0.32 portadisguise/porta-api:latest portadisguise/porta-socket:latest portadisguise/porta-glances:latest portainer/portainer-ce:2.20.3 | gzip > "$ONPREM_BUILD_PATH/$PORTA_IMAGES_ARCHIVE"

print_terminal_message "Docker images saved at '$ONPREM_BUILD_PATH/$PORTA_IMAGES_ARCHIVE'"

# create archive within linux --> ~2 mins
print_terminal_message "Creating on prem archive, this may take a few minutes..."

# cd to avoid the `zip` command including the parent directories in the archive
cd "$BUNDLE_BUILD_PATH" || exit

#tar -czvf "$PORTA_ONPREM_ARCHIVE" *
# use windows zip
# !!NOTE: CANNOT USE LINUX ZIP ON THE CLIENT END -- it is not included by default on WSL Ubuntu.
#         The machine would already need the zip package downloaded and installed
# Generic archive name for the on-prem build files (not batch files)
PORTA_ONPREM_ARCHIVEPATH="$ONPREM_BUILD_PATH.zip"
#PORTA_ONPREM_ARCHIVE="porta-onprem.zip"
zip -r "porta-onprem.zip" porta-onprem/*
print_terminal_message "On-prem archive saved at '$PORTA_ONPREM_ARCHIVEPATH'"

# remove porta-onprem original dir so we don't include it in the bundle archive
rm -rf "$ONPREM_BUILD_PATH"

# Create a bundle of the two archives
print_terminal_message "Creating a bundle of the on-prem and batch archives..."
PORTA_BUNDLE_ARCHIVEPATH="$BUNDLE_BUILD_PATH/porta-onprem-bundle.zip"
zip -r "porta-onprem-bundle.zip" *
print_terminal_message "Bundle archive saved at '$PORTA_BUNDLE_ARCHIVEPATH'"


print_terminal_message "NEXT STEPS: You will need to upload '$PORTA_BUNDLE_ARCHIVEPATH' to the porta-internal-plugins S3 bucket"

# return to original dir
cd - || exit

# Disable errexit (optional, if you want to continue with the script)
set +e
