#!/bin/bash

##############################################################################################
#  Build all on-prem images from source
#      Soft reset docker -- this will delete ALL images not currently in use
#      (databases/volumes/mounts will be left alone)
##############################################################################################

EXEC_DIR="$(dirname $0)" # -- dir of whoever called the initially executing script
SCRIPT_DIR="$(dirname "${BASH_SOURCE[0]}")" # absolute path to THIS script

## SET PERMISSIONS ##

## !! BEFORE RUNNING THIS SCRIPT YOU WILL ALSO NEED TO chmod u+x IT

chmod -R u+x .
chmod -R u+x "$SCRIPT_DIR/../utils"

## VARS ##
source "$SCRIPT_DIR/../utils/vars.sh"

## FUNCTIONS ###
source "$SCRIPT_DIR/../utils/functions.sh"

####################################################################################################

# Set up the custom error handler to run when errexit occurs
trap custom_error_handler ERR

# Enable errexit (exit on error)
set -e

DEFAULT_API_BRANCH="dev" # prod is `dev-k8s`
DEFAULT_FRONTEND_BRANCH="dev" # prod is `main`
DEFAULT_SOCKET_BRANCH="dev" # prod is `dev-auto-server-creation`

##############################################################
## PULL FROM REPOS
##############################################################

print_terminal_message "Removing build code..."
rm -rf ./code
#rmdir ./code

## !NOTE: Make sure ssh agent with github key is running




## DEBUGGING ##
#docker build --no-cache -t "portadisguise/porta-glances" --progress=plain -f "./build/docker/Dockerfile-glances" "./build/docker/"
#docker build --no-cache -t "portadisguise/porta-prometheus" --progress=plain -f "./build/docker/Dockerfile-prometheus" "./build/docker/"
#docker build --no-cache -t "portadisguise/porta-grafana" --progress=plain -f "./build/docker/Dockerfile-grafana" "./build/docker/"
#
## TODO: MOVE TO INSTALL DIR
#docker compose -f "./build/docker/docker-compose.yml" up -d
#exit
##docker compose -f "./build/docker/docker-compose.yml" down




# Make sure code dir exists for pulling from repos
mkdir -p ./code

# Prompt the user for the API branch to use
read -e -p "Enter the porta-api branch to use for the build: " -i "$DEFAULT_API_BRANCH" API_BRANCH

# Check if API_BRANCH is not empty
if [ -z "$API_BRANCH" ]; then
  API_BRANCH="$DEFAULT_API_BRANCH"
  print_terminal_message "Using default API branch '$DEFAULT_API_BRANCH'"
else
  print_terminal_message "Using API branch '$API_BRANCH'"
fi

# Prompt the user for the frontend branch to use
read -e -p "Enter the porta (FRONT END) branch to use for the build: " -i "$DEFAULT_FRONTEND_BRANCH" APP_BRANCH

# Check if APP_BRANCH is not empty
if [ -z "$APP_BRANCH" ]; then
  APP_BRANCH="$DEFAULT_FRONTEND_BRANCH"
  print_terminal_message "Using default frontend branch '$DEFAULT_FRONTEND_BRANCH'"
else
  print_terminal_message "Using frontend branch '$APP_BRANCH'"
fi

# Prompt the user for the socket branch to use
read -e -p "Enter the porta-socket-server branch to use for the build: " -i "$DEFAULT_SOCKET_BRANCH" SOCKET_BRANCH

# Check if SOCKET_BRANCH is not empty
if [ -z "$SOCKET_BRANCH" ]; then
  SOCKET_BRANCH="$DEFAULT_SOCKET_BRANCH"
  print_terminal_message "Using default frontend branch '$DEFAULT_SOCKET_BRANCH'"
else
  print_terminal_message "Using frontend branch '$SOCKET_BRANCH'"
fi


##############################################################
# API

