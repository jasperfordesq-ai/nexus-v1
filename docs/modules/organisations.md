# Organisations Module Guide

Last reviewed: 2026-06-23

Reference guide for the Organisations module: public directory, registration and approval workflow, opportunities on an org page, reviews, impact stats, and the owner/admin dashboard. Verified against `app/Services/VolunteerService.php`, `app/Http/Controllers/Api/VolunteerController.php`, `app/Http/Controllers/Api/AdminVolunteerController.php`, `app/Models/VolOrganization.php`, and `routes/api.php`.

> **Scope of this guide.** The Organisations module is built on top of the Volunteering module. Organisation wallet balance, the auto-mint-on-hours-approval flow, shift management, and the `VolOrgWalletService` are documented in the [Volunteering module guide](volunteering.md). Read that guide first if you are working on time-credit or hour-logging logic. This guide focuses on the organisation lifecycle (directory, registration, approval, public profile, opportunities, reviews, stats) and its own tenant/gate rules.

---

## Audience and supported workflows

| Persona | Supported workflows |
|---------|---------------------|
| **Visitor / member (browsing)** | Browse the public organisation directory, view an organisation profile, see its open opportunities and reviews. |
| **Member (registering)** | Submit a registration form; org is created at `status = 'pending'` and enters the approval queue. |
| **Org owner / admin** | Edit the organisation profile, view the dashboard (stats, volunteers, applications, pending hours), manage the org wallet (see Volunteering guide). |
| **Tenant admin** | Review pending registrations, approve (`active`) or suspend (`suspended`) organisations, adjust the org wallet. |
| **Platform super-admin** | Same as tenant admin with cross-tenant visibility. |

---

## Feature gate

Both the Organisations module and the underlying Volunteering module are on by default.

| Flag | Default | Source |
|------|---------|--------|
| `volunteering` | `true` | `app/Services/TenantFeatureConfig.php` — `FEATURE_DEFAULTS` |
| `organisations` | `true` | `app/Services/TenantFeatureConfig.php` — `FEATURE_DEFAULTS` |

In the React frontend, the public directory (`/organisations`) and the detail page (`/organisations/:id`) are gated on the `volunteering` feature flag (not a separate `organisations` flag):

```tsx
// react-frontend/src/App.tsx
<FeatureGate feature="volunteering" fallback={<ComingSoonPage />}>
  <OrganisationsPage />
</FeatureGate>
```

The registration page (`/organisations/register`) is also gated on `volunteering`. When the feature is off all three routes show a Coming Soon page or redirect.

On the PHP/API side every endpoint calls `VolunteerController::ensureFeature()` or an equivalent inline check that throws `403 FEATURE_DISABLED` when `TenantContext::hasFeature('volunteering')` is false.

---

## Tenant scoping

Every query is scoped by `TenantContext::getId()`:

- `VolOrganization` uses the `HasTenantScope` Eloquent trait, which automatically appends `AND tenant_id = ?` to all queries on that model.
- Raw SQL paths (dashboard stats, org member checks) pass `$tenantId` explicitly as a bind parameter.
- The public directory and detail endpoints are `withoutMiddleware('auth:sanctum')` but still scope by tenant. They never expose wallet balance or `auto_pay_enabled` to unauthenticated callers — those fields are stripped in `organisations()` and `showOrganisation()` before the response is returned.

---

## Key code locations

| Concern | Code |
|---------|------|
| Public directory and detail API | `app/Http/Controllers/Api/VolunteerController.php` — `organisations()`, `showOrganisation()` |
| Registration API | `VolunteerController::createOrganisation()`, `VolunteerService::createOrganization()` |
| Org owner dashboard API | `VolunteerController::orgStats()`, `orgVolunteers()`, `orgApplications()`, `orgHoursPending()` |
| Profile update API | `VolunteerController::updateOrganisation()` |
| Reviews API | `VolunteerController::createReview()`, `getReviews()` |
| Core service | `app/Services/VolunteerService.php` — `getOrganisations()`, `getOrganisationById()`, `createOrganization()`, `createReview()`, `getReviews()` |
| Org wallet | `app/Services/VolOrgWalletService.php` (see Volunteering guide) |
| Admin management | `app/Http/Controllers/Api/AdminVolunteerController.php` — `organizations()`, `createOrganization()`, `updateOrganization()`, `updateOrgStatus()`, `organizationMembers()`, `adjustOrgWallet()` |
| Eloquent model | `app/Models/VolOrganization.php` |
| Membership model | `app/Models/OrgMember.php` |
| Form request (registration) | `app/Http/Requests/Volunteering/CreateOrganisationRequest.php` |
| React directory page | `react-frontend/src/pages/organisations/OrganisationsPage.tsx` |
| React detail page | `react-frontend/src/pages/organisations/OrganisationDetailPage.tsx` |
| React registration page | `react-frontend/src/pages/organisations/RegisterOrganisationPage.tsx` |
| React owner dashboard | `react-frontend/src/pages/volunteering/MyOrganisationsPage.tsx` |
| Accessible frontend trait | `app/Http/Controllers/GovukAlpha/Concerns/OrganisationsParity.php` |
| Accessible parity routes | `routes/govuk-alpha-parity/organisations.php` |

