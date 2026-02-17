#!/usr/bin/env bash
set -euo pipefail

# Source the shared library
source "$(dirname "$0")/lib.sh"

needs_documentation=false;

if [[ ! -d "$CHANGELOG_DIR" ]]; then error "$CHANGELOG_DIR directory missing"; fi

# Get current version (prefer readme Stable tag else plugin header)
current_version=""
if [[ -f "$README_FILE" ]]; then current_version=$(get_readme_version); fi
if [[ -z "$current_version" && -f "$(get_plugin_file)" ]]; then current_version=$(get_plugin_version); fi
if [[ -z "$current_version" ]]; then current_version="0.0.0"; fi

if ! is_semver "$current_version"; then warn "Current version '$current_version' not semver; defaulting to 0.0.0"; current_version="0.0.0"; fi

# Determine required bump level: 0=patch,1=minor,2=major
required_bump=0
entries=()

for f in $(find "$CHANGELOG_DIR" -maxdepth 1 -type f ! -name '.gitkeep' | sort); do
  content=$(sed 's/\r$//' "$f")
  # Extract description (first non-empty line after any metadata lines)
  meta_end_line=$(echo "$content" | awk 'BEGIN{ln=0;m=0} {ln++; if(tolower($0) ~ /^type:/ || tolower($0) ~ /^needs documentation:/) m=ln; else if(length($0)==0 && m==ln-1) m=ln; } END{print m}')
  summary=$(echo "$content" | awk -v m="$meta_end_line" 'NR>m && length($0)>0 {print; exit}')
  type_line=$(echo "$content" | grep -i -m1 '^type:' || true)
  change_needs_doc_line=$(echo "$content" | grep -i -m1 '^needs documentation:' || true)
  if [[ -n "$change_needs_doc_line" ]]; then
    doc_value=$(echo "$change_needs_doc_line" | cut -d':' -f2 | tr -d '[:space:]' | tr 'A-Z' 'a-z')
    if [[ "$doc_value" == "yes" || "$doc_value" == "true" ]]; then
      needs_documentation=true
    fi
  fi
  change_type=""
  if [[ -n "$type_line" ]]; then
    change_type=$(echo "$type_line" | cut -d':' -f2 | tr -d '[:space:]' | tr 'A-Z' 'a-z')
  else
    change_type=$(infer_type_from_content "$content")
  fi
  [[ -z "$summary" ]] && summary="(No description in $f)"
  bump=$(map_type_to_bump "$change_type")
  if (( bump > required_bump )); then required_bump=$bump; fi
  entries+=("$change_type|$summary|$f")
done

# Parse version using regex (already validated above)
if [[ "$current_version" =~ ^([0-9]+)\.([0-9]+)\.([0-9]+)$ ]]; then
  major="${BASH_REMATCH[1]}"
  minor="${BASH_REMATCH[2]}"
  patch="${BASH_REMATCH[3]}"
else
  # Fallback (should never reach here due to validation above)
  error "Failed to parse version: $current_version"
fi
case $required_bump in
  2)
    major=$((major+1)); minor=0; patch=0;;
  1)
    minor=$((minor+1)); patch=0;;
  0)
    patch=$((patch+1));;
  *)
    patch=$((patch+1));;
 esac
suggested_version="$major.$minor.$patch"


