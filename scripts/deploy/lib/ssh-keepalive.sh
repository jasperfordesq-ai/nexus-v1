#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# SSH keepalive for Docker builds.
# Azure NAT gateway kills idle TCP connections after ~4 minutes.
# During --no-cache builds, C compilation of PHP extensions produces no stdout
# for minutes at a time, causing SSH disconnects. This wrapper prints a dot
# every 30s to keep the connection alive.

KEEPALIVE_PID=""

start_keepalive() {
    # Skip keepalive in detached mode — no SSH session to keep alive
    if [ -n "${__NEXUS_DEPLOY_DETACHED__:-}" ]; then return 0; fi
    ( while true; do echo -n "." | tee -a "$LOG_FILE"; sleep 30; done ) &
    KEEPALIVE_PID=$!
}

stop_keepalive() {
    if [ -n "$KEEPALIVE_PID" ] && kill -0 "$KEEPALIVE_PID" 2>/dev/null; then
        kill "$KEEPALIVE_PID" 2>/dev/null
        wait "$KEEPALIVE_PID" 2>/dev/null || true
        KEEPALIVE_PID=""
        echo ""  # newline after dots
    fi
}

# Run a docker build with SSH keepalive active
build_with_keepalive() {
    start_keepalive
    local rc=0
    "$@" || rc=$?
    stop_keepalive
    return $rc
}
