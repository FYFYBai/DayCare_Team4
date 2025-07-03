#!/bin/bash

# Set output file name
OUTPUT_FILE="project-structure.txt"

# Check if 'tree' is available
if command -v tree &> /dev/null
then
echo "Generating project structure using 'tree'..."
tree -a -I 'vendor|node_modules|.git' > "$OUTPUT_FILE"
else
echo "'tree' not installed. Using 'find' instead..."
find . -type d -name 'vendor' -prune -o -name 'node_modules' -prune -o -name '.git' -prune -o -print | \
sed -e 's/[^-][^\/]*\//--/g' -e 's/--/|--/' > "$OUTPUT_FILE"
fi

echo "Project structure saved to $OUTPUT_FILE"