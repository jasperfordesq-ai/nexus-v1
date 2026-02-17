# Project NEXUS — Regression Prevention Guide

**Created:** 2026-02-17
**Updated:** 2026-02-17
**Purpose:** Prevent recurring bugs with 7 automated layers of protection. Zero regressions going forward.

---

## The 5 Root Causes of Regression (Identified Feb 17, 2026)

Full audit of 435 commits identified 37 documented bugs across 5 systemic root causes:

| # | Root Cause | Prevention | Status |
|---|-----------|------------|--------|
| 1 | No CI/CD pipeline | 5-stage GitHub Actions pipeline (BLOCKING) | FIXED |
| 2 | Agent swarms ship without verification | Husky pre-commit/pre-push hooks + `verify-feature.sh` | FIXED |
| 3 | Fix-without-root-cause loops | PR template + CI enforced root cause sections | FIXED |
| 4 | No API contract enforcement | Zod runtime validation (dev) + CI pattern detection | FIXED |
| 5 | Environment drift (local vs production) | Dockerfile drift detection (CI stage 4) + local script | FIXED |

---

## 7 Layers of Protection

### Layer 1: Pre-Commit Hook (Husky + lint-staged)

**Trigger:** Every `git commit`
**Blocking:** Yes

Runs lint-staged on staged files only (fast — sub-second for small changes):
- TypeScript files (`*.ts`, `*.tsx`): `tsc --noEmit`
- PHP files (`*.php`): `php -l` syntax check

**Config:** Root `package.json` → `lint-staged` section
**Hook:** `.husky/pre-commit`

### Layer 2: Pre-Push Hook (Husky)

**Trigger:** Every `git push`
**Blocking:** Yes

Runs full build pipeline before code leaves the machine:
```bash
cd react-frontend && npx tsc --noEmit && npm run build
```

**Hook:** `.husky/pre-push`

### Layer 3: CI/CD Pipeline (GitHub Actions)

**Trigger:** Every push to `main` + every PR
**Blocking:** Yes (all 5 stages)

| Stage | Name | What it checks |
|-------|------|---------------|
| 1 | PHP Checks | Syntax + PHPUnit Unit tests + Service tests (with MariaDB 10.11 + Redis 7) |
| 2 | React Build & Tests | `tsc --noEmit` + Vitest `npm test -- --run` + `npm run build` |
| 3 | Docker Build Verify | Builds all 3 containers (PHP, PHP-prod, React), verifies health endpoint |
| 4 | Dockerfile Drift Detection | Compares 6 PHP settings between `Dockerfile` and `Dockerfile.prod` — fails on mismatch |
| 5 | Regression Pattern Detection | 3 automated checks (see below) |

**Stage 5 Regression Checks:**

| Check | Severity | What it detects |
|-------|----------|----------------|
| `data.data ??` pattern | **BLOCKING** | Incorrect API response unwrapping — causes ErrorBoundary crashes |
| `as any` count | WARNING (>20) | Excessive type assertion usage — TypeScript safety erosion |
| Unscoped DELETE | WARNING | DELETE FROM without `tenant_id` on tenant-scoped tables |

**File:** `.github/workflows/ci.yml`

### Layer 4: PR Root Cause Enforcement (GitHub Actions)

**Trigger:** Every PR to `main` with title containing "fix", "bug", or "hotfix"
**Blocking:** Yes

Checks PR description for two mandatory sections:
- **"Root Cause:"** — WHY the bug happened (not just "the code was wrong")
- **"Prevention:"** — What stops this from ever happening again

**File:** `.github/workflows/pr-checks.yml`
**Template:** `.github/pull_request_template.md`

### Layer 5: Zod Runtime Validation (Dev Only)

**Trigger:** Every API response in development mode
**Blocking:** No (console.warn only, never throws)
**Production overhead:** Zero (tree-shaken out)

Validates API response shapes against Zod schemas:

```tsx
// Schemas: react-frontend/src/lib/api-schemas.ts
// Validator: react-frontend/src/lib/api-validation.ts
// Wired into: api.ts, AuthContext.tsx, TenantContext.tsx

// Schemas defined for: user, login response, tenant bootstrap,
// wallet balance, transaction, listing, paginated responses
```

When a response doesn't match the expected shape, a grouped console.warn appears with:
- Which schema failed
- Which fields are wrong/missing
- The actual data received

This catches API contract changes **immediately** during development, before they ship.

### Layer 6: Local Verification Scripts

**Trigger:** On demand (before deployment or after agent swarms)
**Blocking:** No (informational)

```bash
# Check Dockerfile alignment
bash scripts/check-dockerfile-drift.sh

# Check for known regression patterns
bash scripts/check-regression-patterns.sh

# Full feature verification (after agent swarm)
bash scripts/verify-feature.sh <feature-name>
```