---

## Database tables

| Table | Purpose |
|-------|---------|
| `vol_organizations` | One row per organisation. Key columns: `tenant_id`, `user_id` (owner), `name`, `slug`, `description`, `contact_email`, `website`, `logo_url`, `status` (`pending`/`active`/`approved`/`suspended`), `balance` `decimal(10,2)`, `auto_pay_enabled`, `dlp_user_id`, `deputy_dlp_user_id`. |
| `org_members` | Membership with role (`owner`/`admin`/`member`) and status (`active`/`pending`/`invited`/`removed`). `org_type = 'volunteer'` scopes rows to this module. |
| `vol_reviews` | Reviews for `target_type = 'organization'` (or `'user'`). Columns: `reviewer_id`, `target_type`, `target_id`, `rating` (1–5), `comment`, `approved`. |
| `vol_opportunities` | Opportunities linked to an org via `organization_id`. |
| `vol_logs` | Hour logs linked to an org via `organization_id`. Used for `total_approved_hours` in stats. |

The `status` column uses string values. The directory query (`getOrganisations`) filters on `whereIn('status', ['approved', 'active'])` to handle both the current `active` value and legacy `approved` rows.

---

## Registration and approval workflow

```
Member submits POST /v2/volunteering/organisations
  ↓
VolunteerService::createOrganization()
  → Validates name (≥3, ≤200 chars), description (≥20 chars), contact_email, optional website URL
  → Checks for duplicate name (case-insensitive) within the tenant
  → Generates a slug with retry on collision (up to 3 attempts inside DB::transaction)
  → Inserts row: status = 'pending'
  → Inserts org_members row: role = 'owner', status = 'active'
  ↓
Org is invisible in the public directory (directory filters on approved/active only)
  ↓
Tenant admin reviews via admin panel → PUT /v2/admin/volunteering/organizations/{id}/status
  → Accepts: status = 'active' or 'suspended'
  ↓
status = 'active' → org appears in public directory, owner can post opportunities
```