print_terminal_message "Pulling porta-api from repo into $(dirname "${BASH_SOURCE[0]}")/code..."
# create offline/code/porta-api
mkdir -p ./code/porta-api
git clone git@github.com:polygonlabs-devs/porta-api.git ./code/porta-api
cd ./code/porta-api || exit
git checkout "$API_BRANCH"


# @see https://semver.org/
# Given a version number MAJOR.MINOR.PATCH, increment the:
#
#   MAJOR version for incompatible API changes
#       may include minor and patch level changes
#       MINOR and PATCH versions must be set to 0
#
#   MINOR version for backward compatible changes
#       deprecations
#       new functionality/improvements in private code
#       may include PATCH changes
#       PATCH version must be set to 0
#
#   PATCH version for backward compatible bug fixes
#
#   PRE-RELEASE -- append a hyphen and a series of dot separated identifiers [0-9A-Za-z-]
#       i.e., 1.0.0-alpha, 1.0.0-alpha.1
#       unstable
#       may not satisfy expected requirements
#   BUILD METADATA -- append a plus and a series of dot separated identifiers [0-9A-Za-z-]
#       i.e., 1.0.0-alpha+001, 1.0.0+20230313144700
#       does NOT denote precedence
#

########################################################
# MAJOR.MINOR.PATCH-PRE.RELEASE+BUILD.METADATA
########################################################

## !! NOTE: !! the following won't work globally because each repo has a different long tag and branch name
    #long_tag=$(git describe --tags)
    #branch="$API_BRANCH" #$(git branch --show-current)
    #VERSION="${long_tag}_${branch}"
    #SHORT_VERSION="${SEMVER}_${branch}"
    # latest short commit hash of specific branch
    #latest_hash=$(git log -n 1 --pretty=format:"%h" "$branch")

# !!NOTE: doesn't always show the most recent tag
# $ git describe --tags --abbrev=0
#   v2.1

# $ git log --pretty="%h" -n1 HEAD
#   7d69de37

# $ git log -n1 --pretty=%ci HEAD
#   2023-12-06 15:02:44 -0500

# $ git describe --tags
#   v2.1-756-g7d69de37

#short_tag=$(git describe --tags --abbrev=0) # <-- sometimes this doesn't show the most recent tag

# display the tag name and the short commit hash, ordered by tag name lexically
# git for-each-ref --sort='-version:refname' --format '%(refname:short) %(objectname:short)' refs/tags | head -n 1

# display the tag name and the short commit hash, ordered by tag name lexically, then by taggerdate
# !NOTE: does not always sort correctly
# git for-each-ref --sort='-version:refname'  --sort='-taggerdate' --format '%(refname:short) %(objectname:short)' refs/tags | head -n 1

####
# Determine the build number to use
# TODO: This should be stored somewhere centralized so it can be incremented by any machine
####
BUILD_NUMBER=$(update_build_num "$BUILD_NUMBER_FILEPATH")
BUILD_TAG="$BUILD_NUMBER"
echo "BUILD_NUMBER --> $BUILD_NUMBER";
echo "BUILD_TAG --> $BUILD_TAG";
export BUILD_NUMBER
export BUILD_TAG

####
# Determine the version number to use
# Note: We can do this one time because each repo has the same tag name
# MAJOR.MINOR.PATCH-PRE.RELEASE+BUILD.METADATA
########################################################

# get the "most recent" tag name (sorting by date and name)
# excludes tags that begin with 'v' (i.e., 'v2.0.1') since those pre-date this build system and mess with the sorted order
old_tag=$(git for-each-ref --sort='-taggerdate' --sort='-version:refname' --format '%(refname:short)' refs/tags | grep -v '^v' | head -n 1)  #$(git tag --sort=-version:refname | head -n 1)
# test value:
#old_tag="v2.0.1-prod.1.2+12346523-234"
# Remove PRE-RELEASE and META-DATA suffixes (if any) so we can get the number to increment
tag_without_suffixes="${old_tag%%-*}"
# Remove leading 'v' if present
tag_without_suffixes="${tag_without_suffixes#v}"
# Increment the patch version
VERSION_NUMBER=$(echo "$tag_without_suffixes" | awk -F. -v OFS=. '{$NF++;print}')

