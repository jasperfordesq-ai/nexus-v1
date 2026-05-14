# React Frontend — CLAUDE.md

> Stack-specific conventions for `react-frontend/`. See root `CLAUDE.md` for project-wide rules.

## Stack

| Item | Value |
|------|-------|
| **Framework** | React 18 + TypeScript (strict) |
| **Component Library** | HeroUI (`@heroui/react`) |
| **CSS** | Tailwind CSS 4 (`@tailwindcss/vite` plugin) |
| **Icons** | Lucide React (`lucide-react`) |
| **Animation** | Framer Motion |
| **Rich Text** | Lexical editor |
| **Charts** | Recharts |
| **Routing** | React Router v6 (tenant slug support) |
| **Build** | Vite |
| **Tests** | Vitest |

## 🔴 Mandatory Rules

1. **HeroUI components first** — buttons, inputs, modals, cards, tables, dropdowns all come from `@heroui/react`
2. **Tailwind utilities for layout** — spacing, flex, grid, responsive breakpoints
3. **CSS tokens for theme colors** — use `var(--color-surface)`, `var(--color-text)`, etc. from `src/styles/tokens.css`
4. **No inline styles** — use Tailwind classes or CSS tokens
5. **No separate `.css` files per component** — use Tailwind utilities or extend `tokens.css`
6. **Every page uses `usePageTitle()`** — sets `document.title` to "Page - Tenant"
7. **All internal links use `tenantPath()`** — for tenant slug routing
8. **SPDX header on every file** — see root CLAUDE.md

## Styling Examples

```tsx
// CORRECT — HeroUI + Tailwind
import { Button, Card, Input } from "@heroui/react";

<Card className="p-4 gap-3">
  <Input label="Email" variant="bordered" />
  <Button color="primary" className="mt-2">Submit</Button>
</Card>

// CORRECT — Tailwind utilities for layout
<div className="flex items-center gap-4 px-6 py-3">

// CORRECT — CSS tokens for theme-aware colors
<div className="bg-[var(--color-surface)] text-[var(--color-text)]">

// WRONG — inline styles
<div style={{ padding: '16px' }}>

// WRONG — separate CSS component files
```

## Theme System

- `ThemeContext` manages `light`, `dark`, or `system` preference
- CSS tokens in `src/styles/tokens.css` (light/dark custom properties)
- HeroUI dark mode via `@custom-variant dark (&:is(.dark *))` in `index.css`
- Persists to `users.preferred_theme` via `PUT /api/v2/users/me/theme`
- Toggle in Navbar (sun/moon icon)

## CSS Architecture

| File | Purpose |
|------|---------|
| `src/index.css` | Tailwind CSS 4 entry, HeroUI plugin, design token imports |
| `src/hero.ts` | HeroUI Tailwind plugin configuration |
| `src/styles/tokens.css` | CSS custom properties (light/dark themes) |

## Key Files

| File | Purpose |
|------|---------|
| `src/App.tsx` | Routes, providers, feature/module gates |
| `src/lib/api.ts` | API client with token refresh & interceptors |
| `src/types/api.ts` | TypeScript interfaces for API responses |

## Contexts

| Context | File | Purpose |
|---------|------|---------|
| `AuthContext` | `src/contexts/AuthContext.tsx` | Authentication state, login/logout, user data |
| `TenantContext` | `src/contexts/TenantContext.tsx` | Tenant config, `hasFeature()`, `hasModule()` |
| `ToastContext` | `src/contexts/ToastContext.tsx` | Toast notifications (success/error/info) |
| `ThemeContext` | `src/contexts/ThemeContext.tsx` | Light/dark/system mode |
| `NotificationsContext` | `src/contexts/NotificationsContext.tsx` | Real-time notification state & unread counts |
| `PusherContext` | `src/contexts/PusherContext.tsx` | Pusher WebSocket connection |

## Hooks

