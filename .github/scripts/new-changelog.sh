#!/usr/bin/env bash

# Source the shared library
source "$(dirname "$0")/lib.sh"

# Renders a text based list of options that can be selected by the
# user using up, down and enter keys and returns the chosen option.
#
#   Arguments   : list of options, maximum of 256
#                 "opt1" "opt2" ...
#   Return value: selected index (0 for opt1, 1 for opt2 ...)
function select_option {

    # little helpers for terminal print control and key input
    ESC=$( printf "\033")
    cursor_blink_on()  { printf "$ESC[?25h"; }
    cursor_blink_off() { printf "$ESC[?25l"; }
    cursor_to()        { printf "$ESC[$1;${2:-1}H"; }
    print_option()     { printf "   $1 "; }
    print_selected()   { printf "  $ESC[7m $1 $ESC[27m"; }
    get_cursor_row()   { IFS=';' read -sdR -p $'\E[6n' ROW COL; echo ${ROW#*[}; }
    key_input()        { read -s -n3 key 2>/dev/null >&2
                         if [[ $key = $ESC[A ]]; then echo up;    fi
                         if [[ $key = $ESC[B ]]; then echo down;  fi
                         if [[ $key = ""     ]]; then echo enter; fi; }

    # initially print empty new lines (scroll down if at bottom of screen)
    for opt; do printf "\n"; done

    # determine current screen position for overwriting the options
    local lastrow=`get_cursor_row`
    local startrow=$(($lastrow - $#))

    # ensure cursor and input echoing back on upon a ctrl+c during read -s
    trap "cursor_blink_on; stty echo; printf '\n'; exit" 2
    cursor_blink_off

    local selected=0
    while true; do
        # print options by overwriting the last lines
        local idx=0
        for opt; do
            cursor_to $(($startrow + $idx))
            if [ $idx -eq $selected ]; then
                print_selected "$opt"
            else
                print_option "$opt"
            fi
            ((idx++))
        done

        # user key control
        case `key_input` in
            enter) break;;
            up)    ((selected--));
                   if [ $selected -lt 0 ]; then selected=$(($# - 1)); fi;;
            down)  ((selected++));
                   if [ $selected -ge $# ]; then selected=0; fi;;
        esac
    done

    # cursor position back to normal
    cursor_to $lastrow
    printf "\n"
    cursor_blink_on

    return $selected
}

# Get inputs from command line arguments if they are set.
TYPE="$1"
SLUG="$2"
NEEDS_DOCUMENTATION="$3"
DESCRIPTION="$4"

if [[ ! " ${ACCEPTED_CHANGELOG_TYPES_DISPLAY[@]} " =~ " ${TYPE} " ]]; then
    echo "Select the type of change:"
    select_option "${ACCEPTED_CHANGELOG_TYPES_DISPLAY[@]}"
    TYPE=${ACCEPTED_CHANGELOG_TYPES_DISPLAY[$?]}
fi

if [ -z "$SLUG" ]; then
  read -p "Enter a slug for the change (e.g. short-description): " SLUG
fi

# Sanitize SLUG: remove/convert unsafe characters for filesystem safety
# Keep only alphanumeric, dashes, and underscores; convert spaces to dashes
SLUG=$(echo "$SLUG" | tr '[:upper:]' '[:lower:]' | tr -s '[:space:]' '-' | tr -cd '[:alnum:]-_' | sed 's/^[-_]*//; s/[-_]*$//')

# Validate that slug is not empty after sanitization
if [ -z "$SLUG" ]; then
  echo "Error: Invalid slug. Must contain at least one alphanumeric character."
  exit 1
fi

if [ -z "$NEEDS_DOCUMENTATION" ]; then
  read -p "Does this change need documentation? (yes/no): " NEEDS_DOCUMENTATION
fi

if [ -z "$DESCRIPTION" ]; then
  read -p "Enter a brief description of the change: " DESCRIPTION
fi

# Generates a new file for the changelog entry named yyyy-MM-dd-slug with the content formatted like:
# Type: <TYPE>
# Needs Documentation: <yes/no>
#
# <DESCRIPTION>
TIMESTAMP=$(date +"%Y-%m-%d")
FILENAME="${TIMESTAMP}-${SLUG}"

# Use absolute path from SCRIPT_DIR to avoid issues when script called from different directories
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

cat <<EOL > "$REPO_ROOT/$CHANGELOG_DIR/${FILENAME}"
Type: $TYPE
Needs Documentation: $NEEDS_DOCUMENTATION

$DESCRIPTION
EOL
echo "Changelog entry created at ./$CHANGELOG_DIR/${FILENAME}"
