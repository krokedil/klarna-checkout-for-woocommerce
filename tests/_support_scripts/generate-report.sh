#!/usr/bin/env bash
#
# Renders the Allure report from whichever suite results exist.
#
# Usage: generate-report.sh [--share]
#   --share  Emit one self-contained index.html instead of a directory tree.
set -euo pipefail

cd "$(dirname "$0")/../.."

CONFIG="allurerc.mjs"
OUTPUT="tests/_output/report"
if [[ "${1:-}" == "--share" ]]; then
    CONFIG="allurerc.single.mjs"
    OUTPUT="tests/_output/report-share"
fi

RESULTS=()
for suite in integration harness endtoend; do
    if [[ -d "tests/_output/allure-results/$suite" ]]; then
        RESULTS+=("tests/_output/allure-results/$suite")
    fi
done

if [[ ${#RESULTS[@]} -eq 0 ]]; then
    echo "No Allure results found. Run 'composer test:integration', 'composer test:harness' or 'composer test:e2e' first." >&2
    exit 1
fi

rm -rf "$OUTPUT"
npx allure generate "${RESULTS[@]}" --config "$CONFIG" --output "$OUTPUT"

php tests/_support_scripts/verify-no-secrets.php "$OUTPUT"

echo "Report written to $OUTPUT/index.html"
