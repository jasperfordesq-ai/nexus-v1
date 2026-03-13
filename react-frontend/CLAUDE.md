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