| Hook | File | Purpose |
|------|------|---------|
| `useApi` | `src/hooks/useApi.ts` | GET requests with loading/error states |
| `usePageTitle` | `src/hooks/usePageTitle.ts` | Sets document title ("Page - Tenant") |
| `useToast` | via ToastContext | `showToast('message', 'success')` |
| `useAuth` | via AuthContext | Current user, `isAuthenticated` |
| `useTenant` | via TenantContext | `hasFeature()`, `hasModule()`, tenant settings |
| `useTheme` | via ThemeContext | `theme`, `setTheme('light'/'dark'/'system')` |
| `useNotifications` | via NotificationsContext | Notification list, unread count, mark-read |
| `useApiErrorHandler` | `src/hooks/useApiErrorHandler.ts` | App-level API error → toast listener |
| `useAppUpdate` | `src/hooks/useAppUpdate.ts` | Capacitor native app version check |
| `useGeolocation` | `src/hooks/useGeolocation.ts` | Browser geolocation with localStorage cache |
| `useLegalGate` | `src/hooks/useLegalGate.ts` | Legal doc acceptance check & `acceptAll()` |
| `usePushNotifications` | `src/hooks/usePushNotifications.ts` | FCM push registration (Capacitor only) |

## Key Components

| Component | File | Purpose |
|-----------|------|---------|
| `Layout` | `src/components/layout/Layout.tsx` | Main wrapper (Navbar + Footer + BackToTop + Offline) |
| `Navbar` | `src/components/layout/Navbar.tsx` | Desktop nav, dropdowns, search overlay (Cmd+K) |
| `MobileDrawer` | `src/components/layout/MobileDrawer.tsx` | Mobile slide-out menu |
| `Footer` | `src/components/layout/Footer.tsx` | Site footer (AGPL attribution required) |
| `FeatureGate` | `src/components/routing/FeatureGate.tsx` | Conditional render by `feature` or `module` |
| `Breadcrumbs` | `src/components/navigation/Breadcrumbs.tsx` | Breadcrumb nav |
| `BackToTop` | `src/components/ui/BackToTop.tsx` | Floating scroll-to-top button |
| `OfflineIndicator` | `src/components/feedback/OfflineIndicator.tsx` | Offline/online banner |
| `TransferModal` | `src/components/wallet/TransferModal.tsx` | Time credit transfer dialog |

## Maps & Location Providers

Three independent per-tenant settings, all configurable in `/admin/tenant-features → "Maps & location"`:

| Setting | Values | Default | Effect |
| --- | --- | --- | --- |
| `maps` (feature flag) | on / off | on | Off ⇒ no map components render anywhere; no Google API key reaches the browser. |
| `map_provider` (general setting) | `google` / `openstreetmap` | `google` | Renderer for interactive maps. |
| `geocoding_provider` (general setting) | `google` / `nominatim` | `google` | Address autocomplete. **Always on regardless of `maps` flag.** |

**Dispatch:**

- `LocationMap` checks `hasFeature('maps')` → `mapProvider` → `<OpenStreetMapView/>` (lazy-loaded Leaflet) or `<GoogleMapsProvider/>`.
- `PlaceAutocompleteInput` checks `geocodingProvider` → `<NominatimAutocomplete/>` or Google Places. The Google branch never mounts on Nominatim tenants — zero billable traffic.

**Defence in depth:** `MapsConfigController` (`/api/v2/config/google-maps`) only returns the Google API key when `maps=on` AND `map_provider=google`. `AdminConfigController::updateSettings` validates provider values against allow-lists.

**Compliance:** OSM tiles via `tile.openstreetmap.org` (subject to OSMF tile policy — fine at low/moderate scale; switch to MapTiler/Stadia for high traffic). Nominatim 1 req/sec policy is honored by the 1s frontend debounce. Required attribution renders automatically.

**Cost playbook:** Switch `geocoding_provider` to `nominatim` first (Places sessions are usually the biggest cost). Then `map_provider`. Kill switch is the emergency cutoff.

