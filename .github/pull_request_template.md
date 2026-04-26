## Summary
<!-- 1-3 bullet points describing what this PR does -->

## Type of Change
- [ ] Bug fix (non-breaking change which fixes an issue)
- [ ] New feature (non-breaking change which adds functionality)
- [ ] Breaking change (fix or feature that would cause existing functionality to not work as expected)
- [ ] Refactoring (no functional changes)
- [ ] Documentation update

## Contributor Terms
- [ ] I have read and agree to `CONTRIBUTOR_TERMS.md`, including the licence grant, patent grant, ownership/employer-permission confirmation, third-party-code restrictions, and AI-disclosure requirements.
- [ ] I confirm I own this contribution or am authorised to submit it on behalf of the person or organisation that owns it.
- [ ] I have disclosed meaningful AI-generated or AI-assisted contributions in this PR, or this PR contains no meaningful AI-generated or AI-assisted contribution.

**Third-Party Material Disclosure:** None
<!-- If this PR includes third-party material, list the source, licence, and why it is compatible with Project NEXUS licensing. -->

**AI Contribution Disclosure:** None
<!-- If AI tools materially generated, rewrote, translated, designed, or structured this PR, list the tool/model and what it was used for. -->

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

## Translation Review (required when non-English locale files change)
**Translation Status:**
<!-- Use `reviewed` or `approved` when locale content changed -->

**Translation Reviewer:**
<!-- Name or @handle of the reviewer who checked the locale content -->

**Translation Notes:**
<!-- Optional: call out machine-filled follow-up, scope, or reviewer caveats -->

## Pre-Deployment Checklist
- [ ] `npx tsc --noEmit` passes in `react-frontend/`
- [ ] `npm run build` succeeds in `react-frontend/`
- [ ] PHP syntax check passes
- [ ] New DELETE/UPDATE queries include `AND tenant_id = ?` for tenant-scoped tables
- [ ] No `data.data ??` patterns introduced (use `'data' in data ? data.data : data`)
- [ ] No new `as any` type assertions without justification
- [ ] If locale files changed, `npm run check:i18n:drift` still passes
- [ ] If locale files changed, `npm run check:i18n:baseline` still passes
- [ ] If locale files changed, `npm run check:i18n:gaps` was reviewed and any machine-filled copy is called out in the PR

## Test Plan
<!-- How to verify this change works -->

---
Generated with [Claude Code](https://claude.com/claude-code)
