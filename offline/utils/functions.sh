#!/bin/bash

# Custom function to run when errexit occurs
custom_error_handler() {
    local exit_code=$?
    local failed_command="$BASH_COMMAND"
    local error_line="$BASH_LINENO"

    echo " X "
    echo " XX "
    echo " XXX "
    echo " =================================================================================================="
    echo "$(date): ERROR: Error occurred in command: '$failed_command' (Exit Code: $exit_code) at line $error_line"
    echo " =================================================================================================="
    echo " XXX "
    echo " XX "
    echo " X "
    echo ""
}

print_terminal_message() {
    local message=$1

    echo ""
    echo " =================================================================================================="
    echo " ="
    echo -e "$(date): ==> $message"
    echo " ="
    echo " =================================================================================================="
    echo ""
    echo ""
}

# Something to stand out
print_terminal_billboard() {
    local message=$1

    echo ""
    echo " * "
    echo " ** "
    echo " *** "
    echo " **************************************************************************************************"
    echo " *** "
    echo " *** "
    echo -e " *** $(date): $message"
    echo " *** "
    echo " *** "
    echo " **************************************************************************************************"
    echo " *** "
    echo " ** "
    echo " * "
    echo ""
    echo ""
}

docker_build() {
    local image_name=$1
    local dockerfile=$2 # path to Dockerfile for this image
    local context=$3 # path to the build context (where the build should be run)
    local use_cache="${4:-1}" # if 1, can use docker build cache for image creation
    local repo_name="${5:-portadisguise}"


    if [ "$use_cache" -eq 1 ]; then
        print_terminal_message "Building '$image_name' image from cache..."
        print_terminal_message "Running 'docker build --build-arg APP_VERSION_NUMBER=\"$VERSION_NUMBER\" --build-arg APP_VERSION_DATE=\"$VERSION_DATE\" -t \"$repo_name\"/\"$image_name\" --progress=plain -f \"$dockerfile\" \"$context\"'... "

        docker build --build-arg APP_VERSION_NUMBER="$VERSION_NUMBER" --build-arg APP_VERSION_DATE="$VERSION_DATE" -t "$repo_name"/"$image_name" --progress=plain -f "$dockerfile" "$context"
    else
        print_terminal_message "Building '$image_name' image WITHOUT cache..."
        print_terminal_message "Running 'docker build --no-cache --build-arg APP_VERSION_NUMBER=\"$VERSION_NUMBER\" --build-arg
         APP_VERSION_DATE=\"$VERSION_DATE\" -t \"$repo_name\"/\"$image_name\" --progress=plain -f \"$dockerfile\" \"$context\"' ..."

        docker build --no-cache --build-arg APP_VERSION_NUMBER="$VERSION_NUMBER" --build-arg APP_VERSION_DATE="$VERSION_DATE" -t "$repo_name"/"$image_name" --progress=plain -f "$dockerfile" "$context"
    fi
}

# TODO: add args for file locations, etc.
build_porta_api_images() {
    local use_cache="${1:-1}" # if 1, can use docker build cache for image creation
    local repo_name="${2:-portadisguise}"

    # !NOTE:! we don't want to commit the .env files with the images, they're just here
    # for `npm ci` and `composer install` to use

    # use on-prem .env
    print_terminal_message "Creating API .env file..."
    cp -ar ./install/config/.env-api ./code/porta-api/
    mv ./code/porta-api/.env-api ./code/porta-api/.env
    chmod 775 ./code/porta-api/.env

    # NOTE: composer install is done by Dockerfile


    # FRONT END
    # use on-prem .env
    print_terminal_message "Creating FRONTEND .env file..."
    cp -ar ./install/config/.env-app ./code/porta-api/frontend
    mv ./code/porta-api/frontend/.env-app ./code/porta-api/frontend/.env

    # Docker build contexts should come from the SOURCE CODE location within this build script project
    # To keep each project as its own build source

    # API BASE
    print_terminal_message "Building porta-api-base image..."
    docker_build "porta-api-base" "./code/porta-api/offline/build/docker/Dockerfile.base" "./code/porta-api" "$use_cache" "$repo_name"

    # API
    print_terminal_message "Building porta-api image..."
    docker_build "porta-api" "./code/porta-api/offline/build/docker/Dockerfile" "./code/porta-api" "$use_cache" "$repo_name"
}

# TODO: add args for file locations, etc.
build_socket_image() {
    local use_cache="${1:-1}" # if 1, can use docker build cache for image creation
    local repo_name="${2:-portadisguise}"


    # use on-prem .env
    # !NOTE:! we don't want to commit the .env with the images, it's just here for `npm ci` to use
    print_terminal_message "Creating SOCKET .env file..."
    cp -ar ./install/config/.env-socket ./code/porta-socket-server
    mv ./code/porta-socket-server/.env-socket ./code/porta-socket-server/.env

    print_terminal_message "Building porta-socket image..."
    docker_build "porta-socket" "./code/porta-socket-server/Dockerfile" "./code/porta-socket-server" "$use_cache" "$repo_name"
}

