#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Cross-phase state storage. Phases run as subprocesses so bash globals don't
# survive — state lives in files under /tmp/nexus-deploy-state/.

NEXUS_STATE_DIR="${NEXUS_STATE_DIR:-/tmp/nexus-deploy-state}"

state_init() {
    mkdir -p "$NEXUS_STATE_DIR"
    chmod 0700 "$NEXUS_STATE_DIR"
}

state_set() {
    local key="$1"
    local value="$2"
    mkdir -p "$NEXUS_STATE_DIR"
    printf '%s' "$value" > "$NEXUS_STATE_DIR/$key"
}

state_get() {
    local key="$1"
    if [ -f "$NEXUS_STATE_DIR/$key" ]; then
        cat "$NEXUS_STATE_DIR/$key"
    else
        echo ""
    fi
}

state_clear() {
    if [ -n "$NEXUS_STATE_DIR" ] && [ -d "$NEXUS_STATE_DIR" ]; then
        rm -rf "$NEXUS_STATE_DIR"
    fi
}
