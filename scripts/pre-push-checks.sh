#!/bin/bash
# =============================================================================
# Project NEXUS - Pre-Push Validation Bundle (TD15)
# =============================================================================
# Purpose: Run the cheap-but-critical checks that catch regressions which
# would otherwise only surface at container startup in production.
#
# Husky hooks are INTENTIONALLY DISABLED on this project (see MEMORY.md +
# feedback_husky_hooks.md). Do NOT wire this into .husky/pre-push without
# an explicit user instruction. Run it manually before `git push` or wire
# it into CI (already done — see .github/workflows/ci.yml PHP job).
#
# Usage:
#   bash scripts/pre-push-checks.sh            # run all
#   SKIP_ARTISAN=1 bash scripts/pre-push-checks.sh  # skip container-dependent
#
# Exit codes:
#   0 = all checks passed
#   1 = at least one check failed
# =============================================================================

set -u
FAIL=0

echo "=== Pre-push validation ==="

# 1. Required env vars documented in .env.example match config/ usage
echo ""
echo "[1/2] Env var documentation check"
if php scripts/validate-env.php --file=.env.example; then
    echo "  ✓ env vars OK"
else
    echo "  ✗ env var validation failed"
    FAIL=$((FAIL + 1))
fi

# 2. Artisan cache fail-fast (requires running dev container)
if [ "${SKIP_ARTISAN:-0}" != "1" ]; then
    echo ""
    echo "[2/2] Artisan cache fail-fast test"
    if bash scripts/test-artisan-cache.sh; then
        echo "  ✓ artisan cache OK"
    else
        rc=$?
        if [ "$rc" = "2" ]; then
            echo "  ⚠ dev container not running — skipped (run: docker compose up -d)"
        else
            echo "  ✗ artisan cache test failed"
            FAIL=$((FAIL + 1))
        fi
    fi
else
    echo ""
    echo "[2/2] Artisan cache test skipped (SKIP_ARTISAN=1)"
fi

echo ""
if [ $FAIL -gt 0 ]; then
    echo "=== Pre-push checks FAILED ($FAIL) ==="
    exit 1
fi
echo "=== Pre-push checks passed ==="
exit 0
