#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"

# Detach helper — re-exec safe-deploy.sh in the background, fully detached
# from the terminal/SSH session. Uses setsid + nohup so the process survives
# SSH disconnects, terminal closes, and SIGHUP.
run_detached() {
    local DEPLOY_MODE="$1"
    shift
    local EXTRA_ARGS="$*"

    # Path to the orchestrator (parent of the deploy/ dir)
    local ORCHESTRATOR="$DEPLOY_DIR/scripts/safe-deploy.sh"

    # Create log file early so we can tell the caller where it is
    mkdir -p "$LOG_DIR"
    local TS
    TS=$(date +%Y-%m-%d_%H-%M-%S)
    local DETACH_LOG="$LOG_DIR/deploy-$TS.log"
    touch "$DETACH_LOG"

    # Re-exec the orchestrator with the detach marker set, fully backgrounded
    # setsid creates a new session (no controlling terminal)
    # nohup prevents SIGHUP from killing us
    # stdin from /dev/null, stdout+stderr to the log file
    env __NEXUS_DEPLOY_DETACHED__=1 \
        setsid nohup bash "$ORCHESTRATOR" "$DEPLOY_MODE" $EXTRA_ARGS \
        < /dev/null >> "$DETACH_LOG" 2>&1 &
    local BG_PID=$!

    # Disown so the shell doesn't wait for it
    disown "$BG_PID" 2>/dev/null || true

    echo "============================================"
    echo "  Deploy launched in background"
    echo "============================================"
    echo ""
    echo "  Mode:    $DEPLOY_MODE"
    echo "  PID:     $BG_PID"
    echo "  Log:     $DETACH_LOG"
    echo ""
    echo "  Monitor progress:"
    echo "    sudo bash scripts/safe-deploy.sh logs       # last 80 lines"
    echo "    sudo bash scripts/safe-deploy.sh logs -f    # follow live"
    echo "    sudo bash scripts/safe-deploy.sh status     # deployment status"
    echo ""
    echo "  The deploy will complete even if SSH disconnects."
    echo "  Maintenance mode is disabled automatically on success."
    echo "============================================"
}

run_detached "$@"
