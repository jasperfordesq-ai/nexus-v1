#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TMP_DIR="$(mktemp -d -t nexus-prerender-plan-XXXXXX)"
trap 'rm -rf "$TMP_DIR"' EXIT

export PRERENDER_CONFIG_DIR="$TMP_DIR"
export PRERENDER_CODE_DIR="$REPO_ROOT"
export PRERENDER_OUTPUT_DIR="$TMP_DIR/output"
export NGINX_CONTAINER="test-prerender-nginx"

# shellcheck source=../prerender-tenants.sh
source "$REPO_ROOT/scripts/prerender-tenants.sh"
# The production script installs its own cleanup trap when sourced. This test
# owns only its temp directory and must never invoke Docker during teardown.
trap - EXIT INT TERM
trap 'rm -rf "$TMP_DIR"' EXIT

FORCE_RENDER=1
FILTER_TENANT=""
FILTER_ROUTES=""
EXISTING_CACHE_PATHS=""
STALE_CACHE_PATHS=""
RECENT_FAILURE_PATHS=""

PLAN_JSON='{"tenants":[{"tenant_id":2,"slug":"alpha","host":"app.example.test","prefix":"/alpha","routes":["/","/about","/blog/alpha-post","/page/custom-alpha"]},{"tenant_id":3,"slug":"bravo","host":"bravo.example.test","prefix":"","routes":["/","/about","/blog/bravo-post"]}]}'
MANIFEST="$TMP_DIR/manifest.json"

build_manifest_from_plan "$PLAN_JSON" "$MANIFEST"

python3 - "$MANIFEST" "$SELECTED_COUNT" "$TOTAL_COUNT" <<'PY'
import json
import sys

path, selected, total = sys.argv[1:]
with open(path, encoding='utf-8') as source:
    manifest = json.load(source)

entries = manifest['urls']
assert int(selected) == 7, selected
assert int(total) == 7, total
assert len(entries) == 7

by_tenant = {}
for entry in entries:
    by_tenant.setdefault(entry['tenantSlug'], set()).add(entry['route'])
    assert '//' not in entry['cachePath'], entry['cachePath']
    assert entry['tenantId'] in {'2', '3'}

assert by_tenant['alpha'] == {'/', '/about', '/blog/alpha-post', '/page/custom-alpha'}, by_tenant
assert by_tenant['bravo'] == {'/', '/about', '/blog/bravo-post'}, by_tenant
assert any(e['cachePath'] == 'app.example.test/alpha/index.html' for e in entries), entries
assert any(e['cachePath'] == 'bravo.example.test/index.html' for e in entries), entries
PY

if build_manifest_from_plan '{broken json' "$TMP_DIR/invalid.json"; then
    echo "FAIL: invalid tenant plan was accepted" >&2
    exit 1
fi

if build_manifest_from_plan \
    '{"tenants":[{"tenant_id":2,"slug":"alpha","host":"app.example.test","prefix":"/alpha","routes":["/"]},{"tenant_id":3,"slug":"bravo","host":"127.0.0.1","prefix":"","routes":["/"]}]}' \
    "$TMP_DIR/private-host.json"; then
    echo "FAIL: a partial plan containing a private target was accepted" >&2
    exit 1
fi

if build_manifest_from_plan \
    '{"tenants":[{"tenant_id":2,"slug":"alpha","host":"app.example.test","prefix":"/wrong-tenant","routes":["/"]}]}' \
    "$TMP_DIR/wrong-prefix.json"; then
    echo "FAIL: a plan with mismatched tenant prefix was accepted" >&2
    exit 1
fi

PRERENDER_ALLOW_PRIVATE_HOSTS=1
export PRERENDER_ALLOW_PRIVATE_HOSTS
if ! build_manifest_from_plan \
    '{"tenants":[{"tenant_id":2,"slug":"alpha","host":"localhost","prefix":"/alpha","routes":["/"]}]}' \
    "$TMP_DIR/local-dev.json"; then
    echo "FAIL: explicit local-development private-host opt-in was ignored" >&2
    exit 1
fi
PRERENDER_ALLOW_PRIVATE_HOSTS=0
export PRERENDER_ALLOW_PRIVATE_HOSTS

echo "PASS: tenant-aware manifest ingestion preserves tenant routes and rejects unsafe partial plans"
