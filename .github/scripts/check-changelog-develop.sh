#!/usr/bin/env bash
set -euo pipefail

# Source the shared library
source "$(dirname "$0")/lib.sh"

base_sha="$1"  # base
head_sha="$2"  # head

if [[ -z "$base_sha" || -z "$head_sha" ]]; then
  error "Missing base/head SHAs"
fi

# Validate SHA format (must be 40 hex characters or short SHA of 7-40 chars)
if [[ ! "$base_sha" =~ ^[0-9a-f]{7,40}$ ]]; then
  error "Invalid base SHA format: $base_sha (must be 7-40 hex characters)"
fi

if [[ ! "$head_sha" =~ ^[0-9a-f]{7,40}$ ]]; then
  error "Invalid head SHA format: $head_sha (must be 7-40 hex characters)"
fi

has_changes=0
has_changelog=0

# Use process substitution to safely handle filenames with spaces while avoiding subshell
while IFS= read -r file; do
  # Check if this file should trigger changelog requirement
  if is_monitored_file "$file"; then
    has_changes=1
  fi

  # Check if this is a changelog file
  if [[ "$file" == "$CHANGELOG_DIR"/* ]]; then
    has_changelog=1
  fi
done < <(git diff --name-only "$base_sha" "$head_sha")

if [[ $has_changes -eq 1 && $has_changelog -eq 0 ]]; then
  error "No changelog file found in $CHANGELOG_DIR for code changes. Add new file (YYYYMMDD-short-description.md)."
fi

echo "Changelog check (develop) passed."
