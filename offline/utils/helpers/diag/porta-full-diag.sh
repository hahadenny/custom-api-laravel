#!/bin/bash

#########################################################################
## Script to run for restarting replication nodes
#########################################################################

## ALWAYS RUN FIRST ##
source "$(dirname "${BASH_SOURCE[0]}")/../lib/head.sh"

# Args passed in
WINDOWS_USER=$1
ipconfig_output_file=$2
hosts_file=$3
bridge_logs_dir="$4"
sysinfo_output_file=$5
#wslconfig_file="${6:-''}"

# Where we'll put all the files to zip up
OUTPUT_DIR="porta-full-diag_$MACHINE_TYPE-machine_$(date +'%Y%m%d.%H%M%S.%z')"  # Ymd-HMS-timezone
rm -rf "$OUTPUT_DIR"
mkdir -p "$OUTPUT_DIR"

all_machine_conf_file="$OUTPUT_DIR/all_machine_confs.txt"
# Check if the output file exists, if not create it
if [ ! -f "$all_machine_conf_file" ]; then
    touch "$all_machine_conf_file"
fi

# Disable errexit (exit on error)
set +e

# Get machine info files' contents and save to file
print_terminal_message "Getting machine info..."
{
  echo "Current Machine Type: $MACHINE_TYPE"
  echo "Current Machine IP: $HOST_MACHINE"
  echo "Current Windows User: $WINDOWS_USER"
  echo "Current Ubuntu User: $(whoami)"
  echo -e "\n"
  echo "Main Machine IP: $(cat "$MAIN_CONF_FILE")"
  echo -e "\n"
  echo "Backup Machine IP: $(cat "$BACKUP_CONF_FILE")"
  echo -e "\n"
  echo "Arbiter Machine IP: $(cat "$ARBITER_CONF_FILE")"
} > "$all_machine_conf_file"


# Get the machine's network info
print_terminal_message "Getting network info..."
cat "$ipconfig_output_file" > "$OUTPUT_DIR/network_info.txt"

# Get the machine's system info
print_terminal_message "Getting system info..."
cat "$sysinfo_output_file" > "$OUTPUT_DIR/system_info.txt"

# Get the machine's hosts file
print_terminal_message "Getting hosts file..."
cat "$hosts_file" > "$OUTPUT_DIR/hosts_file.txt"

## Get the machine's .wslconfig file
#print_terminal_message "Getting .wslconfig file..."
#cat "$wslconfig_file" > "$OUTPUT_DIR/wslconfig_file.txt"

# important configs
print_terminal_message "Getting important configs..."
confs_outdir="$OUTPUT_DIR/confs"
mkdir -p "$confs_outdir"
docker exec -it porta bash -c "cat /etc/nginx/sites-enabled/default" > "$confs_outdir/nginx_conf.txt"
docker exec -it porta bash -c "cat /usr/local/etc/php/conf.d/app.ini" > "$confs_outdir/php_conf.txt"
docker exec -it porta bash -c "cat /etc/supervisord.conf" > "$confs_outdir/supervisord_conf.txt"
docker exec -it "$DB_CONT_NAME" bash -c "cat /etc/my.cnf" > "$confs_outdir/my_cnf.txt"

# Worker status
print_terminal_message "Getting worker status (supervisorctl)..."
docker exec -it porta bash -c "supervisorctl status" > "$OUTPUT_DIR/supervisorctl_status.txt"

env_files_outdir="$OUTPUT_DIR/env_files"
mkdir -p "$env_files_outdir"

# Get the machine's environment variables
print_terminal_message "Getting Ubuntu vars..."
printenv > "$env_files_outdir/ubuntu_env_vars.txt"

# Container ENV files
print_terminal_message "Getting container env files..."
docker exec porta cat /var/www/.env > "$env_files_outdir/api_env.txt"
docker exec porta cat /var/www/frontend/.env > "$env_files_outdir/app_env.txt"
docker exec porta-socket cat /usr/src/app/.env > "$env_files_outdir/socket_env.txt"

# Porta Version
print_terminal_message "Getting Porta version info..."
porta_version=$(docker exec porta bash -c "php artisan check:version")
echo "${porta_version[*]}" >& "$OUTPUT_DIR/porta_version.txt"


## Get logs from docker containers

# API - Laravel logs
print_terminal_message "Getting Laravel logs..."
api_logs_outdir="$OUTPUT_DIR/api_logs"
mkdir -p "$api_logs_outdir"
docker exec porta cat /var/www/bootstrap/cache/config.php > "$api_logs_outdir/cached_config.txt"

laravel_logs_outdir="$api_logs_outdir/laravel_logs"
mkdir -p "$laravel_logs_outdir"

