#!/usr/bin/env bash

# ============================================================================
# PLUGIN-SPECIFIC CONFIGURATION
# Customize these values for each plugin repository
# ============================================================================

# File paths
# Set to empty string to enable auto-detection
PLUGIN_FILE="klarna-checkout-for-woocommerce.php" # Main plugin file (e.g., "my-gateway.php")
README_FILE="readme.txt"                          # WordPress readme file
CHANGELOG_FILE=""                                 # Standalone changelog file (optional, leave empty if not used)
CHANGELOG_DIR=".changelogs"                       # Directory for changelog entries
KERNL_VERSION_FILE=""                             # Kernl version file (optional, leave empty if not used)
KERNL_CHANGELOG_FILE=""                           # Kernl changelog file (optional, leave empty if not used)

# Version constant configuration
# The name of the version constant in your plugin file (e.g., MY_PLUGIN_VERSION)
# Set to empty string to auto-detect any version constant
VERSION_CONSTANT_NAME="KCO_WC_VERSION"

# Optional: Path to class file containing version constant
# Use this if your version is defined as a class constant instead of (or in addition to) a define() in the main plugin file
# Example: "src/MyClass.php" for class constants like "public const MY_PLUGIN_VERSION = '1.0.0';"
VERSION_CONSTANT_FILE=""

# Compatibility checks
# Set to "true" to enable, "false" to disable
CHECK_WORDPRESS_COMPAT="true"
CHECK_WOOCOMMERCE_COMPAT="true"

# Optional file requirements
# Set to "true" if file is required for all plugins, "false" if optional
REQUIRE_README="true"               # Require readme.txt file
REQUIRE_VERSION_CONSTANT="true"     # Require version constant in plugin file
REQUIRE_CHANGELOG_FILE="false"      # Require standalone changelog.txt file

# ============================================================================
# REGEX PATTERNS
# Generally don't need to change these unless you have special requirements
# ============================================================================

REGEX_SEMVER="^[0-9]+\.[0-9]+\.[0-9]+$"
REGEX_VERSION_CONSTANT="^define\([[:space:]]'[^']+',[[:space:]]'[0-9]+\.[0-9]+\.[0-9]+'[[:space:]]\);"
REGEX_CLASS_CONSTANT="^[[:space:]]*(public|private|protected)?[[:space:]]*const[[:space:]]+[A-Z_]+[[:space:]]*=[[:space:]]*'[0-9]+\.[0-9]+\.[0-9]+';"
REGEX_README_STABLE_TAG="^Stable tag:"
REGEX_PLUGIN_VERSION="^[[:space:]]*\*[[:space:]]*Version:"
REGEX_PLUGIN_WC_TESTED="^[[:space:]]*\*[[:space:]]*WC tested up to:"
REGEX_README_WP_TESTED="^Tested up to:"
REGEX_README_WC_TESTED="^WC tested up to:"
REGEX_CHANGELOG_VERSION="^[0-9]{4}-[0-9]{2}-[0-9]{2}[[:space:]]*-[[:space:]]*version[[:space:]]+"

# ============================================================================
# CHANGELOG CONFIGURATION
# ============================================================================

# Valid changelog types (lowercase for internal use)
VALID_CHANGELOG_TYPES=(breaking major feature enhancement misc change tweak fix)

# Display names for changelog types (capitalized for user-facing prompts)
ACCEPTED_CHANGELOG_TYPES_DISPLAY=(Breaking Major Feature Enhancement Misc Change Tweak Fix)

# File patterns to monitor for changelog requirement
MONITORED_EXTENSIONS=(php js css ts jsx tsx)
MONITORED_FILES=(readme.txt composer.json composer.lock)

# ============================================================================
# API ENDPOINTS
# ============================================================================

API_WORDPRESS_VERSION="https://api.wordpress.org/core/version-check/1.7/"
API_WOOCOMMERCE_RELEASES="https://api.github.com/repos/woocommerce/woocommerce/releases/latest"