Files: [src/components/location/LocationMap.tsx](src/components/location/LocationMap.tsx), [OpenStreetMapView.tsx](src/components/location/OpenStreetMapView.tsx), [PlaceAutocompleteInput.tsx](src/components/location/PlaceAutocompleteInput.tsx), [NominatimAutocomplete.tsx](src/components/location/NominatimAutocomplete.tsx), admin UI in [src/admin/modules/config/TenantFeatures.tsx](src/admin/modules/config/TenantFeatures.tsx).

## Feature & Module Gating

Two gating mechanisms controlled per-tenant:

- **Features** (`tenants.features` JSON): Optional add-ons — `events`, `groups`, `gamification`, `goals`, `blog`, `resources`, `volunteering`, `exchange_workflow`, etc.
- **Modules** (`tenants.configuration.modules` JSON): Core functionality — `listings`, `wallet`, `messages`, `dashboard`, `feed`, etc.

```tsx
const { hasFeature, hasModule } = useTenant();
if (hasFeature('gamification')) { /* show gamification UI */ }
if (hasModule('wallet')) { /* show wallet nav item */ }

// In App.tsx route definitions
<FeatureGate feature="events"><EventsPage /></FeatureGate>
<FeatureGate module="wallet"><WalletPage /></FeatureGate>
```

Admin UI: `/admin/tenant-features` (React admin) — toggle switches for all features & modules per tenant.

## Pages

All pages use `usePageTitle()` and are feature/module gated in `App.tsx`:

| Page | Route | Gate |
|------|-------|------|
| Dashboard | `/dashboard` | Module: `dashboard` |
| Listings | `/listings`, `/listings/:id` | Module: `listings` |
| Create Listing | `/listings/new`, `/listings/:id/edit` | Module: `listings` |
| Messages | `/messages`, `/messages/:id` | Module: `messages` |
| Wallet | `/wallet` | Module: `wallet` |
| Feed | `/feed` | Module: `feed` |
| Events | `/events`, `/events/:id` | Feature: `events` |
| Groups | `/groups`, `/groups/:id` | Feature: `groups` |
| Members | `/members` | — (protected) |
| Profile | `/profile/:id` | — (public) |
| Exchanges | `/exchanges`, `/exchanges/:id` | Feature: `exchange_workflow` |
| Notifications | `/notifications` | — (protected) |
| Settings | `/settings` | — (protected) |
| Search | `/search` | Feature: `search` |
| AI Chat | `/chat` | Feature: `ai_chat` |
| Polls | `/polls`, `/polls/:id` | Feature: `polls` |
| Job Vacancies | `/jobs`, `/jobs/:id`, `/jobs/create` | Feature: `job_vacancies` |
| Ideation | `/ideation`, `/ideation/:id` | Feature: `ideation_challenges` |
| Skills | `/skills` | — (protected) |
| Activity | `/activity` | — (protected) |
| Leaderboard | `/leaderboard` | Feature: `gamification` |
| Achievements | `/achievements` | Feature: `gamification` |
| Goals | `/goals` | Feature: `goals` |
| Volunteering | `/volunteering` | Feature: `volunteering` |
| Blog | `/blog`, `/blog/:slug` | Feature: `blog` |
| Resources | `/resources` | Feature: `resources` |
| Organisations | `/organisations`, `/organisations/:id` | Feature: `organisations` |
| Federation | `/federation/*` | Feature: `federation` |
| Group Exchanges | `/group-exchanges`, `/group-exchanges/:id`, `/group-exchanges/create` | Feature: `group_exchanges` |
| Matches | `/matches` | — (redirect → listings) |
| Newsletter Unsub | `/newsletter/unsubscribe` | — (public) |
| Onboarding | `/onboarding` | — (protected) |
| Help Center | `/help` | — (public) |
| About | `/about` | — (public) |
| Contact | `/contact` | — (public) |
| Home | `/` | — (public) |

## Legal Document System

Per-tenant custom legal documents (Terms, Privacy, Cookies) managed via admin, rendered on frontend.

