# Changelog

All notable changes to Project NEXUS will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Fixed

- **Proximity "Near me" filter broken end-to-end — five separate issues fixed across listings and events.** Full audit revealed: (1) `ListingService::getAll()` received `near_lat`/`near_lng`/`radius_km` from the controller but never applied them — all listings returned regardless of distance (root cause of Dublin user seeing Cork listings at 2 km). Fixed by delegating to the existing `getNearby()` haversine query when coordinates are present. (2) `ListingService::countAll()` also ignored proximity, showing a wrong total count in the results badge; fixed with a subquery-based haversine count. (3) Both `getAll()` and `countAll()` were missing `near_lat` from the `$hasFacetedFilters` guard, so search + proximity queries incorrectly used Meilisearch totals. (4) `ListingsController` applied the personalisation re-ranker and smart-match ranker after proximity results, destroying the nearest-first order; both ranking passes now skip when proximity is active. (5) `EventService::getAll()` had the identical structural bug (proximity params ignored); fixed by applying the haversine filter inline, consistent with how `VolunteerService` already correctly handles it. Also fixed in the React UI: `activeFilterCount` now includes proximity so the "Filters" badge reflects it, and the "Clear filters" button now resets the proximity pill correctly using a remount key.

### Added

- **Hierarchical domain inheritance for sub-tenants.** Slug-only sub-tenants whose immediate parent has a custom domain are now accessible at `parent.domain/child-slug` (e.g. `timebanking.uk/cardiff`) in addition to `app.project-nexus.ie/cardiff`. The backend resolves the child tenant from the first path segment after locking on the parent's custom domain, and the bootstrap API returns a `parent_domain` field so the SPA automatically uses path-prefixed routing on the parent's domain. Email and notification links for these sub-tenants now correctly emit `timebanking.uk/cardiff/...` URLs (including queued/background jobs via a DB parent-domain lookup fallback). The sitemap layer is fully wired: `timebanking.uk/sitemap.xml` returns a sitemap index listing both the parent's and each sub-tenant's sitemap; `/sitemap-{childslug}.xml` generates URLs with the correct `parentdomain/childslug/...` base. The prerender pipeline (`prerender:plan-routes` and `prerender-tenants.sh`) prerenders sub-tenant pages at `timebanking.uk/cardiff/...` instead of `app.project-nexus.ie/cardiff/...`. `SitemapService::generateForAppDomain()` excludes sub-tenants that belong under a parent domain so they don't appear with wrong canonical URLs in the shared-host sitemap. Redis bootstrap cache is invalidated automatically on hierarchy moves and domain changes. Moving a tenant in the hierarchy tree is safe at any time — domain associations update immediately with no stale cached state. No DNS or nginx changes are required beyond pointing the parent domain at the platform.

- **SEO: organization type, geo meta tags, and structured data enhancements.** Tenant super-admins can now set a `seo_organization_type` (e.g. `LocalBusiness`, `EducationalOrganization`, `NonprofitOrganization`) via the admin tenant form, overriding the global Schema.org `@type` default. Geo meta tags (`geo.region`, `geo.country`, `geo.placename`, ICBM lat/long) are emitted in `<head>` when the tenant has location coordinates — helps Google/Bing assign geographic context and reduces multi-tenant duplicate-content risk. `LocalBusiness` tenants get an `areaServed` block in the org schema that maps `service_area` scope to Schema.org types (City, AdministrativeArea, Country, Place). Added `@id` anchor to org schema for cross-referencing. Lat/lng and `service_area` are now included in the bootstrap API `contact` payload.

- **Admin panel: Registration Security card on `/admin/settings/registration-policy`.** Front-and-centre status card for the per-tenant circuit breaker. Polls every 30s. Green chip when signups are flowing normally; red border + alarm-banner + one-click "Resume signups now" button when the breaker has tripped. Includes the live signup count vs threshold so admins can see "we're at 18 of 20 this hour" before the breaker actually fires. Additive — sits above the existing registration-policy form, doesn't touch any existing components.

### Added

- **Email observability: `email_log` audit table + `email_suppression` cache.** Every `Mailer::send()` now writes a row capturing tenant, user, recipient, subject, status (`queued`/`sent`/`failed`/`suppressed`/`bounced`/`delivered`), provider message id, and error. Operators can finally answer "did Joe Bloggs get his welcome email?" without grepping log files. The companion `email_suppression` table is hydrated hourly by a new `sendgrid:sync-suppressions` artisan command that pulls SendGrid's bounce / block / invalid / spam-report lists; the Mailer checks suppression before every send and refuses to mail addresses SendGrid has already told us are dead (saves quota, protects sender reputation, surfaces invalid member emails to admins).

- **One-click unsubscribe (Gmail/Yahoo Feb-2024 bulk-sender compliance).** New `NotificationUnsubscribeController` plus `/api/v2/notifications/unsubscribe` route (GET for browser visits, POST for `List-Unsubscribe-Post`). Token format is HMAC-signed `userId.tenantId.category.sig`; categories map to notification preference keys (`all`/`messages`/`connections`/`transactions`/`reviews`/`listings`/`digest`/`gamification`/`org`/`federation`). The Mailer auto-attaches the header on every send by looking up the recipient in the current tenant — no caller changes needed for 30+ existing email-sending services. Confirmation page is locale-aware, tenant-branded, no-indexed.

- **One-shot recovery: `php artisan emails:resend-stuck-activations`.** Re-sends welcome/verification emails to members who registered while the earlier TenantContext leak / Mailer bypass bugs were live and never got their activation email. Defaults to `--dry-run` so you can sanity-check the recipient list. `--since=60days`, `--tenant=N`, `--limit=200` flags for scoping. Reuses the canonical Mailer path so the email_log and suppression checks still apply.

- **Notification settings: `caring_smart_nudges` and `federation_notifications_enabled` UI toggles.** Both were backend-supported but had no UI control — members had no way to opt out. Now exposed in `/settings → Notifications`. `federation_notifications_enabled` is a column (not part of the JSON), so the GET/PUT endpoints were extended to read/write it alongside the rest of the preferences.

- **Members can opt INTO the activity digest from notification settings.** New "Activity digest frequency" selector in `/settings → Notifications` lets members pick `off` (default) / `instant` / `daily` / `weekly`. Backed by a new `GET /api/v2/notifications/settings` endpoint that returns the user's `notification_settings.global` row plus per-group and per-thread overrides; the existing `POST` endpoint upserts. Critical user-facing events (direct messages, connection requests/accepts, volunteer-application status, volunteer-hours approval) are forced to `'instant'` regardless of this setting so disabling the digest never silences them.

