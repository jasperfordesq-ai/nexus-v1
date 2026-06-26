#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Candidate Journey Gate — the "act like a real user" deploy-time safety check.
#
# Runs the proven @smoke browser journeys (e2e/tests/smoke.spec.ts via
# playwright.deploy.config.ts) against a freshly built blue/green CANDIDATE,
# BEFORE traffic is switched to it. A non-zero exit makes bluegreen-deploy.sh
# abort the cutover, so a build with a broken core journey never reaches users.
#
# Safety:
#   * The candidate shares the PRODUCTION database. The @smoke suite is
#     read-only + login only (no writes), so it is safe to run live.
#   * Self-skips (warn, exit 0) until E2E_GATE_USER_EMAIL / E2E_GATE_USER_PASSWORD
#     are present in $DEPLOY_DIR/.env. This makes rollout safe: the gate does not
#     block any deploy until you explicitly configure a dedicated test member.
#   * DEPLOY_SKIP_JOURNEYS=1 force-skips (use only for emergency rollback deploys).
#
# Usage (from bluegreen-deploy.sh):
#   bash phases/candidate-journeys.sh <api_port> <frontend_port> <color>

set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"

API_PORT="${1:-${NEXUS_API_PORT:-}}"
FRONTEND_PORT="${2:-${NEXUS_FRONTEND_PORT:-}}"
COLOR="${3:-${NEXUS_COLOR:-candidate}}"
TENANT="${E2E_GATE_TENANT:-hour-timebank}"
RUNNER_IMAGE="nexus-e2e-runner:pw-1.59.1"

env_value() {
    # Read a KEY=value from the deploy .env without exposing it on the process
    # list. Returns empty (never fails under set -e) when absent.
    local key="$1" val=""
    val="$(grep -E "^${key}=" "$DEPLOY_DIR/.env" 2>/dev/null | head -n1 | sed "s/^${key}=//" | tr -d "\"'")" || val=""
    printf '%s' "$val"
}

run_candidate_journeys() {
    log_step "=== Candidate Journey Gate ($COLOR) ==="

    if [ "${DEPLOY_SKIP_JOURNEYS:-0}" = "1" ]; then
        log_warn "DEPLOY_SKIP_JOURNEYS=1 — journey gate skipped by request"
        return 0
    fi

    if [ -z "$API_PORT" ] || [ -z "$FRONTEND_PORT" ]; then
        log_warn "Candidate ports unknown (api='$API_PORT' frontend='$FRONTEND_PORT') — journey gate skipped"
        return 0
    fi

    # Dedicated, low-privilege prod test-member credentials. Kept in .env, never
    # in git. Absent => gate not yet configured => skip (do NOT block deploys).
    local cred_email cred_pass
    cred_email="$(env_value E2E_GATE_USER_EMAIL)"
    cred_pass="$(env_value E2E_GATE_USER_PASSWORD)"
    if [ -z "$cred_email" ] || [ -z "$cred_pass" ]; then
        log_warn "E2E_GATE_USER_EMAIL/E2E_GATE_USER_PASSWORD not set in .env — journey gate NOT configured; skipping."
        log_warn "  -> Set both in $DEPLOY_DIR/.env (a dedicated test member) to ENABLE the gate."
        return 0
    fi

    # Build the runner image once; reused (and cached) on subsequent deploys.
    if ! docker image inspect "$RUNNER_IMAGE" >/dev/null 2>&1; then
        log_info "Building Playwright runner image $RUNNER_IMAGE (one-time)..."
        if ! docker build -f "$DEPLOY_DIR/Dockerfile.e2e" -t "$RUNNER_IMAGE" "$DEPLOY_DIR" >>"$LOG_FILE" 2>&1; then
            log_err "Failed to build journey runner image — see $LOG_FILE"
            return 1
        fi
    fi

    # Defensive: ensure empty auth fixtures exist so any skipped storage-state
    # test never errors on a missing file (admin specs skip without creds).
    mkdir -p "$DEPLOY_DIR/e2e/fixtures/.auth" 2>/dev/null || true
    for f in user admin; do
        [ -f "$DEPLOY_DIR/e2e/fixtures/.auth/$f.json" ] || \
            printf '{"cookies":[],"origins":[]}' > "$DEPLOY_DIR/e2e/fixtures/.auth/$f.json" 2>/dev/null || true
    done

    log_info "Running @smoke journeys against candidate $COLOR (frontend :$FRONTEND_PORT, API :$API_PORT)..."
    # --network host so 127.0.0.1:<port> reaches the candidate's published ports.
    # Tests + config mounted read-only so the run always uses the deployed commit.
    if docker run --rm --network host \
        -e CI=1 \
        -e E2E_BASE_URL="http://127.0.0.1:$FRONTEND_PORT" \
        -e E2E_API_URL="http://127.0.0.1:$API_PORT" \
        -e E2E_REACT_URL="http://127.0.0.1:$FRONTEND_PORT" \
        -e E2E_TENANT="$TENANT" \
        -e E2E_USER_EMAIL="$cred_email" \
        -e E2E_USER_PASSWORD="$cred_pass" \
        -v "$DEPLOY_DIR/e2e:/work/e2e:ro" \
        -v "$DEPLOY_DIR/playwright.deploy.config.ts:/work/playwright.deploy.config.ts:ro" \
        "$RUNNER_IMAGE" 2>&1 | tee -a "$LOG_FILE"; then
        log_ok "Candidate journey gate PASSED — safe to switch traffic to $COLOR"
        return 0
    fi

    log_err "Candidate journey gate FAILED — NOT switching traffic. Candidate $COLOR will be discarded."
    return 1
}

run_candidate_journeys