| File | Purpose |
|------|---------|
| `src/hooks/useLegalDocument.ts` | Fetches custom doc, waits for TenantContext |
| `src/components/legal/CustomLegalDocument.tsx` | Section parser + renderer with TOC |
| `src/pages/public/TermsPage.tsx` | Terms page (custom or default) |
| `src/pages/public/PrivacyPage.tsx` | Privacy page (custom or default) |
| `src/pages/public/CookiesPage.tsx` | Cookies page (custom or default) |
| `src/index.css` | `.legal-content` styles |

**Key details:**
- API response unwrapping uses `'data' in data ? data.data : data` (NOT `data.data ?? data`)
- `useLegalDocument` validates response shape before setting state
- `CustomLegalDocument` detects documents with their own section numbering

## Zod Runtime Validation (Dev Only)

API responses validated against Zod schemas in development mode:

| File | Purpose |
|------|---------|
| `src/lib/api-schemas.ts` | Zod schemas for API responses |
| `src/lib/api-validation.ts` | Dev-only validation helper |

- Dev mode: `console.warn` on schema mismatch (never throws)
- Production: validation code tree-shaken out (zero overhead)

## PWA Update Architecture

**TL;DR:** deploys propagate to users on their next navigation, with no UI prompt. The "Update available" banner exists but is defence-in-depth, not the primary mechanism.

Three layers, in priority order:

### 1. NetworkFirst HTML shell (primary, 99% of cases)

In [vite.config.ts](vite.config.ts) the workbox config does **not** precache `index.html`. Only content-hashed JS/CSS/icons are in `globPatterns`. `navigateFallback: null` disables vite-plugin-pwa's default precache-first NavigationRoute (which would otherwise shadow everything below). A `runtimeCaching` rule with a function `urlPattern` catches all navigation requests and serves them `NetworkFirst` with a 3s timeout. Online → fresh shell on every navigation. Offline → cached fallback.

```ts
runtimeCaching: [{
  urlPattern: ({ request, url }) => {
    if (request.mode !== 'navigate') return false;
    const p = url.pathname;
    if (p.startsWith('/api/')) return false;       // bypass to network
    if (p.startsWith('/admin-legacy/')) return false;
    if (p === '/health.php') return false;
    if (p === '/api/sw-reset') return false;       // recovery URL
    return true;
  },
  handler: 'NetworkFirst',
  options: { cacheName: 'nexus-html-shell', networkTimeoutSeconds: 3, ... },
}]
```

### 2. API stale-client gate (secondary safety net)

Every API response carries `X-Build: <commit-sha>` set by `app/Http/Middleware/SecurityHeaders.php` (sourced from `httpdocs/.build-version` baked by `bluegreen-deploy.sh`). The header is exposed via CORS (`Access-Control-Expose-Headers` in both `EnsureCorsHeaders.php` and `config/cors.php`).

In [src/lib/api.ts](src/lib/api.ts), `checkStaleBuild()` runs on every response from `request()`, `download()`, and `upload()`:

- **Match** → clear the mismatch tracker.
- **First mismatch** → record timestamp in `localStorage` (`nexus_build_mismatch_since`), dispatch the existing `nexus:sw_update_available` event so `UpdateAvailableBanner` fires.
- **Mismatch persists ≥ 10 minutes** → `window.location.replace('/api/sw-reset')`. Forces nuclear recovery via the nginx route that returns `Clear-Site-Data` plus an inline SW unregister + cache wipe script.

The 10-minute grace gives the soft-update path (banner click → SkipWaiting → controllerchange reload) a chance to recover the user gracefully. Only when that path has clearly failed do we eject them.

### 3. Soft update banner (defence-in-depth, rarely seen)

