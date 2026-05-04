#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.
#
# Append a JSON-lines record for the just-finished deploy. Enables tracking:
#   - change failure rate (failed deploys / total)
#   - mean time to recovery (rollback duration)
#   - deploy duration trend
#   - per-color failure asymmetry
#
# Called from the deploy_exit_trap at the end of cmd_deploy / cmd_rollback.
#
# Output: $DEPLOY_DIR/logs/deploys.jsonl  (one JSON object per line)
#
# Usage:
#   bash phases/record-deploy-metrics.sh \
#     <status> <subcommand> <commit> <prev_commit> <color> <duration_seconds>
#
# Aggregation example (run anytime to compute change-failure-rate over last 30 deploys):
#   tail -n 30 logs/deploys.jsonl \
#     | jq -s '{ total: length, failures: [.[]|select(.status=="failed")]|length }'

set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"

status="${1:-unknown}"
subcommand="${2:-deploy}"
commit="${3:-unknown}"
prev_commit="${4:-}"
color="${5:-}"
duration="${6:-0}"

METRICS_FILE="${NEXUS_DEPLOY_METRICS_FILE:-$LOG_DIR/deploys.jsonl}"
mkdir -p "$(dirname "$METRICS_FILE")"

# Escape quotes for JSON safety
esc() { printf '%s' "$1" | sed 's/\\/\\\\/g; s/"/\\"/g'; }

ts="$(date -Iseconds)"
hostname_v="$(hostname -s 2>/dev/null || echo unknown)"
subject="$(git -C "$DEPLOY_DIR" log -1 --format='%s' "$commit" 2>/dev/null || echo '')"

# Compute commits-behind-rolled-forward (number of commits between prev_commit
# and commit). On rollback this is negative; on forward-deploy it's positive.
commit_distance=0
if [ -n "$prev_commit" ] && [ "$prev_commit" != "$commit" ]; then
    commit_distance="$(git -C "$DEPLOY_DIR" rev-list --count "$prev_commit..$commit" 2>/dev/null || echo 0)"
fi

printf '{"timestamp":"%s","host":"%s","subcommand":"%s","status":"%s","commit":"%s","commit_short":"%s","subject":"%s","prev_commit":"%s","commit_distance":%s,"color":"%s","duration_s":%s}\n' \
    "$ts" \
    "$(esc "$hostname_v")" \
    "$(esc "$subcommand")" \
    "$(esc "$status")" \
    "$(esc "$commit")" \
    "${commit:0:8}" \
    "$(esc "$subject")" \
    "$(esc "$prev_commit")" \
    "${commit_distance:-0}" \
    "$(esc "$color")" \
    "${duration:-0}" \
    >> "$METRICS_FILE"

log_ok "Deploy metrics appended to $METRICS_FILE"
