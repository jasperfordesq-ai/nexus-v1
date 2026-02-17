## Summary
<!-- 1-3 bullet points describing what this PR does -->

## Type of Change
- [ ] Bug fix (non-breaking change which fixes an issue)
- [ ] New feature (non-breaking change which adds functionality)
- [ ] Breaking change (fix or feature that would cause existing functionality to not work as expected)
- [ ] Refactoring (no functional changes)
- [ ] Documentation update

## Root Cause Analysis (required for bug fixes)
<!-- If this is a bug fix, you MUST fill in these fields. Delete this section for non-fix PRs. -->
**What was the bug?**
<!-- Describe the symptoms -->

**Root Cause:**
<!-- What actually caused it — not "the code was wrong" but WHY it was wrong -->

**Why wasn't it caught earlier?**
<!-- Missing test? Missing type? No CI check? -->

**Prevention:**
<!-- What have you added to stop this from ever happening again? (test, type guard, CI check, etc.) -->

## Pre-Deployment Checklist
- [ ] `npx tsc --noEmit` passes in `react-frontend/`
- [ ] `npm run build` succeeds in `react-frontend/`
- [ ] PHP syntax check passes
- [ ] New DELETE/UPDATE queries include `AND tenant_id = ?` for tenant-scoped tables
- [ ] No `data.data ??` patterns introduced (use `'data' in data ? data.data : data`)
- [ ] No new `as any` type assertions without justification

## Test Plan
<!-- How to verify this change works -->

---
Generated with [Claude Code](https://claude.com/claude-code)
