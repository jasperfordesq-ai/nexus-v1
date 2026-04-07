#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.
#
# check-i18n.sh — Detect hardcoded English strings in email templates and services.
#
# Scans PHP email/notification files for common hardcoded patterns that should
# use __() translation helpers. Run in CI or pre-push to prevent i18n regressions.
#
# Usage:
#   bash scripts/check-i18n.sh          # Check all email-related PHP files
#   bash scripts/check-i18n.sh --fix    # Show what needs fixing (no auto-fix)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# Files to scan: email templates, services that send emails, notification dispatchers
EMAIL_PATTERNS=(
    "app/Core/EmailTemplate.php"
    "app/Core/EmailTemplateBuilder.php"
    "app/Services/*Email*.php"
    "app/Services/NewsletterService.php"
    "app/Services/NotificationDispatcher.php"
    "app/Services/CronJobRunner.php"
    "app/Services/ListingExpiryService.php"
    "app/Services/ListingExpiryReminderService.php"
    "app/Services/ListingModerationService.php"
    "app/Services/AdminListingsService.php"
    "app/Services/RegistrationService.php"
    "app/Services/StoryService.php"
    "app/Services/JobModerationService.php"
    "app/Services/JobInterviewService.php"
    "app/Services/GuardianConsentService.php"
    "app/Services/SafeguardingService.php"
    "app/Services/SocialNotificationService.php"
    "app/Mail/*.php"
)

# Expand globs into actual file list
FILES=()
for pattern in "${EMAIL_PATTERNS[@]}"; do
    # Use nullglob-safe expansion
    for f in $PROJECT_ROOT/$pattern; do
        [ -f "$f" ] && FILES+=("$f")
    done
done

if [ ${#FILES[@]} -eq 0 ]; then
    echo "✅ No email-related PHP files found to check."
    exit 0
fi

VIOLATIONS=0
VIOLATION_DETAILS=""

# ─────────────────────────────────────────────────────────────────────────────
# Pattern checks — common hardcoded strings that should be translated
# ─────────────────────────────────────────────────────────────────────────────

check_pattern() {
    local pattern="$1"
    local description="$2"
    local exclude_pattern="${3:-}"

    for file in "${FILES[@]}"; do
        local relative="${file#$PROJECT_ROOT/}"
        local matches

        if [ -n "$exclude_pattern" ]; then
            matches=$(grep -nF "$pattern" "$file" 2>/dev/null | grep -v '__(' | grep -v '^\s*//' | grep -v '^\s*\*' || true)
        else
            matches=$(grep -nF "$pattern" "$file" 2>/dev/null | grep -v '^\s*//' | grep -v '^\s*\*' || true)
        fi

        if [ -n "$matches" ]; then
            while IFS= read -r line; do
                VIOLATIONS=$((VIOLATIONS + 1))
                VIOLATION_DETAILS="${VIOLATION_DETAILS}\n  ❌ ${relative}:${line}\n     → ${description}"
            done <<< "$matches"
        fi
    done
}

echo "🔍 Checking email-related PHP files for hardcoded strings..."
echo "   Scanning ${#FILES[@]} files..."
echo ""

# Footer strings that should use __('emails.footer.*')
check_pattern "All rights reserved" "Use __('emails.footer.all_rights_reserved')" "__\("
check_pattern "Manage Notification Preferences" "Use __('emails.footer.manage_preferences')" "__\("
check_pattern "Manage Preferences" "Use __('emails.footer.manage_preferences')" "__\("

# Common greetings that should use __('emails.common.greeting')
check_pattern "Hi {\$" "Use __('emails.common.greeting', ['name' => \$name])" "exclude"
check_pattern ">Hi {\$" "Use __('emails.common.greeting', ['name' => \$name])" "exclude"

# Button fallback text
check_pattern "If the button" "Use __('emails.common.button_fallback')" "__\("

# Member/subscriber notices
check_pattern "You received this email because" "Use __('emails.footer.member_notice') or similar" "__\("
check_pattern "you are a member of" "Use __('emails.footer.member_notice')" "__\("

# Common button text that should be translated
check_pattern "'>View Job<" "Use __('emails.job_alert.view_job')" "__\("
check_pattern "'>View Your Profile<" "Use __('emails.notification.view_profile')" "__\("
check_pattern "'>View Your Wallet<" "Use __('emails.federation.view_wallet')" "__\("
check_pattern "'>View Leaderboard<" "Use __('emails.gamification_digest.view_leaderboard')" "__\("

# Subject lines with hardcoded English (common patterns)
# These use grep -v '__(' to exclude properly translated lines
check_pattern '$subject = "' "Email subjects should use __() translations" "exclude"
check_pattern "\$subject = '" "Email subjects should use __() translations" "exclude"

# ─────────────────────────────────────────────────────────────────────────────
# Also check React admin sidebar for the recurring sidebar.* prefix bug
# ─────────────────────────────────────────────────────────────────────────────

SIDEBAR_FILE="$PROJECT_ROOT/react-frontend/src/admin/components/AdminSidebar.tsx"
if [ -f "$SIDEBAR_FILE" ]; then
    # Allow sidebar.admin, sidebar.expand_sidebar, sidebar.collapse_sidebar, sidebar.broker_panel
    BAD_SIDEBAR=$(grep -n "t('sidebar\." "$SIDEBAR_FILE" 2>/dev/null \
        | grep -v "sidebar\.admin" \
        | grep -v "sidebar\.expand_sidebar" \
        | grep -v "sidebar\.collapse_sidebar" \
        | grep -v "sidebar\.broker_panel" \
        | grep -v '^\s*//' || true)

    if [ -n "$BAD_SIDEBAR" ]; then
        while IFS= read -r line; do
            VIOLATIONS=$((VIOLATIONS + 1))
            VIOLATION_DETAILS="${VIOLATION_DETAILS}\n  ❌ react-frontend/src/admin/components/AdminSidebar.tsx:${line}\n     → Use t('key') not t('sidebar.key') — sidebar keys are at JSON root level"
        done <<< "$BAD_SIDEBAR"
    fi
fi

# ─────────────────────────────────────────────────────────────────────────────
# Report
# ─────────────────────────────────────────────────────────────────────────────

if [ $VIOLATIONS -gt 0 ]; then
    echo "⚠️  Found ${VIOLATIONS} potential i18n violation(s):"
    echo ""
    echo -e "$VIOLATION_DETAILS"
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "All user-facing strings in email templates must use __() translations."
    echo "Add keys to lang/en/emails.json, then reference with __('emails.section.key')."
    echo "See CLAUDE.md section: NO HARDCODED STRINGS"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    exit 1
else
    echo "✅ No hardcoded string violations found in email templates."
    exit 0
fi
