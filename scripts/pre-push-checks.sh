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

# 1. Public docs stay curated and free of task-output junk
echo ""
echo "[1/5] Documentation hygiene check"
if node scripts/check-docs-hygiene.mjs; then
    echo "  docs hygiene OK"
else
    echo "  docs hygiene failed"
    FAIL=$((FAIL + 1))
fi

# 2. Platform version references stay in sync
echo ""
echo "[2/5] Version consistency check"
if node scripts/check-version-consistency.mjs; then
    echo "  version consistency OK"
else
    echo "  version consistency failed"
    FAIL=$((FAIL + 1))
fi

# 3. Release-relevant work updates CHANGELOG.md
echo ""
echo "[3/5] Changelog guard"
CHANGELOG_ARGS=()
UPSTREAM_REF="$(git rev-parse --abbrev-ref --symbolic-full-name @{u} 2>/dev/null || true)"
if [ -n "$UPSTREAM_REF" ]; then
    CHANGELOG_ARGS=(--base "$UPSTREAM_REF" --allow-missing-base)
fi

if node scripts/check-changelog-updated.mjs "${CHANGELOG_ARGS[@]}"; then
    echo "  changelog guard OK"
else
    echo "  changelog guard failed"
    FAIL=$((FAIL + 1))
fi

# 4. Required env vars documented in .env.example match config/ usage
echo ""
echo "[4/5] Env var documentation check"
if php scripts/validate-env.php --file=.env.example; then
    echo "  ✓ env vars OK"
else
    echo "  ✗ env var validation failed"
    FAIL=$((FAIL + 1))
fi

# 5. Artisan cache fail-fast (requires running dev container)
if [ "${SKIP_ARTISAN:-0}" != "1" ]; then
    echo ""
    echo "[5/5] Artisan cache fail-fast test"
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
    echo "[5/5] Artisan cache test skipped (SKIP_ARTISAN=1)"
fi

echo ""
if [ $FAIL -gt 0 ]; then
    echo "=== Pre-push checks FAILED ($FAIL) ==="
    exit 1
fi
echo "=== Pre-push checks passed ==="
exit 0