echo "old_tag --> $old_tag";
echo "tag_without_suffixes --> $tag_without_suffixes";
echo "VERSION_NUMBER (auto) --> $VERSION_NUMBER";
echo "All tags:";
echo "$(git tag --sort='-version:refname')";

read -e -p " New version number has been incremented from '$old_tag' ($tag_without_suffixes) to '$VERSION_NUMBER' -- confirm the version number to be used: " -i "$VERSION_NUMBER" input_num

# set new tag var/value
if [[ "$input_num" != "" ]]; then
    # a custom tag was given
    VERSION_NUMBER="$input_num"
fi
VERSION_DATE=$(date +'%Y%m%d.%H%M%S.%z') # Ymd.HMS.timezone
VERSION_UNIXDATE=$(date +'%s') # Unix timestamp

# Write the version number to file
if [ ! -e "$VERSION_NUMBER_FILEPATH" ]; then
    # Make sure the file exists
    touch "$VERSION_NUMBER_FILEPATH"
fi
echo "$VERSION_NUMBER" > "$VERSION_NUMBER_FILEPATH"


# Write the version "tag" to file
if [ ! -e "$VERSION_FILEPATH" ]; then
    # Make sure the file exists
    touch "$VERSION_FILEPATH"
fi

# TODO: if $branch !== the default branch, then append branch name as "pre-release" name. Otherwise just use version number
# default to using the API's branch name
# should probably check the branch names for the one that doesn't match the others and use that but this is fine for now

# replace any non-alphanumeric, `-`, `+`, or `.` characters with `_` to avoid directory path issues
SANI_API_BRANCH="${API_BRANCH//[^a-zA-Z0-9-\.\+]/_}"
echo "$VERSION_NUMBER-$SANI_API_BRANCH+$BUILD_TAG" > "$VERSION_FILEPATH"

export VERSION
export VERSION_NUMBER
export VERSION_DATE
export VERSION_UNIXDATE
echo "VERSION --> $VERSION";
echo "VERSION_NUMBER --> $VERSION_NUMBER";
echo "VERSION_DATE --> $VERSION_DATE";
echo "VERSION_UNIXDATE --> $VERSION_UNIXDATE";

#######################
# API SPECIFIC
#######################

#    features/staging-PN-990-PN-991-build-versioning

#
# Get info to build the version data
# MAJOR.MINOR.PATCH-PRE.RELEASE+BUILD.METADATA
#
# TODO: if $branch !== the default branch, then append branch name as "pre-release" name. Otherwise just use version number
#       ideally we would only do this once all the default branches are `main` and not weird `dev-k8s`, etc
#API_VERSION="${VERSION_NUMBER}-${API_BRANCH}+${VERSION_UNIXDATE}"
API_VERSION="${VERSION_NUMBER}-${API_BRANCH}+${BUILD_TAG}"
# replace any non- alphanumeric, `-`, `+`, or `.` characters with `_` to avoid directory path issues
API_VERSION="${API_VERSION//[^a-zA-Z0-9-\.\+]/_}"

# Write the version to file
if [ ! -e "$API_VERSION_FILEPATH" ]; then
    # Make sure the file exists
    touch "$API_VERSION_FILEPATH"
fi
echo "$API_VERSION" > "$API_VERSION_FILEPATH"

# Use these to update API .env file with new version(?) or update the tag in Dockerfile (since .env "shouldn't be versioned")
export API_VERSION
echo "API_VERSION --> $API_VERSION";

# return to original dir
cd - || exit

###############################################################
## FRONT END

