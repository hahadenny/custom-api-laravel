#!/bin/bash

#########################################################################
## Script to run for restarting replication nodes
#########################################################################

## ALWAYS RUN FIRST ##
source "$(dirname "${BASH_SOURCE[0]}")/../lib/head.sh"

docker logs "$DB_CONT_NAME"

## ALWAYS RUN LAST ##
source "$(dirname "${BASH_SOURCE[0]}")/../lib/foot.sh"