### Layer 7: Deployment Rules (Manual Enforcement)

These rules MUST be followed on every production deployment:

1. **`--no-cache` on all `docker compose build` commands** — prevents stale cached layers
2. **`sudo docker restart nexus-php-app` after PHP file changes** — OPCache never re-reads
3. **Never build React locally and upload `dist/`** — env vars will be wrong
4. **Always rebuild on the server** with correct `VITE_API_BASE`

---

## Mandatory Pre-Deployment Checklist

Before ANY deployment to production:

- [ ] `npx tsc --noEmit` passes in `react-frontend/` (0 errors)
- [ ] `npm run build` succeeds in `react-frontend/`
- [ ] PHP syntax check: all files in `src/` pass `php -l`
- [ ] `npm test -- --run` passes in `react-frontend/` (all Vitest tests)
- [ ] `scripts/verify-feature.sh` passes (if new features were added)
- [ ] `--no-cache` flag used with `docker compose build` on production
- [ ] `sudo docker restart nexus-php-app` after PHP file changes (OPCache)
- [ ] New DELETE/UPDATE queries include `AND tenant_id = ?` for tenant-scoped tables
- [ ] No `data.data ??` patterns introduced
- [ ] No new `as any` assertions without justification

---

## API Response Unwrapping Rules

The `api.ts` client at line 395 already unwraps the PHP backend's `{ data: X, meta: M }` envelope:

```
PHP returns:  { "data": [...], "meta": { "cursor": "abc" } }
api.ts gives: { success: true, data: [...], meta: { cursor: "abc" } }
```

### DO:
```tsx
// Access data directly
const items = response.data as MyType[];

// Access meta from top-level
const cursor = response.meta?.cursor;

// Access error from top-level
const errorMsg = response.error;

// Use Array.isArray for safety
const items = Array.isArray(response.data) ? response.data : [];
```

### DO NOT:
```tsx
// WRONG: Double-unwrapping — data is already unwrapped
const data = response.data as unknown as { data?: T };
const items = data.data ?? response.data;

// WRONG: data.data ?? pattern
return { success: true, data: data.data ?? data };

// WRONG: Accessing meta through double-cast
const responseData = response.data as unknown as { meta?: M };
const meta = responseData.meta;  // Use response.meta instead
```

### The Correct Unwrapping Pattern (api.ts):
```typescript
// CORRECT (line 395 of api.ts)
data: 'data' in data ? data.data : data,

// WRONG (was on line 568 — fixed Feb 17)
data: data.data ?? data  // treats {data: null} as nullish!
```

---

## Tenant Scoping Rules

### Every DELETE/UPDATE on a tenant-scoped table MUST include tenant_id:

```php
// CORRECT
Database::query(
    "DELETE FROM feed_posts WHERE id = ? AND tenant_id = ?",
    [$id, $tenantId]
);

// WRONG — can delete data from ANY tenant
Database::query(
    "DELETE FROM feed_posts WHERE id = ?",
    [$id]
);
```

### Tables that do NOT have tenant_id (DELETE by id is fine):
`error_404_log`, `login_attempts`, `password_resets`, `revoked_tokens`, `poll_votes`, `poll_options`, `menu_items`, `group_members`, `badge_collection_items`, `user_interests`, `message_reactions`, `listing_attributes`, `page_blocks`, `newsletter_queue`, `notification_queue`, `group_exchange_participants`, `legal_document_versions`, `user_active_unlockables`, `email_verification_tokens`, `federation_audit_log`, `pay_plans`, `connections`, `vol_applications`, `vol_logs`, `activity_log`

### Tables that are intentionally cross-tenant (MasterController):
`MasterController.php` operates across tenants for super admin operations — this is by design.

### How to check:
```bash
# Automated (CI stage 5)
# The CI pipeline checks for unscoped DELETEs automatically

# Manual
bash scripts/check-regression-patterns.sh
```

---

## Environment Alignment Rules

Local and production Dockerfiles MUST have matching limits:

| Setting | Value | File |
|---------|-------|------|
| `upload_max_filesize` | 50M | Both `Dockerfile` and `Dockerfile.prod` |
| `post_max_size` | 55M | Both |
| `max_execution_time` | 60 | Both |
| `max_input_time` | 60 | Both |
| `max_input_vars` | 3000 | Both |
| `memory_limit` | 256M | Both |

If you change a limit in one Dockerfile, **change it in both**. CI stage 4 will fail if they drift.

### OPCache Warning:
- Production: `opcache.validate_timestamps = 0` — PHP NEVER re-reads files
- After deploying PHP changes: `sudo docker restart nexus-php-app`

---

## Docker Build Rules

