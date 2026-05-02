#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.
#
# Post-deploy notification via webhook (Slack-compatible JSON).
# Called automatically by bluegreen-deploy.sh and safe-deploy.sh after every
# deploy attempt (success or failure).
#
# Configure by adding to /opt/nexus-php/.env:
#   NEXUS_DEPLOY_WEBHOOK_URL=https://hooks.slack.com/services/T.../B.../...
#
# The payload is compatible with Slack Incoming Webhooks and any service
# that accepts the standard {"text": "...", "blocks": [...]} format.

set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"

notify_deploy() {
    local status="${1:-success}"   # success | failure
    local commit="${2:-}"
    local subject="${3:-}"
    local color="${4:-}"           # blue | green | '' (empty for safe-deploy path)
    local duration="${5:-}"

    # Resolve commit and subject if not provided
    if [ -z "$commit" ]; then
        commit="$(git -C "$DEPLOY_DIR" rev-parse --short HEAD 2>/dev/null || echo unknown)"
    fi
    if [ -z "$subject" ]; then
        subject="$(git -C "$DEPLOY_DIR" log -1 --format='%s' 2>/dev/null || echo '')"
    fi

    # Read webhook URL from .env or environment
    local WEBHOOK_URL
    WEBHOOK_URL="$(grep "^NEXUS_DEPLOY_WEBHOOK_URL=" "$DEPLOY_DIR/.env" 2>/dev/null \
        | sed 's/^NEXUS_DEPLOY_WEBHOOK_URL=//' | tr -d "\"'" || true)"
    WEBHOOK_URL="${WEBHOOK_URL:-${NEXUS_DEPLOY_WEBHOOK_URL:-}}"

    if [ -z "$WEBHOOK_URL" ]; then
        log_info "NEXUS_DEPLOY_WEBHOOK_URL not set — skipping deploy notification"
        return 0
    fi

    # Build message
    local icon text
    if [ "$status" = "success" ]; then
        icon=":white_check_mark:"
        text="Deploy succeeded"
    else
        icon=":x:"
        text="Deploy FAILED"
    fi

    # Escape double quotes in subject for JSON safety
    subject="$(printf '%s' "$subject" | sed 's/"/\\"/g')"
    commit="$(printf '%s' "$commit" | sed 's/"/\\"/g')"

    # Build optional fields
    local fields
    fields="[{\"type\":\"mrkdwn\",\"text\":\"*Commit:* \`${commit}\`\"}"
    [ -n "$subject" ]  && fields="${fields},{\"type\":\"mrkdwn\",\"text\":\"*Change:* ${subject}\"}"
    [ -n "$color" ]    && fields="${fields},{\"type\":\"mrkdwn\",\"text\":\"*Active color:* ${color}\"}"
    [ -n "$duration" ] && fields="${fields},{\"type\":\"mrkdwn\",\"text\":\"*Duration:* ${duration}\"}"
    fields="${fields},{\"type\":\"mrkdwn\",\"text\":\"*Server:* api.project-nexus.ie\"}"
    fields="${fields}]"

    local payload
    payload="{\"text\":\"${icon} *${text}* — \`${commit}\`\",\"blocks\":[{\"type\":\"section\",\"text\":{\"type\":\"mrkdwn\",\"text\":\"${icon} *${text}*\n${subject}\"},\"fields\":${fields}}]}"

    if curl -s -X POST "$WEBHOOK_URL" \
        -H "Content-Type: application/json" \
        --max-time 10 --connect-timeout 5 \
        -d "$payload" > /dev/null 2>&1; then
        log_ok "Deploy notification sent"
    else
        log_warn "Deploy notification failed to send (non-fatal)"
    fi
}

notify_deploy "${1:-success}" "${2:-}" "${3:-}" "${4:-}" "${5:-}"
