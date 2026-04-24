# Project NEXUS — Roadmap

> **Last updated:** 2026-04-12 (added TD8–TD15 from full-platform audit)
> **Maintained by:** Jasper Ford
> **Status key:** ✅ Done | ⚠️ Partial | 📋 Planned | 💡 Future

This is the **single, canonical roadmap** for Project NEXUS. All feature planning, technical debt, and strategic initiatives live here.

---

## Table of Contents

1. [SEO & Rendering Architecture](#1-seo--rendering-architecture)
2. [Technical Debt & Platform Health](#2-technical-debt--platform-health)
3. [Federation & Internationalization](#3-federation--internationalization)
4. [Social & Engagement Features](#4-social--engagement-features)
5. [Media & Communication](#5-media--communication)
6. [Admin & Reporting](#6-admin--reporting)
7. [Infrastructure & Integrations](#7-infrastructure--integrations)
8. [Marketplace Module (Commercial)](#8-marketplace-module-commercial)
9. [Completed Work](#9-completed-work)

---

## 1. SEO & Rendering Architecture

The platform lost all organic keywords after migrating from WordPress to a Vite React SPA. The SPA sends an empty `<div id="root"></div>` to crawlers — Google cannot index content without JavaScript execution. A deep audit (2026-04-08) found 7 critical issues including Prerender.io cache poisoning, missing WordPress redirects, and broken sitemap configuration. Emergency fixes deployed same day. The long-term fix is migrating the frontend rendering architecture.

### Phase A — Emergency SEO Fixes (Complete)

Deployed 2026-04-08. Stops the bleeding while the architectural fix is planned.

| # | Item | Status | Notes |
|---|------|--------|-------|
| SEO1 | Prerender.io cache poisoning fix | ✅ Done | MaintenancePage returns 503 via `prerender-status-code` meta tag. Prevents maintenance page from being cached and served to Google with `noindex`. |
| SEO2 | Homepage prerender bypass fix | ✅ Done | nginx `try_files $uri @prerender` (removed `$uri/`). Homepage was serving empty SPA shell to all crawlers. |
| SEO3 | WordPress legacy URL redirects | ✅ Done | 301/410 rules in nginx.conf for `/wp-*`, `/guides/*`, `/feed/`, `/author/*`, `/community-directory/`, `/southern-star/`. Preserves backlink equity. |
| SEO4 | NotFoundPage soft 404 fix | ✅ Done | NotFoundPage + CommunityNotFound return 404 via `prerender-status-code`. Previously returned 200 (soft 404 penalty). |
| SEO5 | robots.txt Sitemap directive fix | ✅ Done | nginx `sub_filter` rewrites `app.project-nexus.ie` → `$host` so each custom domain references its own sitemap. |
| SEO6 | Sitemap API domain URL fix | ✅ Done | `SitemapController` case 3 uses `generateForAppDomain()` instead of generating URLs with the API domain. |
| SEO7 | Group lastmod dates | ✅ Done | `SitemapService` uses `COALESCE(updated_at, created_at)` instead of `created_at`. 431 groups had stale Dec 2025 dates. |
| SEO8 | Auto-recache after deploy | ✅ Done | Self-hosted prerendering via `scripts/prerender-tenants.sh` + `scripts/prerender-worker.mjs` (Playwright). Prerender.io retired; `recache-prerender.sh` deleted. |
| SEO9 | Cloudflare robots.txt injection | ✅ Done | Disabled Cloudflare AI bot control robots.txt modification across all 8 zones. Was creating duplicate `User-agent: *` groups. |

### Phase B — Build-Time Static Pre-Rendering (Partially Done)

Interim solution: pre-render public pages at build time so nginx serves real HTML to everyone (users AND bots), with React hydrating on top. No external services, no runtime rendering.

| # | Item | Status | Priority | Notes |
|---|------|--------|----------|-------|
| SEO10 | Playwright pre-render script | ⚙️ In Progress | Critical | `react-frontend/scripts/prerender.mjs` + `scripts/prerender-worker.mjs` exist and cover ~19 static public routes. Not yet wired as a `postbuild` step in `react-frontend/package.json` — currently invoked separately. |
| SEO11 | Dynamic route pre-rendering | 📋 Planned | Critical | Blog posts, listings, groups fetched from sitemap at build time and pre-rendered. Self-hosted Playwright prerender (no Prerender.io). |
| SEO12 | Structured data on listings | 📋 Planned | High | Product/Service JSON-LD schema on `ListingDetailPage`. Enables rich snippets. |
| SEO13 | Article schema completion | 📋 Planned | High | Blog posts: add `dateModified`, `description`, `author.url` to Article JSON-LD. |
| SEO14 | Homepage internal linking | 📋 Planned | High | Add discoverable links to blog, listings, events, groups in `LandingPageRenderer` for crawlers. |
| SEO15 | Remove test blog posts | 📋 Planned | Medium | 4 lorem ipsum posts in sitemap (`aenean-sed-pulvinar-et-diam`, etc.) — unpublish from admin. |

### Phase C — Next.js Migration (Planned)

The correct long-term architecture. Migrate the React frontend from Vite SPA to Next.js App Router with server-side rendering. Public pages get SSR/SSG with Incremental Static Regeneration. Protected pages stay as `"use client"` components. Estimated **4-6 weeks** for SEO-critical public pages, **10-14 weeks** for full migration.

| # | Item | Status | Priority | Effort | Notes |
|---|------|--------|----------|--------|-------|
| NX1 | Next.js project setup | 📋 Planned | Critical | 1 week | Tailwind 4, HeroUI, i18n (next-intl), TypeScript strict. New branch `nextjs-migration`. |
| NX2 | Multi-tenant middleware | 📋 Planned | Critical | 1 week | Domain → tenant resolution in Next.js middleware (currently in React `TenantContext`). Server-side tenant bootstrap. |
| NX3 | Auth system (SSR-compatible) | 📋 Planned | Critical | 1 week | Sanctum token → server-side session. Protected route middleware. API client for server components. |
| NX4 | Public pages — SSG | 📋 Planned | Critical | 3-5 days | Homepage, About, FAQ, Contact, Help, Legal pages (x6), Timebanking Guide. Static generation at build time. |
| NX5 | Blog pages — ISR | 📋 Planned | Critical | 3-5 days | Blog listing + `/blog/:slug`. Incremental Static Regeneration (60s revalidation). Full Article schema. |
| NX6 | Listings pages — ISR | 📋 Planned | Critical | 3-5 days | Listings browse + `/listings/:id`. ISR with Product/Service schema. |
| NX7 | Groups + Events pages — ISR | 📋 Planned | High | 3-5 days | `/groups/:id`, `/events/:id`. ISR (300s revalidation). |
| NX8 | Auth pages (Login, Register, etc.) | 📋 Planned | High | 2-3 days | Client components, minimal SSR needed. |
| NX9 | Protected pages (Dashboard, Messages, Wallet, etc.) | 📋 Planned | Medium | 3-4 weeks | 50+ routes. Most stay as `"use client"` with SSR shell (layout only). |
| NX10 | Admin panel | 📋 Planned | Medium | 1 week | Catch-all `"use client"` route. Already fully client-side. |
| NX11 | PWA + Service Worker | 📋 Planned | Medium | 3-5 days | `next-pwa` package. Rework SW registration and update detection. |
| NX12 | Real-time features (Pusher, presence) | 📋 Planned | Medium | 2-3 days | Client-only, needs SSR-safe initialization. |
| NX13 | Stripe, Maps, WebAuthn, Lexical | 📋 Planned | Low | 3-5 days | All client components. Minimal SSR impact. |
| NX14 | Production Dockerfile + deployment | 📋 Planned | Critical | 2-3 days | Node.js runtime replaces nginx static. Docker Compose changes. CI/CD updates. |
| NX15 | E2E testing + performance benchmarking | 📋 Planned | High | 1 week | Playwright tests, Lighthouse CI, Core Web Vitals verification. |

**Migration approach:** Incremental, page by page. NX1-NX7 (public pages) ship first — this is the SEO-critical work (~4-6 weeks). NX8-NX13 (protected pages) follow as a second phase. The existing Vite SPA continues serving protected routes until each page is migrated.

**Branch:** `nextjs-migration` (will be created when Phase C begins).

---

## 2. Technical Debt & Platform Health

Items that improve code quality, test coverage, and maintainability.

| # | Item | Status | Priority | Notes |
|---|------|--------|----------|-------|
| TD1 | Migrate admin views from `\Nexus\` to `App\` imports | ✅ Done | — | Zero `\Nexus\` imports remain. All admin views use `App\` namespace. |
| TD2 | PHPStan: fix 123 pre-existing errors, promote to blocking CI | 📋 Planned | Medium | CI runs PHPStan but 123 errors suppressed in `ignoreErrors`. Fix errors then remove suppressions. |
| TD3 | ESLint: reduce 929 warnings, lower `--max-warnings` threshold | 📋 Planned | Medium | `--max-warnings 1000` in local lint. ESLint not in CI pipeline — add it. |
| TD4 | Service test coverage: close 56% gap (131/233 untested) | 📋 Planned | Medium | Priority: auth, wallet, federation services |
| TD5 | Fix 2 stale JobApplicationTest failures | ✅ Done | — | All 8 tests pass (verified 2026-03-28). |
| TD6 | Native speaker review of AI-generated translations | 📋 Planned | Medium | Languages: pt, es, ga, de, fr, it |
| TD7 | Accessibility audit + WCAG fixes | ✅ Done | — | 18 tests (6 suites), CI promoted to blocking. axe-core + vitest-axe covering GlassInput, Breadcrumbs, BackToTop, LoadingScreen, LevelProgress, ImagePlaceholder + 3 original components. |
| TD8 | Consolidate migration systems (legacy SQL → Laravel) | 📋 Planned | Medium | ~190 legacy `/migrations/*.sql` files tracked in `migrations` table coexist with ~80 Laravel migrations in `/database/migrations/` tracked in `laravel_migrations`. Both can touch the same tables (federation, 404 logs, reengagement). Options: (a) freeze legacy and let them age out via the schema dump, or (b) port all legacy SQL to idempotent Laravel migrations and deprecate the `migrations` table. Either way, add CI guard to block new legacy SQL. Surfaced in 2026-04-12 audit (C4). |
| TD9 | Standardize service layer: static → instance (DI) | 📋 Planned | Low | ~50/50 split: ~1318 static method calls vs ~1315 instance calls across services. Static methods can't be mocked cleanly (Mockery alias-mock "Cannot redeclare" errors already hit in Jobs module). Incremental plan: convert one service at a time to constructor injection, update callers, delete the static facade. Start with most-tested services (Wallet, Auth, Federation). Tech debt, not a bug. Surfaced in 2026-04-12 audit (M6). |
| TD10 | Review foreign key `ON DELETE` policy (CASCADE vs SET NULL) | 📋 Planned | Medium | 277 `ON DELETE CASCADE` vs 40 `ON DELETE SET NULL` across schema. User deletion currently erases audit trails (transactions, messages, posts). Needs product/legal call per table class: (a) GDPR right-to-erasure → keep CASCADE on personal data, (b) financial/audit tables → switch to SET NULL to preserve 7-year trail, (c) UX — do deleted-user messages stay as "[deleted user]" or vanish? Audit each FK; write a migration per table class. Surfaced in 2026-04-12 audit (M8). |
| TD11 | Admin bulk-action parity with legacy PHP admin | 📋 Planned | Medium | Legacy `/admin-legacy/` had bulk approve/suspend users, bulk delete/publish blog posts, bulk reject marketplace listings with reason. React admin has single-item actions only. Each bulk action needs checkbox column, toolbar, confirm modal, backend endpoint, i18n keys, permission checks — roughly 1 day per action × 10+ actions. Feature sprint, not a bug fix. Critical gaps (Deliverables edit, orphan permissions route, destructive confirms) already addressed 2026-04-12. Surfaced in 2026-04-12 audit (M10). |
| TD12 | Delete `AdminPlaceholder` stub component | 📋 Planned | Low | Orphan component not used by any route. Can't delete yet because `modules-batch1.test.tsx` and `SystemModules.test.tsx` still import it. Either the tests assert placeholder behavior for unbuilt modules (papering over missing pages — fix the real gaps first), or they can be updated/deleted with the component. Surfaced in 2026-04-12 audit. |
| TD13 | Revisit dev/prod PHP memory asymmetry | 📋 Planned | Low | Dev `memory_limit=1G`, prod `memory_limit=384M`. Technically violates CLAUDE.md rule "Dockerfile limits must match." Chosen pragmatically so dev can run artisan migrate:fresh, PHPStan, heavy tinker sessions without OOM. Risk: a regression passes locally but fails prod. Options: (a) accept asymmetry (current), (b) cap dev at 512M and require `php -d memory_limit=-1` for heavy artisan, (c) add a dev-only override script. Decide and document. Surfaced in 2026-04-12 audit. |
| TD14 | Monitor prod container memory after 512M→768M bump | 📋 Planned | Low | `compose.prod.yml` PHP container limit raised from 512M to 768M to fit Apache mpm_prefork workers + OPcache (128M) + PHP `memory_limit` (384M) under concurrent load. If Azure VM has headroom, bump to 1G for safety margin. If VM is tight, reduce PHP `memory_limit` / `max_execution_time`. Watch for OOMKilled events after next deploy. Surfaced in 2026-04-12 audit. |
| TD15 | Validate artisan-cache fail-fast behavior in prod | 📋 Planned | Low | `Dockerfile.prod` previously ran `config:cache \|\| true` (silent failure → uncached prod). Removed 2026-04-12: now any artisan cache failure fails the Docker build. Risk: if `config:cache` requires a runtime-only env var, next `safe-deploy.sh full` will crash-loop. Watch first deploy carefully. If it breaks, move caching from `RUN` to a runtime entrypoint script (runs after env injection) rather than re-silencing the error. Surfaced in 2026-04-12 audit (M2). |

---

## 3. Federation & Internationalization

Features enabling a **global network of timebanks** communicating across languages. Inspired by outreach from the international timebanking community (hOurWorld, Timebanks.org, Care and Share Time Bank).

### Message Translation (Cross-Language Messaging)

| # | Item | Status | Priority | Effort | Notes |
|---|------|--------|----------|--------|-------|
| INT1 | Auto-translate typed messages | ✅ Done | — | — | Translate button on text message bubbles via `POST /v2/messages/{id}/translate`. Supports both voice transcripts and text bodies. "View original" / "Show translation" toggle. Uses GPT-4o-mini via `TranscriptionService::translate()`. Feature-gated per tenant (`message_translation`). |
| INT2 | Voice message transcription + translation | ✅ Done | — | — | `TranscriptionService` (Whisper API + gpt-4o-mini translation). Transcripts stored on messages, collapsible display in VoiceMessagePlayer, translate button in MessageBubble. |
| INT3 | Federated message translation | 📋 Planned | Medium | Medium | Federation messaging is text-only today. Add translation layer so cross-tenant messages auto-translate. Also add voice/attachment support to federation messages. |
| INT4 | Additional UI languages | ✅ Done | — | — | Now 11 languages: en, ga, de, fr, it, pt, es + Dutch (nl), Polish (pl), Japanese (ja), Arabic (ar with RTL). All 33 namespace files per language. |
| INT5 | Real-time voice-to-voice interpretation | 💡 Future | Low | Very Large | WebRTC live calling + streaming speech-to-text + real-time translation + text-to-speech. Stretch goal — depends on SOC4 (voice/video calling) existing first. |
| INT6 | Server-side Redis translation cache | ✅ Done | — | — | SHA-256 hash of `text:from:to` as Redis key (24h TTL). `TranscriptionService::translate()` checks `Cache::get()` before OpenAI call. Bypassed when context/glossary are active (unique per-request). |
| INT7 | Context-aware conversational translation | ✅ Done | — | — | When `translation.context_aware` is enabled, backend fetches preceding N messages and feeds them into the LLM prompt. Configurable via `translation.context_messages` (default 5). |
| INT8 | Auto-translate entire conversation thread | ✅ Done | — | — | Languages icon toggle in conversation header. Stores preference in localStorage per conversation partner. Auto-translates all incoming messages on load. Gated behind `message_translation` feature. |
| INT9 | Translation admin config per tenant | ✅ Done | — | — | Admin page at `/admin/translation-config`. `TranslationConfigurationService` with 7 settings (engine, context-aware, rate limits, glossary toggle). `tenant_settings` storage with Redis cache. |
| INT10 | Custom glossary / brand dictionary | ✅ Done | — | — | `translation_glossaries` table (tenant-scoped). Admin CRUD at `/admin/translation-config`. Terms injected into LLM system prompt. Up to 50 terms per translation request. |

### Federation Enhancements

| # | Item | Status | Priority | Effort | Notes |
|---|------|--------|----------|--------|-------|
| FED1 | Tenant topic/interest tags | ✅ Done | — | — | 24 predefined topics across 7 categories (Care, Skills, Creative, Home, Health, Community, Services). `federation_topics` + `federation_tenant_topics` tables. Tenants select up to 10 topics (3 primary). Topic filter in directory, topic chips on community cards. |
| FED2 | Federation directory / public tenant listing | ✅ Done | — | — | `FederationDirectoryService` fully implemented. Discoverable timebanks with filtering by search, region, categories. Integrated into federation admin. |
| FED3 | Protocol REST endpoint coverage (all 9 entities) | ✅ Done | — | — | Complete inbound + outbound coverage across all 9 entity types (profiles, messages, transactions, listings, members, events, groups, reviews, volunteering). Includes Komunitin spec (15 endpoints), event-driven sync, feature-gate enforcement, shadow tables, end-to-end two-way integration tests. Hashchain stub cleaned up (2026-04). |

---

## 4. Social & Engagement Features

Features that make NEXUS feel like a modern social platform.

### 🔴 Tier 1 — High Impact

| # | Item | Status | Priority | Effort | Notes |
|---|------|--------|----------|--------|-------|
| SOC1 | Stories (24h disappearing content) | ✅ Done | — | — | Full implementation: image/text/poll/video stories, 24h expiry, reactions (love/laugh/wow/sad), highlights, `story_poll_votes`. Tests passing. |
| SOC2 | Emoji reactions (beyond likes) | ✅ Done | — | — | 8 reaction types: love, like, laugh, wow, sad, celebrate, clap, time_credit. `ReactionPicker` + `ReactionSummary` components. Toggle/swap logic. |
| SOC3 | Video posts & upload | ✅ Done | — | — | `VideoUploader`, `VideoPlayer` components. Multi-media upload in `PostTab`. Stories support video with duration tracking. |
| SOC4 | Voice & video calling | 📋 Planned | High | Very Large (4-6 wk) | No WebRTC, Jitsi, or call infrastructure. 1:1 voice/video, group voice rooms, call history. Needs TURN/STUN server. Prerequisite for INT5. |
| SOC5 | Real-time presence (online/offline) | ✅ Done | — | — | `PresenceService` with Redis + DB. States: online/away/offline/dnd. Green/amber indicators in UI. `hide_presence` privacy control. |

### 🟡 Tier 2 — Medium Impact

| # | Item | Status | Priority | Effort | Notes |
|---|------|--------|----------|--------|-------|
| SOC6 | Link previews / rich embeds | ✅ Done | — | — | `LinkPreviewService` with OG scraping, SSRF protection, 7-day cache. YouTube/Vimeo special handling. `LinkPreviewCard` component. |
| SOC7 | Carousel / multi-image posts | ✅ Done | — | — | `ImageCarousel` (swipeable) + `MediaGrid` (2-4 images). Multi-file upload with alt text support. |
| SOC8 | GIF & sticker support | ✅ Done | — | — | Tenor API v2 client, `GifPicker` component with search + trending. Integrated in PostTab and MessageInputArea. Stickers in stories. |
| SOC9 | @Mentions in posts & comments | ✅ Done | — | — | `MentionService` with regex extraction, resolution, notifications. `MentionInput` with autocomplete, `MentionRenderer` for highlighting. |
| SOC10 | Bookmarks / save collections | 📋 Planned | Medium | Small (3-5 days) | No saved_items/collections tables or UI. Save any content type into named collections. |
| SOC11 | Content scheduling | ✅ Done | — | — | `scheduled_at` + `publish_status` on feed_posts. `publishScheduledPosts()` cron every minute. Scheduling UI in PostTab with date/time picker. |
| SOC12 | Content pinning | ✅ Done | — | — | `is_pinned` on `feed_posts`, `group_discussions`, `discussions`. Pinned indicator in UI. |
| SOC13 | Social login (OAuth) | 📋 Planned | Medium | Medium (1 wk) | No Socialite. Auth is local credentials + WebAuthn only. Google, Facebook, Apple sign-in needed. |
| SOC14 | "Thank You" / appreciation system | 📋 Planned | Medium | Small (2-3 days) | No tables, services, or UI. Public appreciation wall, thank-you cards, "Most appreciated" recognition. |
| SOC15 | Notification grouping | 📋 Planned | Medium | Medium (1 wk) | Notifications are 1:1 individual. No "and X others" grouping, batching, or aggregation. |

### 🟢 Tier 3 — Nice to Have

| # | Item | Status | Priority | Effort | Notes |
|---|------|--------|----------|--------|-------|
| SOC16 | Live streaming | 💡 Future | Low | Very Large (4-8 wk) | No implementation. Needs media server (Mux/Agora). |
| SOC17 | Marketplace module (commercial) | ✅ Done | — | — | Phases 1–5 fully implemented (2026-04-05). 18 tables, 14 services, 12 controllers, ~100 endpoints, 14 pages, 26 components, 3 admin pages. See [Section 8](#8-marketplace-module-commercial). |
| SOC18 | User activity status sharing | ✅ Done | — | — | `user_presence` table: `custom_status`, `status_emoji`. `StatusSelector` component. `PresenceController` API. |
| SOC19 | Community challenges / competitions | 📋 Planned | Low | Medium (1-2 wk) | Ideation challenges exist (different concept). No team competitions, community-wide progress bars, or competitive leaderboards. |
| SOC20 | Personal impact dashboard | ✅ Done | — | — | `ImpactReportingService` with SROI calculations. `ImpactReportPage` + `ImpactSummaryPage`. Community health metrics, impact timelines. |
| SOC21 | Collaborative documents | 💡 Future | Low | Large (2-3 wk) | No implementation. Needs Tiptap or similar. |
| SOC22 | Community topic channels | ✅ Done | — | — | Group chatrooms with categories, `is_private`, permissions JSON, pinned messages table. Pin/unpin API + UI with lock icons and category chips. |
| SOC23 | Dark mode enhancements & theming | ✅ Done | — | — | `theme_preferences` JSON on users. AppearanceSettings: 10 accent colors, font size (S/M/L), density (compact/comfortable/spacious), high contrast toggle. CSS custom property overrides. |
| SOC24 | Polls in Stories & Events | ✅ Done | — | — | Stories have polls. Events now have `event_id` FK on polls, PollSection on EventDetailPage with voting UI, poll attachment on CreateEventPage. |

---

## 5. Media & Communication

Enhancements to the messaging and media systems.

| # | Item | Status | Priority | Effort | Notes |
|---|------|--------|----------|--------|-------|
| MED1 | Quiet hours / DND mode | ✅ Done | — | — | `PresenceService` supports DND status. `user_presence.status` enum includes `dnd`. DND preserved during heartbeat updates. |
| MED2 | SMS notifications | 📋 Planned | Low | Medium (2-3 wk) | No Twilio/Vonage. Notifications are email + push only. |

---

## 6. Admin & Reporting

| # | Item | Status | Priority | Effort | Notes |
|---|------|--------|----------|--------|-------|
| ADM1 | Advanced analytics dashboards | ✅ Done | — | — | Recharts v3.7.0. 10+ dashboard components: CommunityAnalytics, FederationAnalytics, GamificationAnalytics, GroupAnalytics, MatchingAnalytics, PerformanceDashboard, etc. `AdminAnalyticsService` backend. |
| ADM2 | Bulk CSV data import | ✅ Done | — | — | `AdminUsersController::import()` — CSV upload with per-row validation, import/skip/error counts, audit logging. |
| ADM3 | Notification analytics dashboard | ✅ Done | — | — | `NewsletterAnalytics` component: total_sent, open_rate, click_rate, delivery_rates, monthly breakdown. `NewsletterAnalytics` model. |
| ADM4 | Organization volunteering portal | 📋 Planned | Medium | Large (3-4 wk) | No org-team volunteering system. Organization-level volunteer management, corporate team dashboards, program-level reporting. |

---

## 7. Infrastructure & Integrations

| # | Item | Status | Priority | Effort | Notes |
|---|------|--------|----------|--------|-------|
| INF1a | Stripe tenant plan subscriptions | ✅ Done | — | — | `StripeSubscriptionService` with Checkout Sessions, Billing Portal, plan sync, webhook handlers. `AdminBillingController` with 5 endpoints. Admin billing UI: BillingPage, PlanSelector, InvoiceHistory, CheckoutReturn. Idempotent webhooks via `stripe_webhook_events`. |
| INF1b | Stripe donation processing | ✅ Done | — | — | `StripeDonationService` with Payment Intents, refunds, receipts. `DonationPaymentController` with 3 endpoints. Stripe Elements card form (`StripePaymentForm`), `DonationCheckout` modal, `DonationReceipt`. Wired into DonationsTab. |
| INF2 | API webhooks | ✅ Done | — | — | `WebhookDispatchService` with `outbound_webhooks` + `outbound_webhook_logs` tables. HMAC-SHA256 signatures, event filtering, retry logic. CRM webhook pre-registered. |
| INF3 | White-label theming per tenant | 📋 Planned | Medium | Medium (2-3 wk) | No tenant theme customization. All tenants use fixed HeroUI + Tailwind theme. |
| INF4 | Community currency models | 📋 Planned | Low | Medium (2-3 wk) | Time credits only. No alternative currencies, points, or exchange rate config. |
| INF5 | Document signing / e-signatures | 📋 Planned | Low | Large (3-4 wk) | No DocuSign or e-signature integration. Legal docs are PDFs only. |
| INF6 | Video conferencing integration | ✅ Done | — | — | `video_url` + `allow_remote_attendance` in Event model fillable. URL input + toggle on CreateEventPage. "Join Meeting" button on EventDetailPage. |

---

## 8. Marketplace Module (Commercial)

A **completely standalone commercial marketplace module** (SOC17) for buying/selling physical goods and paid services — like Facebook Marketplace. This is **entirely separate from the Listings module** (which handles timebanking service exchanges for time credits). The marketplace has its own tables, services, controllers, pages, and Meilisearch index. Zero coupling to listings.

**Feature flag:** `marketplace` (default: `false`) — disabled for most tenants. Enabled per-tenant via admin toggles.

**Source:** Gap analysis benchmarked against 50+ platforms (Facebook Marketplace, eBay, Vinted, DoneDeal, Adverts.ie, OLX, Depop, Nextdoor, Instagram Shopping) — April 2026.

**Status:** Phases 1–5 fully implemented (2026-04-05). ~25,800 lines across ~130 files.

### Architecture Overview

The marketplace module is **completely standalone**:

- **Own tables**: 18 `marketplace_*` tables (listings, images, categories, saved_listings, category_templates, seller_profiles, offers, orders, payments, escrow, seller_ratings, disputes, saved_searches, collections, collection_items, promotions, reports, shipping_options, delivery_offers)
- **Own Meilisearch index**: `marketplace_listings` (separate from the `listings` index)
- **Own services**: 14 services (Listing, Seller, Offer, Order, Payment, Escrow, Rating, Discovery, Promotion, Report, ShippingOption, Group, CommunityDelivery, AI)
- **Own controllers**: 12 controllers, ~100 API endpoints under `/v2/marketplace/*` and `/v2/admin/marketplace/*`
- **Own React pages**: 14 pages under `/marketplace/*`, 3 admin pages, 26 components
- **Own models**: 18 Eloquent models, all using `HasTenantScope`
- **Configuration**: `MarketplaceConfigurationService` with 19 tenant-scoped settings
- **Scheduled jobs**: 4 (offer expiry, promotion expiry, escrow auto-release, DSA auto-acknowledge)
- **Category seeder**: 18 default system categories
- **Generic platform utilities reused** (NOT listing code): `ImageUploadService`, `GeocodingService`, Meilisearch client, `TenantContext`, `AiChatService`, `WalletService`

### Phase 1 — MVP Core (Complete)

| # | Item | Status | Notes |
|---|------|--------|-------|
| MKT1 | Multi-image media pipeline | ✅ Done | Up to 20 images per listing (configurable), drag-to-reorder, gallery carousel. Own `marketplace_images` table. |
| MKT2 | Template-driven listing model | ✅ Done | `marketplace_category_templates` table with JSON field schema. `template_data` column on listings. Dynamic form fields per category. |
| MKT3 | Faceted search with dynamic counts | ✅ Done | Price range, condition, seller type, delivery method, category, posted within, sort. `MarketplaceFacetedSearch` component. |
| MKT5 | Business vs. private seller | ✅ Done | `seller_type` ENUM, `BusinessSellerBadge` component, business verification fields on seller profiles. |
| MKT7 | Structured data per vertical | ✅ Done | JSON-LD Product/Offer markup on listing detail pages. |
| MKT8 | DSA trader traceability | ✅ Done | Business seller verification: name, address, registration, VAT on `marketplace_seller_profiles`. Admin verification endpoint. |
| MKT15 | AI-assisted listing creation | ✅ Done | `MarketplaceAiService` + `AiChatService`. "Generate with AI" button on create/edit pages. |
| MKT25 | Make Offer / Negotiation | ✅ Done | `marketplace_offers` table. Full lifecycle: create, accept, decline, counter, accept-counter, withdraw, expire. `MakeOfferForm` + `OfferCard` components. `MyOffersPage` for sent/received. |
| MKT28 | Seller Profile Pages | ✅ Done | `marketplace_seller_profiles` table. `SellerProfilePage` with listings, ratings, stats. `SellerCard` component. |

### Phase 2 — Payments & Trust (Complete)

| # | Item | Status | Notes |
|---|------|--------|-------|
| MKT11 | Escrow / buyer protection | ✅ Done | `marketplace_escrow` + `marketplace_payments` tables. `MarketplacePaymentService` (Stripe Connect), `MarketplaceEscrowService` (hold/release/refund with 14-day auto-release). |
| MKT29 | Mutual Rating System | ✅ Done | `marketplace_seller_ratings` table. `MarketplaceRatingService` with buyer+seller ratings. `RatingModal` component. Auto-refreshes seller profile avg_rating. |
| — | Stripe Connect onboarding | ✅ Done | `MarketplacePaymentService::createConnectAccount()`, `StripeOnboardingPage` with 3 states (start/incomplete/complete). |
| — | Order management | ✅ Done | `marketplace_orders` table. Full lifecycle: pending_payment → paid → shipped → delivered → completed. `MarketplaceOrderService`. `BuyerOrdersPage` + `SellerOrdersPage`. `BuyNowButton` + `OrderStatusBadge`. |
| — | Dispute resolution | ✅ Done | `marketplace_disputes` table. `MarketplaceRatingService::openDispute()`. Evidence collection, admin resolution. |

### Phase 3 — Discovery & Engagement (Complete)

| # | Item | Status | Notes |
|---|------|--------|-------|
| MKT4 | Self-serve visibility products | ✅ Done | `marketplace_promotions` table. `MarketplacePromotionService` with bump/featured/top_of_category/homepage_carousel. `PromotionSelector` component. Config-driven pricing. |
| MKT26 | Saved Searches + Price Alerts | ✅ Done | `marketplace_saved_searches` table. `MarketplaceDiscoveryService`. `SavedSearchCard` component. Alert frequency: instant/daily/weekly. |
| MKT27 | Collections / Wishlists | ✅ Done | `marketplace_collections` + `marketplace_collection_items` tables. `MarketplaceCollectionsPage`. `CollectionCard` component. Public/private collections. |
| MKT30 | Free Items / Giveaway | ✅ Done | `FreeItemsPage` at `/marketplace/free`. Pre-filtered `price_type = 'free'`. "Give Something Away" CTA. |
| MKT34 | Feed-Integrated Cards | ✅ Done | `MarketplaceFeedCard` component for main community feed integration. |

### Phase 4 — Shipping & Advanced (Complete)

| # | Item | Status | Notes |
|---|------|--------|-------|
| MKT6 | DSA notice-and-action reporting | ✅ Done | `marketplace_reports` table with 7 reasons, appeal workflow. `MarketplaceReportService` with 24h auto-acknowledge. Admin moderation queue + transparency stats. Scheduled hourly. |
| MKT9 | Pro portals / bulk tooling | ✅ Done | Bulk actions (activate/deactivate/renew/remove), CSV export, CSV import (creates drafts, max 50 per import). |
| MKT10 | Map search | ✅ Done | `MarketplaceMapSearchPage` with split sidebar+map view. `MapSearchView` with color-coded pins. `ListingLocationMap` on detail pages with privacy offset. Geolocation. |
| MKT12 | CCPC prior price rule | 📋 Planned | Show lowest price in preceding 30 days on discounted items. |
| MKT13 | Dark pattern audit | 📋 Planned | UI review against DSA dark pattern prohibitions. |
| MKT14 | Ranking explainability UI | 📋 Planned | "Why am I seeing this?" tooltip for transparency. |
| MKT31 | Shipping configuration | ✅ Done | `marketplace_shipping_options` table. `MarketplaceShippingOptionService`. `ShippingOptionsManager` (seller CRUD), `ShippingSelector` (buyer choice). Per-seller courier config. |
| MKT32 | AI Auto-Reply for Sellers | ✅ Done | `MarketplaceAiService::generateAutoReply()`. `AiReplySuggestion` component with regenerate/copy/use actions. |

### Phase 5 — NEXUS Differentiators (Complete)

Features unique to NEXUS — no competitor has these because they require a community/timebanking platform.

| # | Item | Status | Notes |
|---|------|--------|-------|
| MKT33 | Community-Endorsed Sellers | ✅ Done | `is_community_endorsed` + `community_trust_score` on seller profiles. NexusScore integration. `CommunityTrustBadge` component. |
| MKT35 | Collaborative Shopping | ✅ Done | Public/shared collections via `marketplace_collections`. Share with friends via collection links. |
| MKT36 | Time Credit + Cash Hybrid pricing | ✅ Done | `time_credit_price` column. `HybridPriceDisplay` component ("€30 + 2 TC"). `PriceBadge` handles hybrid display. Config: `allow_hybrid_pricing`. |
| MKT37 | Community Group Marketplaces | ✅ Done | `MarketplaceGroupService` + `MarketplaceGroupController`. `GroupMarketplaceTab` component. Listings scoped to group members. |
| MKT38 | Skill-Verified Service Listings | ✅ Done | Seller profiles link to timebanking skill history via `community_trust_score`. |
| MKT39 | Community Delivery | ✅ Done | `marketplace_delivery_offers` table. `MarketplaceCommunityDeliveryService` with time credit transfers via `WalletService`. `CommunityDeliveryCard` component. Config: `allow_community_delivery`. |
| MKT40 | Community-Governed Moderation | ✅ Done | Group marketplace tab allows group admins to manage listings from their group members. |

### Long-Term — Market Expansion (Future)

| # | Item | Status | Notes |
|---|------|--------|-------|
| MKT16 | Eircode integration (Irish market) | 📋 Planned | Eircode API for precise Irish address validation. Auto-fill from Eircode. |
| MKT17 | Real estate vertical module | 💡 Future | 3D virtual tours, automated valuations, floor plans, BER, RTB integration, rent pressure zones. |
| MKT18 | Automotive vertical module | 💡 Future | VIN checks, stolen/lien history, financing calculators, NCT status, make/model structured filters. |
| MKT19 | BNPL integration (Klarna/Afterpay) | 💡 Future | Buy Now Pay Later for high-ticket items. 20-30% conversion uplift. |
| MKT20 | Alternative payment methods per region | 💡 Future | iDEAL (NL), BLIK (PL), Bancontact (BE), COD (Balkans). Stripe Payment Element supports most natively. |
| MKT21 | Performance pricing (pay-per-lead) | 💡 Future | Business sellers pay per qualified lead/application. Lead tracking, qualification scoring, billing. |
| MKT22 | Multi-channel seller sync | 💡 Future | Sync inventory across NEXUS + eBay + Facebook Marketplace. Prevents overselling. |
| MKT23 | API monetization / partner programme | 💡 Future | Public API with tiered access, API key management, usage quotas, developer docs portal. |
| MKT24 | E2E encrypted messaging | 💡 Future | End-to-end encryption for marketplace transactions. Signal protocol or similar. |

### Implementation Summary

| Phase | Status | Deliverables |
|---|---|---|
| **1 — MVP Core** | ✅ Done | 7 tables, 7 models, 4 services, 3 controllers, 9 React pages, 12 components, 18 default categories |
| **2 — Payments & Trust** | ✅ Done | 5 tables, 3 models, 4 services, 2 controllers, 3 React pages (orders + onboarding), 3 components |
| **3 — Discovery** | ✅ Done | 4 tables, 4 models, 2 services, 2 controllers, 2 React pages, 3 components |
| **4 — Shipping & Advanced** | ✅ Done | 2 tables, 2 models, 2 services, 1 controller, 1 React page (map search), 4 components, pro tools (bulk/CSV) |
| **5 — NEXUS Differentiators** | ✅ Done | 1 table, 3 services, 3 controllers, 4 components (group marketplace, community delivery, hybrid pricing, AI reply) |
| **Total** | ✅ Done | 18 tables, 18 models, 14 services, 12 controllers, ~100 endpoints, 14 pages, 26 components, 3 admin pages, 500+ i18n keys, 4 scheduled jobs |

---

## 9. Completed Work

Summary of major completed initiatives. These are kept for historical reference only.

### Laravel Migration (2026-03-19)
- All 223 services native Laravel implementations, zero stubs
- Laravel 12.54 is sole HTTP handler
- Admin view namespace migration complete (TD1)

### Feature Build Sprint (2026-03-01 → 2026-03-02)
- 106 features built across 11 parallel agents covering: Volunteering (10), Jobs (10), Wallet (10), Events (7), Notifications (6), Profiles (6), Feed (4), Listings (5), Search (4), Goals (5), Polls (4), Resources (4), Ideation/Challenges (12), Admin/Reporting (7), Matching (3), Federation (3), Groups (3), Messaging (3)
- Full details preserved in git history (commits around 2026-03-01/02)

### Social Features Sprint (2026-03-23 → 2026-03-24)
- Stories with video, polls, highlights, reactions (SOC1)
- Emoji reactions — 8 types on posts/comments/messages (SOC2)
- Video posts & upload (SOC3)
- Real-time presence — online/away/offline/DND (SOC5)
- Link previews with OG scraping (SOC6)
- Carousel / multi-image posts (SOC7)
- @Mentions with autocomplete (SOC9)
- Content pinning (SOC12)
- User activity status sharing (SOC18)
- Personal impact dashboard with SROI (SOC20)
- DND mode (MED1)

### Explore / "For You" Page (2026-03-24)
- 20 content sections, 6-signal recommendation algorithm (SmartMatchingEngine + CollaborativeFiltering + KNN + embeddings + social graph + contextual timing)
- Unified mixed feed, trending velocity, dismiss with learning, A/B testing framework
- 52 tests passing (27 PHP + 25 Vitest)

### Admin & Infrastructure (various dates)
- Advanced analytics dashboards with Recharts (ADM1)
- Bulk CSV data import (ADM2)
- Notification/newsletter analytics (ADM3)
- API webhooks with HMAC signing (INF2)
- Federation directory (FED2)

### i18n (2026-03-06)
- 7 languages: en, ga, de, fr, it, pt, es
- 33 namespace files per language, ~4,571 keys each
- React i18next + language detector + HTTP backend

### Capacitor Mobile App
- PWA + Capacitor build wrapper
- Native push (FCM), deep links

### Platform Audit (2026-03-23)
- Full codebase inspection: 200+ pages, 1,293 API endpoints, 215 services, 131 models, 386 tables, 19 feature flags
- Competitive gap analysis vs Facebook, Instagram, LinkedIn, Discord, Nextdoor
- Gap findings incorporated into this roadmap (Sections 3-6)

### Security Audits (2026-03-09, 2026-03-27)
- Multi-tenant scoping: 1,377+ properly scoped queries
- SPDX/AGPL compliance: 100% (1,230/1,230 files)
- Critical fixes: Group::findById tenant scoping, AdminContentApi tenant checks, Jobs CSRF bypass

### Broker Approval Workflow (2026-02-07)
- Requested by Matt (Crewkerne Timebank)
- All matches routed through admin approval queue

### Light/Dark Mode (2026-02-07)
- Requested by Matt (Crewkerne Timebank)
- System preference detection, persists across sessions

---

## Contributing

When adding to this roadmap:
1. Use the correct section (Tech Debt, Federation, Social, Media, Admin, Infrastructure)
2. Assign a unique ID prefix (TD, INT, FED, SOC, MED, ADM, INF)
3. Set realistic priority and effort
4. Update the "Last updated" date at the top
