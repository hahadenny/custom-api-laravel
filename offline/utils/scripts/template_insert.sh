#!/bin/bash

## NOTE: DEPRECATED

#SCRIPT_DIR="$(dirname "$0")" -- dir of whoever called the initially executing script
#SCRIPT_DIR="$(dirname "${BASH_SOURCE[0]}")" # absolute path to THIS script

# !! IMPORTANT NOTE: SQL file already has escaped characters
# (single quote, \n, \r\n converted to \n, blank newlines removed, etc)

# Copy and execute the SQL file into the portadb container to insert template data &
# ensure we have access to d3 templates
docker exec -i porta-db mysql -uroot -pporta < "$(dirname "${BASH_SOURCE[0]}")/../sql/insert_template_data.sql"

# Make sure d3 integration is enabled on start
docker exec -i porta-db mysql -uroot -pporta -e "USE porta; INSERT INTO company_integrations (company_id, type, is_active) VALUES (1, 'disguise', 1);"
