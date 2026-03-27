#!/usr/bin/env bash
set -euo pipefail

# Source the shared library
source "$(dirname "$0")/lib.sh"

# Check file existence based on configuration
plugin_file=$(get_plugin_file)
if [[ ! -f "$plugin_file" ]]; then
  error "Plugin file not found: $plugin_file. Please ensure the file exists or update PLUGIN_FILE in config.sh"
fi

if [[ "$REQUIRE_README" == "true" ]] && [[ ! -f "$README_FILE" ]]; then
  error "Required file not found: $README_FILE. Set REQUIRE_README=false in config.sh if readme.txt is optional"
elif [[ ! -f "$README_FILE" ]]; then
  warn "Skipping readme.txt checks (file not required per config)"
fi

# Get version information
readme_version=""
if [[ -f "$README_FILE" ]]; then
  readme_version=$(get_readme_version)
fi

plugin_version=$(get_plugin_version)
plugin_constant_version=$(get_plugin_constant_version)
class_constant_version=""
if [[ -n "$VERSION_CONSTANT_FILE" && -f "$VERSION_CONSTANT_FILE" ]]; then
  class_constant_version=$(get_class_constant_version)
fi

changelog_version=""
if [[ -n "$CHANGELOG_FILE" && -f "$CHANGELOG_FILE" ]]; then
  changelog_version=$(get_changelog_version)
elif [[ "$REQUIRE_CHANGELOG_FILE" == "true" ]]; then
  error "Required file not found: $CHANGELOG_FILE. Set REQUIRE_CHANGELOG_FILE=false in config.sh if changelog.txt is optional"
fi

kernl_version=""
if [[ -n "$KERNL_VERSION_FILE" && -f "$KERNL_VERSION_FILE" ]]; then
  kernl_version=$(get_kernl_version)
fi

kernl_changelog_version=""
if [[ -n "$KERNL_CHANGELOG_FILE" && -f "$KERNL_CHANGELOG_FILE" ]]; then
  kernl_changelog_version=$(get_kernl_changelog_version)
fi

# Validate required versions were found
if [[ -z "$plugin_version" ]]; then
  error "Could not parse Version from plugin file header in $plugin_file. Ensure the file has a 'Version: X.Y.Z' comment"
fi

if [[ "$REQUIRE_README" == "true" ]] && [[ -z "$readme_version" ]]; then
  error "Could not parse 'Stable tag' from $README_FILE. Ensure the file has a 'Stable tag: X.Y.Z' line"
fi

if [[ "$REQUIRE_VERSION_CONSTANT" == "true" ]] && [[ -z "$plugin_constant_version" ]]; then
  error "Could not find version constant in $plugin_file. Either define constant or set REQUIRE_VERSION_CONSTANT=false in config.sh"
fi

