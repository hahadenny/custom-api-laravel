#!/bin/bash

#########################################################################
## Script to run for restarting replication nodes
#########################################################################

## ALWAYS RUN FIRST ##
source "$(dirname "${BASH_SOURCE[0]}")/../lib/head.sh"

docker exec -it "$DB_CONT_NAME" mysql -uporta -pporta -e "SET PERSIST group_replication_bootstrap_group=$BOOTSTRAP; STOP GROUP_REPLICATION;" && docker exec -it "$DB_CONT_NAME" mysql -uporta -pporta -e "START GROUP_REPLICATION;"  && docker exec -it "$DB_CONT_NAME" mysql -uporta -pporta -e "SET PERSIST group_replication_bootstrap_group=OFF;"

if [ "$BOOTSTRAP" == "ON" ]; then
    ask_migrate_or_seed "$DB_CONT_NAME"
fi

## ALWAYS RUN LAST ##
source "$(dirname "${BASH_SOURCE[0]}")/../lib/foot.sh"