# Create the containers from images
docker_start() {
    local machine_type=$1 # "main" or "backup" or "arbiter"

    # Create the Docker network if it doesn't already exist
    create_docker_network

    if [ "$machine_type" = "backup" ]; then
        start_container "porta-db-2"
    else
        if [ "$machine_type" = "arbiter" ]; then
            start_container "porta-db-3"
        else
            start_container "porta-db"
        fi
    fi

    # Start container PORTA-REDIS
    start_container "porta-redis"

    # Start container PORTA-SOCKET
    start_container "porta-socket"

    # Start container PORTA
    # ** This container serves front and back end
    start_container "porta"

    # A restart policy only takes effect after a container starts successfully.
    # In this case, starting successfully means that the container is up for at least 10 seconds and
    # Docker has started monitoring it
    print_terminal_message "Setting all running docker containers to auto-restart unless they are stopped..."
    wait_for_container_ready "porta"
    if [ "$machine_type" = "backup" ]; then
        wait_for_container_ready "porta-db-2"
    else
        if [ "$machine_type" = "arbiter" ]; then
            wait_for_container_ready "porta-db-3"
        else
            wait_for_container_ready "porta-db"
        fi
    fi
    wait_for_container_ready "porta-redis"
    wait_for_container_ready "porta-socket"
    # `$(docker ps -q)` must not be quoted or each container ID will be treated as one
#    docker update --restart unless-stopped $(docker ps -q)
    docker update --restart always $(docker ps -q)

    # check that the policy was set: `docker inspect -f '{{.HostConfig.RestartPolicy.Name}}' <container name or ID>`
}


# Create the containers from images
docker_create() {
    local machine_type=$1 # "main" or "backup"

    # Create the Docker network if it doesn't already exist
    create_docker_network

    if [ "$machine_type" = "backup" ]; then
        # Create container PORTA-DB-2
        create_db_container "$HOST_MACHINE" "$BACKUP_DB_NAME" "$BACKUP_DB_PORT" "$BACKUP_DB_REPL_PORT" "$BACKUP_DB_VOLUME" "my2.cnf"
    else
        if [ "$machine_type" = "arbiter" ]; then
            # Create container PORTA-DB-3
            create_db_container "$HOST_MACHINE" "$ARBITER_DB_NAME" "$ARBITER_DB_PORT" "$ARBITER_DB_REPL_PORT" "$ARBITER_DB_VOLUME" "my3.cnf"
        else
            # Create container PORTA-DB
            create_db_container "$HOST_MACHINE" "$MAIN_DB_NAME" "$MAIN_DB_PORT" "$MAIN_DB_REPL_PORT" "$MAIN_DB_VOLUME" "my.cnf"
        fi
    fi

    # Create container PORTA-REDIS
    create_redis_container "$HOST_MACHINE"

    # Create container PORTA-SOCKET
    create_socket_container

    # Create container PORTA
    # ** This container serves front and back end
    create_api_container
}

# stop the containers, then delete them and their images
docker_soft_reset() {
    local machine_type=$1 # "main" or "backup"

    if [ "$machine_type" = "backup" ]; then
        # reset the backup machine containers
        stop_and_delete_containers "porta" "porta-socket" "porta-redis" "porta-db-2"
    else
        if [ "$machine_type" = "arbiter" ]; then
            # reset the backup machine containers
            stop_and_delete_containers "porta" "porta-socket" "porta-redis" "porta-db-3"
        else
            # reset the main machine containers
            stop_and_delete_containers "porta" "porta-socket" "porta-redis" "porta-db"
        fi
    fi

    print_terminal_message "Deleting all unused images..."

    # temp disable exit on error since errors are ok when trying to delete with xargs
    set +e

    # !!NOTE: this will delete *ALL* images not currently in use, i.e., not tied to an existing, built container
    # TODO : This is to remove dangling or otherwise incorrectly built images, but should probably be refined
    docker images -q | xargs docker rmi

    # re-enable exit on error
    set -e

    print_terminal_message "Docker container and image removal completed."
}