- **Inbound federation event ingestion wired up for review / connection / listing / community-event / member-updated.** The federation webhook controller had been dispatching these 5 events for months with no listener registered, so the `event(...)` calls were dropped on the floor (the controller's local DB persistence still ran, so no data was lost). Five new listeners:
  - `HandleFederatedReviewReceived` — notifies the local reviewee with an in-app bell + email (anonymous "Someone left you a 5-star review" so we don't dox the partner-side reviewer name).
  - `HandleFederatedConnectionReceived` — notifies the local user of an inbound partner connection request / accept.
  - `HandleFederatedListingReceived`, `HandleFederatedCommunityEventReceived`, `HandleFederatedMemberUpdated` — observability-only structured audit logs; persistence is already complete in the controller, and these inbound bulk-content events don't map to a specific local user to notify. Future extension points kept in the listener for search-index sync.

- **In-app bell notifications for group chatroom messages.** `GroupChatroomMessagePosted` had a Pusher broadcast for online members but no listener — members who weren't online missed the message entirely. New `NotifyGroupChatroomMessage` listener creates an in-app bell row for every active group member (excluding the sender and anyone who muted them), with a 5-minute dedup window so a burst of chat messages doesn't produce a wall of bell rows. **No email** — chat volume is too high to safely email every message; users who want email coverage of group chatter can opt into the daily digest.

### Changed

- **Daily activity digest is now OFF by default.** Members reported the previous daily-email default felt like spam. `NotificationDispatcher::dispatch()` and `NotificationDispatcher::getFrequencySetting()` and `EventNotificationService::resolveFrequency()` now fall back to `'off'` when the member has no row in `notification_settings`, replacing the previous `'daily'` fallback. The new opt-in selector in `/settings → Notifications` lets members turn it on if they want it. Six critical activity types still force `'instant'` regardless: `new_message`, `connection_request`, `connection_accepted`, `vol_application_approved`, `vol_application_declined`, `vol_hours_approved`.

### Fixed

- **Five notification-preference enforcement bugs.** An audit of every email-sending listener found preferences silently ignored in 5 paths:
  1. `NotificationDispatcher::sendReviewEmail` did not check `email_reviews` — local + federated review emails ignored the pref.
  2. `NotificationDispatcher::sendReviewRequestEmail` checked the WRONG key (`email_transactions` instead of `email_reviews`).
  3. `NotificationDispatcher::sendCreditEmail` / `sendCreditSentEmail` had no defence-in-depth pref check (only the caller did, so any future caller would bypass).
  4. `HandleFederatedReviewReceived` did not honour `federation_notifications_enabled` (the per-user federation opt-out had no effect on federation review emails).
  5. `CronJobRunner::processDigest` sent the digest to every user with queued items regardless of their `email_digest` preference.
  All five now respect the matching preference; the digest path also marks the queued rows as `sent` when the pref is off so they don't pile up indefinitely.

- **CI guard against Laravel `Mail::` facade.** Extended `EmailMailerRoutingTest::test_no_mail_facade_usage_anywhere_in_app` to scan every file under `app/` (excluding `app/Mail/` Mailable definitions and comments) for `Mail::raw|Mail::to|Mail::send|Mail::queue|Mail::later|Mail::mailer`. Any future regression that bypasses `Mailer::forCurrentTenant()` fails CI instead of silently dropping production emails. The platform `.env` has SendGrid configured and intentionally NO SMTP credentials — facade usage routes through Laravel's default SMTP mailer and silently drops the message.

- **Structural defence: `SerializesModels` removed from all 30 events.** Closes the deserialization trap permanently: even if a queued job's `finally { reset(); }` is missed for any reason, no Eloquent re-fetch happens at job-pickup time, so a stale `TenantContext` can no longer poison a model lookup. Event payloads are now snapshotted by PHP's default serializer with the full in-memory model state. Slightly larger queue payload for negligible savings before; effectively zero risk of the cross-tenant filter ever firing against a wrong-tenant id again.

- **Newsletter template backfill: every tenant now has the starter newsletter templates.** Re-engagement / nurture / onboarding starter templates were originally seeded for tenant 2 only via January-2026 migrations. Other tenants saw an empty admin "New newsletter" page and any code path looking up a template by category returned nothing. New idempotent migration copies every `category='starter'` row from tenant 2 into every active tenant; per-tenant edits made later are preserved (skip on `(tenant_id, name, category)` collision).

- **Critical: daily / weekly digest emails were silently dropped for every user across every tenant due to a TenantContext leak inside the cron loop.** `CronJobRunner::processDigest()` called `User::findById($userId)` BEFORE setting `TenantContext::setById($user['tenant_id'])` — so the Eloquent `HasTenantScope` filter applied a `WHERE tenant_id = <previous-iteration's-tenant>` clause and returned null for every user whose tenant didn't match the leaked context. Result: every user was logged as "Skipping User ID X (No email/Invalid)" — 31 skipped users on the most recent run, and **52 pending notifications stuck in `notification_queue` for 7 weeks** (oldest from 2026-03-30). Fixed by calling `TenantContext::reset()` at the start of each iteration so `findById` runs with a clean baseline. The same pattern now also defends `runSubTask()` (resets before+after every cron sub-task) and `forEachTenant()` (resets between tenant iterations + final reset on exit) — closes the leak surface across the entire cron pipeline.

- **Critical: `BalanceAlertService::checkAllBalances()` and `ListingExpiryService::processAllTenants()` were called statically every day but are instance methods.** The cron threw "Non-static method ... cannot be called statically" 12 times per day for each (once per tenant), silently dropping every low-organisation-wallet alert email and every listing-expiry email across every tenant. Both now resolved via `app(...)` container resolution. Also no-op'd four other cron tasks that referenced services whose methods were removed in a refactor (`JobVacancyService::expireOverdueJobs`/`expireFeaturedJobs`, `BrokerMessageVisibilityService::expireMonitoringBatch`, `AchievementCampaignService::processRecurringCampaigns`) — these were spamming 12-48 "undefined method" errors per cron run; now print a single "skipped" line until the service replacements ship.

- **Critical: four email-sending paths bypassed the platform `Mailer` and silently failed in production.** Production `.env` configures SendGrid (`SENDGRID_API_KEY` set, FROM `noreply@project-nexus.net`) and does NOT configure SMTP (`MAIL_USERNAME` and `MAIL_PASSWORD` are intentionally unset). Four call sites used Laravel's `Mail::to(...)->send(...)` or `Mail::raw(...)` which routes through `config('mail.default')` (= `smtp`) — sends were attempted via an unconfigured SMTP server and dropped silently. Rerouted through `\App\Core\Mailer::forCurrentTenant()` so they now use SendGrid like everything else: (1) `SafeguardingService.php:630` — **critical safeguarding alerts were not being delivered**; the `SafeguardingCriticalMail` Mailable is now rendered to HTML and sent via `Mailer`. (2) `AdminBillingController.php:249` — billing-upgrade-request notifications to the platform owner. (3) `GenerateMonthlyReports.php:127` — regional analytics report-ready emails. (4) `AdminEmailController::test()` — admin "send test email" now uses `Mailer::forCurrentTenant()` (matching `testProvider()`) so it respects per-tenant email_settings instead of only the platform `.env`.

- **Critical follow-up: structural defence against the same trap firing during job *deserialization*.** Per-listener `finally { reset(); }` only fires after `handle()` runs — a job that throws *during* its payload's deserialization (e.g. when `SerializesModels::restoreModel()` calls `User::findOrFail()` with a stale `TenantContext` and `ModelNotFoundException` blows up) never reaches that finally, so the stale context persists into the next job. Registered global `Queue::before` / `Queue::after` / `Queue::failing` hooks in `AppServiceProvider::boot()` that call `TenantContext::reset()` around every queued job. `Queue::before` fires BEFORE deserialization, guaranteeing every job starts with a clean null context regardless of what the previous job did. Combined with the per-listener finally pattern this is true defence-in-depth. Also removed `SerializesModels` from the `UserRegistered` event so its `User` model is snapshotted in-memory instead of re-fetched from the DB on dequeue — eliminates the deserialization round-trip for the most user-facing event (welcome / activation email).

- **Critical: TenantContext static state leaked between Horizon queue jobs, silently dropping welcome emails, cron notifications, and federation pushes for all tenants except the first one processed by a worker.** All 18 queued event listeners were missing `TenantContext::reset()` in a `finally` block. Horizon runs up to 4 long-lived worker processes; without the reset, static `TenantContext` state from one job leaked into the next job the same worker picked up. When Laravel's `SerializesModels` trait re-fetched the `User` Eloquent model during job deserialization, `TenantScope` applied a `WHERE tenant_id = <stale>` clause — causing `ModelNotFoundException` to silently swallow the job and drop the email. Now all queued listeners call `TenantContext::reset()` in `finally`, matching the pattern already used by `NotifyAdminOfNewRegistration`. Affected listeners: `SendWelcomeNotification`, `SendOnboardingCompletionEmail`, `NotifyJobAlertSubscribers`, `UpdateWalletBalance`, `UpdateFeedOnListingCreated`, `CopyMessageForBrokerReview`, and all 12 `Push*ToFederated*` federation listeners. Also fixed `FederationInitialSyncJob` (two `setById` calls, no reset) and `RunAdminCronJob` (delegates to `CronJobRunner` which calls `setById` across 7 code paths for multi-tenant cron processing).

### Security

- **Registration form: multi-field honeypot.** Three additional decoy inputs with realistic names (`confirm_email`, `address_line_2`, `referral_code`) sit alongside the existing `website` honeypot — all hidden via off-screen CSS positioning (catches more bots than `display:none`). Server silent-no-ops if ANY of the four come back non-empty. React form also checks all four refs client-side. Catches sophisticated bots that filter on the legacy `website` field name but can't tell which other inputs to skip.
- **Email verification is now required for every tenant (current and future) and can only be disabled by God (platform super-admin).** `TenantSettingsService::requiresEmailVerification()` now defaults to TRUE (fail-closed), reads the bare `email_verification` key (plus `general.email_verification` fallback for legacy rows), and the login gate calls `requiresEmailVerification()` consistently. A backfill migration (`migrations/2026_05_16_enforce_email_verification_all_tenants.sql`) writes `email_verification=true` to every existing tenant row and syncs `tenant_registration_policies.require_email_verify=1`. New tenant seeding was corrected from the orphaned `general.email_verification` key to the bare `email_verification` key. Both the Admin Settings and Registration Policy pages now lock the toggle with a "God only" chip for non-platform-super-admins. `AdminConfigController::updateSettings()` and `RegistrationPolicyController::updatePolicy()` enforce this server-side with a 403 for non-platform-super-admins.
- **Admin approval and email verification toggles are now God-only in the admin UI.** Both the "Require email verification" and "Admin approval required" switches on `/admin/settings` and `/admin/settings/registration-policy` are locked with a "God only" chip for tenant admins and tenant super-admins. The backend enforces this via `requirePlatformSuperAdmin()` — only platform super-admins (role `god`/`super_admin` or `is_super_admin=true`) may change these settings.
- **Admin approval is now required for every tenant (current and future).** `TenantSettingsService::requiresAdminApproval()` now defaults to TRUE (fail-closed) so any tenant without an explicit setting still enforces the gate. A backfill migration (`migrations/2026_05_16_enforce_admin_approval_all_tenants.sql`) writes `admin_approval=true` to every existing tenant row so the policy is explicit in the database. Tenant seeding (`TenantHierarchyService::seedTenantDefaults`) now writes the bare `admin_approval` key the reader actually checks — the previous `general.admin_approval` row was orphaned (reader never looked it up), which meant new tenants silently ran with admin approval disabled. Already-approved members are unaffected; only new registrations (and accounts still in `status='pending'`) require an admin to approve them before login.
- **Registration form: per-tenant hourly circuit breaker (default 20/h).** Last-line containment when everything else fails. If a single tenant gets a flood of signups in one hour, account creation is automatically paused for that tenant for an hour and the next signup attempt returns HTTP 503 `REGISTRATION_TENANT_PAUSED`. Auto-resumes after 1h; tenant admin can clear manually via `POST /api/v2/admin/registration/resume-signups`. Status visible at `GET /api/v2/admin/registration/breaker`. Worst-case outcome: a tenant loses 1 hour of legitimate signups — much better than waking up to 10,000 fake accounts. Configurable via env `REGISTRATION_TENANT_HOURLY_CAP` (set to 0 to disable).
- **Registration form: per-IP daily cap on successful signups (default 5/24h).** Stacks on top of the existing 3/5min route throttle. The route throttle caps raw request volume; this caps how many accounts a single IP can actually create in a 24-hour window — closes the "patient bot grinding 1 signup every 6 minutes" hole that the short rate-window leaves wide open. Counter increments ONLY on successful registration, so a user typing wrong passwords doesn't burn quota. Configurable via env `REGISTRATION_DAILY_CAP_PER_IP` (set to 0 to disable). Returns new `REGISTRATION_DAILY_LIMIT` (HTTP 429) with retry-after.
- **Registration form: MX-record check on the email domain.** Rejects signups where the email domain has no MX record AND no A record — catches typos like `user@gmial.com`, made-up domains bots fall back to, and freshly-registered burner domains without mail wiring. Returns new `EMAIL_DOMAIN_INVALID` error code with a "check for typos" hint that doubles as a UX win. Results cached 24h (positive) / 1h (negative); fails open on DNS errors so an outage doesn't block legitimate users. RFC-reserved `.invalid` TLD rejected without a DNS round-trip.
- **Registration form: disposable / throwaway email-domain blocklist.** Rejects signups from ~200 known temp-email providers (mailinator, 10minutemail, guerrillamail, tempmail, yopmail, etc., plus their sub-domains). New `DisposableEmailService` loads the curated list from `resources/security/disposable-email-domains.txt`; refresh from the canonical upstream list via `scripts/update-disposable-emails.sh`. Returns the new `EMAIL_DISPOSABLE` error code with a clear "use a permanent email address" message. Kills the cheapest bot-signup path — no inbox to pay for = no per-account cost.
- **Registration form: verified-location gate (anti-fraud).** The location field on both forms is now hard-gated to require lat/lng coordinates that came back from the place-autocomplete API (Google Places or Nominatim). Free-text gibberish like "555" — the exact bypass a recent attacker used — is rejected server-side with a new `LOCATION_NOT_VERIFIED` error code. Null Island (lat=0,lng=0) is also rejected as the obvious signature of a default-zero coordinate. The bar is now: an attacker must call a Geocoding API themselves to forge believable coordinates. Both forms show inline guidance to "pick a suggestion from the list".
- **Registration form: server-side enforcement closes three React-side-only bypasses.** The React frontend has long sent `terms_accepted`, `password_confirmation`, and `invite_code` in the registration payload, but `RegistrationService::register()` ignored them — meaning a scripted submission could skip the terms checkbox, mismatch passwords, or register on an `invite_only` tenant with no code at all. All three are now enforced server-side with distinct error codes (`TERMS_REQUIRED`, `PASSWORD_MISMATCH`, `INVITE_REQUIRED`, `INVITE_INVALID`). Invite codes are validated against `InviteCodeService` and redeemed atomically after the user row is created; if the redeem races out (concurrent registration consumed the last use), the new account is marked `rejected` so the code stays the gating signal.
- **Min-form-time bot gate is now server-enforced.** Both the React form and the new Blade form send a `form_started_at` timestamp; the service silently no-ops (success-shaped response, like the honeypot) when the elapsed time is < 5 seconds. Previously the 5-second check only ran in the React UI and a scripted POST would skip it.

### Fixed

- **Bulk user approval now sends welcome emails, in-app notifications, and grants welcome credits.** Previously `bulkApprove()` silently flipped `is_approved=1` with no further action — users approved in bulk received no welcome email, no in-app notification, and no welcome credits. Fixed: after each successful `updateAdminFields()` call the same `grantWelcomeCredits()` / `sendApprovalWelcomeEmail()` / `sendApprovalInAppNotification()` helpers that single-user `approve()` already calls are now called per user. `grantWelcomeCredits()` is idempotent, so double-approval is still safe.
- **`FederationInitialSyncJob`: TenantContext always reset even when an exception is thrown.** The previous fix added a bare `TenantContext::reset()` on the success path only, leaving the Horizon worker's static TenantContext stale if the audit-log write threw. Wrapped the entire `handle()` body in `try/finally { TenantContext::reset(); }` so cleanup is guaranteed on every exit path.
- **`Mailer::forCurrentTenant()` now logs a warning when called without a TenantContext.** Calling this method with no active tenant context silently fell back to platform SMTP credentials, making cross-tenant email delivery failures invisible. A `Log::warning()` with a 5-frame backtrace is now emitted so these cases appear in the Laravel log.
- **All 18 queued listeners now have `finally { TenantContext::reset(); }` in `handle()`.** The previous listener audit commit was orphaned on a branch that diverged from main and was never merged — the fixes existed in git history but not on disk. Re-applied to all 18 listeners: `CopyMessageForBrokerReview`, `NotifyJobAlertSubscribers`, `PushCommunityEventToFederatedPartners`, `PushConnectionAcceptedToFederatedPartner`, `PushFederationDataRetraction`, `PushGroupMembershipToFederatedPartners`, `PushGroupRetractionToFederatedPartners`, `PushGroupToFederatedPartners`, `PushListingToFederatedPartners`, `PushMemberProfileUpdateToFederatedPartners`, `PushMessageToFederatedPartner`, `PushReviewToFederatedPartner`, `PushTransactionToFederatedPartner`, `PushVolunteerOpportunityToFederatedPartners`, `SendOnboardingCompletionEmail`, `SendWelcomeNotification`, `UpdateFeedOnListingCreated`, `UpdateWalletBalance`. Verified by grep: zero `ShouldQueue` classes that call `setById()` are now missing a reset.

- **Module Configuration: browser autofill permanently blocked on the search input.** Chrome was storing the user's previously-typed value (the admin email address) as "search history" for the `type="search"` input and restoring it on every page load — including after the Refresh button, because the loading spinner was unmounting and remounting the input, triggering a fresh autofill injection cycle. Fixed by: (1) changing the input to `type="text"` so Chrome's search-history persistence doesn't apply, (2) setting `autoComplete="new-password"` which browsers actually honour (unlike `"off"`), and (3) rendering the loading spinner inline instead of replacing the whole page so the input is never unmounted.

### Changed

- **Accessible (GovUK Alpha) registration form: feature parity with the React form.** Added profile-type radios (individual / organisation), conditional organisation-name field, conditional invite-code field (shown only when the tenant's effective registration policy is `invite_only`), password-confirmation field with live-match indicator, mandatory terms-of-service + privacy-policy checkbox, and Google Places autocomplete on the location field (progressive enhancement — form works without JS). The newsletter checkbox is preserved. New `register-enhancements.js` handles all client-side interactions; the existing `password-strength.js` continues to provide live NIST-aligned length + HIBP breach feedback.
- **GovUK Alpha `storeRegister` controller maps six new service error codes** to distinct Blade page statuses so users see specific messages ("you must accept the terms" / "this invite code is invalid") instead of the generic "check the form and try again" fallback.
- **React `RegisterPage` now sends `form_started_at`** to the server so the min-form-time gate enforces on this path too.

### Removed

- **Cloudflare Turnstile removed from login, password-reset, and registration forms (2026-05-16).** Both the React SPA and the GovUK Alpha accessible Blade frontend. Member feedback found the widget too confusing and the false-positive rate unacceptable on account-recovery and sign-in flows. **Turnstile is retained on contact forms** where the cost of a small amount of user friction is acceptable as spam defence.
  - Bot/brute-force defence on auth endpoints is now: the DB-backed per-email + per-IP brute-force limiter, route-level throttle (login 30/min, password-reset 5/15min, register 3/5min), the registration honeypot, the registration admin-approval gate, and the email-enumeration safety on the password-reset response.
  - Removed `TurnstileService` injection from `AuthController`, `PasswordResetController`, and `RegistrationService`.
  - Removed `useTurnstile()` and widget JSX from `LoginPage`, `ForgotPasswordPage`, `RegisterPage` (desktop + mobile mounts).
  - Removed `cf-turnstile` divs and api.js loader from `accessible-frontend/views/login.blade.php` and `register.blade.php`.
  - Dead `turnstile_token` request types and dead `register-turnstile-failed` / `turnstile-failed` Blade status branches dropped.

### Fixed

- **Cloudflare Turnstile rollout UX + silent-failure regressions (emergency).** Same-day hotfix to today's Turnstile/bot-defence rollout. Two valuable members reported real problems: one found the visible "Verify you are human" widget confusing and suspicious, another could not get a password reset email no matter how many times he tried.
  - **Widget is now invisible for legitimate users.** Switched the Turnstile widget to `appearance: 'interaction-only'` (Cloudflare's silent-pass mode). The widget only renders visibly when Cloudflare actually decides a human challenge is needed — roughly 1% of legitimate sessions. The other 99% never see a widget at all.
  - **Forgot-password no longer silently swallows errors.** The page previously caught every error and showed a fake "we've sent you an email" success message — including when a Turnstile failure or rate limit blocked the request. It now distinguishes Turnstile failures, rate-limit hits, and generic errors with distinct messages so users know to retry.
  - **Per-email reset rate limit raised from 3/hr to 10/hr.** Legitimate users hitting the 3/hr ceiling silently got "we sent you an email" with no email ever sent. The cap now matches realistic usage; bots are still blocked by per-IP throttle (5/min) + Turnstile. The endpoint now returns a real 429 instead of fake success.
  - **Single-use Turnstile tokens are reset on every failed submit** across login, register, forgot-password, and contact pages. Previously a failed validation locked the form because the consumed token couldn't be re-used until full page reload.
  - **Backend uses a dedicated `TURNSTILE_FAILED` error code** (was wrongly reusing `VALIDATION_REQUIRED_FIELD` / `VALIDATION_INVALID_FORMAT`). All four API call sites updated.
  - **Registration controller now passes specific error codes through** so the React UI can show "this password appears in known breaches" vs "an account already exists" vs "the security check failed" — instead of a single catch-all message.
  - **GovUK Alpha Blade flows get the same treatment.** `storeLogin` and `storeRegister` now map API error codes to distinct page statuses (`turnstile-failed`, `rate-limited`, `email-not-verified`, `account-suspended`, `register-duplicate`, `register-password-pwned`, etc.) so the accessible frontend shows useful messages too.
  - **Diagnostic logging added** to the password-reset flow: per-email rate hits, unknown-email reset requests, and successful email dispatches are now logged with masked email + IP. Distinguishes "wrong email" from "mailer broken" when investigating future complaints.
  - **New optional `useTurnstile().status` + `useTurnstile().reset()`** for callers that need to react to widget load failures or reset after a failed submit.

### Added

- **Prerender engine — Round 4: tests + retry + sitemap explorer.**
  - **11 new tests** covering the Round 2+3 logic: circuit breaker trip + claim-suppression, per-tenant concurrency cap, route validation rejecting shell metacharacters, audit secret-redaction, health check transitions, snapshot integrity (`ok`/`mismatch`/`missing`), TTL-pattern specificity resolution, safeCachePath accepting route special characters, observer-storm coalescing to a tenant-wide row. Without these, one refactor breaks the safety net.
  - **Job retry button.** Failed / partial / cancelled jobs now have a "Retry" button on the Jobs tab that clones their parameters into a new queued row. Original job is preserved for history. New audit row links the two via `retried_from_job_id`.
  - **Sitemap explorer.** New Overview card lets you punch in a tenant slug and see the exact route list the engine plans to render — static floor (feature/module gated) + dynamic URLs from `SitemapService` (capped at 1,000). Answers "what does the engine think this tenant has?" without grepping logs.
  - **`react-frontend/CLAUDE.md` updated** with the full Round 2+3+4 architecture so future contributors don't have to re-derive it from the code.

- **Prerender engine — Round 3: defense in depth + operator superpowers.**
  - **Scheduler liveness tracking.** Every prerender scheduled task (`detect-drift`, `auto-recache`, `reap-stale`) now stamps a cache key on success. The health endpoint checks the age of each stamp against 2×/3× the expected interval and surfaces a yellow/red check if the Laravel scheduler has stopped firing — catches the "supervisord nexus-scheduler died" failure mode that would otherwise be silent.
  - **Webhook nonce one-time-use.** HMAC `/invalidate` already had a 5-min timestamp window; now each `(timestamp, signature)` pair can only be used once. The nonce is keyed by `sha256(ts:sig)` and persisted for 600s. Replay attempts are bounced AND audited with `outcome=denied, reason=webhook_replay` for forensics.
  - **Snapshot integrity verification.** The Playwright worker now writes a `.sha256` sidecar next to every `index.html` it renders. The Inspect drawer shows an `integrity: ok|missing|mismatch|unreadable` chip — mismatch is highlighted in danger color and the tooltip shows the expected vs actual prefixes. Catches filesystem corruption, bit rot, and hand-edits that would otherwise look like a valid snapshot.
  - **CSV export** for the three operator-facing tables: `GET /api/v2/admin/prerender/export/{audit,inventory,jobs}.csv`. Streamed, capped at 5,000 rows. "Export CSV" button on the History tab; the same URLs work for cron-scraped exports.
  - **TTL inspector** card on the Overview tab. Type a route, see which `config/prerender.php` pattern owns it, what TTL it gets, and what other patterns also match (with their specificities). No more grepping config to understand the freshness policy.

- **Prerender engine — Round 2: self-healing, audit, observability artefacts.** Building on the P0/P1/P2 audit, the engine now self-recovers from worker outages and ships first-class ops artefacts.
  - **Per-tenant concurrency cap.** `claimNextJob` now skips rows whose tenant already has a job in flight. Stops a single slow tenant homepage from starving the queue.
  - **Circuit breaker.** Five failed jobs inside a 10-minute window auto-pauses the queue for 15 minutes. Saves CPU on a wedged host and gives operators time to investigate. Closes automatically on cooldown; can be reset manually via `POST /api/v2/admin/prerender/reset-breaker` or the new admin UI button.
  - **Health endpoint.** `GET /api/v2/admin/prerender/health` returns a traffic-light JSON (`green`/`yellow`/`red`) with per-check details (cache filesystem, breaker, queue age, failure rate, stuck rows) and an actionable `action` string on every failing check. Rendered into a banner at the top of the admin module.
  - **Emergency "Reset stuck queue" button** in the health banner. Requeues every `claimed`/`running` row older than 30 min AND clears the breaker — one click. Rate-limited (2/5min per user) and audited.
  - **Audit log.** New `prerender_audit_log` table persists every mutating action (enqueue, cancel, purge, invalidate, auto_recache, detect_drift, purge_unexpected, reset_breaker, reset_queue) with actor, IP, UA, outcome, sanitised details. New **History** tab in the admin UI surfaces it with an action filter. Secrets are scrubbed before persistence (`password`/`token`/`secret`/`api_key` keys redacted).
  - **Per-user per-action rate limiting** on every mutating endpoint. Denied attempts are themselves audited so abuse leaves a trail.
  - **New Prometheus metrics**: `nexus_prerender_breaker_tripped`, `nexus_prerender_breaker_until_seconds`, `nexus_prerender_queue_oldest_age_seconds`, `nexus_prerender_health_status` (0/1/2 enum).
  - **Grafana dashboard** committed at `docs-public/observability/prerender-grafana-dashboard.json` — health + breaker + coverage + queue age + outcomes + per-tenant missing-route bargauge.
  - **Prometheus alerting rules** committed at `docs-public/observability/prerender-alerts.yml` — 7 alerts (4 critical, 3 warning) covering RED health, breaker, cache, queue jam, coverage, recent failures, asset invalidation.
  - **Operator runbook** at `docs-public/observability/prerender-runbook.md` — alert-by-alert response steps + emergency procedures + forensics index.
  - **Jobs tab gains a PRIORITY column** showing HIGH/NORMAL/LOW with a tooltip explaining the numeric value. The lifecycle was already priority-aware (claim order is `priority ASC, queued_at ASC`); now you can see it at a glance.

### Fixed

- **Prerender engine — admin module audit, full P0→P2 sweep.** Following the new admin panel's introduction, prerender jobs were piling up in `queued` state forever and tenant admins reported all action buttons greyed out. Full audit + 13 fixes:
  - **🔴 P0 — Host cron for the job processor was never installed.** `scripts/prerender-job-processor.sh` documented a `* * * * *` cron entry in its header but nothing in the repo actually wrote it to `/etc/cron.d/`. The in-container Laravel scheduler can run `prerender:detect-drift` and `prerender:auto-recache`, but the processor MUST run on the host because it calls `docker exec`. Result: every job — observer-triggered, drift-triggered, TTL, manual — sat queued forever, and observer-deleted snapshots were never regenerated. New phase: `scripts/deploy/phases/install-prerender-cron.sh` writes `/etc/cron.d/nexus-prerender-processor` idempotently on every deploy.
  - **🔴 P0 — No stale-job reaper.** If the worker was OOM-killed, a deploy SIGTERMed it mid-flight, or the host rebooted, the row stayed `claimed`/`running` forever, blocking dashboards and distorting metrics. New `prerender:reap-stale` artisan command (also installed in the host cron, runs every 5 minutes) plus scheduler registration in `bootstrap/app.php`.
  - **🔴 P0 — Frontend "buttons greyed out" for tenant admins.** `PrerenderAdmin.tsx` gated buttons on `is_super_admin || is_god || role==='super_admin'` while the backend's `requireSuperAdmin` also accepted `is_tenant_super_admin`. A tenant super-admin saw disabled buttons but could have called the API directly via curl — worst of both worlds, AND a cross-tenant operation surface a tenant admin shouldn't reach. Fixed by tightening the controller to `requirePlatformSuperAdmin` on every mutating endpoint (enqueue, purge, cancel, invalidate, auto-recache, detect-drift, purge-unexpected), hiding the sidebar entry from non-platform-super-admins, and adding an explicit read-only banner so anyone landing on the page understands why actions are disabled. Sign in as platform super-admin to drive the engine.
  - **P1 — Race in `enqueueJob` dedup.** SELECT-then-INSERT outside a transaction let concurrent observer callbacks both insert. Wrapped in `DB::transaction` with `lockForUpdate` so MariaDB serializes them. Routes now also validated against the canonical regex inside `enqueueJob` — defence in depth for the host shell `eval` consumer.
  - **P1 — HMAC replay protection on `/invalidate`.** Captured signatures were replayable indefinitely. Now requires `X-Nexus-Timestamp` header within ±300 s and signs `"<ts>.<body>"`.
  - **P1 — `safeCachePath` regex too narrow.** Omitted `: @ ~ ( ) + , ; = ! $ *` so inspecting any snapshot whose route contained those characters silently 404'd the drawer. Widened to match the canonical route regex; `..` block + `/index.html` suffix check preserved.
  - **P1 — Observer storm backpressure.** Bulk imports (e.g. seeding 5k blog posts) would enqueue 5k distinct queued rows because each post has a unique `routes` value. Per-tenant burst counter in a 60s cache window — over 50 invalidations/min collapses subsequent enqueues onto a single tenant-wide row.
  - **P2 — Overview tab double-fetched** when realtime worked (Pusher reload + 30s poll). Poll now disabled when `live === true`.
  - **P2 — KPI grid layout** was ragged on desktop (11 cards in `grid-cols-2 md:grid-cols-4`). Rebreakpointed `grid-cols-2 sm:grid-cols-3 md:grid-cols-3 xl:grid-cols-4`.
  - **P2 — `inventory()` unbounded scan.** A misbehaving Playwright could write thousands of files into one host directory and hang the admin summary. Hard cap at 50k rows with a `__truncated` sentinel surfaced to the UI.
  - **P2 — URL state sync** for the prerender admin tab + tenant filter. Refresh / back / forward now preserve view state (`?tab=coverage&tenant=hour-timebank`).
  - **P2 — `.bot-access.jsonl` logrotate.** New `install-prerender-logrotate.sh` deploy phase writes `/etc/logrotate.d/nexus-prerender-bot-access` (daily, 14 days, compressed, copytruncate) so the bot-only access log doesn't grow unbounded.
- **Cross-tenant login bug — `app.project-nexus.ie/` no longer silently boots into a stale tenant.** Logging into one community and arriving on another is fully resolved.
  - **Root cause.** `TenantContext` had a `storedSlug` fallback that read `nexus_tenant_slug` from `localStorage` whenever a user had auth tokens. On `app.project-nexus.ie/` (the platform root, no slug in the URL), this silently booted the SPA into whichever tenant the user had last visited — e.g. Agoris. The login page then saw a "resolved" tenant slug and hid the community chooser, letting users authenticate against the wrong community.
  - **Fix.** `TenantContext.tsx` — removed the `storedSlug` fallback entirely. Effective tenant slug is now `tenantSlug` prop (from `TenantShell`, URL-derived) OR `detectTenantFromUrl()` only. This matches the 2026-05-08 policy already documented in `TenantShell.tsx`: URL is respected as typed; master tenant renders at `/`, tenant-scoped pages require the slug in the URL.
  - **Defence in depth.** `AuthContext.logout()` now clears `nexus_tenant_id` and `nexus_tenant_slug` from `localStorage` (previously preserved as a UX nicety, which contributed to the leak). Cross-tab logout already did this; the same-tab logout path now matches.

### Changed

- **Sales site (`project-nexus.ie`) — GA messaging and audit-driven fixes.**
  - Hero badge updated from "V1.5 Now Open Source — AGPL-3.0" to "V1.5 Generally Available · Open Source · AGPL-3.0" so the public marketing site matches the actual v1.5 GA status promoted in CHANGELOG.md.
  - **Broken Documentation link fixed.** The Get Started panel linked to `github.com/jasperfordesq-ai/nexus-v1/tree/main/docs`, which 404s — that path doesn't exist (the repo has `docs-public/`, not `docs/`). Repointed to the repo README anchor (`#readme`) with a sublabel referencing `docs-public/`.
  - **WCAG claim softened.** "WCAG 2.1 AA — full accessibility compliance" was an unsupported blanket claim. Now reads "built to WCAG 2.1 AA targets with ongoing audit."
  - **Prerender.io reference removed from the SEO feature card.** The platform is fully self-hosted on Playwright-rendered snapshots; the old "Prerender.io fallback" wording was stale. New copy describes the actual three-layer freshness model (observer + sitemap-drift + TTL) and HTTP status propagation.
  - Sitemap `lastmod` bumped to 2026-05-14.
- **README — v1.5 status promoted to Generally Available.** The top-of-file blurb and the "Project Status" section both said "Release Candidate / in active production use while undergoing final pre-release validation." Updated both to "Generally Available, in active production use" with a pointer to the in-app `/features` page and CHANGELOG for per-module maturity. Historical RC entries in CHANGELOG.md and the `v1.5.0-rc.1` release marker in `.github/RELEASE_PROCESS.md` are left untouched (historical record).
- **Sales-site nginx — security headers hardened.** Added `Content-Security-Policy` (allowing only Google Fonts and Ahrefs analytics, which are the only third-party origins the page actually loads), `Strict-Transport-Security` (`max-age=31536000; includeSubDomains; preload`), and `Permissions-Policy` (deny accelerometer/camera/geo/gyro/mic/payment/usb). Dropped the now-deprecated `X-XSS-Protection` header — modern browsers ignore it and CSP supersedes it. Headers repeated in the static-asset and HTML `location` blocks because nginx `add_header` is replace-not-merge.

### Fixed

- **Admin panel — raw translation keys no longer leak.** The Algorithm Settings and AI Settings pages (and 19 other admin pages, mostly in Caring Community) were rendering raw `t()` keys like `algo.feed_label`, `advanced.provider_openai`, `admin.providers.title` because their translation keys were never added to the locale files.
  - **Algorithm Settings** and **AI Settings** — stripped `useTranslation` / `t()` entirely and inlined literal English (per the admin-is-English-only convention).
  - **236 missing keys added** to `en/admin.json` under `admin.*`, `panel.*`, `billing.*`, `tenant_features.*`, `federation.*`, `groups.*`, `moderation.*`, `resources.*`, `super.*`, and `volunteering.*`. Covers Care Providers, Loyalty Program, Warmth Pass, Hour Transfers, Municipality Feedback, Trust Tier, and the volunteer admin tooling.
  - All 10 non-English locale files filled with English fallbacks; `node scripts/check-i18n-drift.mjs` now passes with 0 drift.
  - All 2,552 admin-side `t()` calls now resolve.

### Changed

- **Prerender engine — Round 5 (the full polish, "better than the big names").** Closes every remaining gap from both audits and adds three things no competitor ships.
  - **Three-layer freshness defence (the headline change).** Stale public pages now have three independent mechanisms trying to keep them fresh:
    - **Observer hook (millisecond layer).** Eloquent model observers for every public content type — `Post`, `Listing`, `Event`, `JobVacancy`, `Group`, `MarketplaceListing`, `MarketplaceCategory`, `VolOpportunity`, `IdeationChallenge`, `Page` (CMS), `ResourceItem`. On save/delete, the affected snapshot is deleted and a NORMAL-priority recache enqueued. Failures are logged, never thrown.
    - **Sitemap drift detector (minute layer).** New `prerender:detect-drift` cron walks every tenant's sitemap, parses `<lastmod>`, compares against snapshot mtimes, enqueues HIGH-priority recaches for any drift. Catches code paths that bypass Eloquent (raw DB writes, migrations, queue jobs). 2-minute cadence; bounded fan-out.
    - **TTL auto-recache (hour/day floor).** Existing Phase 2 cron, still the backstop for content that doesn't appear in either sitemap or model events.
  - **External invalidation webhook.** `POST /api/v2/admin/prerender/invalidate` with Bearer token or HMAC signature. Lets headless CMS, marketing automation, or external integrations invalidate routes directly. Sets `PRERENDER_WEBHOOK_TOKEN` env var to enable.
  - **AI-friendly Markdown rendering.** Worker now extracts a clean Markdown body (`index.md`) alongside the HTML snapshot. nginx detects AI crawlers (GPTBot, ClaudeBot, Perplexity, ByteSpider, Common Crawl, Google-Extended, Amazonbot, etc.) and serves the `.md` variant first via `try_files`. Falls back to HTML if markdown isn't available. **No competitor (Prerender.io, Netlify, Cloudflare Pages) ships this** — DataJelly was the only player doing it.
  - **Admin UI overhaul — six tabs, full polish.**
    - **Overview** — new Freshness automation card (one-click auto-recache + drift detect with dry-run / apply); new Wildcard cache purge form with pattern, tenant scope, dry-run, and auto-recache toggle.
    - **Inventory** — adds HTTP status column, search/filter, status-code filter, bulk selection checkboxes, and bulk-recache button (groups selections by tenant, dispatches via the invalidate API).
    - **Inspect drawer** — front-and-centre SEO score card (0-100 + A–F grade) with must-fix issues list and tips list. HTTP status chip. Reflects the new `seo` field on the inspect response.
    - **Coverage** — new "Refresh all stale (N)" bulk button that enqueues per-tenant recaches for everything missing / stale / asset-broken in one click.
    - **Analytics** — new tab. Bot traffic over 1d / 7d / 30d windows: KPIs (total hits, IP-verified %, spoofed count, unique URIs), hits-by-crawler + hits-by-status breakdowns, top-50 URIs table, recent-activity feed.
  - **Tests.** New `PrerenderServiceTest` cases for `purgePattern` (glob single segment, `**` recursive, host scoping, actual deletion), `ttlForRoute` (specificity + default fallback), `seoScore` (high / low grade scenarios), `_status` sidecar reading, priority promotion on duplicate enqueue, priority-ordered claim, and the JSONL crawler analytics aggregator.
  - **Docs.** `react-frontend/CLAUDE.md` "Prerender Pipeline" section rewritten to describe the three-layer freshness model, priority lanes, status-code propagation, AI Markdown variant, and the six admin tabs.
- **Prerender engine — Phase 4 (hardening).**
  - **Crawler IP-range verification.** New `scripts/refresh-bot-ip-ranges.sh` pulls Google/Bing/DuckDuckGo/Apple's published IP-range JSON feeds and ships them into the nginx container as a `geo` include. `$nexus_bot_ip_verified` is logged on every bot hit; analytics surface `verified_hits` and `spoofed_by_crawler` so admins can spot User-Agent spoofing without blocking (alternative crawlers / IPv6 transitions cause false positives if you block on verification alone). Designed for a weekly cron.
  - **Bot User-Agent refresh helper.** `scripts/refresh-bot-ua-list.sh` diffs Matomo's actively-maintained bot regex list against the names we already cover and writes candidates to `logs/bot-ua-suggestions.txt` for human review. Stops the curated regex in `nginx.bluegreen.conf` from drifting into obsolescence.
  - **Viewport variant flag.** Worker honours `PRERENDER_VIEWPORT=mobile` (414×896 + iPhone Safari UA) for tenants/routes that need a mobile-specific snapshot. nginx routing for the variant is a deferred follow-up — current platform is single-DOM responsive so the desktop snapshot serves both audiences correctly.
- **Prerender engine — Phase 3 (visibility & SEO scoring).**
  - **SEO score per snapshot (0–100, A–F grade).** Synthesised from existing `inspect()` flags — title length, meta description length, canonical, OG completeness, h1 count, JSON-LD validity, asset issues, noscript fallback, body text volume. Surfaced as `seo` on the inspect API response with `issues` (must-fix) and `tips` (suggestions) arrays.
  - **Crawler analytics.** nginx now writes a bot-only JSONL access log (`$status`, prerender override status, crawler label, verified flag, UA, IP, referer, bytes, request time) to the shared prerender volume. `GET /api/v2/admin/prerender/analytics?since=ISO&limit=200` aggregates hits by status, crawler, host, top URIs, recent rows. Default window: 7 days.
  - **Manual auto-recache trigger.** `POST /api/v2/admin/prerender/auto-recache { apply: bool }` runs one immediate pass of the freshness loop (dry-run by default) for operators who don't want to wait for the cron tick.
  - **Inventory/Coverage filter, bulk recache from Coverage tab, admin UI polish:** backend supports `?tenant=` filtering on inventory + the new analytics endpoint; frontend `PrerenderAdmin.tsx` polish deferred to a focused UI change.
- **Prerender engine — Phase 2 (freshness automation).** Snapshots now refresh themselves; deploy-time renders are no longer the only freshness mechanism.
  - **TTL rules per route pattern.** New `config/prerender.php` maps route globs to max snapshot ages (homepage 6h, content index 6–24h, individual items 1–7d, static pages 30d). `PrerenderService::ttlForRoute()` resolves the most-specific pattern.
  - **Auto-recache cron.** New `prerender:auto-recache` artisan command walks the deep inventory, identifies TTL-expired and content-drifted snapshots, and enqueues low-priority recache jobs grouped by tenant. Bounded by `max_tenants_per_run` / `max_routes_per_tenant` so a single tick can't flood the queue. Designed for a 15–30 min cron cadence.
  - **Content-change hooks.** Model observers (`Post`, `Listing`, `Event`) now invalidate the affected snapshots (`/blog`, `/blog/{slug}`, `/listings`, `/listings/{id}`, `/events`, `/events/{id}`) on save/delete and auto-enqueue a low-priority recache. Failures are logged, never thrown — model writes never block on the prerender side-channel. The base `PrerenderInvalidationObserver` makes it a few lines to wire up additional content types.
  - **`window.prerenderReady` signal.** Worker now waits for `window.prerenderReady === true` before snapshotting; falls back to the DOM-content heuristic when the signal is never set. `initPrerenderReady()` in `main.tsx` ensures the variable always exists; `usePrerenderReady(isLoaded)` is a one-line hook for data-driven routes to control snapshot timing.
- **Prerender engine — Phase 1 (coverage & correctness).** Lifts the engine from "render hardcoded routes on deploy" to "render every public URL Google can discover, with correct HTTP status codes." Addresses the highest-impact gaps from both prerender audits.
  - **Sitemap-driven URL discovery.** New `prerender:plan-routes` artisan command unions the static-page floor (`/`, `/about`, …) with every URL `SitemapService` publishes — blog posts, listings, events, jobs, KB articles, marketplace listings/categories, CMS pages, organisations, ideation challenges. `scripts/prerender-tenants.sh` consumes the per-tenant plan; the hardcoded `PUBLIC_ROUTES` list remains as a fallback when the PHP container is unavailable. `--no-sitemap` flag and `NEXUS_PRERENDER_NO_SITEMAP=1` env var disable it for emergencies. Closes the long-tail coverage gap flagged by both audits.
  - **HTTP status code propagation.** Worker now extracts `<meta name="prerender-status-code">` from rendered DOM and writes a `_status` sidecar next to `index.html`. Bash aggregates non-200 routes into `/etc/nginx/prerender-status-overrides.list`; nginx uses a `map` + `error_page`/`return` flow to serve 404/410/503 with the prerendered body. Soft-404s on community-not-found, deleted listings, and maintenance mode now emit the right status to crawlers. Validated with `nginx -t` before reload; reverts atomically if the new map is malformed. Inspect API and Inventory rows now expose `http_status`.
  - **Job priority lane.** New `priority TINYINT` column on `prerender_jobs` (3 = high, 5 = normal, 7 = low). Claim ordering is `(priority, queued_at, id)` so auto-recache jobs can't starve urgent user-initiated runs. Enqueue API accepts an optional `priority` field; duplicate enqueues at a higher priority promote the existing queued row.
  - **Wildcard cache purge.** `POST /api/v2/admin/prerender/purge { pattern: "/blog/*" }` removes matching snapshots (and `_status` sidecars). Supports `*` (single segment), `**` (recursive), `?` (single char), optional `tenant_slug` scoping, `dry_run`, and an optional `recache` flag that auto-enqueues a low-priority re-render.
  - **Dashboard summary now truthful.** `summary()` was reporting `content_stale_count` and `asset_invalid_count` from a shallow inventory pass (`deep=false`), so the overview tab silently under-reported drift. Now uses the deep inventory under a 60-second cache.
- **Partner Communities moved to the left column of the "More" mega menu**, sitting directly under the Tools section. Previously placed beneath Impact in the right column, the federation submenu is now more discoverable to reflect its importance.

### Added

- **In-app `/changelog` page** rendering this file via `react-markdown`. The markdown source is copied from the repo root into `react-frontend/public/changelog.md` at prebuild/predev time by `scripts/copy-changelog.mjs`, so the in-app changelog is always in sync with the file in git. Footer Changelog link is now internal.
- **`Features` link in the public Navbar and Mobile drawer** (About section, alongside About / Blog / FAQ).
- `nav.features` and `nav_desc.features` translation keys in all 11 languages.

### Removed

- **Dead `dev_banner.*` and `dev_status.*` translation keys** swept from all 11 locale files (22 key blocks total). All code references were already gone when the platform moved to GA.
- **"Dev Notice" amber button** in the MobileDrawer bottom bar — redundant post-GA; Features is now reachable via the About accordion. `FlaskConical` icon import removed.

### Fixed

- **Trust & Safety "Garda vetting" section made jurisdiction-neutral.** This is a multi-tenant global platform; the Ireland-specific "Garda vetting" wording was inappropriate for tenants outside Ireland. Section retitled to "Background checks and vetting" and the body rewritten to cover background checks generally, mentioning Garda vetting (Ireland) and DBS (UK) as examples rather than the canonical regime. Applied across all 11 locale files.
- **🔴 Trust & Safety "Insurance and liability" section rewritten to match the actual platform-provider position in the Terms.** Aligns the Trust & Safety page wording with the corrected Terms of Service Section 13 (see [`database/migrations/2026_04_15_000002_fix_terms_insurance_section.php`](database/migrations/2026_04_15_000002_fix_terms_insurance_section.php), 2026-04-15): the organisation is a connection platform, not a service provider; members exchange services entirely at their own risk; members are solely responsible for ensuring they hold appropriate cover for any activities they undertake. Updated `trust_safety.insurance_items` across all 11 locale files and added a pointer to the Terms for the full liability and indemnity language.
- **`{{name}}` literal placeholder rendered on the public Trust & Safety page.** `TrustSafetyPage.tsx` was calling `t(section.introKey)` and `t(\`${section.itemsKey}.${i}\`)` without the `{ name: branding.name }` interpolation context, so strings like `"By using {{name}} you agree to:"` rendered with the raw `{{name}}` placeholder visible. Both intros and list items now pass the tenant brand name. Title interpolation also added defensively.
- **Build commit hash visible in the public footer.** The footer was rendering `__BUILD_COMMIT__` as a monospace string at the bottom of every page (and bleeding into Google snippets). The commit + build time are now exposed only as `data-*` attributes on a hidden element so the same diagnostics remain available via DOM inspection / Sentry tags without being part of the indexable page text.
- **Blog post dates rendered with locale-dependent and ambiguous formatting.** `BlogPage.tsx` was using bare `toLocaleDateString()` (no locale), so the same post showed as `12/9/2025` to some visitors and `9/12/2025` to others — unreadable for an Irish/UK audience. `BlogPostPage.tsx` was using `toLocaleDateString(undefined, …)` with the same issue. Both now pin to `en-GB` (`12 September 2025`).
- **American "neighbors" in public marketing copy.** Standardised to `neighbours` in the Stay Local landing card (`public.json` + `CoreValuesSection.tsx` fallback) and the "Local Hubs" mega-menu description in `NavigationConfig.php`, for consistency with the rest of the Irish/UK English copy.

- **`UnexpectedValueException: chmod(): Operation not permitted` on every request that triggers a Laravel log write** (Sentry [NEXUS-PHP-7](https://hour-timebank-clg.sentry.io/issues/NEXUS-PHP-7)). The `daily` log channel in `config/logging.php` set `'permission' => 0664`, which made Monolog call `chmod()` on the file on every write. When the existing day's log file is owned by a different user — e.g. left behind on a mounted volume from a prior container run — the `chmod()` fails and bubbles up as a 500. Removed the explicit permission so Monolog skips the chmod step entirely; new files are created with the default `0644` and existing files are left untouched.
- **CHANGELOG.md cleaned up.** Removed a block of fabricated legacy entries (a fake `[2.0.0] - 2024-02-13`, a duplicate `[1.5.0] - 2024-02-12`, and `[1.4.0]` through `[1.0.0]` with 2023–2024 dates) that were left over from a template — Project NEXUS development only began in mid-December 2025, so none of those releases ever existed. Also removed an incorrect "Hour Timebank (Crewkerne)" attribution (Crewkerne is an unrelated UK timebank) and the changelog's own contributors list, which conflicted with the canonical [CONTRIBUTORS.md](CONTRIBUTORS.md). Footer compare links pruned to the versions that actually exist (`v1.5.0`, `v1.5.0-rc.1`).

---

## [1.5.0] - 2026-05-13

**Project NEXUS is now Generally Available.** After running as a release candidate since 2026-03-27, the v1.5 line — covering the full Laravel 12 migration, the React SPA frontend, federation, multi-tenant scoping hardening, the SEO overhaul, the email system rewrite, and the PWA update architecture — is promoted to GA. The platform as a whole is live and supported; newer modules may still ship with their own per-module maturity label.

### Changed

- **Release marker promoted from RC → GA.** `RELEASE_STATUS.stageKey` is now `'ga'` with label "Generally Available (v1.5)". The amber "Release Candidate" footer strip is replaced with a calm GA strip linking to the new Features page and the public Changelog (this file, on GitHub).
- **Footer Changelog link** now points to `CHANGELOG.md` in the source repository — the canonical, public-facing version history.
- **`/development-status` page replaced with `/features`** — a public marketing-grade features inventory with honest per-module maturity chips (GA / Beta / Preview). The old `/development-status` URL 301s to `/features` so existing bookmarks survive. Federation is explicitly labelled **Beta — Live with external partners, protocols still hardening** to reflect reality: real partnerships exchange data daily while the wire protocols are still being hardened against edge cases.
- **PWA update flow rewritten (2026-05-10).** Replaced precache-shell + click-to-update workflow with NetworkFirst HTML + API stale-client gate. The HTML shell is no longer precached by the service worker; navigations are served NetworkFirst with a 3s timeout. Every API response carries `X-Build: <sha>`; the frontend interceptor force-redirects to `/api/sw-reset` if a build mismatch persists past a 10-minute grace window. Sentry events are now tagged with `build_commit` and `build_time`. Deploys propagate to users on their next navigation, with no UI prompt. See `react-frontend/CLAUDE.md#pwa-update-architecture` and the `feedback_pwa_android_update.md` memory file for the full architecture.

### Removed

- `react-frontend/public/sw-rescue.js` — service worker rescue shim that force-navigated clients via `client.navigate()`. Made redundant by NetworkFirst.
- `/clear-site-data` nginx route. Older SWs intercepted it and served the precached SPA shell, making it useless for actually-stuck users. `/api/sw-reset` does the same job and bypasses every SW we've ever shipped via the universal `/^\/api\//` denylist.
- "Update to the latest version" link in the mobile drawer (and the `nav.update_app` translation key in all 11 languages, the `triggerSoftAppUpdate` helper). With NetworkFirst + the API gate, no user will ever need a manual force-update button.

### Added

- Public `SECURITY.md` vulnerability disclosure policy.
- Public `CODE_OF_CONDUCT.md` community participation expectations.
- Dependabot coverage for Composer, npm, Docker, and GitHub Actions.
- Dependency Review workflow for pull request dependency changes.
- Tag-driven GitHub Release workflow and release process documentation.
- Request ID middleware that returns `X-Request-Id` and shares request, tenant, and user context with application logs.
- Comprehensive documentation suite
  - API Endpoints V2 reference (80+ endpoints documented)
  - React Component Library documentation (40+ components)
  - Developer Guide for extending the platform
  - User guides for Smart Matching and Reviews System

### Changed
- README now documents the public repository topology, visible quality gates, security process, and release process.
- README now clarifies that native mobile packaging is separate from the default public Docker workflow.

---

## [1.5.0-rc.1] - 2026-03-27

This release candidate covers nearly all development from 2026-01-18 to present. It represents the full maturation of the V1.5 line: a complete React SPA frontend, a full Laravel 12 migration, WebAuthn passkey support, expanded i18n, federation, social features, and comprehensive security hardening.

### Added

#### Laravel 12 Migration (Completed 2026-03-21)
- Laravel 12.54 is now the sole HTTP handler — all 1,218 routes wired to Laravel controllers
- All 223 services converted to native Eloquent implementations (zero stubs remain)
- 5 Event Listeners fully implemented: `NewUserRegistered`, `ExchangeCompleted`, `ListingCreated`, `MessageSent`, `VolunteerHoursLogged`
- Full 386-table baseline migration (`artisan migrate` works from scratch)
- Laravel scheduler replaces custom cron runner for all 25 scheduled tasks
- `Nexus\` namespace fully eliminated — 100% `App\` namespace throughout
- Dead legacy code deleted: 192 `src/Services` and `src/Models` files, 73 legacy framework files, all legacy PHP frontend controllers and views (civicone, modern, starter themes)
- Maintenance mode system: two-layer (file + database) with `scripts/maintenance.sh` and automatic deploy integration

#### React Frontend (Primary UI)
- Full React 18 + TypeScript + HeroUI + Tailwind CSS 4 SPA replacing all PHP-rendered user-facing views
- Capacitor-based native mobile app (iOS/Android) from the same React codebase
- React Native Expo mobile app with separate test suite (`mobile/`)
- 108 admin panel pages with 100% parity to legacy PHP admin
- Super Admin panel for cross-tenant management
- Universal Compose Hub with feature-gated tabs (listing, event, group, poll, post)
- PostDetailPage with direct post links and auto-open comments
- Explore / For You page with 7-source recommendation algorithm
- PWA service worker with auto-reload on stale chunks and update banner
- Google Maps integration (replacing Mapbox) with marker clustering and near-me filters
- Sales site at `project-nexus.ie` (separate container)
- Component refactors: `ConversationPage`, `GroupDetailPage`, `SettingsPage` split into sub-components

#### Authentication & Security
- WebAuthn / passkeys authentication (`react-frontend/src/lib/webauthn.ts`, `BiometricSettings.tsx`)
- TOTP two-factor authentication with trusted device support
- Registration policy engine: email verification gate, admin approval gate, invite codes, waitlist mode
- Identity verification module with per-tenant provider credential management (AES-256-GCM)
- Mandatory profile photo + bio enforcement on onboarding
- 7-layer regression prevention system (pre-commit → pre-push → CI → PR → Zod → local → deploy)
- Redis-based rate limiting on all API endpoints
- CSRF protection on all write operations and forms
- Sentry error tracking integrated in PHP and React
- Dependabot CVE alerts resolved; `rollup`, `dompurify`, `serialize-javascript`, `tar`, `basic-ftp` patched

#### Internationalisation (i18n)
- 7 languages: English, Irish (Gaeilge), German, French, Italian, Portuguese, Spanish
- All languages enabled for every tenant; tenant default language overrides browser detection
- 33 i18n namespace files per language (~4,571 keys each) covering all modules
- Language switcher on unauthenticated navbar and auth pages
- Translation drift detection added to CI and pre-push hook
- PHP admin i18n groundwork (English-only for now)

#### Federation
- Federation API V1 live: Neighborhoods, Credit Agreements, External Partners
- Partner detail page at `/federation/partners/:id`
- Federation connections route and `FederationConnection` type
- All federation features enabled by default for new tenants
- Federation gating uses dedicated tables (`federation_system_control`, `federation_tenant_whitelist`, `federation_tenant_features`)

#### Social Features
- Post reactions (emoji) on feed items and comments
- User presence indicators (online/away/offline) with heartbeat
- Link previews for shared URLs
- Media carousel with lightbox and thumbnail navigation
- @mention system with batch resolution and banned-user guards
- Stories feature with 30-story limit, audience controls, and IDOR prevention
- Video player with accessibility (focus restore, aria-live counter)
- Explore page with category chips, infinite scroll For You feed, and trending content
- Group feed tab and listing social features via shared social module
- Profile aggregated activity feed

#### Jobs & Volunteering Modules
- Enterprise-grade Jobs module: job templates, hiring teams, inline interview/offer response, salary display, bias audit, candidate moderation, talent search
- Volunteering module expansion: 7 new services, 5 React tabs, QR check-in, shift management, recurring shifts, expense tracking, certificates
- Organisation registration and opportunity posting UI
- Volunteer notification dispatch on application events

#### Polls, Ideation & Other Modules
- Polls module: create, vote, and results pages
- Ideation Challenges module: create campaigns, submit ideas, favourites, tags, cover images, draft saving, "turn ideas into teams" conversion
- 96 additional features across 18 modules implemented in the 2026-03-01 build sprint

#### Algorithms & Search
- Meilisearch integration with SQL fallback for listings search; index synced on create/update/delete
- EdgeRank feed algorithm upgraded to 15-signal pipeline with full CTR tracking
- Collaborative Filtering (`CollaborativeFilteringService`) for personalised recommendations
- OpenAI embedding-based matching (`EmbeddingService`)
- `FeedRankingService` with geo-decay, context-aware mode, and configurable signals
- `GroupRecommendationEngine` with cold-start handling
- Rubix ML, Wilson Score, and Bayesian average for member and listing ranking
- Cross-Module Matching Service with debug panel in admin
- User–User CF boost, dismissed listings suppression, skill proficiency in matching
- Batch geocode script (`scripts/batch_geocode_users.php`) for backfilling user coordinates
- OpenAPI 3.0 specification for V2 API added to repo

#### Onboarding
- Admin-configurable onboarding module (5 phases): backend config, admin UI, dynamic frontend steps, safeguarding step, listing creation modes (draft/review/active)
- Broker dashboard integration with safeguarding presets
- Atomic `/complete` transaction wrapping full onboarding flow

#### CRM & Admin
- CRM module: member notes, coordinator tasks, onboarding funnel, CRM webhook dispatches for volunteering events
- Newsletter admin: full parity with legacy PHP admin, stats improvements, activity page, SendGrid provider, per-tenant email config
- Tenant CRUD with full parity to legacy PHP admin including super admin role
- Registration policy admin UI with explanations for all modes
- 6 new admin management pages, algorithm settings page, Match Debug Panel
- Tenant super admin role; tenant lifecycle hardening

#### Email & Notifications
- SendGrid email provider with per-tenant configuration and SPF/DMARC deliverability fixes
- Email notifications for events, groups, endorsements, wallet credits received, reviews received
- All notification links made fully tenant-aware
- Fix for 404 dead links across all email notification types
- Nightly DB backup cron

#### Infrastructure & DevOps
- Git-based production deployment replacing file upload
- `scripts/safe-deploy.sh` with full/quick/rollback/status modes; automated migrations on deploy
- Docker production images protected from dev-image contamination
- Cloudflare cache purge automated in deploy scripts
- `scripts/maintenance.sh` for atomic two-layer maintenance mode toggle
- Migrations tracked in git; all legacy SQL migrations committed to `migrations/`
- Ahrefs Web Analytics on sales site and React app
- PHP memory_limit raised to 4G for PHPUnit; 8G for production containers
- `.gitattributes` enforcing LF line endings on shell scripts

#### Testing & Quality
- PHPStan level 3 added (warning-only; 123 pre-existing errors baseline)
- ESLint 9 flat config with 929-warning baseline
- 4,504+ PHPUnit tests (0 errors, 0 failures at point of Laravel migration merge)
- 118 Eloquent model factories added; 64 service test suites; 88 coverage-gap test files
- Vitest test suite for React with 71 WebAuthn tests, 66 ComposeHub tests, 367 social tests
- React Native Expo mobile test suite with auth, hooks, and screen tests
- E2E tests migrated fully to React frontend (Playwright)
- Lighthouse CI added for performance regression prevention
- Vitest Axe accessibility testing integrated in CI
- API contract test stage added to CI pipeline
- Translation drift detection in CI and pre-push hook

### Changed

- **Primary frontend** is now React SPA only — all PHP-rendered user pages removed
- PHP admin legacy views remain only at `/admin-legacy/` and `/super-admin/`
- Routes split from monolithic `routes.php` (2,487 lines) into 14 domain-specific partials
- Tenant routing: `/:tenantSlug` URL prefix with 42 reserved paths and `tenantPath()` helper
- Login is fully tenant-URL-aware; super admin can access any tenant
- Maps provider migrated from Mapbox to Google Places / Google Maps API
- Feed algorithm: default mode is Recent, EdgeRank as alternative; unified `feed_activity` table
- Compose Hub: Post tab removed; Listing set as default tab
- Navbar redesigned with mega menu, utility bar, command palette, and intelligent collapsing
- More dropdown reorganised with Partner Communities collapsible and Activity dividers
- `avatar` column renamed `avatar_url` across the entire codebase (4 affected files)
- Irish-specific phone and location validation removed globally; international E.164 throughout
- CORS wildcards replaced with per-origin validation
- `routes.php` and all controllers now under `app/` namespace exclusively

### Fixed

- Cross-tenant IDOR in `Group::findById()` — missing `tenant_id` scope (security audit 2026-03-09)
- `AdminContentApiController` menu_items DELETE/UPDATE lacked embedded tenant check (security audit 2026-03-09)
- `WalletFeatures` fatal error and `Exchanges` config regression
- Pusher auth 401 on login page; Pusher unsubscribe against closing WebSocket; Pusher 405 in production
- Feed load-more returning duplicate items from cursor pagination
- Balance alert emails spamming all users instead of the target user
- `register()` function not granting welcome credits on no-approval tenants
- Blog infinite re-render loop (cursor in `useCallback` deps)
- Avatar uploads: DB update silently failing, double `/api/` URL prefix, file permission bug in production
- `FeedRankingService::getConfig()` visibility (private → public)
- Legal document GET routes moved outside `auth:sanctum` — were silently returning generic defaults
- Service worker auto-reloading during message composition
- PWA icons corrected; stale chunk auto-reload on deploy for both Chrome and Firefox error patterns
- AbortController race conditions resolved across 83 pages
- `estimated_hours` column PDOException on listings creation
- `created_by` column reference on jobs page (should be `user_id`)
- `image_url` → `image` column on `feed_posts` table
- Sanctum cross-tenant auth bypass
- GDPR column names (`type`, `location`) fixed across multiple endpoints
- Cookie consent Bearer-token-aware auth; returns 200 when no record found
- Onboarding redirect loop resolved using `onboarding_completed` flag as sole source of truth
- CMS page cascade delete for menu items
- Custom domain tenant resolution: path no longer mistaken for slug
- Presence heartbeat 429 rate limiting
- Broken avatar URLs from stale domain references after legacy frontend removal
- Duplicate comment reactions route removed
- `longitude` field: standardised to `lon` (not `lng`) across nearby endpoint calls

### Security

- Critical: Cross-tenant IDOR in `Group::findById()` — fixed 2026-03-09 (see audit-history.md)
- Critical: `AdminContentApiController` DELETE/UPDATE lacking tenant check — fixed 2026-03-09
- Critical: God mode privilege escalation vulnerabilities fixed
- Critical: Open redirect vulnerabilities removed from login scripts
- Critical: SQL injection protections hardened across 50+ files
- Critical: Hardcoded production DB credentials removed from tracked files
- Critical: Pusher fallback key removed from `NotificationsContext`
- High: XSS vulnerabilities in view files fixed; DOMPurify and serialize-javascript patched
- High: CORS wildcards replaced with origin validation
- High: Rate limiting added to auth endpoints; login relaxed to 10 attempts/5 min
- High: Registration policy gates enforced on all entry points (not just registration)
- High: Super admin cross-tenant access control hardened
- High: Tenant isolation gaps in events, groups, messages, exchanges hardened
- High: 2FA enforced for all admin users
- High: AES-256-GCM encryption for per-tenant identity provider credentials
- Medium: `nosemgrep` annotations added for Semgrep false positives
- 18 tenant isolation regression tests added; admin security regression gate script added
- SPDX/AGPL-3.0-or-later headers on 100% of source files (1,230/1,230 files verified)

### Removed

- All legacy PHP frontend themes: civicone, modern (user-facing), starter — fully deleted
- 229 dead PHP frontend routes and 42 legacy frontend controllers
- 192 `src/Services` and `src/Models` files replaced by native Laravel equivalents
- Legacy `Database::` class replaced everywhere by Laravel DB facade
- `Nexus\` namespace entirely eliminated from the codebase
- 73 dead legacy framework files and all legacy ob_start delegation patterns

---

## Project history

Project NEXUS development began in mid-December 2025. The 1.5 line was developed throughout early 2026 and entered release-candidate status on 2026-03-27 before being promoted to General Availability on 2026-05-13. There are no earlier public releases — anything tagged before 1.5.0-rc.1 was internal development against the legacy PHP codebase and is not separately versioned here.

For the people behind the project, see [CONTRIBUTORS.md](CONTRIBUTORS.md) — the canonical attribution file.

---

## Support

- **Issues**: https://github.com/jasperfordesq-ai/nexus-v1/issues
- **Documentation**: `/docs` directory
- **Email**: support@project-nexus.ie

---

[Unreleased]: https://github.com/jasperfordesq-ai/nexus-v1/compare/v1.5.0...HEAD
[1.5.0]: https://github.com/jasperfordesq-ai/nexus-v1/compare/v1.5.0-rc.1...v1.5.0
[1.5.0-rc.1]: https://github.com/jasperfordesq-ai/nexus-v1/releases/tag/v1.5.0-rc.1