### Admin approval endpoints

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/v2/admin/volunteering/organizations` | List all orgs with stats (admin only) |
| `POST` | `/v2/admin/volunteering/organizations` | Admin-create an org (bypasses pending) |
| `PUT` | `/v2/admin/volunteering/organizations/{id}` | Update org fields (admin only) |
| `PUT` | `/v2/admin/volunteering/organizations/{id}/status` | Set status: `active` or `suspended` |
| `GET` | `/v2/admin/volunteering/organizations/{id}/members` | List org members |
| `PUT` | `/v2/admin/volunteering/organizations/{id}/wallet/adjust` | Adjust org wallet balance |
| `GET` | `/v2/admin/volunteering/organizations/{id}/wallet/transactions` | Org wallet transaction log |

All admin endpoints require the `auth:sanctum` middleware and a passing `requireAdmin()` check.

---

## Public API endpoints

The public directory endpoints are declared `withoutMiddleware('auth:sanctum')` and are safe to call without a session token. See `routes/api.php` for full definitions.

| Method | Path | Auth required | Description |
|--------|------|--------------|-------------|
| `GET` | `/v2/volunteering/organisations` | No | Paginated directory (cursor). Strips `balance` and `auto_pay_enabled`. |
| `GET` | `/v2/volunteering/organisations/{id}` | No | Single org with stats. Strips `balance` and `auto_pay_enabled`. |
| `GET` | `/v2/volunteering/my-organisations` | Yes | Orgs where the caller is owner/admin. |
| `POST` | `/v2/volunteering/organisations` | Yes | Register a new org (`status = 'pending'`). Rate-limited: 5/min. |
| `PUT` | `/v2/volunteering/organisations/{id}` | Yes (owner/admin) | Update org profile fields. |

Directory query parameters (`GET /v2/volunteering/organisations`):

| Param | Type | Default | Notes |
|-------|------|---------|-------|
| `per_page` | int | 20 | Max 50 |
| `search` | string | — | LIKE match on `name` and `description` |
| `cursor` | string | — | Base64-encoded last-seen id |

Each item in the directory response includes: `id`, `name`, `description`, `logo_url`, `website`, `contact_email`, `location`, `opportunity_count`, `volunteer_count`, `total_hours`, `average_rating`, `created_at`, and the `owner` sub-object (first/last name, avatar).

Full route definitions: `routes/api.php` lines ~793–814.

---

## Organisation owner/admin dashboard

The following endpoints are owner/admin-only; access is enforced by `ensureOrgAccess()` in `VolunteerController`, which checks:

1. The caller is the org's `user_id` (creator/owner), OR
2. The caller has an `active` `org_members` row with `role = 'owner'` or `'admin'` for that org, OR
3. The caller has a site-level `super_admin` or `god` role.

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/v2/volunteering/organisations/{id}/stats` | Totals: volunteers, pending applications, pending hours, approved hours, active opportunities, wallet balance, auto-pay flag. |
| `GET` | `/v2/volunteering/organisations/{id}/volunteers` | Cursor-paginated list of approved volunteers with total approved hours and application count. |
| `GET` | `/v2/volunteering/organisations/{id}/applications` | Cursor-paginated list of applications to this org's opportunities. |
| `GET` | `/v2/volunteering/organisations/{id}/hours/pending` | Hours pending review across all org opportunities. |
| `GET` | `/v2/volunteering/organisations/{id}/wallet` | Wallet balance and recent activity summary. |
| `GET` | `/v2/volunteering/organisations/{id}/wallet/transactions` | Paginated wallet transaction log. |
| `POST` | `/v2/volunteering/organisations/{id}/wallet/deposit` | Transfer credits from the caller's personal wallet into the org wallet. |
| `PUT` | `/v2/volunteering/organisations/{id}/wallet/auto-pay` | Toggle auto-pay. |

Wallet and auto-pay details are in the [Volunteering module guide](volunteering.md).

---

## Reviews

Reviews are shared with the Volunteering module (`vol_reviews` table). They cover both organisations and individual volunteers.

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| `POST` | `/v2/volunteering/reviews` | Yes | Create a review. `target_type` = `organization` or `user`. Rating 1–5. |
| `GET` | `/v2/volunteering/reviews/{type}/{id}` | No | List reviews for a target. `type` = `organization` or `user`. |

Constraints enforced by `VolunteerService::createReview()`:

- `target_type` must be `organization` or `user`.
- `rating` must be 1–5.
- Self-review is blocked when `target_type = 'user'`.
- Each reviewer may leave at most one review per target (duplicate check against `reviewer_id` + `target_type` + `target_id` + `tenant_id`).

Reviews are stored approved (`approved = 1`) by default. There is currently no moderation queue for org reviews.

---

## Impact stats (public profile)

`VolunteerService::getOrganisationById()` returns these aggregated stats on the public org profile:

| Field | Source |
|-------|--------|
| `opportunity_count` | `vol_opportunities` with `is_active = 1` |
| `total_hours` | `SUM(hours)` from `vol_logs` with `status = 'approved'` |
| `review_count` | Rows in `vol_reviews` for this org |
| `average_rating` | Average of `rating` column in `vol_reviews`, rounded to 1 dp |
| `volunteer_count` | Distinct `user_id` count from `vol_applications` with `status = 'approved'` |

The directory listing (`getOrganisations`) computes these with fixed-count grouped sub-queries over the page's org IDs, not correlated per-row subqueries, to keep list performance predictable.

---

## Security and privacy invariants

- **Wallet data never reaches unauthenticated callers.** `organisations()` and `showOrganisation()` explicitly `unset($org['balance'], $org['auto_pay_enabled'])` before returning. Owners read financial state from the ownership-scoped `/stats` endpoint.
- **Cross-tenant isolation.** All queries bind `tenant_id`. The `HasTenantScope` trait on `VolOrganization` adds the scope automatically to Eloquent queries; raw SQL paths pass it explicitly.
- **Org access gate.** `ensureOrgAccess()` returns `null` (→ `403`) for any caller who is neither the org's creator, an active owner/admin member, nor a site super-admin. There is no way to reach dashboard endpoints for an org in a different tenant because the DB lookup includes `AND tenant_id = ?`.
- **Duplicate name prevention.** `createOrganization()` rejects a registration when an org with the same case-insensitive name already exists for the tenant and has not been declined.
- **Rate limiting.** Registration: 5 calls/min per IP. Directory: 60/min. Org show: 120/min.