### ALWAYS use `--no-cache`:
```bash
# CORRECT
sudo docker compose build --no-cache frontend

# WRONG — may serve stale cached layers
sudo docker compose build frontend
```

### NEVER build React locally and upload dist/:
```bash
# CORRECT — build on the server
ssh azureuser@20.224.171.253
cd /opt/nexus-php && sudo docker compose build --no-cache frontend

# WRONG — env vars will be wrong
npm run build  # then scp dist/ to server
```

---

## Post-Swarm Verification

After any agent swarm builds features, run:
```bash
./scripts/verify-feature.sh <feature-name>
```

This checks:
1. TypeScript compilation
2. Vite production build
3. PHP syntax
4. Integration file wiring (routes.php, App.tsx, types, etc.)
5. Common regression patterns (wrong unwrapping, unscoped DELETEs, `as any` casts)
6. Tenant scoping in new services

### The 9+ Integration Files That Must Be Wired:
1. `httpdocs/routes.php` — API routes
2. `react-frontend/src/App.tsx` — Frontend routes
3. `react-frontend/src/types/api.ts` — TypeScript types
4. `react-frontend/src/lib/tenant-routing.ts` — Reserved paths
5. `react-frontend/src/contexts/TenantContext.tsx` — Feature/module flags
6. `react-frontend/src/components/layout/Navbar.tsx` — Navigation links
7. `react-frontend/src/components/layout/MobileDrawer.tsx` — Mobile navigation
8. `react-frontend/src/admin/routes.tsx` — Admin routes (if admin feature)
9. `react-frontend/src/admin/api/adminApi.ts` — Admin API client (if admin feature)

---

## Git Commit Convention for Fixes

The `.gitmessage` template enforces:

```
fix(scope): <subject>

Root Cause: <what actually caused the bug>
Prevention: <what stops this from happening again>

Co-Authored-By: Claude Opus 4.6 <noreply@anthropic.com>
```

PR template (`.github/pull_request_template.md`) requires the same sections, enforced by CI.

---

## Known Schema Gotchas

| What You Might Expect | Actual Column Name | Table |
|-----------------------|-------------------|-------|
| `start_date` | `start_time` | `events` |
| `end_date` | `end_time` | `events` |
| `image_url` | `cover_image` | `events` |
| `created_by` | `user_id` | `events` |
| `cover_image` | `image_url` | `groups` |
| `privacy` | `visibility` | `groups` |
| `referrer` | `referer` | `error_404_log` |
| `hits` | `hit_count` | `error_404_log` |
| `created_at` | `first_seen_at` | `error_404_log` |
| `site_name` | `name` | `TenantBranding` type |

---

## Quick Reference: File Locations

| What | Where |
|------|-------|
| **Git Hooks** | |
| Pre-commit hook | `.husky/pre-commit` |
| Pre-push hook | `.husky/pre-push` |
| Commit message template | `.gitmessage` |
| **CI/CD** | |
| Main CI pipeline | `.github/workflows/ci.yml` |
| PR root cause check | `.github/workflows/pr-checks.yml` |
| PR template | `.github/pull_request_template.md` |
| **Zod Validation** | |
| API schemas | `react-frontend/src/lib/api-schemas.ts` |
| Validation helper | `react-frontend/src/lib/api-validation.ts` |
| **Local Scripts** | |
| Dockerfile drift check | `scripts/check-dockerfile-drift.sh` |
| Regression pattern check | `scripts/check-regression-patterns.sh` |
| Feature verification | `scripts/verify-feature.sh` |
| **Documentation** | |
| This document | `docs/REGRESSION_PREVENTION.md` |
| Regression audit report | `docs/plans/REGRESSION_AUDIT_REPORT.md` |
| CLAUDE.md summary | `CLAUDE.md` → "Regression Prevention System" |
| **Key Source Files** | |
| API client (unwrapping) | `react-frontend/src/lib/api.ts` line 395 |
| Deploy script (Windows) | `scripts/deploy-production.bat` |
| Deploy script (Linux) | `scripts/deploy-production.sh` |
| Local Dockerfile | `Dockerfile` |
| Production Dockerfile | `Dockerfile.prod` |

---

## Audit History

| Date | Action | Result |
|------|--------|--------|
| 2026-02-17 | Full regression audit (7 agents, 435 commits) | Found 37 bugs, 5 root causes |
| 2026-02-17 | Fixed 37 double-unwrap patterns (19 React files) | 0 remaining |
| 2026-02-17 | Added tenant_id guards (20+ PHP files) | All tenant-scoped DELETEs now guarded |
| 2026-02-17 | Built 7-layer prevention system | All layers operational |
| 2026-02-17 | Deployed all fixes to production | API + Frontend verified healthy |