# Perform version consistency checks
if [[ -n "$readme_version" && "$readme_version" != "trunk" ]]; then
  if [[ "$readme_version" != "$plugin_version" ]]; then
    error "Version mismatch: readme.txt Stable tag ($readme_version) does not match plugin header Version ($plugin_version). Update both to match."
  fi

  if [[ -n "$plugin_constant_version" && "$plugin_version" != "$plugin_constant_version" ]]; then
    error "Version mismatch: Plugin header Version ($plugin_version) does not match constant $VERSION_CONSTANT_NAME ($plugin_constant_version). Update $plugin_file to match."
  fi

  if [[ -n "$class_constant_version" && "$plugin_version" != "$class_constant_version" ]]; then
    error "Version mismatch: Plugin header Version ($plugin_version) does not match class constant $VERSION_CONSTANT_NAME in $VERSION_CONSTANT_FILE ($class_constant_version). Update $VERSION_CONSTANT_FILE to match."
  fi

  if [[ -n "$changelog_version" && "$plugin_version" != "$changelog_version" ]]; then
    error "Version mismatch: Plugin header Version ($plugin_version) does not match $CHANGELOG_FILE ($changelog_version). Update changelog.txt to match."
  fi

  if [[ -n "$kernl_version" && "$plugin_version" != "$kernl_version" ]]; then
    error "Version mismatch: Plugin header Version ($plugin_version) does not match $KERNL_VERSION_FILE ($kernl_version). Update kernl.version to match."
  fi

  if [[ -n "$kernl_changelog_version" && "$plugin_version" != "$kernl_changelog_version" ]]; then
    error "Version mismatch: Plugin header Version ($plugin_version) does not match latest version in $KERNL_CHANGELOG_FILE ($kernl_changelog_version). Update changelog.json to match."
  fi

  info "Version numbers match: $plugin_version"
  if [[ -n "$plugin_constant_version" ]]; then info "Plugin constant version also matches: $plugin_constant_version"; fi
  if [[ -n "$class_constant_version" ]]; then info "Class constant version also matches: $class_constant_version"; fi
  if [[ -n "$changelog_version" ]]; then info "Changelog.txt version also matches: $changelog_version"; fi
  if [[ -n "$kernl_version" ]]; then info "Kernl version file also matches: $kernl_version"; fi
  if [[ -n "$kernl_changelog_version" ]]; then info "Kernl changelog.json also matches: $kernl_changelog_version"; fi

  if ! is_semver "$plugin_version"; then
    error "Version $plugin_version must follow semantic versioning format X.Y.Z (e.g., 1.0.0, 2.3.4)"
  fi

  if [[ "$plugin_version" =~ [-+] ]]; then
    error "Version $plugin_version must not contain pre-release or build metadata (e.g., -alpha, +build.123). Use X.Y.Z format only."
  fi
  latest_tag=$(git tag --list --sort=-v:refname 2>/dev/null | grep -E '^[0-9]+\.[0-9]+\.[0-9]+$' | head -1 || true)
  if [[ -n "$latest_tag" ]]; then
    smallest=$(printf '%s\n%s' "$latest_tag" "$plugin_version" | sort -V | head -1)
    if [[ "$smallest" == "$plugin_version" && "$plugin_version" != "$latest_tag" ]]; then
      error "Version $plugin_version is not greater than previous git tag $latest_tag. Bump the version number before releasing."
    fi
    if [[ "$plugin_version" == "$latest_tag" ]]; then
      error "Version $plugin_version is already tagged in git. Please bump the version number before creating a new release."
    fi
  else
    info "No previous version tags found; allowing any version"
  fi
else
  info "Stable tag is trunk; skipping plugin version bump checks."
fi

if [[ "$CHECK_WORDPRESS_COMPAT" == "true" ]]; then
  wp_tested=$(get_readme_wp_tested)
  latest_wp=$(get_latest_wordpress_version)
  if [[ -z "$latest_wp" ]]; then
    warn "Could not fetch latest WordPress version from API. Skipping WordPress compatibility check."
  else
    # Compare only major.minor versions (e.g., 6.7) per WordPress guidelines
    wp_tested_major_minor=$(echo "$wp_tested" | cut -d'.' -f1-2)
    latest_wp_major_minor=$(echo "$latest_wp" | cut -d'.' -f1-2)

    if [[ "$wp_tested_major_minor" != "$latest_wp_major_minor" ]]; then
      error "WordPress compatibility mismatch: Plugin tested up to WordPress $wp_tested, but latest WordPress is $latest_wp (major.minor: $latest_wp_major_minor). Update 'Tested up to' field in $README_FILE"
    fi
    info "WordPress tested version up-to-date: $wp_tested (compatible with WordPress $latest_wp_major_minor)"
  fi
fi

if [[ "$CHECK_WOOCOMMERCE_COMPAT" == "true" ]]; then
  wc_tested=$(get_readme_wc_tested)
  plugin_wc_tested=$(get_plugin_wc_tested)
  latest_wc=$(get_latest_woocommerce_version)
  if [[ -z "$latest_wc" ]]; then
    warn "Could not fetch latest WooCommerce version from GitHub API. Skipping WooCommerce compatibility check."
  else
    if [[ "$wc_tested" != "$latest_wc" ]]; then
      error "WooCommerce compatibility mismatch: Plugin tested up to WooCommerce $wc_tested (in $README_FILE), but latest WooCommerce is $latest_wc. Update 'WC tested up to' field."
    fi
    if [[ "$plugin_wc_tested" != "$latest_wc" ]]; then
      error "WooCommerce compatibility mismatch: Plugin tested up to WooCommerce $plugin_wc_tested (in $plugin_file header), but latest WooCommerce is $latest_wc. Update 'WC tested up to' field."
    fi
    info "WooCommerce tested version up-to-date: $wc_tested"
  fi
fi


info "All version & compatibility checks passed."
