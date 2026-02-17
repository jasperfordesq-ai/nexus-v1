#!/usr/bin/env bash
# =============================================================================
# check-dockerfile-drift.sh
# Compares PHP settings between the local (dev) and production Dockerfiles.
# Exits 0 if all settings match, 1 if any mismatch is found.
#
# Usage:
#   ./scripts/check-dockerfile-drift.sh
#   bash scripts/check-dockerfile-drift.sh
#
# Works on Linux (bash), macOS, Git Bash (Windows), and CI environments.
# =============================================================================

set -euo pipefail

# ---------------------------------------------------------------------------
# Resolve project root (directory containing this script's parent)
# ---------------------------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

LOCAL_DOCKERFILE="$PROJECT_ROOT/Dockerfile"
PROD_DOCKERFILE="$PROJECT_ROOT/Dockerfile.prod"

# ---------------------------------------------------------------------------
# Validate that both files exist
# ---------------------------------------------------------------------------
if [[ ! -f "$LOCAL_DOCKERFILE" ]]; then
    echo "ERROR: Local Dockerfile not found at $LOCAL_DOCKERFILE"
    exit 2
fi

if [[ ! -f "$PROD_DOCKERFILE" ]]; then
    echo "ERROR: Production Dockerfile not found at $PROD_DOCKERFILE"
    exit 2
fi

# ---------------------------------------------------------------------------
# Settings to compare
# ---------------------------------------------------------------------------
SETTINGS=(
    "upload_max_filesize"
    "post_max_size"
    "max_execution_time"
    "max_input_time"
    "max_input_vars"
    "memory_limit"
)

# ---------------------------------------------------------------------------
# extract_setting FILE SETTING_NAME
#   Extracts the value of a PHP ini setting from a Dockerfile.
#   Handles lines like:  upload_max_filesize = 50M\n\
#                   or:  upload_max_filesize = 50M
# ---------------------------------------------------------------------------
extract_setting() {
    local file="$1"
    local setting="$2"
    # Match "setting = value" allowing optional trailing \n\ from Dockerfile RUN echo
    local value
    value=$(grep -oP "(?<=^|\\\\n)${setting}\s*=\s*\K[^\s\\\\]+" "$file" 2>/dev/null | head -1)
    if [[ -z "$value" ]]; then
        echo "(not set)"
    else
        echo "$value"
    fi
}

# ---------------------------------------------------------------------------
# Main comparison
# ---------------------------------------------------------------------------
echo "============================================================"
echo "  Dockerfile Drift Detection"
echo "  Local: Dockerfile"
echo "  Prod:  Dockerfile.prod"
echo "============================================================"
echo ""

mismatches=0
max_name_len=0

# Calculate column width for alignment
for setting in "${SETTINGS[@]}"; do
    if (( ${#setting} > max_name_len )); then
        max_name_len=${#setting}
    fi
done

echo "DRIFT REPORT:"
echo ""

for setting in "${SETTINGS[@]}"; do
    local_val=$(extract_setting "$LOCAL_DOCKERFILE" "$setting")
    prod_val=$(extract_setting "$PROD_DOCKERFILE" "$setting")

    # Pad the setting name for alignment
    padded_name=$(printf "%-${max_name_len}s" "$setting")

    if [[ "$local_val" == "$prod_val" ]]; then
        echo "  ${padded_name}  local=${local_val}, prod=${prod_val}  MATCH"
    else
        echo "  ${padded_name}  local=${local_val}, prod=${prod_val}  MISMATCH"
        mismatches=$((mismatches + 1))
    fi
done

echo ""

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------
if (( mismatches > 0 )); then
    echo "RESULT: ${mismatches} mismatch(es) detected between local and production Dockerfiles."
    echo "Review the settings above and align them if the drift is unintentional."
    exit 1
else
    echo "RESULT: All ${#SETTINGS[@]} settings match between local and production Dockerfiles."
    exit 0
fi