print_changelog() {
  local format="${1:-detailed}"  # detailed or simple

  # Print header for detailed format
  if [[ "$format" == "detailed" ]]; then
    echo "= $(date +%Y-%m-%d)    - version $suggested_version ="
  fi

  # Group by type
  declare -A grouped
  for e in "${entries[@]}"; do
    local IFS='|'
    read -r t s file <<<"$e"
    grouped["$t"]+="- $s\n"
  done

  # Print grouped entries
  for t in "${VALID_CHANGELOG_TYPES[@]}"; do
    if [[ -n "${grouped[$t]:-}" ]]; then
      header=$t
      ## Get the header length and pad with spaces to align the entries
      header_len=${#header}
      pad_len=$((12 - header_len))
      pad_len=$((pad_len < 1 ? 1 : pad_len))
      pad=$(printf '%*s' "$pad_len" '')
      printf "* ${header^} %s %b" "$pad" "${grouped[$t]}"
    fi
  done
}

# If any entry needs documentation, warn the user
if $needs_documentation; then
  warn "One or more changelog entries indicate they need documentation. Please ensure documentation is updated or prepared before release."
fi

## If the argument "u" or "update" is given, update the readme.txt file
if [[ "${1:-}" == "u" || "${1:-}" == "update" ]]; then
  plugin_file=$(get_plugin_file)

  ## Create a backup of the readme and plugin files before modifying
  if [[ -f "$README_FILE" ]]; then cp "$README_FILE" "$README_FILE.bak"; fi
  if [[ -f "$plugin_file" ]]; then cp "$plugin_file" "$plugin_file.bak"; fi

  if [[ -f "$README_FILE" ]]; then
    update_readme_version "$suggested_version"
    info "Updated $README_FILE Stable tag to $suggested_version"
  fi
  if [[ -f "$plugin_file" ]]; then
    update_plugin_header_version "$suggested_version"
    info "Updated $plugin_file Version to $suggested_version"

    # Update the version constant definition (if it exists)
    if update_plugin_constant_version "$current_version" "$suggested_version"; then
      info "Updated $plugin_file version constant to $suggested_version"
    fi

    # Update the class constant version (if configured)
    if [[ -n "$VERSION_CONSTANT_FILE" && -f "$VERSION_CONSTANT_FILE" ]]; then
      if update_class_constant_version "$current_version" "$suggested_version"; then
        info "Updated $VERSION_CONSTANT_FILE class constant to $suggested_version"
      fi
    fi
  fi
  # Move processed changelog files to an "archived" subdirectory
  archive_dir="$CHANGELOG_DIR/backup-$(date +%Y%m%d%H%M%S)"
  mkdir -p "$archive_dir"
  for e in "${entries[@]}"; do
    IFS='|' read -r t s file <<<"$e"
    mv "$file" "$archive_dir/"
  done
  info "Moved processed changelog files to $archive_dir/"
  info "Changelog generation and version update complete."

  if [[ -f "$README_FILE" ]]; then
    changelog_content=$(print_changelog)
    awk -v new_content="$changelog_content" '
      BEGIN { added=0 }
      /^== Changelog ==/ {
        print;
        if (added==0) {
          print new_content;
          print "";
          added=1;
        }
        next
      }
      { print }
    ' "$README_FILE" > "$README_FILE.tmp" && mv "$README_FILE.tmp" "$README_FILE"
    info "Appended changelog to $README_FILE"
  else
    error "Cannot append changelog; $README_FILE not found."
  fi

  # Update changelog.txt if it exists
  if [[ -n "$CHANGELOG_FILE" && -f "$CHANGELOG_FILE" ]]; then
    changelog_simple_content=$(print_changelog simple)
    if update_changelog_file "$suggested_version" "$changelog_simple_content"; then
      info "Updated $CHANGELOG_FILE with version $suggested_version"
    fi
  fi

  # Update kernl.version if it exists
  if [[ -n "$KERNL_VERSION_FILE" && -f "$KERNL_VERSION_FILE" ]]; then
    if update_kernl_version "$suggested_version"; then
      info "Updated $KERNL_VERSION_FILE to $suggested_version"
    fi
  fi

  # Update changelog.json if it exists
  if [[ -n "$KERNL_CHANGELOG_FILE" && -f "$KERNL_CHANGELOG_FILE" ]]; then
    changelog_simple_content=$(print_changelog simple)
    if update_kernl_changelog "$suggested_version" "$changelog_simple_content"; then
      info "Updated $KERNL_CHANGELOG_FILE with version $suggested_version"
    fi
  fi
else
  echo "Suggested new version: $suggested_version"
  echo ""
  print_changelog
  echo ""
  echo "To apply this changelog and update version numbers, run this script with the argument 'u' or 'update':"
  echo "  bash $0 update"
fi
