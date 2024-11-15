#!/bin/bash

## VARS ##
source "$(dirname "${BASH_SOURCE[0]}")/../utils/vars.sh"

## FUNCTIONS ###
source "$(dirname "${BASH_SOURCE[0]}")/../utils/functions.sh"

################################################################

# Directory containing the files
directory="/tmp/mysql"

# Iterate over files in the directory
for file in "$directory"/*; do
    # Check if it's a regular file
    if [ -f "$file" ]; then
        # Check if the file name does not end with ".bak"
        if [[ "$file" != *.bak ]]; then
            # Generate the new file name by adding a ".bak" extension
            new_name="${file}.bak"

            # Rename the file
            sudo mv "$file" "$new_name"

            # Copy it back to the original name
            sudo cp -a "$new_name" "$file"

            # Optional: Verify the renaming and copying
            echo "Renamed and copied: $file -> $new_name -> $file"
        fi
    fi
done

# Use the find command to locate files ending with two or more ".bak" extensions
files_to_delete=$(find "$directory" -type f -name "*.bak.bak*")

# Check if any files match the criteria
if [ -n "$files_to_delete" ]; then
    # Loop through the files and delete each one
    for file in $files_to_delete; do
        rm "$file"
        echo "Deleted: $file"
    done
else
    echo "No files matching the criteria found."
fi
