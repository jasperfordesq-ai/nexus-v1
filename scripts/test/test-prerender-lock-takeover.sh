#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Smoke test for scripts/prerender-tenants.sh acquire_lock() lock-or-cancel.
#
# Three scenarios:
#   1. Fresh lock — no prior holder. acquire_lock should succeed and write pid.
#   2. Stale lock — pid file points at dead pid. Should reclaim.
#   3. Live lock — pid file points at running pid. Should kill it and reclaim.
#
# Test isolation: uses a temp PRERENDER_CONFIG_DIR. Stubs docker via PATH so
# real docker is never invoked. Avoids the trap cleanup of the real script
# affecting test state by running each scenario in a subshell.
#
# Run: bash scripts/test/test-prerender-lock-takeover.sh

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/prerender-tenants.sh"
PASS=0
FAIL=0

assert() {
    local DESC="$1"
    local COND="$2"
    if [ "$COND" = "true" ] || [ "$COND" = "1" ]; then
        echo "  PASS: $DESC"
        PASS=$((PASS + 1))
    else
        echo "  FAIL: $DESC"
        FAIL=$((FAIL + 1))
    fi
}

# Stub `docker` on PATH so the script can't invoke real docker
STUB_DIR="$(mktemp -d -t nexus-test-stubs-XXXXXX)"
cat > "$STUB_DIR/docker" <<'STUB'
#!/usr/bin/env bash
# Stub: succeed silently for kill/rm/stop. Anything else is unexpected
# in the lock-takeover code path; record it.
case "${1:-}" in
    kill|rm|stop) exit 0 ;;
    *)
        echo "STUB-DOCKER-UNEXPECTED: $*" >> "${STUB_LOG:-/tmp/nexus-stub-docker.log}"
        exit 0
        ;;
esac
STUB
chmod +x "$STUB_DIR/docker"
export PATH="$STUB_DIR:$PATH"
export STUB_LOG="$(mktemp -t nexus-stub-docker-XXXXXX.log)"

trap 'rm -rf "$STUB_DIR" "$STUB_LOG" 2>/dev/null || true' EXIT

# --------------------------------------------------------------------------
# Scenario 1: fresh lock
# --------------------------------------------------------------------------
echo "Scenario 1: fresh lock"
(
    TMP="$(mktemp -d -t nexus-prerender-test-XXXXXX)"
    trap 'rm -rf "$TMP" 2>/dev/null || true' EXIT
    export PRERENDER_CONFIG_DIR="$TMP"
    export PRERENDER_CODE_DIR="$REPO_ROOT"
    # Source script — main() is guarded by BASH_SOURCE check so we just get
    # functions and globals.
    # shellcheck disable=SC1090
    source "$SCRIPT" --dry-run >/dev/null 2>&1 || true
    # `set -e` from the sourced script could kill us; re-enable defensively.
    set +e

    LOCK_DIR_VAL="$TMP/.prerender-lock"
    PID_FILE="$LOCK_DIR_VAL/pid"

    if [ -d "$LOCK_DIR_VAL" ]; then
        # Source may have invoked acquire_lock indirectly via dry-run path
        rm -rf "$LOCK_DIR_VAL"
    fi

    acquire_lock 2>/dev/null
    [ -d "$LOCK_DIR_VAL" ] && [ -f "$PID_FILE" ] && [ "$(cat "$PID_FILE")" = "$$" ] && echo PASS_S1 || echo FAIL_S1
) | grep -q PASS_S1 && assert "fresh lock acquired and pid written" 1 || assert "fresh lock acquired and pid written" 0

# --------------------------------------------------------------------------
# Scenario 2: stale lock (dead pid)
# --------------------------------------------------------------------------
echo "Scenario 2: stale lock (dead pid)"
(
    TMP="$(mktemp -d -t nexus-prerender-test-XXXXXX)"
    trap 'rm -rf "$TMP" 2>/dev/null || true' EXIT
    export PRERENDER_CONFIG_DIR="$TMP"
    export PRERENDER_CODE_DIR="$REPO_ROOT"
    # Pre-create a stale lock with a definitely-dead pid (kernel.pid_max = 4M
    # max on Linux; 999999999 is reliably dead).
    LOCK_DIR_VAL="$TMP/.prerender-lock"
    mkdir -p "$LOCK_DIR_VAL"
    echo "999999999" > "$LOCK_DIR_VAL/pid"

    # shellcheck disable=SC1090
    source "$SCRIPT" --dry-run >/dev/null 2>&1 || true
    set +e

    # Force re-lock attempt (lock dir exists, pid is dead → must reclaim)
    if [ -d "$LOCK_DIR_VAL" ]; then
        # If source already ran main, it left the lock. Reset for our own test.
        rm -rf "$LOCK_DIR_VAL"
        mkdir -p "$LOCK_DIR_VAL"
        echo "999999999" > "$LOCK_DIR_VAL/pid"
    fi

    acquire_lock 2>/dev/null
    NEW_PID="$(cat "$LOCK_DIR_VAL/pid" 2>/dev/null)"
    [ -d "$LOCK_DIR_VAL" ] && [ "$NEW_PID" = "$$" ] && echo PASS_S2 || echo FAIL_S2
) | grep -q PASS_S2 && assert "stale lock with dead pid reclaimed" 1 || assert "stale lock with dead pid reclaimed" 0

# --------------------------------------------------------------------------
# Scenario 3: live lock (running pid is killed and superseded)
# --------------------------------------------------------------------------
echo "Scenario 3: live lock (running pid)"
(
    TMP="$(mktemp -d -t nexus-prerender-test-XXXXXX)"
    trap 'rm -rf "$TMP" 2>/dev/null || true; kill -9 "$VICTIM_PID" 2>/dev/null || true' EXIT
    export PRERENDER_CONFIG_DIR="$TMP"
    export PRERENDER_CODE_DIR="$REPO_ROOT"

    # Spawn a victim process with a known pid
    sleep 60 &
    VICTIM_PID=$!

    LOCK_DIR_VAL="$TMP/.prerender-lock"
    mkdir -p "$LOCK_DIR_VAL"
    echo "$VICTIM_PID" > "$LOCK_DIR_VAL/pid"

    # shellcheck disable=SC1090
    source "$SCRIPT" --dry-run >/dev/null 2>&1 || true
    set +e

    # Reset lock to the live victim (source may have stomped it)
    rm -rf "$LOCK_DIR_VAL"
    mkdir -p "$LOCK_DIR_VAL"
    echo "$VICTIM_PID" > "$LOCK_DIR_VAL/pid"

    acquire_lock 2>/dev/null

    # Wait up to 12s for victim to die (script's grace is 10s + 1s for SIGKILL)
    for i in 1 2 3 4 5 6 7 8 9 10 11 12; do
        if ! kill -0 "$VICTIM_PID" 2>/dev/null; then
            break
        fi
        sleep 1
    done

    NEW_PID="$(cat "$LOCK_DIR_VAL/pid" 2>/dev/null)"
    if ! kill -0 "$VICTIM_PID" 2>/dev/null && [ "$NEW_PID" = "$$" ]; then
        echo PASS_S3
    else
        echo "FAIL_S3 victim_alive=$(kill -0 "$VICTIM_PID" 2>/dev/null && echo yes || echo no) new_pid=$NEW_PID our_pid=$$"
    fi
) | grep -q PASS_S3 && assert "live lock killed and reclaimed" 1 || assert "live lock killed and reclaimed" 0

echo ""
echo "Result: $PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ]
