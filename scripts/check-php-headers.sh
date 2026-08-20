#!/bin/bash

# php file should start with <?php

set -e

directories=(
  "drupal/custom-modules"
  "drupal/custom-themes"
)

extensions=(
  "*.php"
  "*.module"
  "*.install"
  "*.theme"
  "*.profile"
)

failed=0

for directory in "${directories[@]}"; do
  for extension in "${extensions[@]}"; do
    while IFS= read -r -d '' file; do
      if ! head -n 1 "$file" | grep -q '^<?php'; then
        echo "ERROR: $file does not start with <?php"
        failed=1
      fi
    done < <(find "$directory" -type f -name "$extension" -print0)
  done
done

if [ "$failed" -ne 0 ]; then
  echo
  echo "PHP header check failed."
  exit 1
fi

echo "PHP header check passed."