print_terminal_message "Pulling front end code from repo..."
# create offline/code/porta-api/frontend
mkdir -p ./code/porta-api/frontend
git clone git@github.com:polygonlabs-devs/porta.git ./code/porta-api/frontend
cd ./code/porta-api/frontend || exit
git checkout "$APP_BRANCH"

#
# Get info to build the version data
# MAJOR.MINOR.PATCH-PRE.RELEASE+BUILD.METADATA
#
# TODO: if $branch !== the default branch, then append branch name as pre-release name. Otherwise just use version number
#       ideally we would only do this once all the default branches are `main` and not weird `dev-k8s`, etc
#FRONTEND_VERSION="${VERSION_NUMBER}-${APP_BRANCH}+${VERSION_UNIXDATE}"
FRONTEND_VERSION="${VERSION_NUMBER}-${APP_BRANCH}+${BUILD_TAG}"
# replace any non-alphanumeric, `-`, `+`, or `.` characters with `_` to avoid directory path issues
FRONTEND_VERSION="${FRONTEND_VERSION//[^a-zA-Z0-9-\.\+]/_}"

# Write the version to file
if [ ! -e "$FRONTEND_VERSION_FILEPATH" ]; then
    # Make sure the file exists
    touch "$FRONTEND_VERSION_FILEPATH"
fi
echo "$FRONTEND_VERSION" > "$FRONTEND_VERSION_FILEPATH"

# Use these to update APP .env file with new version(?) or update the tag in Dockerfile (since .env "shouldn't be versioned")
export FRONTEND_VERSION
echo "FRONTEND_VERSION --> $FRONTEND_VERSION";

# return to original dir
cd - || exit

##############################################################
# SOCKET

print_terminal_message "Pulling porta-socket-server code from repo..."
# create offline/code/porta-socket
mkdir -p ./code/porta-socket-server
git clone git@github.com:polygonlabs-devs/porta-socket-server.git ./code/porta-socket-server
cd ./code/porta-socket-server || exit
git checkout "$SOCKET_BRANCH"

#
# Get info to build the version data
# MAJOR.MINOR.PATCH-PRE.RELEASE+BUILD.METADATA
#
# TODO: if $branch !== the default branch, then append branch name as pre-release name. Otherwise just use version number
#       ideally we would only do this once all the default branches are `main` and not weird `dev-k8s`, etc
#SOCKET_VERSION="${VERSION_NUMBER}-${SOCKET_BRANCH}+${VERSION_UNIXDATE}"
SOCKET_VERSION="${VERSION_NUMBER}-${SOCKET_BRANCH}+${BUILD_TAG}"
# replace any non-alphanumeric, `-`, `+`, or `.` characters with `_` to avoid directory path issues
SOCKET_VERSION="${SOCKET_VERSION//[^a-zA-Z0-9-\.\+]/_}"

# Write the version to file
if [ ! -e "$SOCKET_VERSION_FILEPATH" ]; then
    # Make sure the file exists
    touch "$SOCKET_VERSION_FILEPATH"
fi
echo "$SOCKET_VERSION" > "$SOCKET_VERSION_FILEPATH"

# Use these to update SOCKET .env file with new version(?) or update the tag in Dockerfile (since .env "shouldn't be versioned")
export SOCKET_VERSION
echo "SOCKET_VERSION --> $SOCKET_VERSION";

# return to original dir
cd - || exit

##############################################################
## SOFT RESET DOCKER
##############################################################
#stop_and_delete_containers "porta" "porta-socket" "porta-redis" "porta-db" "porta-db-2" "porta-db-3"

##############################################################
## BUILD IMAGES
##############################################################

## BASE API IMAGE & PORTA FRONT + API
# set version info in API .env file
sed -i "s|APP_VERSION=.*|APP_VERSION='$API_VERSION'|" ./install/config/.env-api;
sed -i "s|APP_VERSION_NUMBER=.*|APP_VERSION_NUMBER='$VERSION_NUMBER'|" ./install/config/.env-api;
sed -i "s|APP_VERSION_DATE=.*|APP_VERSION_DATE='$VERSION_DATE'|" ./install/config/.env-api;
sed -i "s|APP_BUILD_NUMBER=.*|APP_BUILD_NUMBER='$BUILD_NUMBER'|" ./install/config/.env-api;

