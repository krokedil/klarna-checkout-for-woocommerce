#!/usr/bin/env bash
set -euo pipefail

# Purpose: Prepare the plugin with the needed build steps for production release.
# This ensures we perform the same build steps both in all relevant workflows, such as when deploying a new release or creating dev zips.

echo "[plugin-build] Step 1: Build the plugin with dev dependencies to scope the dependency packages..." >&2
composer install

echo "[plugin-build] Step 2: Build the plugin for release..." >&2
composer install --no-dev

echo "[plugin-build] Completed plugin production build." >&2
