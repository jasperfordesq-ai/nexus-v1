#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"

# --- TD14: Schedule post-deploy container health check ---
# Runs 5 minutes after a successful deploy to catch OOMKills / memory
# pressure that only manifest once real traffic hits the new containers.
# This is OBSERVABILITY — a failure here does NOT fail the deploy (the
# deploy is already done). We log a warning for the operator to follow up.
if [ -x "$DEPLOY_DIR/scripts/check-container-health.sh" ]; then
    log_info "Scheduling post-deploy health check in 5 minutes (background)"
    (
        # CRITICAL: disable the parent's EXIT trap in this subshell. Otherwise
        # when the delayed check finishes (~5 min later) the inherited
        # cleanup() trap will run and rm -f "$LOCK_FILE" — potentially
        # deleting the lock of a NEW deploy started in the meantime.
        trap - EXIT
        sleep 300
        LOG="$LOG_DIR/post-deploy-health-$(date +%Y%m%d-%H%M%S).log"
        if LOCAL_MODE=1 bash "$DEPLOY_DIR/scripts/check-container-health.sh" > "$LOG" 2>&1; then
            echo "[$(date -Iseconds)] post-deploy health check: PASS — see $LOG" >> "$LOG_DIR/health-checks.log"
        else
            echo "[$(date -Iseconds)] post-deploy health check: FAIL — see $LOG" >> "$LOG_DIR/health-checks.log"
            # Best-effort notification via syslog — Apache error logs get scraped
            logger -t nexus-deploy "POST-DEPLOY HEALTH CHECK FAILED — inspect $LOG" 2>/dev/null || true
        fi
    ) </dev/null >/dev/null 2>&1 &
    disown 2>/dev/null || true
    log_ok "Post-deploy check scheduled (PID $!) — results in $LOG_DIR/health-checks.log"
else
    log_warn "Post-deploy health check script not found or not executable: $DEPLOY_DIR/scripts/check-container-health.sh"
fi