[`UpdateAvailableBanner.tsx`](src/components/feedback/UpdateAvailableBanner.tsx) shows when either the API gate or `useVersionCheck` (`/build-info.json` poll, every 5 min) detects a mismatch. Click handler still does the Android-Chrome dance (disconnect Pusher → postMessage SKIP_WAITING → 8s `controllerchange`-fallback that calls `forceClearAppCaches` + cache-busted reload). With layers 1 and 2 above, the user almost never sees this banner — but if they do, it works.

### Sentry visibility

[src/lib/sentry.ts](src/lib/sentry.ts) tags every event with `build_commit` and `build_time` from `__BUILD_COMMIT__` / `__BUILD_TIME__`. Use Sentry Discover with `tag:build_commit:<sha>` to measure how a stale cohort drains over time after a deploy. `release` is `nexus-react@<commit>`.

### Things to never reintroduce

- HTML in `globPatterns` — every PWA tutorial copies `'**/*.{js,css,html,ico,png,svg,woff2}'` from the vite-plugin-pwa README; that single `'html'` is the original sin that caused six months of staleness incidents.
- `navigateFallback: 'index.html'` — vite-plugin-pwa's default. Silently registers a precache-first NavigationRoute *before* any runtimeCaching rules. Always set to `null` when using NetworkFirst navigation.
- `/clear-site-data` nginx route — older SWs intercepted it and served the precached SPA shell. Useless for actually-stuck users. Use `/api/sw-reset` only (the universal `/^\/api\//` denylist guarantees every SW we've ever shipped passes it through).
- `sw-rescue.js`-style force-eviction shims — not needed when deploys propagate via NetworkFirst.
- Manual "Update to latest version" buttons — users should never need one.

## Prerender Pipeline (bot-only, detached, three-layer freshness)

Prerendered HTML is **served only to SEO crawlers**, never to real users. This keeps snapshot freshness completely separate from user-facing correctness.

### Three-layer freshness (the "big names do this" pattern)

The engine has three independent freshness mechanisms working together — defence in depth so no single failure leaves stale pages live:

1. **Observer hook (millisecond layer).** Eloquent model observers (`PostPrerenderObserver`, `ListingPrerenderObserver`, `EventPrerenderObserver`, `JobVacancyPrerenderObserver`, `GroupPrerenderObserver`, `MarketplaceListingPrerenderObserver`, `MarketplaceCategoryPrerenderObserver`, `VolOpportunityPrerenderObserver`, `IdeationChallengePrerenderObserver`, `PagePrerenderObserver`, `ResourceItemPrerenderObserver`) delete the affected snapshot and enqueue a NORMAL-priority recache on every `saved`/`deleted` event. Wired in `AppServiceProvider::boot()`. To add a new content type, extend `PrerenderInvalidationObserver` and implement `routesFor()` — the base class handles the rest. Failures are swallowed and logged; the model save never blocks.

2. **Sitemap drift detector (minute layer).** `prerender:detect-drift` cron (every 2 min) walks each tenant's sitemap, parses `<lastmod>` values, and compares against snapshot mtimes. Anything stale gets a HIGH-priority recache. This is the safety net for code paths that bypass Eloquent (raw DB writes, queue jobs, migrations, admin tools that use the query builder). Cap: `--max-tenants` × `--max-routes` per pass so a single tick stays bounded.

3. **TTL auto-recache (hour/day floor).** `prerender:auto-recache` cron (every 15–30 min) reads `config/prerender.php`'s per-pattern TTLs (homepage 6h, content indexes 6–24h, individual items 1–7d, static pages 30d) and enqueues LOW-priority recaches for snapshots past their TTL. Backstop for the cases where both observer and drift detector miss (e.g. content that doesn't appear in the sitemap).

External-system hook: `POST /api/v2/admin/prerender/invalidate` with HMAC or Bearer auth lets headless CMS / marketing automation tools invalidate routes without going through the model layer.

### Priority lanes

`prerender_jobs.priority` (1–9, lower wins):
- **3 HIGH** — drift detector, user-initiated force-refresh
- **5 NORMAL** — observer-triggered recache, manual API enqueue, bulk admin UI recache
- **7 LOW** — TTL auto-recache, after-purge auto-recache

