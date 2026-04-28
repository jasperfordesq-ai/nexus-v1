# Project NEXUS — Roadmap

> **Last updated:** 2026-04-28 (overnight wave AG20–AG27: federation aggregates IMPLEMENTATION, Future Care Fund + Reciprocal balance, predictive coordinator dashboard, cooperative-to-cooperative hour transfer, GDPR member data export, safeguarding escalation workflow, national KISS dashboard, hour gifting + intergenerational matching emphasis — 8 features shipped, 56 feature tests passing)
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
10. [Strategic Partnerships: Agoris / KISS Caring Communities](#10-strategic-partnerships-agoris--kiss-caring-communities)

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

## 10. Strategic Partnerships: Agoris / KISS Caring Communities

This section preserves the Agoris/KISS research, April 2026 email context, product conclusions, production tenant work, and recommended next moves so the opportunity can be recalled without relying on chat history.

### Source Materials And Session Record

| Source | Status | Notes |
|---|---|---|
| Martin Villiger email, 2026-04-25 | Active opportunity | KISS has strong current political attention in Switzerland. A KISS half-day conference in Zurich included a member of the Swiss national parliament, a Zurich canton government member, and another canton representative discussing the economic value of volunteering and time banking. Martin reports steep social-cost increases from demographic change and says the KISS time-banking model is attracting attention across cantons. He asked for administration access for Roland Greber and Christopher Mueller and asked whether NEXUS can be adapted and extended with additional functionality. |
| `C:\Users\jaspe\Downloads\deep-research-report (1).md` | Read 2026-04-26 | Research frames AGORIS AG as a Swiss "marketplace of trust" / regional civic platform connecting residents, municipalities, organizations, clubs, local businesses, and potential banking/payment/admin integrations. It finds a credible leadership story but limited public proof of execution. |
| `C:\Users\jaspe\Downloads\Researching Agoris' Business and Reputation.md` | Read 2026-04-26 | Strategic report frames Agoris as digital civic infrastructure for Caring Communities: time banking, municipal coordination, local commerce, data sovereignty, AI matching, and regional nodes of roughly 15,000-30,000 residents. Some claims in the report should be treated as strategic analysis rather than verified facts. |
| Production `/agoris` tenant seed | Completed 2026-04-26 | Backed up production DB, downloaded the backup locally, seeded a professional Agoris Caring Community tenant, verified API bootstrap, verified frontend route, then deployed production. |
| Live web research — agoris.ch, LinkedIn, RocketReach, Fondation KISS, caringcommunities.ch, Seniorweb | Completed 2026-04-27 | agoris.ch was returning database errors on the day of research so could not be read directly. Platform vision sourced from LinkedIn company page, RocketReach company description, and Agoris's own tagline material: "Marktplatz des Vertrauens", "Deine Region. Deine Community. Deine App." — connects all generations, regional orgs, businesses and institutions; supports neighbour help WITH and WITHOUT time tracking; combines commercial + voluntary marketplace in one product. KISS model confirmed: Fondation KISS founded 2011 by Susanna Fassbind (Zug), 20 Swiss cooperatives, 320 members in St Gallen alone with 80,000+ banked hours; hours function as "Zeitvorsorge" (4th pension pillar). The caringcommunities.ch national network confirms the Swiss caring-community ecosystem is government-supported and maps hundreds of active projects. Full gap analysis recorded in section below. |

### Agoris/KISS Opportunity Summary

Agoris appears to be a Swiss early-commercial or early-stage civic platform opportunity centered on a trusted regional ecosystem. Its public story is broader than a normal municipal app: it combines caring-community coordination, time banking, local commerce visibility, municipal communication, organizations, resident participation, and future-facing integrations with banking, payments, public administration, data, and AI.

The most important strategic fit for NEXUS is that Agoris/KISS are not asking for a generic timebank. They are asking for a time bank embedded in a wider Caring Community platform. NEXUS already has much of that wider platform: tenants, modules, feature gates, multilingual UI, time-credit wallet, listings, feed, events, groups, volunteering, organizations, resources, polls, goals, gamification, federation, messaging, translation, marketplace, admin analytics, and white-label tenant configuration foundations.

The most important product implication is that any Agoris/KISS work should be built as a switchable module or module cluster. It must behave like other NEXUS modules: tenant-level enable/disable, module configuration, kill switch, and no orphan UI. If the switch is turned off, routes, nav links, dashboard cards, buttons, calls to action, feed cards, admin menu entries, and API feature surfaces must disappear immediately.

### What We Already Have In NEXUS

| Capability | Current Fit | Notes |
|---|---|---|
| Multi-tenant platform | Strong | `/agoris` exists as tenant slug. Tenant bootstrap supports feature/module config. |
| Time-credit exchange | Strong | Wallet, listings, transactions, reviews, service exchange workflow, and tenant currency naming support the core time-banking model. |
| Caring Community primitives | Strong | Feed, groups, events, members, connections, goals, resources, polls, volunteering, organizations, volunteer opportunities, shifts, logs, and impact reporting are already in the product surface. |
| Feature/module kill switches | Strong foundation | Tenant features/modules already hide/show core areas. Agoris-specific work should reuse the same mechanism and harden any remaining stray buttons or links. |
| Multilingual Switzerland readiness | Good | Agoris tenant is configured for `de`, `fr`, `it`, `en`, with German default. Native-speaker review remains a roadmap item. |
| Federation | Strong strategic fit | Supports multi-community networks and could map well to canton, municipality, regional-node, or KISS cooperative federation models. |
| Volunteering and organizations | Strong | Existing module supports organizations, opportunities, shifts, applications, logs, verification, wellbeing, matching, certificates, safeguarding, expenses, and QR check-in configuration. |
| Admin/reporting | Good | Analytics, tenant settings, module toggles, billing, CSV import, and admin tooling exist. Agoris/KISS will need municipal-grade reports and export packs. |
| Marketplace/local commerce | Strong but likely disabled initially | Full standalone commercial marketplace exists with hybrid time-credit/cash pricing and community delivery. Agoris public materials mention local commerce, but KISS immediate need appears caring community/time bank first. |
| AI/translation | Good | AI chat and message translation exist. Useful later for Swiss multilingual support, matching, onboarding, and summaries. |

### What Is Missing Or Needs Hardening

> **Updated 2026-04-27** — items marked ✅ have been resolved; new gaps from live web research added below the original list.

| Gap | Priority | Status |
|---|---|---|
| Agoris/KISS module boundary | Critical | ✅ Done — `caring_community` feature flag, kill switch, admin config page, all entry points gated. |
| Formal kill-switch audit | Critical | ✅ Done — 12 API endpoints return `FEATURE_DISABLED`, admin routes feature-gated at route level, nav/dashboard/quick-create gated, bootstrap test added. |
| KISS time-bank workflow detail | Critical | ✅ Done — hour approval, trusted-reviewer auto-approval, coordinator assignment/escalation, SLA queues, recurring support relationships, member statements, CHF social value, org auto-payment. |
| Municipal/canton reporting | High | ✅ Done — admin `/reports/municipal-impact` with verified hours, active volunteers, SROI, CHF value, categories, date filters, saved templates, CSV/PDF export, audience context. |
| Admin roles for Agoris/KISS | High | ✅ Done — installable KISS role preset pack: national foundation admin, canton admin, municipality admin, cooperative coordinator, organisation coordinator, trusted reviewer. |
| Swiss trust/compliance pack | High | 📋 Pending — FADP/GDPR documentation, in-product consent settings, retention controls, audit log export, data-residency statement. |
| Data sovereignty / regional node story | High | ⚠️ Partial — architecture doc written (`docs/AGORIS_CARING_COMMUNITY_ARCHITECTURE.md`); cross-node aggregate reporting policy and isolated-node deployment option still undefined. |
| Onboarding for older/nontechnical users | High | ✅ Done — low-friction Request Help page, coordinator-assisted member creation (temp password), printable invite codes with member join page (`/join/:code`). |
| Evidence and demo polish | High | ⚠️ Partial — `/agoris` tenant seeded with realistic data; demo script (AG2) not yet written. |
| Local commerce / POS integrations | Medium | 📋 Phase 2 — Agoris has a separate POS & Inventory App Store app. NEXUS marketplace exists but is not bridged to time credits in a single "Markt" view. See AG13. |
| Banking/payment/admin interfaces | Medium | 📋 Phase 3 — Roland Greber's domain. Not day-one. |
| Public proof and diligence | High | ⚠️ Ongoing — diligence question pack (AG3) not yet written; agoris.ch was returning DB errors on 2026-04-27. |

### Live Research Gap Analysis — 2026-04-27

Based on the 2026-04-27 web research session, here is a precise assessment of NEXUS coverage against Agoris's five public platform layers.

**Overall match: ~72%. KISS time-bank layer is production-ready and best-in-class. Gaps are on the regional commerce and municipal infrastructure layers, both of which Agoris describes as part of their full vision.**

#### Layer 1: KISS Time-Banking (Zeitvorsorge)
NEXUS coverage: **95% — stronger than anything Agoris has publicly described.**

The KISS model (Keep It Small and Simple, Fondation KISS, 20 Swiss cooperatives, Zeitvorsorge as 4th pension pillar) requires: hour logging, coordinator review, trusted volunteer model, cooperative-level reporting, and redeemable credits. Every element is implemented. CHF social value estimates and canton-level role presets directly address the political narrative KISS is using with Swiss parliamentary contacts.

#### Layer 2: Voluntary Help Without Time Credits
NEXUS coverage: **30% — significant gap.**

Agoris explicitly supports "Nachbarschaftshilfe mit und ohne Zeitnachweis" (neighbour help with and without time tracking). Simple "pay it forward" help — someone helps a neighbour carry shopping, no credits logged, no wallet transaction — is not a first-class NEXUS flow. The Request Help page (AG10) exists but routes through the caring community module; there is no truly credit-free, low-friction favour-exchange path. See **AG11**.

#### Layer 3: Unified "Marktplatz" (Commercial + Voluntary)
NEXUS coverage: **45% — both sides exist but are siloed.**

Agoris's "Marktplatz des Vertrauens" is a single marketplace combining: local business goods/services (commercial, cash), time-credit skill/service exchange (voluntary, wallet), and cooperative/club offers (mixed). NEXUS has a standalone commercial Marketplace module and a separate time-credit Listings module. They are not combined in a single discoverable view. See **AG12 and AG13**.

#### Layer 4: Regional Community Infrastructure (Clubs, Municipality, Proximity)
NEXUS coverage: **55% — modules exist but regional presentation is missing.**

Swiss civic life requires: Vereine (clubs/associations) as a distinct category, official municipality announcement channels, and "near me" proximity filtering. NEXUS groups approximate Vereine, and the feed can surface official posts, but there is no verified municipality announcer role, no Verein-specific profile/directory, and no radius-based "what's happening near me" view. See **AG14 and AG15**.

#### Layer 5: Modern, User-Friendly, Hides Complexity
NEXUS coverage: **70% — good foundations, UX polish needed for elderly users.**

Agoris's public materials emphasise an app that "delivers individual value without users having to perceive the complexity and scope of the technology." NEXUS has a capable platform but the Caring Community hub, member onboarding, and help-request flows need warmth and simplicity polish targeted at elderly/non-technical Swiss residents. Coordinator-assisted onboarding and invite codes (AG10) address part of this. See **AG16** for the remaining UX pass.

### Agoris Diligence Findings To Preserve

The strongest positive signal is the leadership mix. Publicly named roles align with the opportunity: Roland Greber for banking/innovation and regulated commercial models; Martin Villiger for KISS, time banking, governance, privacy, and caring-community credibility; Dr. Christopher H. Mueller for UX and regional development; Tom Debus for technology, AI, and data. This is unusually well matched to a platform that must blend social infrastructure, municipal trust, local commerce, and technology.

The main concern is public proof of execution. The research did not find enough visible evidence of live customers, public price sheets, quantified case studies, implementation metrics, certification pages, current app-store traction, or a fully verifiable public registry extract. This does not make the opportunity weak, but it means the next stage should be founder-led diligence and document-backed validation rather than assumption-led buildout.

Closest comparables to track: Crossiety, Localcities, My Local Services, Hoplr, nebenan.de, beUnity, and Locality. Crossiety and Localcities are the most important Swiss benchmarks because they already occupy municipal/community communication layers. Hoplr and nebenan.de prove neighborhood networks can scale. beUnity overlaps with trusted member/community spaces.

### Recommended Product Direction

Build toward a **Caring Community module cluster** for NEXUS, not an Agoris-only fork. The module cluster should be reusable for KISS, Swiss cantons, Irish/UK timebanks, public-sector pilots, and international federation partners.

| Module Area | Build Direction |
|---|---|
| Caring Community dashboard | Single home for requests, offers, support hours, trusted organizations, upcoming events, priority needs, and coordinator actions. |
| KISS time-bank workflows | Verified hour logging, approval queues, recurring support relationships, future-care credit story, member statements, and cooperative-level reporting. |
| Municipal/canton console | Dashboards for verified hours, active contributors, care categories, geography, SROI, unmet needs, and exportable reports for public-sector stakeholders. |
| Organization coordination | Organization profiles, programs, volunteer rosters, shifts, training/credentials, approval workflows, safeguarding notes, and coordinator permissions. |
| Trust and safety | Identity/trust badges, verification state, audit trail, privacy controls, moderation queues, and sensitive-care escalation paths. |
| Multilingual Swiss support | German-first tenant, French/Italian/English support, translation glossary for KISS/Agoris terminology, and native-speaker review. |
| Federation and regional nodes | Support canton/municipality/cooperative nodes with opt-in cross-node discovery, shared resources, and controlled data boundaries. |
| Local commerce later phase | Optional marketplace/local-business visibility, regional campaigns, hybrid cash/time-credit offers, loyalty hooks, and POS/API partner exploration. |

### Production Work Completed On 2026-04-26

| Item | Result |
|---|---|
| Development seed | `tenant:seed-agoris-demo agoris` succeeded locally before production work. |
| Production DB backup | Created `/opt/nexus-php/backups/nexus_pre_agoris_seed_20260426_163432.sql.gz` on production. |
| Local backup copy | Downloaded and verified at `C:\platforms\htdocs\staging\backups\nexus_pre_agoris_seed_20260426_163432.sql.gz`. SHA-256 matched the server copy: `E1CD1BB8070295BACFE52C4E2665936FF09141194FEBB8F768937B7649E7E7D7`. |
| Production seed | Seeded tenant `/agoris` as `Agoris Caring Community`, German default, supported languages `de`, `fr`, `it`, `en`, teal branding, caring-community modules on, unrelated modules like blog/jobs/ideation/marketplace off. |
| Seeded production counts | Users 10, listings 27, organizations 4, opportunities 5, volunteer logs 15, transactions 6, feed posts 4, events 4, groups 3, resources 4, goals 3, polls 1. |
| Production deploy | Full deploy started 2026-04-26 with `safe-deploy.sh full --detach`. Containers rebuilt, migrations up to date, maintenance mode turned off, Cloudflare purged for all domains, smoke tests passed. Per-tenant pre-render continued after the site was live. |
| Smoke checks | API health returned healthy with database/Redis connected. `https://api.project-nexus.ie/api/v2/tenant/bootstrap?slug=agoris` returned the expected Agoris tenant config. `https://app.project-nexus.ie/agoris` returned HTTP 200. |
| Deploy warning to investigate | `php artisan storage:link` logged `symlink(): Read-only file system` during cache rebuild. Deploy continued and smoke tests passed. Decide whether storage symlink should be prebuilt into image, mounted writable, skipped when already present, or made read-only-safe. |
| Initial module build | `caring_community` feature flag added, admin module switch registered, React tenant typing updated, public `/caring-community` hub page added, Community nav entry added, and mobile quick-create action added. All new entry points are hidden when the feature switch is off. |
| Module completion pass | Dashboard Caring Community card and quick action added, admin Caring Community configuration detail page added, admin module registry linked to the detail page, admin sidebar entries gated, municipal/KISS reporting pack surface added, and route/nav smoke coverage expanded. Frontend build passed. |
| Municipal report data pass | Added tenant-scoped municipal impact report service, admin API endpoint, CSV/PDF export type, real reporting metrics on the municipal report page, category/trend sections, and export buttons. Route cache was cleared locally to verify the new route. |
| KISS workflow console | Added admin workflow endpoint and page for KISS-style operations: trusted hour review queue, pending/approved/overdue metrics, coordinator signals, operating stages, and Agoris/KISS role presets. Linked from Caring Community config and admin navigation. |
| KISS role pack | Added idempotent installer for national foundation admin, canton admin, municipality admin, cooperative coordinator, organisation coordinator, and trusted reviewer roles. The installer binds each preset to caring-community, volunteering, reporting, onboarding, safeguarding, and federation permissions through the existing RBAC tables, and the workflow console now shows installed status. |
| Workflow policy controls | Added tenant-scoped KISS workflow policy settings in `tenant_settings` for hour approval, review and escalation SLAs, member self logging, partner-organisation requirement, statement day, report period, social-value inclusion, and CHF hourly value. The workflow console now edits and saves these defaults. |
| Workflow policy enforcement pass | KISS review SLA and escalation SLA now drive trusted-review queue counts, row chips, and waiting-age labels. Municipal impact reports now use the policy default reporting period, policy CHF hour value fallback, and social-value inclusion switch when explicit report filters/config are absent. |
| Hour logging policy pass | Volunteering hour submission now honours the Caring Community workflow policy for approval-required, trusted-reviewer auto-approval, and member self-logging. Approved-on-submit logs return `approved` to the API instead of always reporting `pending`. |
| Saved municipal report templates | Added tenant-scoped municipal/KISS report templates with API storage, admin UI, audience/period/social-value/hour-value assumptions, template-aware report preview, and template-aware CSV/PDF export links. |
| Workflow assignment and escalation | Pending KISS hour reviews can now be assigned to coordinators and manually escalated. The workflow console shows assigned coordinator, escalation state, and uses tenant-scoped API actions backed by `vol_logs` assignment/escalation fields. |
| Municipal proof-pack polish | Re-checked the module against the Agoris research briefs. Added explicit report date filters, audience context, procurement-readiness signals, and a stronger municipal/KISS PDF export narrative so the module better addresses the research gap around public proof, municipal value, participation evidence, and partner-network credibility. |
| Kill-switch API hardening | Municipal impact export affordances now honour the `caring_community` switch: export types hide `municipal_impact` when disabled, and direct CSV/PDF export attempts return `FEATURE_DISABLED`. Added backend regression coverage. |
| Regional-node architecture note | Added `docs/AGORIS_CARING_COMMUNITY_ARCHITECTURE.md` to preserve the Agoris/KISS module boundary, tenant/node mapping, data-sovereignty rules, reporting model, and next build priorities. |
| Caring Community route smoke coverage | Added route-level regression coverage for the admin Caring Community workflow and role endpoints. Workflow, policy update, review assignment, review escalation, role preset status, and role preset install all return `FEATURE_DISABLED` when the module switch is off. |
| Auto-approved hour payment parity | Auto-approved KISS/partner hour logs now trigger organisation wallet auto-payment when the partner has auto-pay enabled. Funded organisations debit their wallet, credit the member by floored hours, and write both organisation and member wallet audit entries; underfunded organisations leave the log approved without moving balances. |
| KISS member statement API | Added a Caring Community member statement service/API behind the module switch. Statements combine member identity, policy statement day, approved/pending support hours, partner organisation totals, wallet earned/spent movements, current balance, and CHF social-value estimate, with JSON and CSV payloads. |
| KISS member statement UI | Added member statement preview/export affordances to the KISS Workflow Console. Coordinators can enter member ID and date range, preview approved/pending hours, wallet earned/spent movement, CHF social value, partner totals, and export the same statement as CSV. |
| Recurring support relationship API | Added tenant-scoped `caring_support_relationships` migration and admin APIs behind the Caring Community switch. Coordinators can create, list, and update ongoing support relationships between supporter and recipient members with partner organisation, category, frequency, expected hours, check-in dates, and lifecycle status. |
| Recurring support relationship UI | Added KISS Workflow Console controls for recurring support relationships. Coordinators can view active/paused/check-in statistics, create supporter-recipient relationships by member ID, set frequency/expected hours/start date, and pause or resume relationships. |
| Relationship-linked hour logging | Added `vol_logs` relationship linkage for recurring support relationships, made partner organisation optional for this path, and added a switch-gated admin endpoint/UI so coordinators can log verified recurring support hours directly from the KISS Workflow Console. Logs capture supporter, recipient, relationship, date, hours, status, and check-in rollover, with organisation auto-pay still available when an organisation backs the relationship. |
| KISS review decisions | Added approve/decline actions to the KISS Workflow Console review queue. Decisions are switch-gated, tenant-scoped, remove rows from the pending queue, refresh workflow metrics, support person-to-person relationship logs without organisations, and preserve organisation wallet auto-pay when a pending support log is backed by an auto-pay organisation. |
| Relationship member lookup polish | Replaced raw supporter/recipient ID entry in the KISS Workflow Console with translated searchable member pickers using the tenant-scoped admin member lookup. Relationship creation now stores the selected member IDs while showing coordinators names, email addresses, avatars, clear controls, and no-result feedback. |
| Admin route feature-gate hardening (2026-04-27) | `FeatureGatedElement` wrapper added to `admin/routes.tsx` — both `/admin/caring-community` and `/admin/caring-community/workflow` now redirect to admin 404 when feature is off, complementing the existing API-level guards. `test_caring_community_feature_disabled_reflects_in_tenant_config` added verifying bootstrap endpoint reflects toggle state with Redis cache flush. |
| Member-facing support relationships — AG4 (2026-04-27) | New `MySupportRelationshipsPage.tsx` at `/caring-community/my-relationships`. Shows supporter/recipient role badge, partner name and avatar, active/paused status, overdue check-in highlight, and recent hours timeline. Backed by `GET /api/v2/caring-community/my-relationships` (auth + feature gated, windowed query for bulk log load). Route and hub card added. Translation keys propagated to all 11 languages. |
| Low-friction help request — AG10 part 1 (2026-04-27) | `RequestHelpPage.tsx` at `/caring-community/request-help` with warm form (what/when/contact preference), `caring_help_requests` migration, `POST /api/v2/caring-community/request-help` endpoint. Hub page entry point added. |
| Coordinator-assisted onboarding — AG10 part 2 (2026-04-27) | "Assisted Onboarding" card in KISS Workflow Console. `POST /api/v2/admin/caring-community/assisted-onboarding` creates member account with generated temp password for coordinator to share. Duplicate-email guard, activity log, welcome email (skipped for placeholder domains). |
| Printable invite codes — AG10 part 3 (2026-04-27) | `caring_invite_codes` migration, `CaringInviteCodeService` with collision-safe 6-char codes (omits 0/O/1/I). Admin: generate + list codes in KISS Workflow Console with copy-URL and print-card actions. Print card: large code box, tenant name, full invite URL, coordinator instruction (print-optimised). Public: `GET /api/v2/caring-community/invite/:code` (no auth, throttled) returns valid/expired/used/invalid without revealing whether code exists. Member: `InviteRedemptionPage.tsx` at `/join/:code` — warm welcome → register CTA, or expired/used/invalid message. Translation keys propagated to all 11 languages. |

### Immediate Next Actions

| # | Item | Priority | Notes |
|---|---|---|---|
| AG1 | Provide admin access to Roland Greber and Christopher Mueller | High | User-facing request from Martin. Use real emails only after Jasper confirms addresses. Grant least-privilege admin/coordinator access appropriate for evaluation. |
| AG2 | Create Agoris/KISS demo script and walkthrough | High | 20-30 minute flow: dashboard, member onboarding, request/offer exchange, verified hours, organization opportunity, event, report, module kill switch. |
| AG3 | Write diligence question pack | High | Registry/UID, live deployments, pricing, KPIs, security/privacy docs, hosting, integrations, roadmap, data ownership, procurement expectations. |
| AG4 | Build formal Caring Community module profile | Critical | ✅ Complete (2026-04-27): Member-facing support relationships page (`MySupportRelationshipsPage.tsx`) added at `/caring-community/my-relationships` — shows role (supporter/recipient), partner info, check-in status with overdue highlight, and recent hours timeline. `GET /api/v2/caring-community/my-relationships` endpoint scoped, feature-gated, schema-guarded. Admin routes now feature-gated at route level (`FeatureGatedElement` in `admin/routes.tsx`) in addition to API guards. |
| AG5 | Add kill-switch regression tests | Critical | ✅ Complete (2026-04-27): `test_caring_community_feature_disabled_reflects_in_tenant_config` added to `AdminCaringCommunityControllerTest` — verifies bootstrap endpoint reflects toggle state (with Redis cache flush). All 12 admin endpoints tested for 403 when feature off, all nav/route/dashboard/export affordances covered. |
| AG6 | Create municipal impact report exports | High | ✅ Complete (2026-04-27): `MunicipalImpactReportService` now produces three audience-specific narrative variants (canton / municipality / cooperative) with audience-specific extra fields. Canton: aggregate municipalities count, multi-node hours, est. cost avoidance with 1.5x professional-care multiplier, YoY change. Municipality: top 12 partner orgs with hours, recipients reached, geographic distribution. Cooperative: member retention rate, reciprocity rate, tandem count, coordinator load avg, future-care credit pool. PDF template polished with header band + two-column metric summary + audience narrative section. |
| AG7 | Harden Swiss localization | Medium | ✅ Complete (2026-04-27) — native German pass: ~160 strings hand-translated using KISS-canonical terminology (Zeitvorsorge, Sorgende Gemeinschaft, Unterstützungsbeziehung, Vertrauensperson, Gefälligkeit), du-form for community warmth, gender-neutral colon notation (Unterstützer:in / Empfänger:in / Koordinator:in). Covers caring_community, request_help, offer_favour, my_support_relationships, invite, markt, clubs, proximity, feed.official_badge. Native French and Italian reviews still on the medium-term list. |
| AG8 | Prepare technical architecture response | High | ✅ Complete (2026-04-27) — `docs/AGORIS_MARTIN_RESPONSE_PACK.md` shipped: executive summary, direct answers to Martin's questions, full inventory of what is built, honest 5-layer gap analysis, architecture summary for Roland and Christopher, suggested next steps, risks and open questions, and self-evaluation instructions. Ready to send after Jasper review. |
| AG9 | Define regional-node and data-sovereignty architecture | High | ✅ Complete (2026-04-27) — `docs/AGORIS_CARING_COMMUNITY_ARCHITECTURE.md` extended with two new sections: (1) Cross-Node Aggregate Reporting Policy — what can/cannot be shared, signed federation aggregate JSON contract, canton-rollup process, audit trail (12-month retention), opt-out controls. (2) Isolated-Node Deployment Option — canton-controlled hosting, canton-managed DB, canton-managed backups, opt-in federation via signed API, three-mode deployment summary table (hosted / hosted with custom domain / isolated node). |
| AG10 | Build older-user and coordinator onboarding aids | High | ✅ Complete (2026-04-27): (1) Low-friction "Request Help" form at `/caring-community/request-help`. (2) Coordinator-assisted member creation with temp password in KISS Workflow Console. (3) Printable invite codes — generate, copy URL, print card, member join page at `/join/:code`. All onboarding aids shipped. |

### Gap Items From 2026-04-27 Live Research (AG11–AG16)

> These items address the gaps identified by live research against agoris.ch's public platform vision. They are ordered by demo/deal impact.

| # | Item | Priority | Notes |
|---|---|---|---|
| AG11 | Credit-free "Pay It Forward" help flow | High | Agoris explicitly supports neighbour help without time tracking alongside KISS. Add a simple "Offer a Favour" path that records the act (category, description, beneficiary optional) with no wallet transaction. Coordinator sees it in a separate "informal help" log. No credits issued or required. Completes Layer 2 coverage. |
| AG12 | "Near Me" proximity filter | High | Agoris is a regional platform — "Deine Region" is core to the brand. Add a radius-based filter (500m / 1km / 2km / 5km) to listings, opportunities, and events using lat/lng already stored in the system. Default view on the Caring Community hub should surface what is closest to the member. Completes Layer 4 proximity gap. |
| AG13 | Unified "Marktplatz" page | High | Single `/caring-community/markt` view combining time-credit listings (volunteer skill exchange) and commercial marketplace offers in one discoverable page with a type toggle (All / Time Credits / Goods & Services). Bridges Layer 3 — the two sides of the Agoris marketplace in one place. Requires Marketplace to be enabled; gracefully degrades to time-credit-only if it is off. |
| AG14 | Municipal announcement channel | Medium | Add a `municipality_announcer` role that can post pinned, badged official notices to the community feed. Notices appear with a government/municipality badge and cannot be hidden by members. Admin can designate which user/org has this role. Addresses Layer 4 municipality communication gap. |
| AG15 | Verein (club/association) directory | Medium | Add a `club` sub-type to organisations with its own directory page at `/clubs`. Display name, description, meeting schedule, membership count, and contact. Filterable by category. Swiss civic life runs on Vereine; this is a distinct community object from volunteering organisations. Addresses Layer 4 Vereine gap. |
| AG16 | Caring Community UX warmth pass | Medium | ✅ Complete (2026-04-27) — applied to CaringCommunityPage, RequestHelpPage, MySupportRelationshipsPage, InviteRedemptionPage. Larger headings (`text-2xl`), warm subtitles, friendly loading/error language, taller form fields, increased line-height, gentle welcome banner on hub. |
| AG17 | KISS Tandem matching engine | High | ✅ Complete (2026-04-27) — `CaringTandemMatchingService` scores supporter ↔ recipient pairs by distance (0.30), language overlap (0.25), skill complement (0.20), availability overlap (0.15), interest overlap (0.10). Score ≥ 0.4 threshold, max 3 suggestions per user, 90-day suppression of dismissed pairs. `caring_tandem_suggestion_log` migration tracks created_relationship/dismissed actions. Admin UI: "Tandem Suggestions" card in KISS Workflow Console with score chips, signal chips, [Create Tandem] pre-fills relationship form, [Dismiss] suppresses pair. Existing relationship creation now auto-logs to prevent re-suggesting. |
| AG18 | Closed-loop time-credit ↔ merchant loyalty bridge | Critical | ✅ Complete (2026-04-27) — the closed-loop regional economy that Agoris's public materials describe but does not appear to have shipped. Members earn hours via KISS Caring Community, opt-in marketplace merchants set CHF/hour exchange rate (default 25) and max % per order (default 50%); members redeem at checkout with live discount preview. `caring_loyalty_redemptions` immutable ledger snapshots exchange_rate per row. `marketplace_seller_loyalty_settings` per-merchant opt-in. Six API endpoints (3 member, 3 admin). Admin: per-seller settings + redemption ledger card in KISS Workflow Console. Differentiator: **no visible competitor (Crossiety, Localcities, Hoplr, nebenan.de, beUnity, Locality) has this closed-loop bridge**. |
| AG19 | Realistic Cham/Zug production demo seed | High | ✅ Complete (2026-04-27) — `tenant:seed-agoris-realistic` artisan command (idempotent). 15 Swiss members with German bios, 10 organisations (KISS Genossenschaft Cham as primary partner — same address as Agoris AG, plus Spitex Zug, Pro Senectute, Männerturnverein, Frauenchor, Velo-Club, Quartierverein Lorzenhof, etc.), 8 feed posts (incl. pinned Gemeindekanzlei announcement), 12 time-credit listings, 59 logged hours over 30 days (60% approved / 25% pending / 10% auto-approved / 5% just-logged), 5 active recurring tandems (1 paused), 5 upcoming events incl. KISS Cham Mitgliederversammlung, 3 groups, 3 community goals (1000h target), 4 KB resources. Tenant location set to Cham (47.1758, 8.4622). |
| AG20 | R1+R2 — Federation aggregates endpoint + audit trail (IMPLEMENTATION) | Critical | ✅ Complete (2026-04-28) — closes the documented-but-not-built credibility gap from AG9. `federation_aggregate_consents` (per-tenant opt-in + HMAC secret) and `federation_aggregate_query_log` (12-month audit) migrations. `FederationAggregateService` with compute(), signPayload(), rotateSecret(), pruneOldLogs(). `GET /api/v2/federation/aggregates` (public, throttled 60/min, signed JSON, silent 404 when opted-out). 5 admin endpoints (consent get/put, rotate-secret, audit-log, preview). Daily 02:00 prune scheduled. Privacy: member counts always bucketed (`<50`/`50-200`/`200-1000`/`>1000`), top categories capped at 10, partner orgs by count only never names. 8 feature tests (52 assertions) passing. |
| AG21 | K1+K2 — Future Care Fund (Zeitvorsorge) + Reciprocal Balance | High | ✅ Complete (2026-04-28) — KISS sociological narrative made visible. `FutureCareFundService`: lifetime given/received, reciprocity ratio (capped 0–2.0), CHF estimate via policy rate, by-year breakdown, this-month stats. `GET /api/v2/caring-community/my-future-care-fund`. `FutureCareFundPage` at `/caring-community/future-care-fund`: hero card with banked hours + CHF value, 3 stat cards (Lifetime Given/Received/Active Months), reciprocity bar with friendly framing (strong giver / balanced / strong receiver), year-by-year breakdown, how-it-works CTAs. `ReciprocalBalanceWidget` (compact 320px embeddable). Translations + Swiss German native pass with KISS terminology (Zeitvorsorge-Fonds, Gesparte Stunden, Gegenseitige Bilanz). |
| AG22 | T1+T2 — Predictive coordinator dashboard | High | ✅ Complete (2026-04-28) — Tom Debus AI/Daten pillar. `CaringCommunityForecastService`: closed-form linear regression on 6mo history → 3mo forecast with ±1 sd confidence band, trend label (growing/stable/declining at ±5% mean), confidence bucket from r-squared. Three series: approved hours, distinct active members, distinct recipients reached. `CaringCommunityAlertService`: 7 proactive signals (recipients without tandem, inactive members, overdue reviews, coordinator overload, retention dropping, overdue check-ins, low supply). `GET /api/v2/admin/caring-community/forecast`. KISS Workflow Console "Predictive Insights" card with 3 Recharts ComposedCharts + severity-coloured alert rows. 7 feature tests passing. |
| AG23 | K3 — Cooperative-to-cooperative hour transfer | High | ✅ Complete (2026-04-28) — federation actually does something useful. `caring_hour_transfers` migration. `CaringHourTransferService` with HMAC-SHA256 signature contract, atomic wallet debit/credit, tamper-rejection. Same-platform federation works end-to-end; cross-platform HTTP transport documented as TODO with signature contract in place. 4 admin endpoints + 2 member endpoints. Member UI at `/caring-community/hour-transfer` + admin queue at `/admin/caring-community/hour-transfers`. 7 feature tests passing. Swiss German native pass. |
| AG24 | R3 — GDPR member data export | High | ✅ Complete (2026-04-28) — FADP compliance signal. `MemberDataExportService` (1015 lines): profile + addresses + wallet + vol_logs (given/received) + support_relationships + favours + redemptions + hour_transfers + listings + events + groups + messages metadata + feed posts + reviews + logins + notifications + consents — all tenant-scoped, schema-guarded. `buildJsonArchive()` and `buildZipArchive()` (ZIP includes plain-language regulator-friendly README.md). `member_data_exports` audit table with rate-limit (5/day) + IP/UA tracking. `POST /api/v2/me/data-export`, `GET /api/v2/me/data-export/history`. `DataExportPage` at `/settings/data-export`. 8 feature tests (28 assertions) passing. |
| AG25 | K9 — Safeguarding escalation workflow | High | ✅ Complete (2026-04-28) — vulnerable members deserve formal time-bound auditable escalation. `safeguarding_reports` + `safeguarding_report_actions` migrations. `SafeguardingService`: submit, assign, escalate, changeStatus, addNote, listReports, dashboardSummary, myReports — severity → SLA hours (4/24/72/168). Critical reports fan out immediate notifications to safeguarding reviewers. 7 admin endpoints + 2 member endpoints. Member UI: `SafeguardingReportPage` (calm warm form with confidentiality reassurance), `MySafeguardingReportsPage` (status chips). Admin: full `SafeguardingReportsAdminPage` with filter, assign, escalate, status, notes, history timeline. "Safeguarding Reports" card in KISS Workflow Console with severity-coloured counts and overdue indicator. 9 feature tests passing. |
| AG26 | K4 — National Fondation KISS dashboard | High | ✅ Complete (2026-04-28) — what Martin Villiger himself would use. Cross-tenant comparative dashboard for the `national_foundation_admin` role: all KISS cooperatives at a glance. `tenant_category` column added to tenants table (kiss_cooperative / caring_community / agoris_node / community). `NationalKissDashboardService` with cross-tenant aggregation (member counts bucketed never raw, results cached 1h in Redis). 4 super-admin endpoints requiring `national.kiss_dashboard.view` permission. `NationalKissDashboardPage`: 4-card summary row, 12-month trend chart, comparative table with thriving/stable/struggling status, top/bottom 5 leaderboards, privacy footer. 6 feature tests (68 assertions) passing. |
| AG27 | K5+K8 — Hour gifting + intergenerational matching emphasis | Medium | ✅ Complete (2026-04-28). K5: `caring_hour_gifts` migration. `CaringHourGiftService`: send/accept/decline/revert/inbox/sent — atomic, tenant-scoped, optional 500-char message. 6 endpoints. `HourGiftPage` with Send/Inbox/Sent tabs. K8: `INTERGENERATIONAL_BOOST` (0.10) added to `CaringTandemMatchingService` scoring when age diff ≥ 25 years; new `intergenerationalTandemCount()` metric. Purple "Generationenübergreifend" chip on relationship cards. 11 feature tests passing (6 hour-gift + 5 intergenerational). Swiss German native pass. |
| AG28 | A1 — Regional points (third currency) | High | 📋 **Not yet built** — out of agent quota at 2026-04-28 03:00 (resets 07:00 Europe/Dublin). Planned: tenant-configurable regional points wallet, optional auto-issuance per logged hour, member-to-member transfer toggle, marketplace redemption hook alongside CHF/time credits. 99designs brief lists "money, hours, OR regional points" — third currency type. |
| AG29 | R5+R6 — Verified municipality + SROI methodology transparency | Medium | 📋 **Not yet built** — out of agent quota. Planned: DNS TXT-record domain verification for municipalities, admin attestation flow, official badge on listings/feed/clubs. SROI methodology panel on Municipal Impact Report showing formula, multipliers, reference sources, plain-language caveat. |
| AG30 | V1+V2 — Verein bulk member import + scoped admin role | Medium | 📋 **Not yet built** — out of agent quota. Planned: CSV bulk import with preview/validation/duplicate detection, `verein_admin` role with org-scoped permissions (`scope_organization_id` on `user_roles`), `userHasPermissionInOrg()` RBAC helper. |
| AG31 | T3 — Smart member nudges | Medium | 📋 **Not yet built** — out of agent quota. Planned: `CaringNudgeService` that scores nearby candidates above 0.55 threshold and dispatches notifications when help is needed; member opt-out toggle; daily sweep cron; admin Nudge Analytics page with conversion-rate stats. |

### Suggested Reply Themes For Martin

- Yes, NEXUS can be adapted and extended with additional functionality.
- The right architecture is a switchable Caring Community add-on/module cluster integrated with the rest of the platform. It is already built and production-ready for the KISS time-bank layer.
- Any added buttons, routes, dashboard widgets, admin links, or feature affordances are governed by tenant module configuration and disappear immediately when disabled — demonstrated by the kill-switch tests.
- NEXUS covers ~72% of the full Agoris platform vision: time banking, volunteering, organizations, groups, events, resources, goals, polls, feed, messaging, multilingual support (de/fr/it/en), admin reporting, municipal impact reports with CHF social value, and federation. The KISS time-bank workflow layer is stronger in NEXUS than anything Agoris has publicly described.
- Honest Phase 1 scope: KISS time-bank + Caring Community coordination + municipal reporting + multilingual Switzerland = production-ready today.
- Honest Phase 2 scope: unified "Marktplatz" (commercial + time-credit), proximity/radius filtering, municipal announcement channel, Verein directory, and credit-free informal help (AG11–AG15). These are defined items, not unknowns.
- Phase 3 scope: POS integration, banking/payment interfaces, self-service regional node creation.
- The next step should be a guided evaluation with Roland and Christopher, followed by a focused diligence/product workshop on KISS workflows, municipal reports, data protection, and Swiss deployment expectations.

---

## Contributing

When adding to this roadmap:
1. Use the correct section (Tech Debt, Federation, Social, Media, Admin, Infrastructure)
2. Assign a unique ID prefix (TD, INT, FED, SOC, MED, ADM, INF)
3. Set realistic priority and effort
4. Update the "Last updated" date at the top
