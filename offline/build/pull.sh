#!/bin/bash

##############################################################################################
#  Pull newest on-prem images from docker hub
##############################################################################################

## !! BEFORE RUNNING THIS SCRIPT YOU WILL ALSO NEED TO chmod u+x push.sh

docker pull portadisguise/porta-api
docker pull mysql/mysql-server
docker pull portadisguise/porta-socket
docker pull redis