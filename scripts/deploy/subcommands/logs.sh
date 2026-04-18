#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"

show_logs() {
    local FOLLOW=0
    local LINES=80
    for arg in "$@"; do
        case "$arg" in
            -f|--follow) FOLLOW=1 ;;
            [0-9]*) LINES="$arg" ;;
        esac
    done

    # Find latest deploy log
    local LATEST
    LATEST=$(ls -t "$LOG_DIR"/deploy-*.log 2>/dev/null | head -1)
    if [ -z "$LATEST" ]; then
        echo "No deploy logs found in $LOG_DIR"
        exit 1
    fi

    echo "=== Latest deploy log: $(basename "$LATEST") ==="

    # Check if a deploy is currently running
    if [ -f "$LOCK_FILE" ]; then
        local LOCK_PID
        LOCK_PID=$(cat "$LOCK_FILE" 2>/dev/null)
        if ps -p "$LOCK_PID" > /dev/null 2>&1; then
            echo ">>> Deploy is RUNNING (PID: $LOCK_PID) <<<"
        else
            echo ">>> Deploy finished (stale lock) <<<"
        fi
    else
        echo ">>> No deploy in progress <<<"
    fi
    echo ""

    if [ "$FOLLOW" = "1" ]; then
        tail -f "$LATEST"
    else
        tail -n "$LINES" "$LATEST"
    fi
}

show_logs "$@"