log_files=($(docker exec porta find "$LARAVEL_LOGS_PATH" -maxdepth 1 -type f -name "*.log" -exec basename {} \; | sort -r))
# Iterate through each log file and copy it to the host machine
for log_file in "${log_files[@]}"; do
    docker cp porta:"$LARAVEL_LOGS_PATH/$log_file" "$laravel_logs_outdir"
done

# Laravel storage cache files
print_terminal_message "Getting Laravel framework cache files..."
storage_dir="/var/www/storage"
laravel_cache_outdir="$api_logs_outdir/laravel_framework_cache"
mkdir -p "$laravel_cache_outdir"

cache_files=($(docker exec porta find "$storage_dir/framework" -type f | sort -r))
# Iterate through each cache file and copy it to the host machine
for cache_file in "${cache_files[@]}"; do
    docker cp porta:"$cache_file" "$laravel_cache_outdir"
done

# queue logs
print_terminal_message "Getting horizon queue logs..."
docker cp porta:/var/www/logs/horizon.log "$api_logs_outdir"

# tasks logs
print_terminal_message "Getting scheduled tasks logs..."
docker cp porta:/var/www/logs/default_tasks.log "$api_logs_outdir"

# failed jobs
docker exec -it porta bash -c "php artisan queue:failed" > "$api_logs_outdir/failed_jobs.txt"

# storage dir contents
print_terminal_message "Listing storage files..."
docker exec porta bash -c "find $storage_dir/app -type f -print"  > "$api_logs_outdir/storage.txt"
docker exec porta bash -c "find $storage_dir/logs -type f -print"  >> "$api_logs_outdir/storage.txt"


# PHP logs
print_terminal_message "Getting PHP logs..."
php_logs_file="/var/log/php/errors.log"
php_logs_outdir="$api_logs_outdir/php_logs"
mkdir -p "$php_logs_outdir"
docker cp porta:"$php_logs_file" "$php_logs_outdir"

# docker logs for this container
print_terminal_message "Getting docker logs for porta container..."
# >& instead of > will also print to terminal
docker logs porta >& "$api_logs_outdir/docker_logs_porta.txt"

## todo: get logs from mysql container
print_terminal_message "Getting docker logs for database container..."
db_logs_outdir="$OUTPUT_DIR/database_logs"
mkdir -p "$db_logs_outdir"
# docker logs for this container
docker logs "$DB_CONT_NAME" >& "$db_logs_outdir/docker_logs_$DB_CONT_NAME.txt"

# replication group status
repl_group_status=$(docker exec "$DB_CONT_NAME" mysql -u "$database_user" -pporta -e "SELECT * FROM performance_schema.replication_group_members\\G")
echo "${repl_group_status[*]}" >& "$db_logs_outdir/replication_group_status.txt"

## get logs from socket container
print_terminal_message "Getting logs for porta-socket container..."
socket_logs_outdir="$OUTPUT_DIR/socket_logs"
mkdir -p "$socket_logs_outdir"

# General and error logs
socket_logs_dir="/tmp/porta-socket-server"
docker cp porta-socket:"$socket_logs_dir/porta-socket-server.log" "$socket_logs_outdir"
docker cp porta-socket:"$socket_logs_dir/porta-socket-server-error.log" "$socket_logs_outdir"

# docker logs for this container
print_terminal_message "Getting docker logs for socket server container..."
docker logs porta-socket >& "$socket_logs_outdir/docker_logs_porta-socket.txt"

## get logs from redis container
print_terminal_message "Getting docker logs for redis container..."
redis_logs_outdir="$OUTPUT_DIR/redis_logs"
mkdir -p "$redis_logs_outdir"
# docker logs for this container
docker logs porta-redis >& "$redis_logs_outdir/docker_logs_porta-redis.txt"

## Bridge Logs
print_terminal_message "Getting Porta Bridge logs from '$bridge_logs_dir'..."
bridge_logs_outdir="$OUTPUT_DIR/bridge_logs"
mkdir -p "$bridge_logs_outdir"
cp -ra "$bridge_logs_dir"/* "$bridge_logs_outdir"


## ZIP IT UP (NOT with zip; tool must be available on all machines)
print_terminal_message "Compressing diag files..."
# -c : create new archive
# -p : preserve-permissions
# -z : uze gzip compression
# -v : verbose
# -f : set name of new archive (?)
tar -czvf "$OUTPUT_DIR.tar.gz" "$OUTPUT_DIR"

print_terminal_message "Diagnostic has been completed and is available at '$OUTPUT_DIR.tar.gz'."

## ALWAYS RUN LAST ##
source "$(dirname "${BASH_SOURCE[0]}")/../lib/foot.sh"
