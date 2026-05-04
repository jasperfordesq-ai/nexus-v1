#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Deployment lock + cleanup trap. Sourced by the orchestrator.
# Requires common.sh and state.sh already sourced. Maintenance helpers
# sourced lazily inside cleanup() to avoid load-order issues.

# Atomic lock using mkdir(2). On all POSIX filesystems mkdir is atomic — only
# ONE caller wins when two scripts race. This eliminates the TOCTOU window in
# the previous "stat then echo $$ >" approach.
LOCK_DIR="${LOCK_FILE}.d"

check_lock() {
    # Stale-recovery sweep BEFORE attempting to acquire. If the lock dir is
    # >2 h old or the recorded PID is dead/recycled, drop it.
    if [ -d "$LOCK_DIR" ]; then
        local lock_age recorded_pid
        lock_age=$(( $(date +%s) - $(stat -c %Y "$LOCK_DIR" 2>/dev/null || echo 0) ))

        if [ "$lock_age" -gt 7200 ]; then
            log_warn "Lock dir is $((lock_age / 60))m old (>2h) — removing stale lock"
            rm -rf "$LOCK_DIR"
            return
        fi

        recorded_pid="$(cat "$LOCK_DIR/pid" 2>/dev/null || echo "0")"
        if ! ps -p "$recorded_pid" > /dev/null 2>&1; then
            log_warn "Stale lock (PID $recorded_pid dead) — removing"
            rm -rf "$LOCK_DIR"
            return
        fi
        local proc_cmd
        proc_cmd="$(ps -p "$recorded_pid" -o args= 2>/dev/null || echo "")"
        if ! echo "$proc_cmd" | grep -Eq "safe-deploy|bluegreen-deploy"; then
            log_warn "Stale lock (PID $recorded_pid recycled) — removing"
            rm -rf "$LOCK_DIR"
            return
        fi
    fi
}

create_lock() {
    # mkdir is atomic — the loser sees EEXIST and we exit cleanly.
    if ! mkdir "$LOCK_DIR" 2>/dev/null; then
        local recorded_pid age
        recorded_pid="$(cat "$LOCK_DIR/pid" 2>/dev/null || echo unknown)"
        age=$(( $(date +%s) - $(stat -c %Y "$LOCK_DIR" 2>/dev/null || echo 0) ))
        log_err "Another deployment is running (PID: $recorded_pid, age: $((age / 60))m)"
        exit 1
    fi
    echo "$$" > "$LOCK_DIR/pid"
    # Mirror to legacy LOCK_FILE for any external watcher that still polls it.
    echo "$$" > "$LOCK_FILE"
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

    if [ -d "$LOCK_DIR" ] || [ -f "$LOCK_FILE" ]; then
        rm -rf "$LOCK_DIR" 2>/dev/null || true
        rm -f "$LOCK_FILE" 2>/dev/null || true
        log_info "Deployment lock released"
    fi
}
