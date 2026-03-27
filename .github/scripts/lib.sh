#!/usr/bin/env bash

# Source the config file
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/config.sh"

# ============================================================================
# OUTPUT FUNCTIONS (GitHub Actions compatible)
# ============================================================================

error() { echo "::error::$1"; exit 1; }
warn() { echo "::warning::$1"; }
info() { echo "$1"; }

# ============================================================================
# DEPENDENCY CHECKS
# ============================================================================

# Check if required commands are available
check_required_commands() {
  local missing=()
  for cmd in "$@"; do
    if ! command -v "$cmd" &>/dev/null; then
      missing+=("$cmd")
    fi
  done
  if [[ ${#missing[@]} -gt 0 ]]; then
    error "Required commands not found: ${missing[*]}. Please install them to continue."
  fi
}

# ============================================================================
# CHANGELOG HELPER FUNCTIONS
# ============================================================================

# Check if a file should be monitored for changelog requirements
is_monitored_file() {
  local file="$1"

  # Check if file matches any monitored extension
  for ext in "${MONITORED_EXTENSIONS[@]}"; do
    if [[ "$file" == *."$ext" ]]; then
      return 0
    fi
  done

  # Check if file matches any monitored file
  for monitored in "${MONITORED_FILES[@]}"; do
    if [[ "$file" == "$monitored" ]]; then
      return 0
    fi
  done

  return 1
}

# ============================================================================
# AUTO-DETECTION FUNCTIONS
# ============================================================================

# Auto-detect the main plugin file if not configured
detect_plugin_file() {
  if [[ -n "$PLUGIN_FILE" && -f "$PLUGIN_FILE" ]]; then
    echo "$PLUGIN_FILE"
    return 0
  fi

  # Look for PHP files with "Plugin Name:" header in root directory
  # First check if any PHP files exist to avoid glob expansion issues
  if ! compgen -G "*.php" > /dev/null 2>&1; then
    error "No PHP files found in current directory. Please set PLUGIN_FILE in config.sh"
  fi

  local found
  found=$(grep -l "Plugin Name:" *.php 2>/dev/null | head -1)
  if [[ -n "$found" ]]; then
    echo "$found"
    return 0
  fi

  error "Could not auto-detect plugin file. Please set PLUGIN_FILE in config.sh"
}

# Get the actual plugin file (from config or auto-detect)
get_plugin_file() {
  if [[ -n "$PLUGIN_FILE" && -f "$PLUGIN_FILE" ]]; then
    echo "$PLUGIN_FILE"
  else
    detect_plugin_file
  fi
}

# ============================================================================
# VERSION VALIDATION
# ============================================================================

is_semver() { [[ $1 =~ $REGEX_SEMVER ]]; }

# ============================================================================
# VERSION EXTRACTION FUNCTIONS
# ============================================================================

get_readme_version() {
  local file="${1:-$README_FILE}"
  if [[ ! -f "$file" ]]; then return 1; fi
  grep -E "$REGEX_README_STABLE_TAG" "$file" | head -1 | cut -d':' -f2 | tr -d '[:space:]'
}

get_plugin_version() {
  local file="${1:-$(get_plugin_file)}"
  if [[ ! -f "$file" ]]; then return 1; fi
  grep -E "$REGEX_PLUGIN_VERSION" "$file" | head -1 | cut -d':' -f2 | tr -d '[:space:]'
}

get_plugin_constant_version() {
  local file="${1:-$(get_plugin_file)}"
  if [[ ! -f "$file" ]]; then return 1; fi

  # If VERSION_CONSTANT_NAME is specified, search for that specific constant
  if [[ -n "$VERSION_CONSTANT_NAME" ]]; then
    # Escape special regex characters for use in grep pattern
    local escaped_name=$(printf '%s\n' "$VERSION_CONSTANT_NAME" | sed 's/[.[\^$*]/\\&/g')
    grep -E "^define\([[:space:]]'${escaped_name}',[[:space:]]'[0-9]+\.[0-9]+\.[0-9]+'[[:space:]]\);" "$file" \
      | sed -E "s/^define\([[:space:]]'[^']+',[[:space:]]'([0-9]+\.[0-9]+\.[0-9]+)'[[:space:]]\);/\1/" || true
  else
    # Auto-detect any version constant
    grep -E "$REGEX_VERSION_CONSTANT" "$file" | head -1 \
      | sed -E "s/^define\([[:space:]]'[^']+',[[:space:]]'([0-9]+\.[0-9]+\.[0-9]+)'[[:space:]]\);/\1/" || true
  fi
}

get_class_constant_version() {
  local file="${1:-$VERSION_CONSTANT_FILE}"
  if [[ -z "$file" || ! -f "$file" ]]; then return 1; fi

  # If VERSION_CONSTANT_NAME is specified, search for that specific constant
  if [[ -n "$VERSION_CONSTANT_NAME" ]]; then
    # Escape special regex characters for use in grep pattern
    local escaped_name=$(printf '%s\n' "$VERSION_CONSTANT_NAME" | sed 's/[.[\^$*]/\\&/g')
    grep -E "^[[:space:]]*(public|private|protected)?[[:space:]]*const[[:space:]]+${escaped_name}[[:space:]]*=[[:space:]]*'[0-9]+\.[0-9]+\.[0-9]+';" "$file" \
      | sed -E "s/^[[:space:]]*(public|private|protected)?[[:space:]]*const[[:space:]]+[A-Z_]+[[:space:]]*=[[:space:]]*'([0-9]+\.[0-9]+\.[0-9]+)';/\2/" || true
  else
    # Auto-detect any class constant
    grep -E "$REGEX_CLASS_CONSTANT" "$file" | head -1 \
      | sed -E "s/^[[:space:]]*(public|private|protected)?[[:space:]]*const[[:space:]]+[A-Z_]+[[:space:]]*=[[:space:]]*'([0-9]+\.[0-9]+\.[0-9]+)';/\2/" || true
  fi
}

get_plugin_wc_tested() {
  local file="${1:-$(get_plugin_file)}"
  if [[ ! -f "$file" ]]; then return 1; fi
  grep -E "$REGEX_PLUGIN_WC_TESTED" "$file" | head -1 | cut -d':' -f2 | tr -d '[:space:]'
}

get_readme_wp_tested() {
  local file="${1:-$README_FILE}"
  if [[ ! -f "$file" ]]; then return 1; fi
  grep -E "$REGEX_README_WP_TESTED" "$file" | head -1 | sed -E 's/^Tested up to:\s*([0-9.]+).*$/\1/'
}

get_readme_wc_tested() {
  local file="${1:-$README_FILE}"
  if [[ ! -f "$file" ]]; then return 1; fi
  grep -E "$REGEX_README_WC_TESTED" "$file" | head -1 | sed -E 's/^WC tested up to:\s*([0-9.]+).*$/\1/'
}

get_changelog_version() {
  local file="${1:-$CHANGELOG_FILE}"
  if [[ -z "$file" || ! -f "$file" ]]; then return 1; fi
  # Extract version from lines like "2026-01-22      - version 3.1.0"
  grep -E "$REGEX_CHANGELOG_VERSION" "$file" | head -1 | sed -E 's/^[0-9]{4}-[0-9]{2}-[0-9]{2}[[:space:]]*-[[:space:]]*version[[:space:]]+([0-9]+\.[0-9]+\.[0-9]+).*$/\1/'
}

get_kernl_version() {
  local file="${1:-$KERNL_VERSION_FILE}"
  if [[ -z "$file" || ! -f "$file" ]]; then return 1; fi
  # Extract version from plain text file (trim whitespace)
  tr -d '[:space:]' < "$file"
}

get_kernl_changelog_version() {
  check_required_commands jq
  local file="${1:-$KERNL_CHANGELOG_FILE}"
  if [[ -z "$file" || ! -f "$file" ]]; then return 1; fi
  # Get the last (most recent) version key from the JSON object
  jq -r 'keys | last' "$file" 2>/dev/null || true
}

get_readme_requires() {
  local file="${1:-$README_FILE}"
  if [[ ! -f "$file" ]]; then return 1; fi
  grep -E "^Requires at least:" "$file" | head -1 | sed -E 's/^Requires at least:\s*([0-9.]+).*$/\1/'
}

# ============================================================================
# CHANGELOG TYPE FUNCTIONS
# ============================================================================

map_type_to_bump() {
  local t="$1"; t=$(echo "$t" | tr 'A-Z' 'a-z')
  case "$t" in
    breaking|major) echo 2 ;;
    feature|feat|minor) echo 1 ;;
    *) echo 0 ;;
  esac
}

