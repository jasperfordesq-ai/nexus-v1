# Nexus Bug Reporting System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a professional in-app problem reporting system for Nexus that captures user reports, redacted diagnostic context, and support workflow state without making email or Canny the system of record.

**Architecture:** Nexus owns the user-facing intake, triage data, tenant scoping, and admin workflow. Sentry remains the technical diagnostics engine for crashes, stack traces, breadcrumbs, releases, and optional masked replay. GitHub, Linear, or another engineering tracker is fed from accepted reports rather than receiving raw user submissions directly.

**Tech Stack:** React 19, TypeScript, HeroUI v3, Tailwind CSS 4, Laravel 12/PHP 8.2, MariaDB, Sentry React/PHP, existing Nexus i18n and notification conventions.

---

## Product Direction

The correct professional pattern is a hybrid system:

- **Nexus in-app reporter:** first-class, translated, tenant-aware user intake.
- **Sentry:** stack traces, breadcrumbs, release/build metadata, optional masked replay, alerting.
- **Nexus admin triage:** statuses, severity, dedupe, owner, notes, member follow-up.
- **Engineering tracker:** GitHub/Linear/Jira issues created only after triage.
- **Canny:** product ideas, public roadmap, voting, and non-sensitive feedback only.

The current footer-only Canny link is too hidden and sends bug reports to the wrong kind of system. Keep Canny for product feedback, but move operational bug reporting into Nexus.

## User-Facing UI

Place `Report a problem` in these surfaces:

- App header/profile menu.
- Mobile drawer.
- Admin sidebar/footer.
- Help Centre.
- Error boundary fallback screen.
- Global API/server-error toast action.
- Footer as a secondary fallback.

The modal should be short:

- What happened?
- What were you trying to do?
- Severity/impact.
- Optional screenshot.
- Checkbox to include technical diagnostics.

After submission, show a report reference such as `Report #1234` and store the report in the user's account where possible.

## Data Capture

Capture by default:

- Tenant ID, slug, module, feature flags.
- User ID, role, locale, timezone.
- Route, referrer, page title.
- Browser, OS, viewport, device class, PWA/service-worker state.
- Build commit and build time.
- Last Sentry event ID where available.
- Last 50 API calls as redacted method/path/status/duration records.
- Last 50 console warnings/errors as redacted text.
- Last 20 navigation/action breadcrumbs.

Never capture by default:

- Passwords, tokens, cookies, auth headers.
- Request or response bodies.
- Private message content.
- Payment, safeguarding, health, or identity-document content.
- Raw email/phone values in diagnostics.

Capture only with explicit user action:

- Screenshot.
- Masked session replay link.
- Larger diagnostic bundle.

## Privacy And Consent

Use a conservative default:

- Passive telemetry stays minimal and redacted.
- User-submitted reports may include technical details only when the user leaves the checkbox enabled.
- Full replay starts with on-error sampling only, masked by default.
- Do not enable request/response body capture unless a future DPIA and explicit allowlist exist.
- Keep privacy/cookie pages accurate before enabling replay.

## Triage Workflow

Statuses:

- `new`
- `triaging`
- `needs_info`
- `reproduced`
- `engineering`
- `fixed_pending_deploy`
- `monitoring`
- `closed`

Severity:

- `p0`: security, data leak, payments, account lockout, data loss.
- `p1`: core flow broken for many users.
- `p2`: important module degraded.
- `p3`: annoyance, visual issue, typo.

Core admin actions:

- Assign owner.
- Change status/severity.
- Add internal note.
- Mark duplicate.
- Link Sentry issue/event.
- Link GitHub/Linear issue.
- Send translated member update.

## Implementation Tasks

### Task 1: Root Plan And Scope

**Files:**
- Create: `BUG_REPORTING_SYSTEM_PLAN.md`

- [x] Save the plan in the repository root so it is easy to find.
- [x] Explicitly separate Nexus intake, Sentry diagnostics, Canny product feedback, and engineering tracker workflow.

