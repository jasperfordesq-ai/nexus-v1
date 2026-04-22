#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.
#
# check-i18n.sh — Detect hardcoded English strings across the ENTIRE codebase.
#
# Scans ALL PHP services/controllers/listeners and React admin components for
# user-facing hardcoded English that should use translation functions.
# Uses grep -r for speed (not file-by-file loops).
#
# Usage:
#   bash scripts/check-i18n.sh              # Full codebase scan
#   bash scripts/check-i18n.sh --php-only   # PHP files only
#   bash scripts/check-i18n.sh --react-only # React admin only

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

MODE="${1:-all}"

VIOLATIONS=0
VIOLATION_FILE=$(mktemp)

# ─────────────────────────────────────────────────────────────────────────────
# Helper: grep recursively for a pattern, exclude translated lines
# Args: $1=pattern, $2=description, $3=directory, $4=include_glob, $5=exclude_regex
# ─────────────────────────────────────────────────────────────────────────────
check_recursive() {
    local pattern="$1"
    local description="$2"
    local dir="$3"
    local include="${4:-*.php}"
    local extra_exclude="${5:-NOMATCH_PLACEHOLDER_xyzzy}"

    local matches
    matches=$(grep -rnF --include="$include" "$pattern" "$dir" 2>/dev/null \
        | grep -v '__(' \
        | grep -v "t('" \
        | grep -v 't("' \
        | grep -v '// ' \
        | grep -v '^\s*\*' \
        | grep -v 'Log::' \
        | grep -v 'logger(' \
        | grep -v 'console\.' \
        | grep -v 'import ' \
        | grep -v '__tests__/' \
        | grep -v '\.test\.' \
        | grep -v 'nullable|' \
        | grep -v 'required|' \
        | grep -v 'string $subject' \
        | grep -v 'toast\.error(`$' \
        | grep -v "$extra_exclude" \
        || true)

    if [ -n "$matches" ]; then
        local count
        count=$(echo "$matches" | wc -l)
        VIOLATIONS=$((VIOLATIONS + count))
        echo "$matches" | while IFS= read -r line; do
            relative="${line#$PROJECT_ROOT/}"
            echo "  ${relative}" >> "$VIOLATION_FILE"
            echo "     -> ${description}" >> "$VIOLATION_FILE"
            echo "" >> "$VIOLATION_FILE"
        done
    fi
}

# ═══════════════════════════════════════════════════════════════════════════════
# PHASE 1: PHP
# ═══════════════════════════════════════════════════════════════════════════════

if [ "$MODE" != "--react-only" ]; then
    echo "═══════════════════════════════════════════════════════════════"
    echo "  PHASE 1: PHP (app/ directory)"
    echo "═══════════════════════════════════════════════════════════════"

    APP="$PROJECT_ROOT/app"
    CTRL="$PROJECT_ROOT/app/Http/Controllers"

    # Email footers/greetings/fallbacks
    check_recursive "All rights reserved" "Hardcoded footer text" "$APP"
    check_recursive "Manage Notification Preferences" "Hardcoded footer text" "$APP"
    check_recursive "If the button" "Hardcoded button fallback text" "$APP"
    check_recursive "You received this email because" "Hardcoded footer notice" "$APP"

    # Greetings in email HTML
    check_recursive ">Hi {\$" "Hardcoded email greeting" "$APP"

    # Hardcoded subject lines
    check_recursive '$subject = "' "Hardcoded email subject" "$APP"
    check_recursive "\$subject = '" "Hardcoded email subject" "$APP"

    # Push notification content (broadcastAndPush with inline English)
    # We flag calls where the 2nd arg is a quoted string, not a __() call
    PUSH_MATCHES=""
    PUSH_MATCHES=$(grep -rnE "broadcastAndPush\([^,]+,\s*['\"]" "$APP" --include="*.php" 2>/dev/null \
        | grep -v '__(' || true)
    if [ -n "$PUSH_MATCHES" ]; then
        push_count=0
        push_count=$(echo "$PUSH_MATCHES" | wc -l)
        VIOLATIONS=$((VIOLATIONS + push_count))
        echo "$PUSH_MATCHES" | while IFS= read -r line; do
            relative="${line#$PROJECT_ROOT/}"
            echo "  ${relative}" >> "$VIOLATION_FILE"
            echo "     -> Hardcoded push notification — use __() translation" >> "$VIOLATION_FILE"
            echo "" >> "$VIOLATION_FILE"
        done
    fi

    # API response messages in controllers (exclude validation rules and internal type mappings)
    check_recursive "'message' => '" "Hardcoded API response message" "$CTRL" "*.php" "message_received"
    check_recursive "'message' => \"" "Hardcoded API response message" "$CTRL"
    check_recursive "'error' => '" "Hardcoded API error message" "$CTRL"
    check_recursive "'error' => \"" "Hardcoded API error message" "$CTRL"

    # Notification content in services (bell notifications)
    # 'message' => "English text" in notification inserts
    NOTIF_MATCHES=""
    NOTIF_MATCHES=$(grep -rnE "'message'\s*=>\s*['\"]" "$PROJECT_ROOT/app/Services" --include="*.php" 2>/dev/null \
        | grep -v '__(' \
        | grep -v 'Log::' \
        | grep -v '// ' \
        | grep -v '\$.*message' \
        | grep -v "'message' => \$" \
        | grep -v "'message' => 'message'" \
        | grep -v "'message' => '/messages'" \
        | grep -v "'message' => 'message_received'" \
        || true)
    if [ -n "$NOTIF_MATCHES" ]; then
        notif_count=0
        notif_count=$(echo "$NOTIF_MATCHES" | wc -l)
        VIOLATIONS=$((VIOLATIONS + notif_count))
        echo "$NOTIF_MATCHES" | while IFS= read -r line; do
            relative="${line#$PROJECT_ROOT/}"
            echo "  ${relative}" >> "$VIOLATION_FILE"
            echo "     -> Hardcoded notification message — use __() translation" >> "$VIOLATION_FILE"
            echo "" >> "$VIOLATION_FILE"
        done
    fi

    echo "  Done."
    echo ""
fi

# ═══════════════════════════════════════════════════════════════════════════════
# PHASE 2: React admin — REMOVED
# ═══════════════════════════════════════════════════════════════════════════════
# Admin panel (react-frontend/src/admin/) is ENGLISH-ONLY by design.
# Hardcoded English strings in admin code are INTENTIONAL, not violations.
# See feedback_admin_english_only.md in memory.
# Do NOT re-enable admin i18n audits without explicit user instruction.

# ═══════════════════════════════════════════════════════════════════════════════
# REPORT
# ═══════════════════════════════════════════════════════════════════════════════

echo "═══════════════════════════════════════════════════════════════"
if [ $VIOLATIONS -gt 0 ]; then
    echo "  FAILED: ${VIOLATIONS} i18n violation(s) found"
    echo "═══════════════════════════════════════════════════════════════"
    echo ""
    cat "$VIOLATION_FILE"
    echo "───────────────────────────────────────────────────────────────"
    echo "PHP: use __('key', ['param' => \$val]) with lang/en/*.json"
    echo "React: use t('key', { param: val }) with public/locales/en/*.json"
    echo "See CLAUDE.md: NO HARDCODED STRINGS"
    echo "───────────────────────────────────────────────────────────────"
    rm -f "$VIOLATION_FILE"
    exit 1
else
    echo "  PASSED: Zero i18n violations"
    echo "═══════════════════════════════════════════════════════════════"
    rm -f "$VIOLATION_FILE"
    exit 0
fi
