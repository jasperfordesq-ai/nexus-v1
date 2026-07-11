#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PROCESSOR="$REPO_ROOT/scripts/prerender-job-processor.sh"
TMP_DIR="$(mktemp -d -t nexus-prerender-pgroup-XXXXXX)"
trap 'rm -rf "$TMP_DIR"' EXIT

command -v setsid >/dev/null
command -v timeout >/dev/null
grep -Fq 'kill -TERM -- "-$WORKER_PGID"' "$PROCESSOR"
grep -Fq 'kill -0 -- "-$WORKER_PGID"' "$PROCESSOR"
grep -Fq 'PRERENDER_JOB_CLAIMED_BY="$JOB_CLAIMED_BY"' "$PROCESSOR"

# Reproduce the processor topology: setsid -> timeout -> bash child -> child.
# A group TERM must remove the complete tree, not only the timeout wrapper.
setsid timeout --foreground --signal=TERM --kill-after=2s 60s \
    bash -c 'sleep 60 & echo "$!" > "$1/child.pid"; wait' _ "$TMP_DIR" &
leader=$!
for _ in $(seq 1 50); do
    [ -s "$TMP_DIR/child.pid" ] && break
    sleep 0.05
done
[ -s "$TMP_DIR/child.pid" ]
child="$(cat "$TMP_DIR/child.pid")"
kill -0 "$leader"
kill -0 "$child"

kill -TERM -- "-$leader"
set +e
wait "$leader"
set -e
for _ in $(seq 1 50); do
    if ! kill -0 "$child" 2>/dev/null; then break; fi
    sleep 0.05
done
if kill -0 "$child" 2>/dev/null; then
    echo "FAIL: prerender descendant survived process-group termination" >&2
    exit 1
fi

# A dead session leader does not imply an empty process group. Reproduce an
# orphaned descendant and prove the group can still be detected and killed.
setsid bash -c 'sleep 60 & echo "$!" > "$1/orphan.pid"; wait' _ "$TMP_DIR" &
orphan_leader=$!
for _ in $(seq 1 50); do
    [ -s "$TMP_DIR/orphan.pid" ] && break
    sleep 0.05
done
orphan_child="$(cat "$TMP_DIR/orphan.pid")"
kill -KILL "$orphan_leader"
set +e
wait "$orphan_leader" 2>/dev/null
set -e
kill -0 "$orphan_child"
kill -0 -- "-$orphan_leader"
kill -KILL -- "-$orphan_leader"
for _ in $(seq 1 50); do
    if ! kill -0 "$orphan_child" 2>/dev/null; then break; fi
    sleep 0.05
done
if kill -0 "$orphan_child" 2>/dev/null; then
    echo "FAIL: orphaned prerender descendant survived process-group termination" >&2
    exit 1
fi

echo "PASS: lease-loss termination removes the complete prerender process group"
