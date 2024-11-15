#!/bin/bash

#########################################################################
## Script to run for restarting replication nodes
#########################################################################

## ALWAYS RUN FIRST ##
source "$(dirname "${BASH_SOURCE[0]}")/../lib/head.sh"

docker exec -it "$DB_CONT_NAME" mysql -uroot -pporta -e "select * from performance_schema.replication_applier_status_by_worker\G"


## ALWAYS RUN LAST ##
source "$(dirname "${BASH_SOURCE[0]}")/../lib/foot.sh"