---

## Accessible (GOV.UK) frontend

The accessible frontend has a parity implementation for the Organisations module under `/{tenantSlug}/alpha/organisations/*`. It calls the same `VolunteerService` methods as the React API controllers — no business logic is reimplemented.

Routes (`routes/govuk-alpha-parity/organisations.php`):

| Path | Controller method | Description |
|------|------------------|-------------|
| `GET /organisations/browse` | `organisationsBrowse` | Paginated directory with cursor "load more" |
| `GET /organisations/register` | `organisationsRegisterForm` | Registration form |
| `POST /organisations/register` | `organisationsRegister` | Submit registration (throttled: 10/min) |
| `GET /organisations/manage` | `organisationsManage` | Owner/admin entry listing their orgs |
| `GET /organisations/opportunities/{id}/apply` | `organisationsApplyForm` | HTML-first apply-to-opportunity confirm page |
| `GET /organisations/{id}/jobs` | `organisationsJobs` | Open job vacancies for an org |

The `POST /organisations/register` form POSTs to the same `VolunteerService::createOrganization()` method the React API uses. The opportunity apply confirm page POSTs to the existing `govuk-alpha.volunteering.apply.store` route — no duplicate apply endpoint is introduced.

The accessible-frontend trait is `app/Http/Controllers/GovukAlpha/Concerns/OrganisationsParity.php`.

---

## Tests

```bash
# PHP — from repo root
vendor/bin/phpunit --testsuite=Laravel --filter=Organisation

# All volunteering-related tests (includes org tests)
vendor/bin/phpunit --testsuite=Laravel --filter=Volunteer

# React — from react-frontend/
npm test -- Organisations
```

Key test files:

| File | Coverage |
|------|----------|
| `tests/Laravel/Feature/GovukAlpha/OrganisationsParityTest.php` | Browse list (search, cursor, empty-state, feature gate), registration (form render, inline errors, terms required, happy path), manage, per-org jobs, cross-tenant 404, apply confirm, auth redirect. |
| `tests/Laravel/Feature/Controllers/VolunteerControllerTest.php` | Directory listing, org stats, org wallet, pending hours (see `test_organisations_returns_data`). |
| `tests/Laravel/Feature/Controllers/AdminVolunteerControllerTest.php` | Admin listing and access-control (see `test_organizations_returns_200_or_403_for_admin`). |
| `tests/Laravel/Unit/Models/VolOrganizationTest.php` | Model relationships and fillable assertions. |

---

## Failure modes and recovery

| Failure | Behaviour | Recovery |
|---------|-----------|----------|
| Org stuck in `pending` after registration | Public directory does not show it; owner sees pending state. Happens when no admin has approved. | Tenant admin navigates to `/admin/volunteering/organizations` and sets `status = active`. |
| Slug collision on registration | `createOrganization()` retries the DB insert up to three times with a different suffix. After three failures it returns `SERVER_ERROR`. | Rare in practice; check `laravel.log` for the `VolunteerService::createOrganization error` entry and retry the registration manually. |
| Duplicate org name rejected at registration | `409 ALREADY_EXISTS` returned. Happens when an active/pending org with the same name (case-insensitive) already exists for the tenant. | Ask the registrant to use a distinct name, or a tenant admin can decline the existing pending org first. |
| Wallet balance / `auto_pay_enabled` appearing in public response | This should not happen — both fields are explicitly removed before `respondWithData()`. If observed, check that neither field appears in the model's `toArray()` output after a `VolOrganization` is fetched via the `getOrganisationById` path. | Confirm the `unset()` calls in `VolunteerController::showOrganisation()` and `organisations()` are in place. |
| `ensureOrgAccess` returns null for a legitimate org admin | `403 FORBIDDEN` on all dashboard endpoints. The check queries `org_members` for `org_type = 'volunteer'` and `status = 'active'`. | Verify the `org_members` row exists, has `role` in `('owner', 'admin')`, `status = 'active'`, and `org_type = 'volunteer'`. |
