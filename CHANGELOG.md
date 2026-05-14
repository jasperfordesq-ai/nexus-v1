# Changelog

All notable changes to Project NEXUS will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

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

- **🔴 Removed false "platform liability insurance" claim from Trust & Safety.** The Trust & Safety "Insurance and liability" section incorrectly stated that the platform *carries platform liability insurance for the running of the website and coordinator activity.* This contradicts the actual position — already documented in the Terms of Service (Section 13, corrected 2026-04-15 via [`database/migrations/2026_04_15_000002_fix_terms_insurance_section.php`](database/migrations/2026_04_15_000002_fix_terms_insurance_section.php)): as a platform provider (not a service provider) the organisation does **not** carry liability insurance for member exchanges, and members are solely responsible for ensuring they hold appropriate cover. Rewrote `trust_safety.insurance_items.0` to make the no-insurance position explicit and added a pointer to the Terms for the full liability/indemnity language. Applied across all 11 locale files for consistency.
- **`{{name}}` literal placeholder rendered on the public Trust & Safety page.** `TrustSafetyPage.tsx` was calling `t(section.introKey)` and `t(\`${section.itemsKey}.${i}\`)` without the `{ name: branding.name }` interpolation context, so strings like `"By using {{name}} you agree to:"` and the platform-liability-insurance item rendered with the raw `{{name}}` placeholder visible. Both intros and list items now pass the tenant brand name. Title interpolation also added defensively.
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
