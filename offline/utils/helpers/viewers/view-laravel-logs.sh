#!/bin/bash

#########################################################################
## Scripts to run for data restoration using an existing backup
#########################################################################

## ALWAYS RUN FIRST ##
source "$(dirname "${BASH_SOURCE[0]}")/../lib/head.sh"

# Get a list of the latest log file names from the API container
# The name will look something like `laravel-Y-m-d.log`

# Run the script inside the container
# set max number of files to show
list_max=15
log_files=($(docker exec porta find "$LARAVEL_LOGS_PATH" -maxdepth 1 -type f -name "*.log" -exec basename {} \; | sort -r | head -n $list_max))

# Check if there are any log files
if [ ${#log_files[@]} -eq 0 ]; then
    echo "No log files found in $LARAVEL_LOGS_PATH."
    exit 1
fi

##
## Prompt the user to choose the file to restore from
##
selected_option=""
while true; do
    # Display options
    echo "" # newline
    echo "Choose a log file (from the $list_max most recent logs):"
    for ((i=0; i<${#log_files[@]}; i++)); do
        echo "$((i+1))) ${log_files[i]}"
    done
    read -p "Enter the number of your choice: " selection
    if [[ "$selection" =~ ^[0-9]+$ && $selection -ge 1 && $selection -le ${#log_files[@]} ]]; then
        # break out of the loop and continue with the script
        selected_option="${log_files[$((selection-1))]}"
        LOGFILE="$LARAVEL_LOGS_PATH/$selected_option"
        break
    else
        echo "Invalid selection, please enter a valid number."
    fi
done

# Use the selected option
print_terminal_message "Printing $LOGFILE..."

# Show log file
docker exec -it porta bash -c "cat $LOGFILE"

## ALWAYS RUN LAST ##
source "$(dirname "${BASH_SOURCE[0]}")/../lib/foot.sh"
