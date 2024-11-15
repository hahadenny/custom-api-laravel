#!/bin/bash

REDIS_PORT="6379"
#MYSQL_PORT="3306"
SOCKETIO_PORT="6001"
API_PORT="8000"
APP_PORT="8080"

DOCKER_REPO="portadisguise"
DOCKER_NETWORK="porta-net"

# CONTAINERS
API_CONTAINER="porta"
SOCKET_CONTAINER="porta-socket"
DB_CONTAINER="porta-db"
REDIS_CONTAINER="porta-redis"

BUNDLE_BUILD_PATH="/home/jessd/code/on-prem/build/"

# Name of text file containing build number.
BUILD_NUMBER_FILE="build_number.txt"
BUILD_NUMBER_FILEPATH="$BUNDLE_BUILD_PATH$BUILD_NUMBER_FILE"

# Name of text file containing version "tag" (for creating build folder)
VERSION_FILE="version.txt"
VERSION_FILEPATH="$BUNDLE_BUILD_PATH$VERSION_FILE"

# Name of text file containing version number.
VERSION_NUMBER_FILE="version_number.txt"
VERSION_NUMBER_FILEPATH="$BUNDLE_BUILD_PATH$VERSION_NUMBER_FILE"

# Name of text file containing full version
API_VERSION_FILE="api_version.txt"
API_VERSION_FILEPATH="$BUNDLE_BUILD_PATH$API_VERSION_FILE"
FRONTEND_VERSION_FILE="frontend_version.txt"
FRONTEND_VERSION_FILEPATH="$BUNDLE_BUILD_PATH$FRONTEND_VERSION_FILE"
SOCKET_VERSION_FILE="socket_version.txt"
SOCKET_VERSION_FILEPATH="$BUNDLE_BUILD_PATH$SOCKET_VERSION_FILE"

PORTA_IMAGES_TAR="porta-images.tar"
PORTA_IMAGES_ARCHIVE="$PORTA_IMAGES_TAR.gz"

# Path to the Porta directory on the host machine (WSL)
# SAME AS `.bat`'s : WSL_DEST=/home/%WSL_USER%/porta
PORTA_PATH="/home/$USER/porta"
# Path to Porta On Prem installation directory on the host machine
# Note: This dir is removed and recreated on each install/update
PORTA_ONPREM_PATH="$PORTA_PATH/porta-onprem"
# Path to the logs directory on the host machine
# For things like installer or helper logs
PORTA_LOGS_PATH="$PORTA_PATH/logs"
# Install / update logs
PORTA_INSTALLER_LOGS="$PORTA_LOGS_PATH/installer"
# Helper logs
PORTA_HELPER_LOGS="$PORTA_LOGS_PATH/helper"

INSTALL_CONF_DIR="conf"
HOST_CONF_FILE="$INSTALL_CONF_DIR/host_machine.conf"
MACHINE_TYPE_CONF_FILE="$INSTALL_CONF_DIR/machine_type.conf"
MAIN_CONF_FILE="$INSTALL_CONF_DIR/main_machine.conf"
BACKUP_CONF_FILE="$INSTALL_CONF_DIR/backup_machine.conf"
ARBITER_CONF_FILE="$INSTALL_CONF_DIR/arbiter_machine.conf"

# config file to mount in container and use
DEV_MYCNF_FILE_PATH="./build/docker/config"
HOST_CONFIG_PATH="$HOST_PATH/config"

API_APP_STORAGE_VOLUME="porta-app-storage"
# host machine directory where porta container files used to be stored
OLD_API_STORAGE_MOUNT="/tmp/storage/app"

MAIN_DB_NAME="porta-db"
MAIN_DB_VOLUME="porta-db-volume"

BACKUP_DB_NAME="porta-db-2"
BACKUP_DB_VOLUME="$BACKUP_DB_NAME-volume"

ARBITER_DB_NAME="porta-db-3"
ARBITER_DB_VOLUME="$ARBITER_DB_NAME-volume"


MAIN_DB_PORT=3306
BACKUP_DB_PORT=3307
ARBITER_DB_PORT=3308

MAIN_DB_REPL_PORT=33061
BACKUP_DB_REPL_PORT=33062
ARBITER_DB_REPL_PORT=33063

DB_BACKUPS_PATH="/var/www/storage/app/Porta-Backups"
# relative path to db dumps folder from the backups path
# (i.e., where mysql-porta.sql.gz is after the parent backups archive is unzipped)
DB_DUMPS_RELPATH="db-dumps"
DB_BACKUP_SQL_FILE="mysql-porta.sql"
DB_BACKUP_SQL_ARCHIVE="$DB_BACKUP_SQL_FILE.gz"

SHOULD_BOOTSTRAP="false"

LARAVEL_LOGS_PATH="/var/www/storage/logs"


