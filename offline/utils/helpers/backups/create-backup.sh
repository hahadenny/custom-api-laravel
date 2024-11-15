#!/bin/bash

#########################################################################
## Script to run for manually creating a database backup
#########################################################################

## ALWAYS RUN FIRST ##
source "$(dirname "${BASH_SOURCE[0]}")/../lib/head.sh"

docker exec -it porta bash -c "php artisan backup:run --only-db --disable-notifications"

## ALWAYS RUN LAST ##
source "$(dirname "${BASH_SOURCE[0]}")/../lib/foot.sh"
