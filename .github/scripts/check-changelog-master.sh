#!/usr/bin/env bash
set -euo pipefail

# Source the shared library
source "$(dirname "$0")/lib.sh"

if [[ ! -d "$CHANGELOG_DIR" ]]; then
  error "$CHANGELOG_DIR directory missing"
fi

# Count changelog files (excluding .gitkeep and backup directories)
changelog_count=$(find "$CHANGELOG_DIR" -maxdepth 1 -type f ! -name .gitkeep -print 2>/dev/null | wc -l)
if [[ $changelog_count -gt 0 ]]; then
  # List the files for better error reporting
  unconsolidated_files=$(find "$CHANGELOG_DIR" -maxdepth 1 -type f ! -name .gitkeep -print 2>/dev/null | head -5)
  error "Found $changelog_count changelog file(s) in $CHANGELOG_DIR that should have been consolidated. Files: $unconsolidated_files"
fi

echo "Changelog check (master) passed."
