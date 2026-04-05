# Project NEXUS — Roadmap

> **Last updated:** 2026-04-05 (Marketplace module: full implementation plan — completely standalone from Listings)
> **Maintained by:** Jasper Ford
> **Status key:** ✅ Done | ⚠️ Partial | 📋 Planned | 💡 Future

This is the **single, canonical roadmap** for Project NEXUS. All feature planning, technical debt, and strategic initiatives live here.

---

## Table of Contents

1. [Technical Debt & Platform Health](#1-technical-debt--platform-health)
2. [Federation & Internationalization](#2-federation--internationalization)
3. [Social & Engagement Features](#3-social--engagement-features)
4. [Media & Communication](#4-media--communication)
5. [Admin & Reporting](#5-admin--reporting)
6. [Infrastructure & Integrations](#6-infrastructure--integrations)
7. [Marketplace Module (Commercial)](#7-marketplace-module-commercial)
8. [Completed Work](#8-completed-work)

---

## 1. Technical Debt & Platform Health

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

---

## 2. Federation & Internationalization

Features enabling a **global network of timebanks** communicating across languages. Inspired by outreach from the international timebanking community (hOurWorld, Timebanks.org, Care and Share Time Bank).

### Message Translation (Cross-Language Messaging)

| # | Item | Status | Priority | Effort | Notes |
|---|------|--------|----------|--------|-------|
| INT1 | Auto-translate typed messages | 📋 Planned | High | Small | OpenAI API already integrated. Add translate button on message bubbles. Receiver sees message in their preferred language with "View original" toggle. |
| INT2 | Voice message transcription + translation | ✅ Done | — | — | `TranscriptionService` (Whisper API + gpt-4o-mini translation). Transcripts stored on messages, collapsible display in VoiceMessagePlayer, translate button in MessageBubble. |
| INT3 | Federated message translation | 📋 Planned | Medium | Medium | Federation messaging is text-only today. Add translation layer so cross-tenant messages auto-translate. Also add voice/attachment support to federation messages. |
| INT4 | Additional UI languages | ✅ Done | — | — | Now 11 languages: en, ga, de, fr, it, pt, es + Dutch (nl), Polish (pl), Japanese (ja), Arabic (ar with RTL). All 33 namespace files per language. |
| INT5 | Real-time voice-to-voice interpretation | 💡 Future | Low | Very Large | WebRTC live calling + streaming speech-to-text + real-time translation + text-to-speech. Stretch goal — depends on SOC4 (voice/video calling) existing first. |

### Federation Enhancements

| # | Item | Status | Priority | Effort | Notes |
|---|------|--------|----------|--------|-------|
| FED1 | Tenant topic/interest tags | ✅ Done | — | — | 24 predefined topics across 7 categories (Care, Skills, Creative, Home, Health, Community, Services). `federation_topics` + `federation_tenant_topics` tables. Tenants select up to 10 topics (3 primary). Topic filter in directory, topic chips on community cards. |
| FED2 | Federation directory / public tenant listing | ✅ Done | — | — | `FederationDirectoryService` fully implemented. Discoverable timebanks with filtering by search, region, categories. Integrated into federation admin. |

---

## 3. Social & Engagement Features

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
| SOC17 | Marketplace module (commercial) | 📋 Planned | Low | Very Large | Separate commercial marketplace module — see [Marketplace Module Roadmap](#marketplace-module-commercial) below. |
| SOC18 | User activity status sharing | ✅ Done | — | — | `user_presence` table: `custom_status`, `status_emoji`. `StatusSelector` component. `PresenceController` API. |
| SOC19 | Community challenges / competitions | 📋 Planned | Low | Medium (1-2 wk) | Ideation challenges exist (different concept). No team competitions, community-wide progress bars, or competitive leaderboards. |
| SOC20 | Personal impact dashboard | ✅ Done | — | — | `ImpactReportingService` with SROI calculations. `ImpactReportPage` + `ImpactSummaryPage`. Community health metrics, impact timelines. |
| SOC21 | Collaborative documents | 💡 Future | Low | Large (2-3 wk) | No implementation. Needs Tiptap or similar. |
| SOC22 | Community topic channels | ✅ Done | — | — | Group chatrooms with categories, `is_private`, permissions JSON, pinned messages table. Pin/unpin API + UI with lock icons and category chips. |
| SOC23 | Dark mode enhancements & theming | ✅ Done | — | — | `theme_preferences` JSON on users. AppearanceSettings: 10 accent colors, font size (S/M/L), density (compact/comfortable/spacious), high contrast toggle. CSS custom property overrides. |
| SOC24 | Polls in Stories & Events | ✅ Done | — | — | Stories have polls. Events now have `event_id` FK on polls, PollSection on EventDetailPage with voting UI, poll attachment on CreateEventPage. |

---

## 4. Media & Communication

Enhancements to the messaging and media systems.

| # | Item | Status | Priority | Effort | Notes |
|---|------|--------|----------|--------|-------|
| MED1 | Quiet hours / DND mode | ✅ Done | — | — | `PresenceService` supports DND status. `user_presence.status` enum includes `dnd`. DND preserved during heartbeat updates. |
| MED2 | SMS notifications | 📋 Planned | Low | Medium (2-3 wk) | No Twilio/Vonage. Notifications are email + push only. |

---

## 5. Admin & Reporting

| # | Item | Status | Priority | Effort | Notes |
|---|------|--------|----------|--------|-------|
| ADM1 | Advanced analytics dashboards | ✅ Done | — | — | Recharts v3.7.0. 10+ dashboard components: CommunityAnalytics, FederationAnalytics, GamificationAnalytics, GroupAnalytics, MatchingAnalytics, PerformanceDashboard, etc. `AdminAnalyticsService` backend. |
| ADM2 | Bulk CSV data import | ✅ Done | — | — | `AdminUsersController::import()` — CSV upload with per-row validation, import/skip/error counts, audit logging. |
| ADM3 | Notification analytics dashboard | ✅ Done | — | — | `NewsletterAnalytics` component: total_sent, open_rate, click_rate, delivery_rates, monthly breakdown. `NewsletterAnalytics` model. |
| ADM4 | Organization volunteering portal | 📋 Planned | Medium | Large (3-4 wk) | No org-team volunteering system. Organization-level volunteer management, corporate team dashboards, program-level reporting. |

---

## 6. Infrastructure & Integrations

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

## 7. Marketplace Module (Commercial)

A **completely standalone commercial marketplace module** (SOC17) for buying/selling physical goods and paid services — like Facebook Marketplace. This is **entirely separate from the Listings module** (which handles timebanking service exchanges for time credits). The marketplace has its own tables, services, controllers, pages, and Meilisearch index. Zero coupling to listings.

**Feature flag:** `marketplace` (default: `false`) — disabled for most tenants. Enabled per-tenant via admin toggles.

**Source:** Gap analysis benchmarked against 50+ platforms (Facebook Marketplace, eBay, Vinted, DoneDeal, Adverts.ie, OLX, Depop, Nextdoor, Instagram Shopping) — April 2026.

### Architecture Overview

The marketplace module is **completely standalone**:

- **Own tables**: `marketplace_listings`, `marketplace_images`, `marketplace_categories`, `marketplace_saved_listings`, `marketplace_orders`, `marketplace_offers`, `marketplace_payments`, `marketplace_escrow`, `marketplace_seller_profiles`, `marketplace_seller_ratings`, `marketplace_saved_searches`, `marketplace_collections`, `marketplace_collection_items`, `marketplace_category_templates`, `marketplace_promotions`, `marketplace_reports`, `marketplace_shipping_options`, `marketplace_disputes` (17 tables)
- **Own Meilisearch index**: `marketplace_listings` (separate from the `listings` index)
- **Own services**: 10 services (`MarketplaceListingService`, `MarketplaceOfferService`, `MarketplaceOrderService`, `MarketplacePaymentService`, `MarketplaceEscrowService`, `MarketplaceSellerService`, `MarketplaceRatingService`, `MarketplaceDiscoveryService`, `MarketplacePromotionService`, `MarketplaceDisputeService`)
- **Own controllers**: 8 controllers, ~80 API endpoints under `/v2/marketplace/*` and `/v2/admin/marketplace/*`
- **Own React pages**: 12 pages under `/marketplace/*`, 3 admin pages, ~25 components
- **Own models**: 14 Eloquent models, all using `HasTenantScope`
- **Generic platform utilities reused** (NOT listing code): `ImageUploadService`, `GeocodingService`, Meilisearch client, `TenantContext`

### MVP — Table Stakes (Phase 1)

| # | Item | Status | Priority | Effort | Notes |
|---|------|--------|----------|--------|-------|
| MKT1 | Multi-image media pipeline | 📋 Planned | High | Medium (1-2 wk) | Up to 20 images per listing, drag-to-reorder, gallery carousel. Own `marketplace_images` table. Benchmarks: DoneDeal (12), eBay (24), Vinted (20). |
| MKT2 | Template-driven listing model (vertical fields) | 📋 Planned | High | Large (2-3 wk) | Category-specific templates via `marketplace_category_templates` table with JSON field schema. Property: bedrooms/BER/floor area. Vehicles: make/model/mileage. `template_data` JSON column on `marketplace_listings`. |
| MKT3 | Faceted search with dynamic counts | 📋 Planned | High | Medium (1 wk) | Price range slider, date posted, condition, per-category attribute filters. Own Meilisearch `marketplace_listings` index with facets. |
| MKT4 | Self-serve visibility products (paid bumps/boosts) | 📋 Planned | High | Large (2-3 wk) | Stripe checkout: bumps, featured, top-of-category, homepage carousel. `marketplace_promotions` table. Product catalog, durations, rotation, boost ROI analytics. Primary revenue model. |
| MKT5 | Business vs. private seller distinction | 📋 Planned | High | Small (2-3 days) | `seller_type` ENUM on `marketplace_listings`. Clear labeling on cards/detail. CRA 2022 compliance: business sellers show legal name, address, registration. |
| MKT6 | DSA notice-and-action reporting | 📋 Planned | High | Medium (1 wk) | `marketplace_reports` table. User-facing report button with structured reasons. 24h acknowledgement, 7-day review, reasoning, appeal. Transparency counters. EU DSA compliance. |
| MKT7 | Structured data per vertical (JSON-LD) | 📋 Planned | Medium | Small (3-5 days) | Auto JSON-LD per listing: Product/Offer, LocalBusiness, Vehicle. Required for Google rich results. |
| MKT8 | DSA trader traceability (Article 30) | 📋 Planned | Medium | Medium (1 wk) | Business seller verification fields on `marketplace_seller_profiles`: name, address, ID, business registration, VAT, self-certification. Required before business sellers can list. |
| MKT15 | AI-assisted listing creation | 📋 Planned | **High** | Medium (1 wk) | AI auto-generates description, suggests category, auto-categorizes from photos. Uses `AiChatService`. **Priority upgraded** — Facebook, OLX, Nextdoor all shipped GenAI listing creation in 2025-2026. OLX reports 35-55% reduction in posting time. Now table stakes. |
| MKT25 | Make Offer / Negotiation | 📋 Planned | High | Medium (1-2 wk) | `marketplace_offers` table. Structured offer flow with accept/counter/decline. Binding offer lock. Inspired by Depop, eBay. |
| MKT28 | Seller Profile Pages | 📋 Planned | High | Medium (1 wk) | `marketplace_seller_profiles` table. Dedicated seller storefront: listings, ratings, response time, member since, community trust score. |

### Short-Term — Payments & Trust (Phase 2)

| # | Item | Status | Priority | Effort | Notes |
|---|------|--------|----------|--------|-------|
| MKT11 | Escrow / buyer protection | 📋 Planned | **High** | Very Large (3-4 wk) | `marketplace_escrow` + `marketplace_payments` tables. Stripe Connect escrow, dispute resolution, buyer protection fee (5-15% take rate). **Priority upgraded** — Vinted's entire 75M-user business is built on escrow. Single biggest trust accelerator. |
| MKT29 | Mutual Rating System | 📋 Planned | High | Medium (1 wk) | `marketplace_seller_ratings` table. Two-way ratings (buyer rates seller AND seller rates buyer). Builds trust both ways. Inspired by Vinted. |
| — | Stripe Connect onboarding | 📋 Planned | High | Medium (1 wk) | `stripe_account_id` on `marketplace_seller_profiles`. Full Stripe Connect onboarding flow for sellers. |
| — | Order management | 📋 Planned | High | Medium (1-2 wk) | `marketplace_orders` table. Order lifecycle: pending_payment → paid → shipped → delivered → completed → disputed → refunded → cancelled. |
| — | Dispute resolution | 📋 Planned | High | Medium (1 wk) | `marketplace_disputes` table. Open → under_review → resolved. Evidence collection, refund processing. |

### Short-Term — Discovery & Engagement (Phase 3)

| # | Item | Status | Priority | Effort | Notes |
|---|------|--------|----------|--------|-------|
| MKT26 | Saved Searches + Price Alerts | 📋 Planned | High | Medium (1 wk) | `marketplace_saved_searches` table. Email/push when new items match saved search or price drops on watched items. |
| MKT27 | Collections / Wishlists | 📋 Planned | High | Small (3-5 days) | `marketplace_collections` + `marketplace_collection_items` tables. Named collections, public/private, share with others. |
| MKT30 | Free Items / Giveaway | 📋 Planned | Medium | Small (2-3 days) | Dedicated "Free" section (`price_type = 'free'`). 25% of Nextdoor listings are free. Community engagement driver. |
| MKT34 | Feed-Integrated Cards | 📋 Planned | Medium | Small (3-5 days) | Marketplace items appear as cards in the main community feed. Instagram-style content-is-commerce. |

### Medium-Term — Shipping & Advanced (Phase 4)

| # | Item | Status | Priority | Effort | Notes |
|---|------|--------|----------|--------|-------|
| MKT9 | Pro portals / bulk tooling | 📋 Planned | Medium | Large (2-3 wk) | Business accounts, team roles, bulk edit, CSV import/export, listing templates, inventory dashboard. Subscription revenue. |
| MKT10 | Polygon/draw-on-map search | 📋 Planned | Medium | Medium (1-2 wk) | Draw custom polygon on map. Pre-defined area boundaries. Benchmarks: Rightmove, Zoopla, Daft.ie. |
| MKT12 | CCPC prior price rule | 📋 Planned | Medium | Small (2-3 days) | Show lowest price in preceding 30 days on discounted items. EU Omnibus Directive / CCPC compliance. |
| MKT13 | Dark pattern audit | 📋 Planned | Medium | Small (1-2 days) | UI review against DSA dark pattern prohibitions. No fake urgency, no pre-checked consent, no hidden costs. |
| MKT14 | Ranking explainability UI | 📋 Planned | Low | Small (3-5 days) | "Why am I seeing this?" tooltip. For pros: "How to improve ranking." P2B Regulation transparency. |
| MKT31 | Shipping Integration | 📋 Planned | Medium | Large (2-3 wk) | `marketplace_shipping_options` table. Auto-generated shipping labels, courier selection (DPD, An Post, DHL), tracking. Inspired by Vinted, eBay. |
| MKT32 | AI Auto-Reply for Sellers | 📋 Planned | Medium | Small (3-5 days) | AI-suggested responses to common buyer questions. Uses `AiChatService`. Inspired by Facebook Marketplace (March 2026). |

### NEXUS Differentiators (Phase 5)

Features unique to NEXUS — no competitor has these because they require a community/timebanking platform.

| # | Item | Status | Priority | Effort | Notes |
|---|------|--------|----------|--------|-------|
| MKT33 | Community-Endorsed Sellers | 📋 Planned | Medium | Small (3-5 days) | Leverage community trust graph — sellers with high NexusScore get badges on `marketplace_seller_profiles`. |
| MKT35 | Collaborative Shopping | 📋 Planned | Low | Medium (1 wk) | Share items with friends in chat. Shared collections. |
| MKT36 | Time Credit + Cash Hybrid pricing | 📋 Planned | Medium | Medium (1 wk) | `time_credit_price` column. "€50 or €30 + 2 time credits" — bridge timebanking and commercial economy. |
| MKT37 | Community Group Marketplaces | 📋 Planned | Medium | Medium (1 wk) | Groups have their own marketplace tab/section. Group admins moderate marketplace listings in their groups. |
| MKT38 | Skill-Verified Service Listings | 📋 Planned | Low | Small (3-5 days) | Link paid service listings to verified timebanking skill history. |
| MKT39 | Community Delivery | 📋 Planned | Low | Medium (1 wk) | `delivery_method = 'community_delivery'`. Members can deliver items for time credits (last-mile via community). |
| MKT40 | Community-Governed Moderation | 📋 Planned | Low | Small (3-5 days) | Existing group admins moderate marketplace listings in their groups. |

### Long-Term — Market Expansion

| # | Item | Status | Priority | Effort | Notes |
|---|------|--------|----------|--------|-------|
| MKT16 | Eircode integration (Irish market) | 📋 Planned | Low | Small (2-3 days) | Eircode API for precise Irish address validation. Auto-fill from Eircode. |
| MKT17 | Real estate vertical module | 💡 Future | Low | Very Large | 3D virtual tours, automated valuations, floor plans, BER, RTB integration, rent pressure zones. |
| MKT18 | Automotive vertical module | 💡 Future | Low | Very Large | VIN checks, stolen/lien history, financing calculators, NCT status, make/model structured filters. |
| MKT19 | BNPL integration (Klarna/Afterpay) | 💡 Future | Low | Medium (1 wk) | Buy Now Pay Later for high-ticket items. 20-30% conversion uplift. |
| MKT20 | Alternative payment methods per region | 💡 Future | Low | Medium (1 wk) | iDEAL (NL), BLIK (PL), Bancontact (BE), COD (Balkans). Stripe Payment Element supports most natively. |
| MKT21 | Performance pricing (pay-per-lead) | 💡 Future | Low | Large (2-3 wk) | Business sellers pay per qualified lead/application. Lead tracking, qualification scoring, billing. |
| MKT22 | Multi-channel seller sync | 💡 Future | Low | Very Large | Sync inventory across NEXUS + eBay + Facebook Marketplace. Prevents overselling. |
| MKT23 | API monetization / partner programme | 💡 Future | Low | Medium (1-2 wk) | Public API with tiered access, API key management, usage quotas, developer docs portal. |
| MKT24 | E2E encrypted messaging | 💡 Future | Low | Very Large | End-to-end encryption for marketplace transactions. Signal protocol or similar. |

### Database Schema (17 standalone tables)

**No existing tables are modified.** Every table is prefixed with `marketplace_` and tenant-scoped.

**Core tables:**
- `marketplace_listings` — 25+ columns: title, description, price, currency, price_type (fixed/negotiable/free/auction/contact), condition, quantity, location/lat/lng, shipping, delivery_method, seller_type, status, moderation, views/saves/contacts counts, promoted_until, template_data (JSON), expires_at
- `marketplace_images` — image_url, thumbnail_url, alt_text, sort_order, is_primary
- `marketplace_categories` — hierarchical (parent_id), slug, icon, sort_order, template_id
- `marketplace_saved_listings` — user favorites/bookmarks

**Orders & payments:** `marketplace_orders`, `marketplace_offers`, `marketplace_payments`, `marketplace_escrow`

**Seller infrastructure:** `marketplace_seller_profiles` (Stripe Connect, business verification, cached stats), `marketplace_seller_ratings` (mutual buyer/seller ratings)

**Discovery:** `marketplace_saved_searches`, `marketplace_collections`, `marketplace_collection_items`

**Templates & compliance:** `marketplace_category_templates` (JSON field schemas), `marketplace_promotions`, `marketplace_reports` (DSA), `marketplace_shipping_options`, `marketplace_disputes`

### API Endpoints (~80 new, all under `/v2/marketplace/*`)

| Controller | Endpoints | Purpose |
|---|---|---|
| `MarketplaceListingController` | ~25 | CRUD, images, search, nearby, categories, facets, AI description, featured, free items |
| `MarketplaceOfferController` | ~8 | Make/accept/counter/decline offers, sent/received |
| `MarketplaceOrderController` | ~12 | Create, ship, confirm delivery, cancel, dispute, rate, track, refund |
| `MarketplacePaymentController` | ~6 | Stripe intent, confirm, status, webhooks, payouts, balance |
| `MarketplaceSellerController` | ~10 | Profile, onboard, dashboard, shipping options, public storefront |
| `MarketplaceDiscoveryController` | ~10 | Saved searches, collections, recommended |
| `MarketplacePromotionController` | ~4 | Promotion products, purchase, status, analytics |
| `AdminMarketplaceController` | ~10 | Dashboard, moderation, DSA reports, seller management, disputes, transparency |

### React Frontend (12 pages, ~25 components)

**Pages** (all in `react-frontend/src/pages/marketplace/`):

| Page | Route | Description |
|---|---|---|
| `MarketplacePage` | `/marketplace` | Hub — search, browse, categories, featured, nearby (Facebook Marketplace-style grid) |
| `MarketplaceListingPage` | `/marketplace/:id` | Detail — image gallery, seller card, make offer, buy now, shipping |
| `CreateMarketplaceListingPage` | `/marketplace/sell` | Create — multi-image upload, category templates, AI description, pricing |
| `EditMarketplaceListingPage` | `/marketplace/:id/edit` | Edit listing |
| `MarketplaceSearchPage` | `/marketplace/search` | Advanced — faceted filters, price range, condition, map view |
| `SellerProfilePage` | `/marketplace/seller/:id` | Public seller storefront — listings, ratings, stats |
| `SellerDashboardPage` | `/marketplace/dashboard` | Seller dashboard — listings, orders, earnings, analytics |
| `BuyerOrdersPage` | `/marketplace/orders` | Purchase history, tracking, disputes |
| `MarketplaceCollectionsPage` | `/marketplace/collections` | Wishlists/collections |
| `FreeItemsPage` | `/marketplace/free` | Community giveaways |
| `MarketplaceCategoryPage` | `/marketplace/category/:slug` | Category browse with faceted filters |
| `StripeOnboardingPage` | `/marketplace/seller/onboard` | Stripe Connect seller onboarding |

**Admin pages** (in `react-frontend/src/admin/modules/marketplace/`): `MarketplaceAdmin`, `MarketplaceModerationPage`, `MarketplaceSellerAdmin`

**Existing page enhancements** (conditional on `hasFeature('marketplace')`): Navbar mega menu item, feed marketplace cards, profile "Selling" tab, group marketplace tab.

### Feature Gating & Admin Config

**Feature flag:** `marketplace` added to `TenantFeatureConfig::FEATURE_DEFAULTS` (default: `false`), React `TenantFeatures` type, `TenantContext` defaults, admin toggle UIs (`TenantFeatures.tsx`, `TenantForm.tsx`), `moduleRegistry.ts`.

**Granular config options** (19 settings in `moduleRegistry.ts`):
- **Core** (6): enabled, allow_shipping, allow_free_items, allow_business_sellers, allow_hybrid_pricing, allow_community_delivery
- **Payments** (4): stripe_enabled, escrow_enabled, platform_fee_percent, escrow_auto_release_days
- **Moderation** (3): moderation_enabled, dsa_compliance, auto_approve_trusted
- **Promotions** (3): promotions_enabled, bump_price, featured_price
- **Limits** (3): max_images, max_active_listings, listing_duration_days

### Implementation Phases

| Phase | Scope | Effort | Key Deliverables |
|---|---|---|---|
| **1 — MVP Core** | Feature flag, listing CRUD, browse/search, seller profiles, offers | 3-4 wk | 7 tables, 7 models, 3 services, 3 controllers, 6 React pages, ~15 components |
| **2 — Payments & Trust** | Stripe Connect, escrow, orders, mutual ratings, DSA compliance | 2-3 wk | 5 tables, 5 services, 2 controllers, 3 React pages |
| **3 — Discovery** | Saved searches, collections, price alerts, free items, feed integration, promotions | 2 wk | 4 tables, 2 services, 2 controllers, 3 React pages |
| **4 — Shipping & Advanced** | Shipping integration, map search, admin tools, pro seller tools | 2-3 wk | 1 table, shipping API, 3 admin pages |
| **5 — NEXUS Differentiators** | Hybrid pricing, community delivery, group marketplaces, AI auto-reply, federation | 1-2 wk | NEXUS-unique features, i18n, polish |

---

## 8. Completed Work

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