stop_and_delete_containers() {
    if [ $# -eq 0 ]; then
        echo "No container names provided. Usage: stop_and_delete_containers 'container_name1' 'container_name2' ..."
        return 1
    fi

    # stop the containers, then delete them and their images
    print_terminal_message "Stopping and deleting containers and images..."

    for container_name in "$@"; do
        stop_and_delete_container "$container_name"
    done
}

# Sets global var `SHOULD_BOOTSTRAP` to true or false
prompt_db_bootstrap() {
    local machine_type=$1 # "main" or "backup" or "arbiter"

    read -e -p "*** Should this database be bootstrapped? !! NOTE: Bootstrapping a database in an existing, healthy group can lead to data loss and corruption, please consult the README if you are unsure how to proceed. (y/n): " -i "n" confirm

        if [ "$confirm" == "y" ]; then
            read -p "*** Please confirm that there is currently no primary database online and you would like to bootstrap this database (y/n): " confirm_again
            if [ "$confirm_again" == "y" ]; then
                print_terminal_message "** Bootstrapping $machine_type machine's database ** \n Please ensure other databases in the group are shut down."
                SHOULD_BOOTSTRAP="true"
            else
                SHOULD_BOOTSTRAP="false"
                print_terminal_message "$machine_type machine's database will not be bootstrapped."
            fi
        else
            SHOULD_BOOTSTRAP="false"
            print_terminal_message "$machine_type machine's database will not be bootstrapped."
        fi
}

# Determine the database name based on the machine type
determine_db_name() {
    local machine_type=$1 # "main" or "backup" or "arbiter"

    if [ "$machine_type" = "backup" ]; then
        echo "porta-db-2"
    else
        if [ "$machine_type" = "arbiter" ]; then
            echo "porta-db-3"
        else
            echo "porta-db"
        fi
    fi
}

# setup for specific machine type
setup_machine_db_replication() {
    local machine_type=$1 # "main" or "backup" or "arbiter"

    prompt_db_bootstrap "$machine_type"

    if [ "$machine_type" = "backup" ]; then
        # setup backup machine database
        setup_db_replication "porta-db-2" 2 "$BACKUP_DB_REPL_PORT" 50 "$SHOULD_BOOTSTRAP"
    else
        if [ "$machine_type" = "arbiter" ]; then
            # setup arbiter machine database
            setup_db_replication "porta-db-3" 3 "$ARBITER_DB_REPL_PORT" 20 "$SHOULD_BOOTSTRAP"
        else
            # setup main machine database
            setup_db_replication "porta-db" 1 "$MAIN_DB_REPL_PORT" 80 "$SHOULD_BOOTSTRAP"
        fi
    fi
}

setup_db_replication() {
    local container_name=$1
    local server_id=$2
    local repl_port=$3
    local weight="${4:-50}" # 50 is mysql default weight; 0 min, 100 max
    local bootstrap="${5:-"false"}" # default to not boostrap
    local group_uuid="${6:-a5c236d2-2c38-46ce-a15f-ebe5d5560e9c}"
    local container_name_prefix="${7:-porta-db}"

    local db_user="porta"
    local db_pswd="porta"

    local repl_user="repl"
    local repl_pswd="password"

    # Begin setup replication
    # Most of these steps must be done AS PRIVILEGED USER, i.e., root
    print_terminal_message "Setting up database replication for container '$container_name'..."

    # make sure config permissions are correct
    set_permissions "/etc/my.cnf" "644" "false" "$container_name"

    # install plugin only if not exists
    print_terminal_message "Installing group replication mysql plugin for container '$container_name'..."
    plugin_exists=$(docker exec "$container_name" mysql -uroot -p"$db_pswd" -e "SELECT * FROM information_schema.plugins WHERE PLUGIN_NAME = 'group_replication';")
    if [[ -z $plugin_exists ]]; then
      # The plugin doesn't exist, so install it
      docker exec "$container_name" mysql -uroot -p"$db_pswd" -e "INSTALL PLUGIN group_replication SONAME 'group_replication.so';"
    else
      print_terminal_message "Plugin 'group_replication' is already installed! Continuing..."
    fi

    # Print mainly for debugging
    docker exec -i "$container_name" mysql -uroot -p"$db_pswd" -e "SHOW PLUGINS;"

    # all nodes should have all seeds in case of failure
    group_seeds="'$container_name_prefix:$MAIN_DB_REPL_PORT,$container_name_prefix-2:$BACKUP_DB_REPL_PORT,$container_name_prefix-3:$ARBITER_DB_REPL_PORT'"

    # set replication vars
    print_terminal_message "Setting group replication variables for container '$container_name'..."
    docker exec -i "$container_name" mysql -uroot -p"$db_pswd" -e \
    "SET PERSIST group_replication_group_name='$group_uuid'; \
    SET PERSIST group_replication_local_address='$container_name:$repl_port'; \
    SET PERSIST group_replication_group_seeds=$group_seeds; \
    SET PERSIST group_replication_ip_allowlist='AUTOMATIC'; \
    SET PERSIST group_replication_start_on_boot='OFF'; \
    SET PERSIST server_id=$server_id; \
    SET PERSIST group_replication_bootstrap_group=OFF;\
    SET PERSIST group_replication_recovery_get_public_key=ON; \
    SET PERSIST group_replication_enforce_update_everywhere_checks=OFF; \
    SET PERSIST group_replication_single_primary_mode=ON; \
    SET PERSIST group_replication_components_stop_timeout=30; \
    "

    # config replication user
    print_terminal_message "Configuring group replication mysql user for container '$container_name'..."
    # REPLICATION SLAVE -- required for making a distributed recovery connection to a donor to retrieve data
    # CONNECTION_ADMIN -- ensures that Group Replication connections are not terminated if one of the
    #                     servers involved is placed in offline mode
    # BACKUP_ADMIN -- if the servers in the replication group are set up to support cloning, then
    #                  this privilege is required for a member to act as the donor in a cloning operation
    #                  for distributed recovery. `CLONE_ADMIN` includes `BACKUP_ADMIN` and `SHUTDOWN` privileges
    # CHANGE REPLICATION SOURCE... -- Supply the user credentials to the server for use with distributed recovery by
    #                                 setting the user credentials as the credentials for the
    #                                 `group_replication_recovery` channel
    docker exec -i "$container_name" mysql -uroot -p"$db_pswd" -e "SET SQL_LOG_BIN=0; \
    CREATE USER IF NOT EXISTS '$repl_user'@'%' IDENTIFIED BY '$repl_pswd'; \
    GRANT REPLICATION SLAVE ON *.* TO '$repl_user'@'%'; \
    GRANT CLONE_ADMIN ON *.* TO '$repl_user'@'%'; \
    GRANT BACKUP_ADMIN ON *.* TO '$repl_user'@'%'; \
    GRANT CONNECTION_ADMIN ON *.* TO '$repl_user'@'%'; \
    FLUSH PRIVILEGES; \
    SET SQL_LOG_BIN=1; \
    CHANGE REPLICATION SOURCE TO SOURCE_USER='$repl_user', SOURCE_PASSWORD='password' FOR CHANNEL 'group_replication_recovery'; \
    "

    # grant mysql user access to check replication vars from porta API
    print_terminal_message "Granting mysql user '$db_user' permissions to SELECT from performance_schema and run backups for container '$container_name'..."
    docker exec -i "$container_name" mysql -uroot -p"$db_pswd" -e \
        "SET SQL_LOG_BIN=0; \
        GRANT SELECT ON performance_schema.replication_group_members TO '$db_user'@'%'; \
        GRANT SELECT ON performance_schema.replication_connection_status TO '$db_user'@'%'; \
        GRANT SELECT ON performance_schema.replication_group_member_stats TO '$db_user'@'%'; \
        GRANT BACKUP_ADMIN ON *.* TO '$db_user'@'%'; \
        GRANT GROUP_REPLICATION_ADMIN ON *.* TO '$db_user'@'%'; \
        GRANT SYSTEM_VARIABLES_ADMIN ON *.* TO '$db_user'@'%'; \
        GRANT FLUSH_TABLES ON *.* TO '$db_user'@'%'; \
        FLUSH PRIVILEGES; \
        SET SQL_LOG_BIN=1;";

    if [ $weight -ne 50 ]; then
        print_terminal_message "Setting container '$container_name' primary election weight to $weight..."
        docker exec -i "$container_name" mysql -uroot -p"$db_pswd" -e \
            "SET PERSIST group_replication_member_weight=$weight;"
    fi

    # start group replication
    # and set containers to start replication on boot
    if [ "$bootstrap" == "true" ]; then
        print_terminal_message "Bootstrapping group replication for container '$container_name'..."
        # Bootstrap first node & start replication
        docker exec -i "$container_name" mysql -uroot -p"$db_pswd" -e \
        "SET PERSIST group_replication_bootstrap_group=ON; \
        START GROUP_REPLICATION; \
        SET PERSIST group_replication_bootstrap_group=OFF; \
        SET PERSIST group_replication_start_on_boot=ON; \
        SELECT * FROM performance_schema.replication_group_members;"
    else
        print_terminal_message "Starting group replication for container '$container_name'..."
        docker exec -i "$container_name" mysql -uroot -p"$db_pswd" -e \
        "SET PERSIST group_replication_bootstrap_group=OFF; \
        SET PERSIST group_replication_start_on_boot=ON; \
        START GROUP_REPLICATION; \
        SELECT * FROM performance_schema.replication_group_members;"
    fi

    # make CERTAIN we don't auto-bootstrap on restart
    persist_bootstrap_off "$container_name" "$db_pswd"
}


# shellcheck disable=SC2120
# ^ disable IDE warning for args that are never passed in
create_api_container() {
    local container_name="${1:-porta}" # default to "porta"
    local API_PORT="${2:-8000}"  # default to "8000"
    local APP_PORT="${3:-8080}"  # default to "8080"
    local network_name="${4:-porta-net}"  # default to "porta-net"
    # If you start a container with a volume that doesn't yet exist, Docker creates the volume for you
    local volume="${5:-porta-app-storage}"  # default to "porta-app-storage"

    local command="docker create --name $container_name --network=$network_name \
    --mount source=$volume,target=/var/www/storage/app \
    -p $API_PORT:$API_PORT -p $APP_PORT:$APP_PORT \
    --restart always \
    portadisguise/porta-api"

    print_terminal_message "Running command --> $command"
    create_container "$container_name" "$command"
}

# shellcheck disable=SC2120
# ^ disable IDE warning for args that are never passed in
create_socket_container() {
    local container_name="${1:-porta-socket}"
    local SOCKET_PORT="${2:-6001}"
    local network_name="${3:-porta-net}"

    local command="docker create --name $container_name --network=$network_name -p $SOCKET_PORT:$SOCKET_PORT \
     --restart always \
     portadisguise/porta-socket"

    print_terminal_message "Running command --> $command"
    create_container "$container_name" "$command"
}

create_redis_container() {
    local HOST_MACHINE=$1
    local container_name="${2:-porta-redis}"
    local REDIS_PORT="${3:-6379}"
    local network_name="${4:-porta-net}"

    local command="docker create --name $container_name --network=$network_name \
    --restart always \
    -p $HOST_MACHINE:$REDIS_PORT:$REDIS_PORT redis:7.4.0"

    print_terminal_message "Running command --> $command"
    create_container "$container_name" "$command"
}

create_db_container() {
    # create_db_container "$HOST_MACHINE" "porta-db-3" 3308 33063 "porta-db-3-volume" "my3.cnf"

    local HOST_MACHINE=$1
    local container_name="${2:-porta-db}"
    local mysql_port="${3:-3306}"
    local mysql_repl_port="${4:-33061}"
    # name of volume to use/create
    local volume="${5:-porta-db-volume}"
    # not the full path
    local config_file="${6:-my.cnf}"
    local network_name="${7:-porta-net}"

    local command="docker create --name $container_name --network=porta-net \
                       -e MYSQL_ROOT_PASSWORD=porta -e MYSQL_DATABASE=porta -e MYSQL_USER=porta -e MYSQL_PASSWORD=porta \
                       --mount source=$volume,target=/var/lib/mysql \
                       --restart always \
                       -p $HOST_MACHINE:$mysql_port:$mysql_port -p $HOST_MACHINE:$mysql_repl_port:$mysql_repl_port \
                       mysql/mysql-server:8.0.32"

    if [ "$DB_REPLICATION_ENABLED" == "true" ]; then
        # replication is enabled, load the related plugins on start
        command="$command --plugin-load=clone=mysql_clone.so;group_replication=group_replication.so"
    fi

    print_terminal_message "Running command --> $command"
    create_container "$container_name" "$command"
}

create_container() {
    local container_name=$1
    local create_cmd=$2

    if ! docker inspect "$container_name" >/dev/null 2>&1; then
        print_terminal_message "Creating Docker container '$container_name'... "
        $create_cmd
    else
        print_terminal_message "Docker container '$container_name' already exists."
    fi
}

start_container() {
    local container_name=$1

    if ! docker inspect "$container_name" >/dev/null 2>&1; then
        print_terminal_message "ERROR -- Docker container '$container_name' does not exist."
        exit 1;
    else
        local msg=""

        # If stopped, start it
        if ! docker inspect "$container_name" | grep '"Status": "running"' >/dev/null; then
            print_terminal_message "Starting Docker container '$container_name'..."
            docker start "$container_name"
        else
            print_terminal_message "Docker container '$container_name' is already running."
        fi
    fi
}

# stop and remove the given container by name
stop_and_delete_container() {
    local container_name=$1

  if docker ps -a --format '{{.Names}}' | grep -q "^$container_name$"; then
      print_terminal_message "Stopping container '$container_name'..."
      docker stop "$container_name"
      print_terminal_message "Removing container '$container_name'..."
      docker rm "$container_name"
  else
      print_terminal_message "Container '$container_name' does not exist, skipping removal."
  fi
}

# Create the Docker network if it doesn't already exist
# shellcheck disable=SC2120
# ^ disable IDE warning for args that are never passed in
create_docker_network() {
    local network_name="${1:-porta-net}"  # default to "porta-net"

    if ! docker network inspect $network_name >/dev/null 2>&1; then
        print_terminal_message "Creating Docker network '$network_name'..."
        docker network create $network_name
    else
        print_terminal_message "Docker network '$network_name' already exists."
    fi
}

# check if the container is ready
wait_for_container_ready() {
  local container_name="$1"
  local max_attempts=30  # Adjust the number of attempts and sleep duration as needed
  local sleep_duration=3

  print_terminal_message "Waiting for '$container_name' container to be running... "

  # Check if the container is running and healthy
  if echo "$container_name" | grep -q "porta-db"; then
    for ((i=1; i<$max_attempts; i++)); do
        status=$(docker ps --filter "name=$container_name" --format "{{.Status}}")
        # Check if health status is available & correct
        if echo "$status" | grep -q "healthy"; then
            print_terminal_message "Container '$container_name' is ready! "
            return 0  # Success, container is ready
        else
            if echo "$status" | grep -q "Up"; then
               print_terminal_message "Container '$container_name' is running but not healthy. \n (status: $status) \n Checking again [$i of $max_attempts tries]..."
            fi
        fi

        # Sleep for a few seconds before checking again
        sleep $sleep_duration
    done
  else
    status=$(docker ps --filter "name=$container_name" --format "{{.Status}}")
    # Check if health status is available & correct
    if echo "$status" | grep -q "Up"; then
       print_terminal_message "Container '$container_name' is ready!"
       return 0  # Success, container is ready
    fi
  fi

  print_terminal_message "Container '$container_name' did not become ready within the timeout."

  return 1  # Failure, container is not ready
}

copy_env_to_container() {
    # including filename
    local target_path=$1
    # not including filename
    local dest_path=$2
    local container_name="${3:-porta}"  # default to "porta"

    # substring after the last /
    target_file="${target_path##*/}"

    print_terminal_message "Copying '$target_path/$target_file' into container as '$dest_path.env'..."
    # add to tmp location so we can copy it into container as its correct `.env` name
    mkdir -p tmp
    cp "$target_path" "./tmp/$target_file"
    # rename
    mv "tmp/$target_file" tmp/.env
    # add to stopped or running container
    docker cp tmp/.env "$container_name:$dest_path"
    # destroy tmp
    rm -rf tmp
}

# copy into stopped or running container
copy_file_to_container() {
    # including filename
    local target_path=$1
    # including filename
    local dest_path=$2
    local container_name="${3:-porta}"  # default to "porta"

    # substring after the last /
    target_file="${target_path##*/}"

    print_terminal_message "Copying '$target_path' into container as '$dest_path'..."
    docker cp "$target_path" "$container_name:$dest_path"
    # normally called before the container is started, so we can't set permissions here
}

set_permissions() {
    local file_path=$1
    local permissions=$2
    local recursive="${3:-true}"
    local container_name="${4:-false}"

    if [[ "$container_name" == "false" ]]; then
        print_terminal_message "Setting permissions of '$file_path' to '$permissions'..."
        if [[ "$recursive" == "true" ]]; then
            chmod -R "$permissions" "$file_path"
        else
            chmod "$permissions" "$file_path"
        fi
    else
        print_terminal_message "Setting permissions of '$file_path' to '$permissions' in container '$container_name'..."
        if [[ "$recursive" == "true" ]]; then
            docker exec -it "$container_name" bash -c "chmod -R $permissions $file_path"
        else
            docker exec -it "$container_name" bash -c "chmod $permissions $file_path"
        fi
    fi
}

set_owner() {
    local container_name=$1
    local file_path=$2
    local owner=$3
    local group=$4
    local recursive="${5:-true}"
    local use_docker="${6:-true}"

    if [[ "$use_docker" == "true" ]]; then
        print_terminal_message "Setting owner:group of '$file_path' to '$owner:$group' in container '$container_name'..."
        if [[ "$recursive" == "true" ]]; then
            docker exec -it "$container_name" bash -c "chown -R $owner:$group $file_path"
        else
            docker exec -it "$container_name" bash -c "chown $owner:$group $file_path"
        fi
    else
        print_terminal_message "Setting permissions of '$file_path' to '$owner:$group'..."
        if [[ "$recursive" == "true" ]]; then
            chown -R "$owner:$group" "$file_path"
        else
            chown "$owner:$group" "$file_path"
        fi
    fi
}

wait_for_db_and_migrate() {

    local SCRIPT_DIR=$1
    local should_seed="${2:-0}" # bash boolean --> 0 is `true`, 1 is `false`
    local db_name="${3:-porta-db}"

    # Wait for the database to be ready
    if wait_for_container_ready "$db_name"; then
        # Container is ready

        if [ "$should_seed" -eq 0 ]; then
            # migrate DB & seed
            print_terminal_message "Migrating and seeding the database... "
            docker exec -it porta bash -c "php artisan config:cache && yes | php artisan migrate --seed"

            # !NOTE: no longer using old D3 templates, so skip the import
            # Import d3 template data
#            print_terminal_message "Creating D3 templates... "
#            source "$SCRIPT_DIR/../utils/scripts/template_insert.sh"
        else
            # migrate DB only
            # We run the permission seeder so we ensure permissions exist in the database
            # in case this is an update of an installation without the permissions initialized.
            # This seeder is idempotent and will not duplicate
            print_terminal_message "Migrating the database WITHOUT seeding... "
            docker exec -it porta bash -c "php artisan config:cache && yes | php artisan migrate && yes | php artisan db:seed --class=PermissionSeeder"
        fi
    else
        # container was not ready within the timeout
        if [ "$should_seed" -eq 0 ]; then
            print_terminal_message "Database container timed out. \n In the terminal, please copy, paste, and run the following command: \n docker start porta-db && docker exec -it porta bash -c 'php artisan config:cache && yes | php artisan migrate --seed'"
        else
            print_terminal_message "Database container timed out. \n In the terminal, please copy, paste, and run the following command: \n docker start porta-db && docker exec -it porta bash -c 'php artisan config:cache && yes | php artisan migrate'"
        fi
    fi
}

# ONLY SEED
wait_for_db_and_seed() {
    local db_name="${1:-porta-db}"

    # Wait for the database to be ready
    if wait_for_container_ready "$db_name"; then
        # Container is ready

        # DB seed
        print_terminal_message "Seeding the database... "
        docker exec -it porta bash -c "php artisan config:cache && yes | php artisan db:seed"
    else
        # container was not ready within the timeout
        print_terminal_message "Database container timed out. \n In the terminal, please copy, paste, and run the following command: \n docker start porta-db && docker exec -it porta bash -c 'php artisan config:cache && yes | php artisan db:seed'"
    fi
}

wipe_db() {
    # Prompt the user for confirmation
    echo " =!!!= "
    echo " =================================================================================================="
    read -p " !!! This will wipe ALL database related data. Are you sure you want to continue?  (y/n): " choice
    echo " =================================================================================================="
    echo " =!!!= "

    # Convert the user's input to lowercase for easier comparison
    choice=${choice,,}

    # Check if the user's choice is 'y' (yes)
    if [[ "$choice" == "y" ]]; then
        echo "Wiping database..."
        # wipe volume
        docker volume rm -f "$MAIN_DB_VOLUME" "$BACKUP_DB_VOLUME" "$ARBITER_DB_VOLUME"
    else
        echo "Database wipe declined, installation aborted."
        exit 1
    fi
}

#############################################################################################################
## SINGLE USE:

set_socket_host() {
    local host_machine=$1
    local api_port="${2:-8000}"

    ## CHANGE ENV VALUES TO MACHINE IP FOR SOCKET
    print_terminal_message "Setting Socket's host machine to '$host_machine'... "

    docker exec -it porta-socket sh -c 'sed -i "s/PORTA_API_HOST=.*/PORTA_API_HOST='"$host_machine"'/" .env; sed -i "s/PORTA_API_PORT=.*/PORTA_API_PORT='"$api_port"'/" .env; sed -i "s/REDIS_HOST=.*/REDIS_HOST='"$host_machine"'/" .env;'

    # RESTART SOCKET WITH NEW VALUES
    print_terminal_message "Restarting Socket... "
    docker restart porta-socket
}

# CHANGE API ENV VALUES TO MACHINE IP
set_frontend_host() {
    local host_machine=$1
    local api_port="${2:-8000}"

    print_terminal_message "Setting Front end's host machine to '$host_machine'... "

    docker exec -it porta sh -c "sed -i 's|REACT_APP_BASE_URL=.*|REACT_APP_BASE_URL=http://$host_machine:$api_port|' frontend/.env;"
}

# CHANGE API ENV VALUES TO MACHINE IP
set_api_host() {
    local host_machine=$1
    local api_port="${2:-8000}"
    local front_port="${3:-8080}"

    print_terminal_message "Setting API's host machine to '$host_machine'... "
    docker exec -it porta sh -c "sed -i 's|APP_URL=.*|APP_URL=http://$host_machine:$api_port|' .env; sed -i 's|FRONT_URL=.*|FRONT_URL=http://$host_machine:$front_port|' .env; sed -i 's|REDIS_HOST=.*|REDIS_HOST=$host_machine|' .env;"

    # !!NOTE: MUST ALSO SET LARAVEL CONFIG CACHE (php artisan config:cache)
}

# CHANGE API ENV VALUES TO MACHINE IPs
set_db_hosts() {
    local machine_type=$1
    local main_machine=$2
    local backup_machine=$3
    local arbiter_machine=$4
    local main_port="${5:-$MAIN_DB_PORT}"
    local backup_port="${6:-$BACKUP_DB_PORT}"
    local arbiter_port="${7:-$ARBITER_DB_PORT}"

    print_terminal_message "Setting API's default database host to '$main_machine' with port $main_port... "
    docker exec -it porta sh -c "sed -i 's|DB_HOST_1=.*|DB_HOST_1=$main_machine|' .env; sed -i 's|DB_PORT_1=.*|DB_PORT_1=$main_port|' .env;"

    print_terminal_message "Setting API's backup database host to '$backup_machine' with port $backup_port... "
        docker exec -it porta sh -c "sed -i 's|DB_HOST_2=.*|DB_HOST_2=$backup_machine|' .env; sed -i 's|DB_PORT_2=.*|DB_PORT_2=$backup_port|' .env;"

    print_terminal_message "Setting API's arbiter database host to '$arbiter_machine' with port $arbiter_port... "
        docker exec -it porta sh -c "sed -i 's|DB_HOST_3=.*|DB_HOST_3=$arbiter_machine|' .env; sed -i 's|DB_PORT_3=.*|DB_PORT_3=$arbiter_port|' .env;"

    # !!NOTE: MUST ALSO SET LARAVEL CONFIG CACHE (php artisan config:cache) -- done later
}

update_my_cnf() {

    local host_machine=$1
    # db container name
    local db_name=$2
    # my.cnf file
    local file=$3

    print_terminal_message "Setting report_host to '$host_machine'... "
    sed -i "s|report_host=.*|report_host=$host_machine|" "$file"
    print_terminal_message "Setting permissions 644 on '$file'... "
    set_permissions "$file" 644

}

#############################################################################################################

# INSTALLING FOR MAIN MACHINE OR BACKUP?
get_machine_type() {
    local conf_file="$1"
    local machine_type

    if [ -f "$conf_file" ]; then
        # Configuration file exists; read and display its contents
        machine_type=$(cat "$conf_file")

        while true; do
            read -e -p "*** Is this the main, the backup, or the arbiter machine for Porta On Prem? (Enter main, backup, or arbiter): " -i "$machine_type" new_machine_type

            # Check the user's response
            if [ "$new_machine_type" == "main" ] || [ "$new_machine_type" == "backup" ] || [ "$new_machine_type" == "arbiter" ]; then
                echo "$new_machine_type" > "$conf_file"
                machine_type="$new_machine_type"
                break
            fi
        done
    else
        # Config file does not exist; prompt the user for input and save it
        while true; do
            read -p "*** Is this the main, the backup, or the arbiter machine for Porta On Prem? (Enter main, backup, or arbiter): " new_machine_type

            # Check the user's response
            if [ "$new_machine_type" == "main" ] || [ "$new_machine_type" == "backup" ] || [ "$new_machine_type" == "arbiter" ]; then
                # Make sure conf dir exists
                mkdir -p 'conf'
                if [ ! -e "$conf_file" ]; then
                    # Make sure the file exists
                    touch "$conf_file"
                fi
                echo "$new_machine_type" > "$conf_file"
                machine_type="$new_machine_type"
                break
            fi
        done
    fi

    # can't print here it will try to be "returned"
    #    print_terminal_message "Set up will continue for the $machine_type machine..."

    echo "$machine_type"
}

get_machine_ip() {

    local conf_file="$1"
    # machine to get the IP for
    local machine_type="${2:-current}"
    local machine

    if [ -f "$conf_file" ]; then
        # Configuration file exists; read and display its contents
        machine=$(cat "$conf_file")

        read -e -p "*** Please enter the IPv4 address of the $machine_type machine: " -i "$machine" new_machine
        echo "$new_machine" > "$conf_file"
        machine="$new_machine"
    else
        # Config file does not exist; prompt the user for input and save it
        read -p "*** Please enter the IPv4 address of the $machine_type machine: " machine
        # Make sure conf dir exists
        mkdir -p 'conf'
        if [ ! -e "$conf_file" ]; then
            # Make sure the file exists
            touch "$conf_file"
        fi
        echo "$machine" > "$conf_file"
    fi

    echo "$machine"
}

get_build_num() {
    local number_file="${1:-$BUILD_NUMBER_FILEPATH}"

    # Read the current build number from the file
    read -r current_build_number < "$number_file"

    # Return it to be used elsewhere
    echo "$current_build_number"
}


update_build_num() {
    local number_file="${1:-$BUILD_NUMBER_FILEPATH}"

    # Read the current build number from the file
    read -r current_build_number < "$number_file"

    # Increment the build number
    new_build_number=$((current_build_number + 1))

    # Write the new build number back to the file
    echo "$new_build_number" > "$BUILD_NUMBER_FILEPATH"

    # Check if the new build number has fewer than 5 digits
    if ((new_build_number < 10000)); then
        # Format the new build number with leading zeros
        formatted_build_number=$(printf "%05d" "$new_build_number")
    else
        # Use the new build number as is
        formatted_build_number=$new_build_number
    fi

    # Return it to be used elsewhere
    echo "$formatted_build_number"
}

get_dbrepl_status() {
    local db_repl_on

    read -p "*** Enable database replication? (y/n): " choice

    # Convert the user's input to lowercase for easier comparison
    choice=${choice,,}

    # Check if the user's choice is 'y' (yes)
    if [[ "$choice" == "y" ]]; then
        db_repl_on=true
    else
        db_repl_on=false
    fi

    echo "$db_repl_on"
}

supervisor_action() {
    local action=$1
    local service=$2

    # NOTE: we need to access the container as `root` here
    docker exec -it porta bash -c "supervisorctl $action $service"
}

supervisor_restart_all_workers() {
    print_terminal_message "Restarting Porta workers..."
#    supervisor_action "restart" "porta-worker:*"
    supervisor_action "restart" "porta-task-worker:*"
    supervisor_action "restart" "porta-horizon-worker:*"
}

supervisor_start_all_workers() {
    print_terminal_message "Starting Porta workers..."
#    supervisor_action "start" "porta-worker:*"
    supervisor_action "start" "porta-task-worker:*"
    supervisor_action "start" "porta-horizon-worker:*"
}

supervisor_stop_all_workers() {
    print_terminal_message "Stopping Porta workers..."
#    supervisor_action "stop" "porta-worker:*"
    supervisor_action "stop" "porta-task-worker:*"
    supervisor_action "stop" "porta-horizon-worker:*"
}

ask_migrate_or_seed() {
    local db_name=$1
    # See if we should still migrate and/or seed the database even if not bootstrapping
    # i.e., If the update was done without bringing down the replication group,
    #       so there was no migration/seed done on the primary machine
    read -e -p "*** Should the database be migrated? (Answer 'n' unless instructed) (y/n): " -i "n" should_migrate

    read -e -p "*** Should the database be seeded? (Answer 'n' unless instructed) (y/n): " -i "n" should_seed


    if [ "$should_migrate" == "y" ]; then
        if [ "$should_seed" == "y" ]; then
            # Wait for the database to be ready and migrate + seed
            wait_for_db_and_migrate "$(dirname "${BASH_SOURCE[0]}")" 0 "$db_name" # bash boolean --> 0 is `true`, 1 is `false`
        else
            # Wait for the database to be ready and migrate but DO NOT seed
            wait_for_db_and_migrate "$(dirname "${BASH_SOURCE[0]}")" 1 "$db_name" # bash boolean --> 0 is `true`, 1 is `false`
        fi
    else
        if [ "$should_seed" == "y" ]; then
            ## no migration, just seed
            wait_for_db_and_seed "$db_name"
        fi
    fi
}

fixcrlf() {
    # default to current dir
    local path="${1:-.}"
    if [ -z "$path" ]; then
        echo "Usage: remove_carriage_returns /your/directory/path"
    else
        # docker exec -it -u 0 porta bash -c "find \"$path\" -type f -exec sed -i 's/\r$//' {} \;" # ROOT USER
        docker exec -it porta bash -c "find \"$path\" -type f -exec sed -i 's/\r$//' {} \;"
    fi
}

fix_logs_crlf() {
     fixcrlf "/etc"
     fixcrlf "/etc/logrotate.d"
}

fix_laravel_perms() {
    print_terminal_message "Setting API permissions..."
    docker exec -u 0 -it porta bash -c "chown www-data:www-data .env && chown www-data:www-data frontend/.env && chown -R www-data:www-data storage && chown -R www-data:www-data bootstrap/cache"
    docker exec -it porta bash -c "chmod -R 775 storage && chmod -R 775 bootstrap/cache"
}

fix_db_perms() {
    print_terminal_message "Setting DB permissions..."
    local container_name
    docker exec -u 0 -it "$container_name" bash -c "chmod 644 /etc/my.cnf"
}

persist_bootstrap_off() {
    local container_name=$1
    local db_pswd="${2:-porta}"

    print_terminal_message "Persisting group_replication_bootstrap_group OFF for container '$container_name'..."
    docker exec -i "$container_name" mysql -uroot -p"$db_pswd" -e \
        "SET PERSIST group_replication_bootstrap_group=OFF;"
}

# disable exit on error
disable_errexit() {
    set +e
}

# enable exit on error
enable_errexit() {
    set -e
}
