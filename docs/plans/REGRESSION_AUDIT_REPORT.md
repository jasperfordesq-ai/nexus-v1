# PROJECT NEXUS — FULL REGRESSION AUDIT REPORT

**Date:** 2026-02-17
**Audited by:** 7 parallel Claude Opus 4.6 agents
**Scope:** Git history, architecture, code quality, production server, deployment pipeline, known regressions

---

## EXECUTIVE SUMMARY

**Your codebase has a 46.7% fix rate.** Out of 435 commits in 60 days, 203 are fixes. That's nearly 1 fix for every feature. The root causes are systemic — not individual mistakes:

1. **No CI/CD pipeline** — Zero automated testing gates before code reaches production
2. **No test coverage to speak of** — 120+ PHP services, virtually no unit tests. 41+ React pages, no component tests
3. **Deployment is manual and error-prone** — The `.bat` script is missing `--no-cache`, uses `scp` instead of `rsync`, and runs no tests
4. **Agent swarm output ships unverified** — Features built overnight go straight to production with known gaps in 9+ integration files
5. **78% of bug prevention relies on humans reading documentation** — Only 22% of known bugs have automated prevention

---

## THE 5 ROOT CAUSES (Why Things Keep Breaking)

### ROOT CAUSE 1: No Automated Testing Gate

**This is the #1 reason for regression.** There is:
- No CI/CD pipeline (no GitHub Actions, no pre-deploy test run)
- No pre-commit hooks enforcing tests
- The deployment scripts (`deploy-production.bat`, `deploy-production.sh`) run zero tests before deploying
- PHPUnit tests exist but are not run as part of any automated workflow
- Playwright E2E tests exist but reference `staging.timebank.local` (old XAMPP URL), not the Docker environment
- React Vitest config exists but test files are sparse

**Impact:** Every deployment is a gamble. A column rename, a method name change, or a response shape change can break production silently.

### ROOT CAUSE 2: Fix-Without-Root-Cause Pattern

The git history shows a devastating pattern: symptoms get patched, which creates new symptoms.

**Exhibit A — The Scroll Catastrophe (Jan 19):**
11 contradictory commits in 2.5 hours, each undoing the previous. Commit at 17:39 added "aggressive scroll monitoring" that was removed 13 minutes later because it broke scrolling worse. Same bug resurfaced Feb 1 requiring 8 more commits.

**Exhibit B — The Security Fix Avalanche (Jan 23-24):**
15 fix commits across 2 days. Each round claimed to be "comprehensive" yet was followed by another round.

**Exhibit C — The Admin Panel Fix Chain (Feb 14-17):**
Agent swarm builds admin panel → 6+ fix commits over 3 days including "11 API bugs found via deep verification."

### ROOT CAUSE 3: Agent Swarm Ships Without Verification

The "overnight swarm" pattern consistently produces:
- Features with gaps in shared integration points (routes.php, App.tsx, Navbar, types, etc.)
- Code that uses wrong method names (`getInput()` vs `getAllInput()`)
- Undefined constants (`ApiErrorCodes::NOT_FOUND`)
- Type mismatches (`as any` casts instead of proper types)
- 31 instances of a non-existent constant shipped in one swarm session

Every swarm session is followed by 1-3 days of fix commits.

### ROOT CAUSE 4: No API Contract Enforcement

PHP and React are completely decoupled with no contract validation:
- TypeScript types (1,194 lines in `api.ts`) exist only at compile time
- No runtime schema validation (no Zod, no OpenAPI enforcement)
- PHP backend can change response shapes silently
- Evidence: `Group` type has 3 image fields and 2 count fields (compatibility aliases from past breakage)
- Event schema: TypeScript says `start_date`, PHP returns `start_time`

### ROOT CAUSE 5: Environment Drift Between Local and Production

