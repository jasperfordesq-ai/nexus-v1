#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Deployment lock + cleanup trap. Sourced by the orchestrator.
# Requires common.sh and state.sh already sourced. Maintenance helpers
# sourced lazily inside cleanup() to avoid load-order issues.

check_lock() {
    if [ -f "$LOCK_FILE" ]; then
        LOCK_PID=$(cat "$LOCK_FILE" 2>/dev/null || echo "0")

        # Lock files older than 2 hours are always stale (no legitimate deploy takes that long,
        # even with --no-cache full rebuilds + migrations). This is a safety net — the EXIT trap
        # should already have removed the lock in normal failure paths.
        LOCK_AGE=$(( $(date +%s) - $(stat -c %Y "$LOCK_FILE" 2>/dev/null || echo "0") ))
        if [ "$LOCK_AGE" -gt 7200 ]; then
            log_warn "Lock file is $((LOCK_AGE / 60))m old (>2h) — removing stale lock"
            rm -f "$LOCK_FILE"
            return
        fi

        if ps -p "$LOCK_PID" > /dev/null 2>&1; then
            # Verify it's actually a deploy process, not a recycled PID
            PROC_CMD=$(ps -p "$LOCK_PID" -o args= 2>/dev/null || echo "")
            if echo "$PROC_CMD" | grep -Eq "safe-deploy|bluegreen-deploy"; then
                log_err "Another deployment is running (PID: $LOCK_PID, age: $((LOCK_AGE / 60))m)"
                exit 1
            else
                log_warn "Stale lock (PID $LOCK_PID recycled to different process) — removing"
                rm -f "$LOCK_FILE"
            fi
        else
            log_warn "Stale lock file found (PID $LOCK_PID dead) — removing"
            rm -f "$LOCK_FILE"
        fi
    fi
}

create_lock() {
    echo $$ > "$LOCK_FILE"
    log_info "Deployment lock created (PID: $$)"
}

cleanup() {
    local exit_code=$?
    # Kill any lingering keepalive background process (best effort — KEEPALIVE_PID
    # lived in a child subprocess in refactored layout, so normally a no-op here).
    if type stop_keepalive >/dev/null 2>&1; then
        stop_keepalive
    fi

    local maint_enabled_by_us deploy_success
    maint_enabled_by_us="$(state_get MAINTENANCE_ENABLED_BY_US)"
    deploy_success="$(state_get DEPLOY_SUCCESS)"

    # If WE enabled maintenance and deploy didn't succeed, auto-disable it.
    # The OLD containers are still running and healthy — bring the site back online.
    if [ "$maint_enabled_by_us" = "1" ] && [ "$deploy_success" = "0" ]; then
        echo ""
        log_warn "Deploy failed (exit $exit_code) — automatically disabling maintenance mode"
        # Use || true so the trap itself doesn't fail (we're already in cleanup)
        # shellcheck source=maintenance-helpers.sh
        . "$DEPLOY_DIR/scripts/deploy/lib/maintenance-helpers.sh" 2>/dev/null || true
        bash "$DEPLOY_DIR/scripts/deploy/phases/maintenance-off.sh" 2>/dev/null \
            || log_err "Could not auto-disable maintenance mode — run: sudo bash scripts/maintenance.sh off"
    fi

    if [ -f "$LOCK_FILE" ]; then
        rm -f "$LOCK_FILE"
        log_info "Deployment lock released"
    fi
}
