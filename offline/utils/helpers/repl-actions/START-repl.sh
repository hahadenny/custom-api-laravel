#!/bin/bash

#########################################################################
## Script to run for restarting replication nodes
#########################################################################

## ALWAYS RUN FIRST ##
source "$(dirname "${BASH_SOURCE[0]}")/../lib/head.sh"


docker exec -it "$DB_CONT_NAME" mysql -uporta -pporta -e "START GROUP_REPLICATION;"


## ALWAYS RUN LAST ##
source "$(dirname "${BASH_SOURCE[0]}")/../lib/foot.sh"