# set version info in FRONTEND .env file
sed -i "s|REACT_APP_VERSION=.*|REACT_APP_VERSION='$FRONTEND_VERSION'|" ./install/config/.env-app;
sed -i "s|REACT_APP_VERSION_NUMBER=.*|REACT_APP_VERSION_NUMBER='$VERSION_NUMBER'|" ./install/config/.env-app;
sed -i "s|REACT_APP_VERSION_DATE=.*|REACT_APP_VERSION_DATE='$VERSION_DATE'|" ./install/config/.env-app;
sed -i "s|REACT_APP_BUILD_NUMBER=.*|REACT_APP_BUILD_NUMBER='$BUILD_NUMBER'|" ./install/config/.env-app;

build_porta_api_images

## REDIS
docker pull redis:7.4.0

## SOCKET
# set version info in SOCKET .env file
sed -i "s|APP_VERSION=.*|APP_VERSION='$SOCKET_VERSION'|" ./install/config/.env-socket;
sed -i "s|APP_VERSION_NUMBER=.*|APP_VERSION_NUMBER='$VERSION_NUMBER'|" ./install/config/.env-socket;
sed -i "s|APP_VERSION_DATE=.*|APP_VERSION_DATE='$VERSION_DATE'|" ./install/config/.env-socket;
sed -i "s|APP_BUILD_NUMBER=.*|APP_BUILD_NUMBER='$BUILD_NUMBER'|" ./install/config/.env-socket;

build_socket_image

# MYSQL
docker pull mysql/mysql-server:8.0.32

# Custom monitoring containers
docker build -t "portadisguise/porta-glances" --progress=plain -f "./build/docker/Dockerfile-glances" "./build/docker/"
#docker build -t "portadisguise/porta-prometheus" --progress=plain -f "./build/docker/Dockerfile-prometheus" "./build/docker/"
#docker build -t "portadisguise/porta-grafana" --progress=plain -f "./build/docker/Dockerfile-grafana" "./build/docker/"

#docker build --no-cache -t "portadisguise/porta-glances" --progress=plain -f "./build/docker/Dockerfile-glances" "./build/docker/"
#docker build --no-cache -t "portadisguise/porta-prometheus" --progress=plain -f "./build/docker/Dockerfile-prometheus" "./build/docker/"
#docker build --no-cache -t "portadisguise/porta-grafana" --progress=plain -f "./build/docker/Dockerfile-grafana" "./build/docker/"

# Node Exporter
#docker pull prom/node-exporter:v1.8.2
# cAdvisor
#docker pull gcr.io/cadvisor/cadvisor:v0.49.1
# Portainer
docker pull portainer/portainer-ce:2.20.3


##############################################################
## TAG THE BUILDS IN THE REPOS
##############################################################

echo "BUILD_NUMBER --> $BUILD_NUMBER";
echo "BUILD_TAG --> $BUILD_TAG";
echo "VERSION --> $VERSION";
echo "VERSION_NUMBER --> $VERSION_NUMBER";
echo "VERSION_DATE --> $VERSION_DATE";
echo "VERSION_UNIXDATE --> $VERSION_UNIXDATE";
echo "API_VERSION --> $API_VERSION";
echo "FRONTEND_VERSION --> $FRONTEND_VERSION";
echo "SOCKET_VERSION --> $SOCKET_VERSION";

