#!/bin/bash

#########################################################################
## Script to run to migrate and/or seed the database
#########################################################################

## ALWAYS RUN FIRST ##
source "$(dirname "${BASH_SOURCE[0]}")/../lib/head.sh"

db_name=$(determine_db_name "$MACHINE_TYPE")

ask_migrate_or_seed "$db_name"

## ALWAYS RUN LAST ##
source "$(dirname "${BASH_SOURCE[0]}")/../lib/foot.sh"
