#!/bin/bash

#########################################################################
## Script to run for restarting replication nodes
#########################################################################

## ALWAYS RUN FIRST ##
source "$(dirname "${BASH_SOURCE[0]}")/../lib/head.sh"

docker exec -it "$DB_CONT_NAME" mysql -uporta -pporta -e "SHOW GLOBAL VARIABLES WHERE variable_name LIKE 'group_repl%' OR variable_name = 'server_id' OR variable_name = 'gtid_executed';"

## ALWAYS RUN LAST ##
source "$(dirname "${BASH_SOURCE[0]}")/../lib/foot.sh"