read -p " Commit & push new version '$VERSION_NUMBER' to each repo branch and docker hub? (y/n): " choice
if [[ "$choice" == "y" ]]; then

    print_terminal_message "Committing '$VERSION_NUMBER' to repos..."

    # Make sure if tags were deleted remotely that we delete them locally
    git fetch origin 'refs/tags/*:refs/tags/*'

    # API
    cd ./code/porta-api || exit
    repo="porta-api"
    branch="$API_BRANCH"
    version="$API_VERSION"
    git checkout "$branch"
    git tag "$version"
    print_terminal_message "Pushing '$version' (build $BUILD_NUMBER) to the $repo '$branch' branch..."
    git push origin "$version"

    # docker doesn't allow + in tags; replace any non-alphanumeric, `-`, or `.` characters with `_`
    docker_tag="${version//[^a-zA-Z0-9-\.]/_}"
    docker tag "portadisguise/porta-api-base" "portadisguise/porta-api-base:$docker_tag"
    docker tag "portadisguise/porta-api" "portadisguise/porta-api:$docker_tag"

#    print_terminal_message "Pushing '$version' porta-api & porta-api-base images to docker hub..."
#    docker push portadisguise/porta-api-base
#    docker push "portadisguise/porta-api-base:$docker_tag"
#    docker push portadisguise/porta-api
#    docker push "portadisguise/porta-api:$docker_tag"

    # return to original dir
    cd - || exit

    ## APP
    cd ./code/porta-api/frontend || exit
    repo="porta"
    branch="$APP_BRANCH"
    version="$FRONTEND_VERSION"
    git checkout "$branch"
    git tag "$version"
    print_terminal_message "Pushing '$version' (build $BUILD_NUMBER) to the $repo '$branch' branch..."
    git push origin "$version"

    # return to original dir
    cd - || exit

    ## SOCKET
    cd ./code/porta-socket-server || exit
    repo="porta-socket-server"
    branch="$SOCKET_BRANCH"
    version="$SOCKET_VERSION"
    git checkout "$branch"
    git tag "$version"
    print_terminal_message "Pushing '$version' (build $BUILD_NUMBER) to the $repo '$branch' branch..."
    git push origin "$version"

    # docker doesn't allow + in tags
    docker_tag="${version//[^a-zA-Z0-9-\.]/_}"
    docker tag "portadisguise/porta-socket" "portadisguise/porta-socket:$docker_tag"

#    print_terminal_message "Pushing '$docker_tag' porta-socket images to docker hub..."
#    docker push portadisguise/porta-socket
#    docker push "portadisguise/porta-socket:$docker_tag"

    # return to original dir
    cd - || exit

    # custom docker images
    # TODO: give own version ?
    version="$API_VERSION"

    # docker doesn't allow + in tags; replace any non-alphanumeric, `-`, or `.` characters with `_`
    docker_tag="${version//[^a-zA-Z0-9-\.]/_}"
    docker tag "portadisguise/porta-glances" "portadisguise/porta-glances:$docker_tag"
    docker tag "portadisguise/porta-prometheus" "portadisguise/porta-prometheus:$docker_tag"
    docker tag "portadisguise/porta-grafana" "portadisguise/porta-grafana:$docker_tag"

#    print_terminal_message "Pushing '$version' porta-glances, porta-prometheus, porta-grafana images to docker hub..."
#    docker push portadisguise/porta-glances
#    docker push portadisguise/porta-prometheus
#    docker push portadisguise/porta-grafana
#    docker push "portadisguise/porta-glances:$docker_tag"
#    docker push "portadisguise/porta-prometheus:$docker_tag"
#    docker push "portadisguise/porta-grafana:$docker_tag"

else
    print_terminal_message " !! Tag commit declined. Version was not saved to repos. !!"
fi

## Push to docker hub?
#docker push portadisguise/porta-api-base:latest && \
#docker push portadisguise/porta-api:latest && \
#docker push portadisguise/porta-socket:latest

print_terminal_message "Builds completed."

print_terminal_message "Removing build code..."
rm -rf ./code

# Disable errexit (optional, if you want to continue with the script)
set +e