- **3 conflicting Docker configurations** in the repo (`compose.yml`, `compose.prod.yml`, `docker/docker-compose.prod.yml`)
- Production PHP allows **50MB uploads**, local allows **100MB** — files upload locally but fail on production
- Production `max_execution_time` is **60s**, local is **120s** — long operations timeout only on production
- No automated migration tracking — no way to know what migrations have been applied where
- Production DB has **260 tables**, last documented sync was at 254 tables
- The `.bat` deployment script is missing `--no-cache` (a known production outage cause!)

---

## CRITICAL FINDINGS

### LIVE PRODUCTION ERRORS (Found During This Audit)

```
Column not found: 1054 Unknown column 'approval_status' in 'WHERE'
SQL: SELECT COUNT(*) FROM users WHERE tenant_id = ? AND (status = 'pending' OR approval_status = 'pending')
Referer: https://hour-timebank.ie/admin-legacy/broker-controls/stats

Column not found: 1054 Unknown column 'tenant_id' in 'WHERE'
SQL: SELECT COUNT(*) FROM error_404_log WHERE tenant_id = ? AND resolved = 0
Referer: https://hour-timebank.ie/admin-legacy/broker-controls/stats
```

These errors are happening **right now** on production. The broker-controls stats page queries columns that don't exist.

### Missing Tenant Scoping — DATA LEAK RISK

**~30 SQL queries** across controllers and services perform DELETE/UPDATE/SELECT on user-facing tables using only the primary key `id`, without a `AND tenant_id = ?` guard:

- `DELETE FROM users WHERE id = ?` (AdminController.php)
- `DELETE FROM feed_posts WHERE id = ?` (SocialApiController.php)
- `DELETE FROM comments WHERE id = ?` (CommentService.php)
- `UPDATE users SET xp = xp + ? WHERE id = ?` (GamificationService.php)
- `UPDATE users SET balance = balance + ? WHERE id = ?` (FederatedTransactionService.php)
- And ~25 more across GoalService, PollService, MessageService, GroupService, etc.

In a multi-tenant application, **any DELETE/UPDATE without tenant_id is a potential data leak between tenants.**

### Response Unwrapping Bug Still Active

`api.ts` line 568 (upload method) still uses the dangerous `data.data ?? data` pattern:
```typescript
// WRONG (line 568) - upload() method
return { success: true, data: data.data ?? data };

// CORRECT (line 395) - request() method
data: 'data' in data ? data.data : data,
```

Additionally, **8 React pages** double-unwrap responses (`response.data.data ?? ...`), which works by accident today but will break if the API client changes.

### Sales Site Container Missing

The `nexus-sales-site` container does not exist on production. `project-nexus.ie` (port 3001) is not running.

---

## HOT FILES (Most Unstable)

| File | Fix Commits | Total Commits | Rating |
|------|-------------|---------------|--------|
| `ConversationPage.tsx` | 11 | 11 | CRITICAL |
| `MobileDrawer.tsx` | 11 | 13 | CRITICAL |
| `Navbar.tsx` | 10 | 13 | HIGH |
| `routes.php` | 14 | 42 | HIGH |
| `App.tsx` | 8 | 13 | HIGH |
| `api.ts` | 7 | 8 | HIGH |
| `MessagesPage.tsx` | 8 | 8 | HIGH |
| `DashboardPage.tsx` | 7 | 7 | MODERATE |

---

## THE FIX — PRIORITIZED ACTION PLAN

### TIER 1: STOP THE BLEEDING (Do This Week)

1. **Fix the live production SQL errors** — The broker-controls/stats page has two broken queries right now. Either add the missing columns (`approval_status` on `users`, `tenant_id` on `error_404_log`) or fix the queries.

2. **Fix `api.ts` upload() unwrapping** — Line 568: change `data.data ?? data` to `'data' in data ? data.data : data`.

3. **Fix `deploy-production.bat`** — Add `--no-cache` to the Docker build command. This exact bug has caused production outages before.