Claim order is `(priority, queued_at, id)`. Duplicate-enqueue at a higher priority promotes the existing queued row.

### HTTP status code propagation (Phase 1.2)

The React app emits `<meta name="prerender-status-code" content="404|410|503">` for soft-error pages (community-not-found, deleted listings, maintenance mode). The worker extracts this and:

1. Writes a `_status` sidecar next to `index.html` containing the integer status.
2. Adds an entry to `.prerender-status-overrides.json`.

The bash orchestrator aggregates the JSON into `/etc/nginx/prerender-status-overrides.list`. nginx includes this file inside a `map` block, and the server block uses `error_page` + conditional `return` to serve the snapshot body with the correct HTTP status. The list is validated by `nginx -t` before reload; the prior version is restored on validation failure.

### AI-friendly Markdown variant (Round 5)

After rendering the HTML snapshot, the worker also extracts a clean Markdown body (`index.md` sidecar). nginx's `$nexus_is_ai_bot` map routes GPTBot, ClaudeBot, Perplexity, ByteSpider, Common Crawl, Google-Extended, etc. to the `.md` variant first via `try_files` — falling back to the `.html` if the markdown isn't available. AI crawlers ingest Markdown more token-efficiently than HTML; this puts NEXUS ahead of every competitor that serves raw HTML to LLM bots.

### Sitemap-driven route planning

`prerender-tenants.sh` invokes `php artisan prerender:plan-routes` to get the full per-tenant URL list (static floor + every dynamic URL `SitemapService` publishes — blog posts, listings, events, jobs, KB articles, etc). The hardcoded `PUBLIC_ROUTES` array is the fallback when the PHP container is unavailable. `--no-sitemap` or `NEXUS_PRERENDER_NO_SITEMAP=1` forces fallback mode.

### Crawler analytics (Phase 3.2)

nginx writes a JSONL bot-only access log to the shared prerender volume (`.bot-access.jsonl`). The admin Analytics tab reads it via `crawlerAnalytics()`, surfacing hits by status / crawler / host, top URIs, IP-verification rate, and the spoofed-vs-verified breakdown for major crawlers. IP verification uses `/etc/nginx/prerender-trusted-bot-ips.list` (refreshed weekly by `scripts/refresh-bot-ip-ranges.sh` pulling Google / Bing / DuckDuckGo / Apple feeds).

### Admin UI (`/admin/prerender`)

Six tabs:
- **Overview** — KPIs, freshness controls (auto-recache + drift trigger), wildcard purge form, force-refresh job queue
- **Inventory** — every snapshot with HTTP status, SEO score, age, content/asset flags; bulk-select + bulk-recache; filter by host/route/staleness/status/issue
- **Coverage** — per-tenant expected-vs-rendered matrix with "Refresh all stale" bulk action
- **Jobs** — queued/running/completed job history with realtime updates via Pusher
- **Analytics** — bot crawl activity, top URIs, verified vs spoofed
- **Events** — JSONL deploy event stream
- **Failures** — recent failed paths in the backoff window

### Serving rules ([nginx.bluegreen.conf](nginx.bluegreen.conf))

### Serving rules ([nginx.bluegreen.conf](nginx.bluegreen.conf))

A User-Agent regex map (`$nexus_is_seo_bot`) classifies the request. The composite key `$nexus_is_seo_bot:$arg_nexus_prerender_bypass` then routes:

- **Real user (any UA not matching the bot list):** never sees a snapshot. `try_files` falls through to `/index.html` and the SPA boots normally.
- **Bot, no bypass:** served `/prerendered/$host$uri/index.html` if it exists, otherwise the SPA.
- **Playwright worker (`?nexus_prerender_bypass=1`):** always served the SPA, regardless of UA. Without this, the worker would render snapshots of snapshots.