infer_type_from_content() {
  local c="$1" lc
  lc=$(echo "$c" | tr 'A-Z' 'a-z')
  if echo "$lc" | grep -q 'breaking change'; then echo breaking; return; fi
  if echo "$lc" | grep -q -E '\bfeat(ure)?\b'; then echo feature; return; fi
  if echo "$lc" | grep -q -E '\bmajor\b'; then echo major; return; fi
  if echo "$lc" | grep -q -E '\bfix|patch|tweak|enhancement|perf|security\b'; then echo fix; return; fi
  echo misc
}

# ============================================================================
# VERSION UPDATE FUNCTIONS
# ============================================================================

update_readme_version() {
  local new_version="$1"
  local file="${2:-$README_FILE}"
  sed -i.bak -E "s/^(Stable tag:[[:space:]]*)[0-9]+\.[0-9]+\.[0-9]+/\1$new_version/" "$file" && rm -f "$file.bak"
}

update_plugin_header_version() {
  local new_version="$1"
  local file="${2:-$(get_plugin_file)}"
  sed -i.bak -E "s/^([[:space:]]*\*[[:space:]]*Version:[[:space:]])*[0-9]+\.[0-9]+\.[0-9]+/\1$new_version/" "$file" && rm -f "$file.bak"
}

update_plugin_constant_version() {
  local current_version="$1"
  local new_version="$2"
  local file="${3:-$(get_plugin_file)}"

  local escaped_current=$(echo "$current_version" | sed 's/\./\\./g')

  # If VERSION_CONSTANT_NAME is specified, update that specific constant
  if [[ -n "$VERSION_CONSTANT_NAME" ]]; then
    # Escape special regex characters for use in sed pattern
    local escaped_name=$(printf '%s\n' "$VERSION_CONSTANT_NAME" | sed 's/[.[\^$*]/\\&/g')
    if grep -q -E "^define\([[:space:]]'${escaped_name}',[[:space:]]'[0-9]+\.[0-9]+\.[0-9]+'[[:space:]]\);" "$file"; then
      sed -i.bak -E "s/^(define\([[:space:]]'${escaped_name}',[[:space:]]')${escaped_current}('[[:space:]]\);)/\1${new_version}\2/" "$file" && rm -f "$file.bak"
      return 0
    fi
  else
    # Auto-detect and update any version constant
    if grep -q -E "$REGEX_VERSION_CONSTANT" "$file"; then
      sed -i.bak -E "s/^(define\([[:space:]]'[^']+',[[:space:]]')${escaped_current}('[[:space:]]\);)/\1${new_version}\2/" "$file" && rm -f "$file.bak"
      return 0
    fi
  fi

  return 1
}