4. **Add tenant_id to critical DELETE queries** — Start with AdminController, SocialApiController, and CommentService. Every `DELETE FROM x WHERE id = ?` should be `DELETE FROM x WHERE id = ? AND tenant_id = ?`.

### TIER 2: BUILD THE SAFETY NET (Do This Month)

5. **Create a minimal CI/CD pipeline** — GitHub Actions that runs:
   - `npx tsc --noEmit` (TypeScript check)
   - `npx vite build` (React build)
   - `vendor/bin/phpunit` (PHP tests)
   - Block deployment if any fail

6. **Add regression tests for known bugs** — Write tests for:
   - API response unwrapping (`{data: null}` returns `null`, not wrapper)
   - Token refresh serialization
   - Tenant scoping on critical operations
   - Event/Group field name validation

7. **Create a post-swarm verification checklist script** — Automated check that verifies all 9+ integration files are wired when a new feature is added.

8. **Delete the orphaned `docker/` directory** — It contains a completely different architecture (php-fpm, MySQL 8, Traefik, Vault) that has never been deployed and creates dangerous confusion.

9. **Document the production `.env` file** — Create a `deploy-production-env.example` with all required variables. The current production `.env` is a single point of failure with no backup or template.

### TIER 3: STRUCTURAL IMPROVEMENTS (Ongoing)

10. **Add runtime API response validation** — Use Zod or similar to validate API responses match TypeScript types at runtime. Start with the most-changed endpoints.

11. **Namespace localStorage keys by tenant** — Change `nexus_access_token` to `nexus_${tenantSlug}_access_token` to prevent cross-tenant token contamination.

12. **Migrate standalone controllers to extend BaseApiController** — 13 controllers define their own `jsonResponse()`, missing rate limit headers, tenant headers, and standardized error codes.

13. **Fix TypeScript type mismatches** — Event `start_date` → `start_time`, Group redundant fields, User type with all-optional fields.

14. **Align PHP limits between local and production** — Upload max (50MB vs 100MB), execution time (60s vs 120s). Developers need to encounter the same constraints locally.

15. **Add automated migration tracking** — Track which migrations have been applied to each environment. Add a migration step to the deployment pipeline.

---

## PRODUCTION HEALTH SNAPSHOT (At Time of Audit)

| Metric | Status |
|--------|--------|
| Server uptime | 12 days |
| Container restarts | 0 (all containers) |
| PHP API health | HEALTHY |
| React frontend health | HEALTHY |
| Database tables | 260 (was 254 at last documented sync) |
| Disk usage | 34% (82G/247G) |
| Memory usage | 19% (2.9G/15G) |
| Active SQL errors | 2 (broker-controls/stats) |
| Sales site | DOWN (container missing) |
| Email sending | DISABLED (`USE_GMAIL_API=false`) |

---

## KNOWN REGRESSION REGISTER (37 Documented Bugs)

| Severity | Count | % With Automated Prevention |
|----------|-------|---------------------------|
| CRITICAL | 6 | 0% (all documentation-only) |
| HIGH | 19 | ~26% |
| MEDIUM | 12 | ~25% |
| **TOTAL** | **37** | **22%** |

| Risk of Recurrence | Count |
|-------------------|-------|
| HIGH | 14 (38%) |
| MEDIUM | 15 (41%) |
| LOW | 8 (21%) |

**The bottom line: 78% of known bugs are prevented only by someone reading documentation. 38% of known bugs have a HIGH risk of recurring.**

---

*This report was generated by 7 parallel audit agents examining: git history (435 commits), architecture (1,524 routes, 120+ services, 41+ pages), code quality (~30 unscoped queries, 14 `as any` casts, 14 exhaustive-deps suppressions), production server (live SSH inspection), deployment pipeline (both .bat and .sh scripts), and all documented regressions across CLAUDE.md, MEMORY.md, and project documentation.*
