#!/bin/bash

# Directory to store the generated files
OUTPUT_DIR="./files"
mkdir -p "$OUTPUT_DIR"

# Function to generate a random filename
generate_filename() {
    PREFIX=$(head /dev/urandom | tr -dc A-Z | head -c 1)
    NUM=$(shuf -i 1-999 -n 1)
    SUFFIX=$(shuf -e Report Data Summary Notes Log -n 1)
    echo "${PREFIX}${NUM}_${SUFFIX}.txt"
}

# Generate 100 files
for i in $(seq 1 100); do
    FILENAME=$(generate_filename)
    FILEPATH="$OUTPUT_DIR/$FILENAME"

    # Generate random file size between 1KB and 100KB
    FILE_SIZE=$((RANDOM % 1000 + 1)) # File size in KB

    # Generate random content and write to file
    head -c "${FILE_SIZE}K" /dev/urandom > "$FILEPATH"

    echo "Generated: $FILEPATH (Size: ${FILE_SIZE}KB)"
done

echo "All files have been generated in $OUTPUT_DIR."