update_class_constant_version() {
  local current_version="$1"
  local new_version="$2"
  local file="${3:-$VERSION_CONSTANT_FILE}"

  if [[ -z "$file" || ! -f "$file" ]]; then return 1; fi

  local escaped_current=$(echo "$current_version" | sed 's/\./\\./g')

  # If VERSION_CONSTANT_NAME is specified, update that specific constant
  if [[ -n "$VERSION_CONSTANT_NAME" ]]; then
    # Escape special regex characters for use in sed pattern
    local escaped_name=$(printf '%s\n' "$VERSION_CONSTANT_NAME" | sed 's/[.[\^$*]/\\&/g')
    if grep -q -E "^[[:space:]]*(public|private|protected)?[[:space:]]*const[[:space:]]+${escaped_name}[[:space:]]*=[[:space:]]*'[0-9]+\.[0-9]+\.[0-9]+';" "$file"; then
      sed -i.bak -E "s/^([[:space:]]*(public|private|protected)?[[:space:]]*const[[:space:]]+${escaped_name}[[:space:]]*=[[:space:]]*')${escaped_current}(';)/\1${new_version}\3/" "$file" && rm -f "$file.bak"
      return 0
    fi
  else
    # Auto-detect and update any class constant
    if grep -q -E "$REGEX_CLASS_CONSTANT" "$file"; then
      sed -i.bak -E "s/^([[:space:]]*(public|private|protected)?[[:space:]]*const[[:space:]]+[A-Z_]+[[:space:]]*=[[:space:]]*')${escaped_current}(';)/\1${new_version}\3/" "$file" && rm -f "$file.bak"
      return 0
    fi
  fi

  return 1
}