HTML responses are sent with `Cache-Control: no-store, no-cache, must-revalidate, max-age=0` and `Vary: User-Agent`. **CDN never caches HTML** — bots and users get different bytes for the same URL, so a per-URL CDN cache would poison across user-agents. Immutable assets (`/assets/*.js`/`.css`) remain cached at the edge as before.

### Why bot-only matters

Snapshots reference build-hashed asset URLs (`/assets/index-{HASH}.js`). When a deploy ships new hashes, those references go dead. Bots don't execute JS so they don't care. Users would 404 on the script tags and fail to hydrate — which is why pre-bot-only we had to invalidate every snapshot on every deploy and re-render the entire `(active tenants × 19 routes)` matrix from scratch.

With bot-only serving, `load_stale_cache_paths` in [scripts/prerender-tenants.sh](../scripts/prerender-tenants.sh) is now a no-op. Snapshots survive deploys indefinitely; they only need to be re-rendered when their content (DB-driven) changes.

### Snapshot persistence (shared volume)

In production blue/green ([compose.bluegreen.yml](../compose.bluegreen.yml)), both colors mount the same external named volume `nexus-php-prerendered` at `/usr/share/nginx/html/prerendered`. **Snapshots are shared between colors and persist across deploys.** When the inactive color spins up, it sees the same prerender cache the active color has been using.

Practical consequences:

- Snapshots survive container rebuilds, deploys, and color switches automatically. No copy step is needed at cutover.
- The pre-cutover [warmup phase](../scripts/deploy/phases/warmup-prerender-snapshots.sh) auto-detects the shared mount and skips with `event:"skip","reason":"shared_volume"`. It still works as a fallback in setups without the shared volume (legacy single-color, dev compose).
- Concurrent writes from two prerender runs are prevented by the lock-or-cancel logic, not by isolation.

### Deploy-time behavior

The prerender phase runs **detached from the deploy critical path** ([bluegreen-deploy.sh](../scripts/deploy/bluegreen-deploy.sh)). After traffic switch + Cloudflare purge, the deploy script forks the prerender into a backgrounded subshell and exits. The deploy lock releases immediately; the next deploy is unblocked even if prerender is still running. Prerender logs land in `$LOG_DIR/prerender-detached-{commit}-{ts}.log`.

If a newer deploy starts before the prior prerender finishes, the new deploy's prerender phase **supersedes** the old one (lock-or-cancel in `acquire_lock`):
1. Reads the prior pid from `$LOCK_DIR/pid`.
2. `docker stop nexus-prerender-worker` — kills the Playwright container directly. The container has a stable `--name` so we don't have to discover its ID.
3. SIGTERM → 10s grace → SIGKILL the prior bash.
4. Reclaims the lock and starts fresh.

### Skip-on-clean

[scripts/deploy/phases/prerender-tenants.sh](../scripts/deploy/phases/prerender-tenants.sh) compares HEAD against `.last-successful-prerender`. If `git diff --quiet` reports no changes under `react-frontend/` or `public/`, the prerender is skipped entirely — no Playwright container starts, no lock contention. Override with `PRERENDER_SKIP_ON_CLEAN=0` or `--force-prerender`.

### Manual operations

```bash
# Re-render everything
sudo bash scripts/prerender-tenants.sh --force

# Re-render one tenant
sudo bash scripts/prerender-tenants.sh --tenant hour-timebank

# Re-render specific routes across all tenants
sudo bash scripts/prerender-tenants.sh --routes /about,/blog

# Stop a stuck worker (if cleanup trap missed)
sudo docker stop nexus-prerender-worker
sudo rm -rf /opt/nexus-php/.prerender-lock
```

## Commands

```bash
npm install              # Install dependencies
npm run dev              # Dev server (localhost:5173)
npm run build            # Production build
npm test                 # Run Vitest tests
npm run lint             # TypeScript check (tsc --noEmit)
```

## 🔴 Deployment Warning

**NEVER build locally and upload `dist/` to production!** Local builds use wrong environment variables. Always rebuild on the server. See [docs/DEPLOYMENT.md](../docs/DEPLOYMENT.md).