### Task 2: Backend Report Intake

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_support_reports_table.php`
- Create: `app/Models/SupportReport.php`
- Create: `app/Http/Controllers/Api/SupportReportController.php`
- Modify: API route definitions.
- Test: Laravel feature test for creating a support report.

- [ ] Write a failing feature test for an authenticated member submitting a support report.
- [ ] Create a tenant-scoped `support_reports` table with JSON diagnostics and nullable external links.
- [ ] Add a controller that validates title/body/impact/diagnostics and stores the report for the current tenant and user.
- [ ] Return a stable public reference code in the API response.
- [ ] Verify unauthenticated or cross-tenant writes cannot occur.

### Task 3: Frontend Diagnostics Buffer

**Files:**
- Create: `react-frontend/src/lib/supportDiagnostics.ts`
- Modify: `react-frontend/src/lib/api.ts`
- Modify: `react-frontend/src/lib/sentry.ts`
- Test: Vitest unit tests for redaction and bounded buffers.

- [ ] Write failing tests for token/email/password redaction.
- [ ] Write failing tests that API and console buffers keep only the most recent safe entries.
- [ ] Implement a small in-memory diagnostics buffer.
- [ ] Add API breadcrumbs into the buffer alongside existing Sentry breadcrumbs.
- [ ] Add safe console warning/error capture without breaking browser console behaviour.

### Task 4: In-App Problem Reporter

**Files:**
- Create: `react-frontend/src/components/support/ReportProblemModal.tsx`
- Create: `react-frontend/src/components/support/ReportProblemButton.tsx`
- Create or modify: translations under `react-frontend/src/locales/*`
- Test: React/Vitest tests for modal submission and diagnostics opt-in.

- [ ] Write failing tests for opening the modal, filling the form, and posting the report.
- [ ] Build the modal using HeroUI v3 `Modal`, `Button`, `TextArea`, `Select`, `Checkbox`, and `Alert`.
- [ ] Use translated strings for every visible label and message.
- [ ] Submit to the backend support report endpoint.
- [ ] Show the returned report reference after success.

### Task 5: Prominent Entry Points

**Files:**
- Modify: app shell/profile menu/mobile drawer components.
- Modify: `react-frontend/src/components/layout/Footer.tsx`
- Modify: `react-frontend/src/components/feedback/ErrorBoundary.tsx`
- Test: component tests for each entry point where coverage exists.

- [ ] Replace the footer Canny bug link with a native Nexus report action.
- [ ] Add `Report a problem` to visible support/profile navigation.
- [ ] Add a report action to the error boundary fallback.
- [ ] Keep Canny linked separately as product feedback or roadmap if desired.

### Task 6: Admin Triage Dashboard

**Files:**
- Create: backend admin list/detail/update endpoints.
- Create: `react-frontend/src/admin/modules/support/SupportReportsAdminPage.tsx`
- Modify: admin routing/sidebar.
- Test: Laravel authorization tests and React list/update tests.

- [ ] List reports by status, severity, tenant, and date.
- [ ] View redacted diagnostics.
- [ ] Update status/severity/owner.
- [ ] Link Sentry and GitHub/Linear references.
- [ ] Add internal notes.

### Task 7: Notifications And SLAs

**Files:**
- Create or modify: Laravel notification/listener classes.
- Modify: `lang/en/emails.json` and notification translation files.
- Test: locale-context notification tests.

- [ ] Notify tenant admins on new P0/P1 reports.
- [ ] Send all user-facing email text through translations.
- [ ] Wrap per-recipient email rendering in `LocaleContext::withLocale`.
- [ ] Add digest/low-priority routing for P2/P3 to avoid alert fatigue.

### Task 8: Sentry Feedback, Replay, And Issue Links

**Files:**
- Modify: `react-frontend/src/lib/sentry.ts`
- Modify: deployment/env documentation.
- Test: Sentry helper unit tests where practical.

- [ ] Add Sentry user feedback integration behind consent/config.
- [ ] Enable masked on-error replay only after privacy copy is updated.
- [ ] Upload source maps in CI/deploy so stack traces are readable.
- [ ] Configure Sentry alerts for new P0/P1 issues.
- [ ] Configure Linear/GitHub integration for accepted engineering issues.

## Verification Commands

Run focused checks after each slice:

```bash
vendor/bin/phpunit --testsuite=Laravel --filter=SupportReport
cd react-frontend && npm test -- support
cd react-frontend && npx tsc --noEmit
npm run check:i18n:baseline
```

Run broader checks before completion:

```bash
vendor/bin/phpunit --testsuite=Laravel,LaravelMigrated --colors=always
cd react-frontend && npm run build
npm run check:i18n:gaps
```

## Initial Rollout

Ship in this order:

1. Native Nexus support report intake.
2. Diagnostics buffer and visible UI entry points.
3. Admin triage dashboard.
4. Notifications and status updates.
5. Sentry feedback/replay enhancements.
6. Engineering tracker automation.

Do not enable broad session replay, request/response body capture, or auto-created public GitHub issues in the first release.