update_changelog_file() {
  local new_version="$1"
  local changelog_content="$2"
  local file="${3:-$CHANGELOG_FILE}"

  if [[ -z "$file" || ! -f "$file" ]]; then return 1; fi

  # Read the first line (header) and content after it
  local header=$(head -1 "$file")
  local rest=$(tail -n +2 "$file")

  # Create new changelog entry in the format: YYYY-MM-DD      - version X.Y.Z
  local new_entry="$(date +%Y-%m-%d)      - version $new_version"

  # Write header, new entry, changelog content, blank line, then rest
  {
    echo "$header"
    echo "$new_entry"
    echo "$changelog_content"
    echo ""
    echo "$rest"
  } > "$file.tmp"

  mv "$file.tmp" "$file"
  return 0
}

update_kernl_version() {
  local new_version="$1"
  local file="${2:-$KERNL_VERSION_FILE}"

  if [[ -z "$file" || ! -f "$file" ]]; then return 1; fi

  echo "$new_version" > "$file"
  return 0
}

update_kernl_changelog() {
  check_required_commands jq
  local new_version="$1"
  local changelog_content="$2"
  local file="${3:-$KERNL_CHANGELOG_FILE}"

  if [[ -z "$file" || ! -f "$file" ]]; then return 1; fi

  # Get WordPress compatibility info from readme
  local requires=$(get_readme_requires)
  local tested=$(get_readme_wp_tested)

  # Convert plain text changelog to HTML list format
  # Each line becomes an <li> item
  local html_description="<ul>"
  while IFS= read -r line; do
    # Skip empty lines
    if [[ -n "$line" ]]; then
      # Remove leading "- " or "* " if present
      line="${line#- }"
      line="${line#\* }"
      # Normalize type prefix spacing (e.g., "Fix           - " becomes "Fix - ")
      line=$(echo "$line" | sed -E 's/^([A-Za-z]+)[[:space:]]+-[[:space:]]*/\1 - /')
      html_description+="<li>${line}</li>"
    fi
  done <<< "$changelog_content"
  html_description+="</ul>"

  # Read existing JSON
  local existing_json=$(cat "$file")

  # Add new version entry using jq
  local updated_json=$(echo "$existing_json" | jq \
    --arg version "$new_version" \
    --arg desc "$html_description" \
    --arg req "$requires" \
    --arg test "$tested" \
    '. + {($version): {description: $desc, requires: $req, tested: $test, upgrade_notice: ""}}')

  # Write updated JSON back to file
  echo "$updated_json" | jq '.' > "$file"
  return 0
}

# ============================================================================
# COMPATIBILITY CHECK FUNCTIONS
# ============================================================================

get_latest_wordpress_version() {
  check_required_commands curl jq
  curl -fsSL "$API_WORDPRESS_VERSION" | jq -r '.offers[0].version' 2>/dev/null || true
}

get_latest_woocommerce_version() {
  check_required_commands curl jq
  local version
  version=$(curl -fsSL "$API_WOOCOMMERCE_RELEASES" | jq -r 'select(.prerelease==false) | .tag_name' 2>/dev/null || true)
  echo "${version#v}"  # Remove 'v' prefix if present
}
