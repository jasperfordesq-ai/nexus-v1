# UI Display Comparison: Next.js vs React Frontend

**Date:** 2026-02-14
**Next.js App:** `C:\xampp\htdocs\nexus-modern-frontend` (.NET backend, dark-only)
**React App:** `c:\xampp\htdocs\staging\react-frontend` (PHP API backend, light/dark/system)

---

## Executive Summary

The React frontend (`app.project-nexus.ie`) is the production UI with 27+ pages, full light/dark theming, WCAG 2.1 AA accessibility, and mobile-optimized UX. The Next.js frontend (`nexus-modern-frontend`) is an earlier prototype with 11 pages, dark-mode only, and fewer accessibility provisions. Both share the same glassmorphic design language (indigo/purple/cyan palette, HeroUI components, Framer Motion animations) but differ significantly in implementation maturity, theming, navigation, and accessibility.

---

## 1. Theme Support

| Feature | Next.js | React |
|---------|---------|-------|
| **Dark mode** | Dark only (`<html className="dark">`) | Dark + Light + System toggle |
| **Theme toggle** | None | Sun/Moon in Navbar + user dropdown |
| **CSS approach** | CSS variables in `globals.css` | Design tokens in `tokens.css` with full light/dark sets |
| **Theme persistence** | N/A | `localStorage` + `PUT /api/v2/users/me/theme` |
| **Meta theme-color** | Not set | Dynamic (`#0a0a0f` dark / `#f8fafc` light) |

**Impact:** The Next.js app is permanently dark. The React app supports three modes and syncs preference to the backend.

---

## 2. Background Effects

| Feature | Next.js | React |
|---------|---------|-------|
| **Background type** | Static radial gradients (CSS `.gradient-background`) | Animated pulsing blobs (CSS `.blob` class) |
| **Animation** | None — fixed gradients | `pulse` keyframe: 4s ease-in-out infinite (scale 1→1.05, opacity 1→0.7) |
| **Blobs** | 3 elliptical gradients at fixed positions | 3 circular blobs: indigo (18rem), purple (24rem, 1s delay), cyan (37.5rem, 2s delay) |
| **Blur amount** | Built into gradient (no filter) | `filter: blur(3rem)` per blob |
| **Light mode opacity** | N/A | Reduced (indigo 8%, purple 6%, cyan 5%) |

---

## 3. Glassmorphism System

| Feature | Next.js | React |
|---------|---------|-------|
| **Implementation** | Inline Tailwind classes per component | Dedicated `glass.css` with `.glass-card`, `.glass-button`, `.glass-card-hover` classes |
| **Base card** | `bg-white/5 backdrop-blur-xl backdrop-saturate-150 border border-white/10 rounded-xl` | `background: var(--glass-bg); backdrop-filter: blur(var(--glass-blur)) saturate(var(--glass-saturate))` |
| **Blur amount** | `backdrop-blur-xl` (16px Tailwind) | `--glass-blur: 16px` (same effective value) |
| **Saturate** | `backdrop-saturate-150` (150%) | `--glass-saturate: 180%` (higher) |
| **Hover glow** | `hover:shadow-[0_0_30px_rgba(99,102,241,0.3)]` (inline) | `.glow-primary { box-shadow: 0 0 20px ..., 0 0 40px ... }` |
| **Light mode glass** | N/A | `--glass-bg: rgba(255,255,255,0.7)` — fully adapted |
| **GlassCard component** | Custom `Glass*` wrappers (GlassCard, GlassButton, GlassInput) | `GlassCard.tsx` with `hoverable`, `glow`, `animated` props + Framer Motion |
| **Active/pressed** | `whileTap={{ scale: 0.98 }}` (Framer) | `transform: scale(0.98)` (CSS) |

---

## 4. Navigation

### 4.1 Navbar

| Feature | Next.js | React |
|---------|---------|-------|
| **Position** | Not fixed (scrolls with page) | `fixed top-0` with `z-50` |
| **Height** | `4rem` (64px) | `h-14 sm:h-16` (56px mobile, 64px desktop) |
| **Background** | `bg-white/5 backdrop-blur-xl border-b border-white/10` | `glass-surface backdrop-blur-xl border-b border-theme-default` |
| **Brand** | `Hexagon` icon + "NEXUS" text gradient | `Hexagon` icon + tenant `branding.name` text gradient |
| **Desktop nav items** | Dashboard, Community dropdown, Explore dropdown, Messages, Connections, Wallet | Dashboard, Feed, Listings, Messages, Community dropdown, More dropdown |
| **Theme toggle** | None | Sun/Moon icon button |
| **Search** | Icon → navigates to `/search` page | `Cmd+K` overlay with live suggestions, type badges, keyboard nav |
| **Create button** | Dropdown (gradient) → New Listing, Event, Group | "New Listing" link button (gradient) |
| **Notifications badge** | Red pill with count (custom HTML) | HeroUI `<Badge>` component with `color="danger"` |
| **Admin link** | Shield icon (amber), role-gated | In More dropdown + Mobile Drawer (admin role only) |
| **Mobile trigger** | `<NavbarMenuToggle />` at `sm:hidden` | Hamburger at `lg:hidden` |

### 4.2 Mobile Navigation

| Feature | Next.js | React |
|---------|---------|-------|
| **Type** | `<NavbarMenu>` (full-screen overlay) | `<Drawer>` (slides from right) |
| **Background** | `bg-black/90 backdrop-blur-xl` | `bg-white dark:bg-gray-900` (solid, theme-aware) |
| **User section** | None — menu items only | Avatar + name + email + quick stats grid (balance, messages, notifications) |
| **Section headers** | `text-xs uppercase tracking-wider text-white/40` | `text-xs font-semibold text-theme-subtle uppercase tracking-wider` |
| **Item animation** | Sequential `x: -20` fade-in (`delay: 0.05 + index * 0.05`) | No per-item animation |
| **Bottom tab bar** | Not present | `<MobileTabBar />` — persistent bottom navigation |

### 4.3 Breadcrumbs

| Feature | Next.js | React |
|---------|---------|-------|
| **Present** | No | Yes — on 8+ detail/create pages |
| **Home icon** | N/A | `w-3.5 h-3.5` Home icon |
| **Separator** | N/A | `ChevronRight w-3 h-3 text-theme-subtle/50` |
| **Tenant-aware** | N/A | Uses `tenantPath()` for links |

---

## 5. Layout Structure

| Feature | Next.js | React |
|---------|---------|-------|
| **Container** | `max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8` | `container mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8` |
| **Main offset** | No `pt-*` (navbar not fixed) | `pt-16` (compensates for fixed navbar) |
| **Skip link** | None | `<a href="#main-content">Skip to main content</a>` (WCAG) |
| **Footer** | Simple 2-column (brand left, links right) | 4-column grid (Brand, Platform, Support, Legal) + bottom bar |
| **OfflineIndicator** | Not present | Fixed banner below navbar (amber offline, emerald reconnected) |
| **BackToTop** | Not present | Floating button at 400px scroll threshold |
| **SessionExpiredModal** | Not present | Auto-popup on token expiry |

---

## 6. Design Tokens & Colors

### 6.1 Base Colors

| Token | Next.js | React (Dark) | React (Light) |
|-------|---------|--------------|---------------|
| Background | `#0a0a0f` | `#0a0a0f` | `#f8fafc` |
| Foreground | `#ededed` | `#ffffff` | `#1e293b` |
| Glass BG | `rgba(255,255,255,0.05)` | `rgba(255,255,255,0.05)` | `rgba(255,255,255,0.7)` |
| Glass border | `rgba(255,255,255,0.1)` | `rgba(255,255,255,0.10)` | `rgba(0,0,0,0.08)` |
| Primary | `#6366f1` (Indigo-500) | `#6366f1` (Indigo-500) | `#4f46e5` (Indigo-600) |
| Secondary | `#a855f7` (Purple-500) | `#a855f7` | `#9333ea` |
| Accent | `#06b6d4` (Cyan-500) | `#06b6d4` | `#0891b2` |

### 6.2 Text Opacity

| Level | Next.js | React (Dark) | React (Light) |
|-------|---------|--------------|---------------|
| Primary | `text-white` (100%) | `#ffffff` (100%) | `#0f172a` |
| Secondary | `text-white/70` (70%) | `rgba(255,255,255,0.8)` (80%) | `#334155` |
| Muted | `text-white/50` (50%) | `rgba(255,255,255,0.6)` (60%) | `#64748b` |
| Subtle | `text-white/40` (40%) | `rgba(255,255,255,0.4)` (40%) | `#7e8ca3` |

**Key difference:** React's secondary text is 80% opacity vs Next.js's 70% — slightly more readable.

### 6.3 Shadows

| Level | Next.js | React (Dark) | React (Light) |
|-------|---------|--------------|---------------|
| sm | Not standardized | `0 2px 8px rgba(0,0,0,0.3)` | `0 2px 8px rgba(0,0,0,0.08)` |
| md | Not standardized | `0 4px 16px rgba(0,0,0,0.4)` | `0 4px 16px rgba(0,0,0,0.12)` |
| lg | Not standardized | `0 8px 32px rgba(0,0,0,0.5)` | `0 8px 32px rgba(0,0,0,0.16)` |
| xl | Not standardized | `0 16px 48px rgba(0,0,0,0.6)` | `0 16px 48px rgba(0,0,0,0.20)` |

React has a standardized shadow scale with light mode variants. Next.js uses ad-hoc Tailwind shadow utilities.

### 6.4 Border Radius

| Element | Next.js | React |
|---------|---------|-------|
| Small | `rounded-lg` (8px) | `--radius-sm: 8px` |
| Cards | `rounded-xl` (12px) | `--radius-md: 12px` |
| Large | `rounded-2xl` (16px) | `--radius-lg: 16px` |
| XL | N/A | `--radius-xl: 24px` |
| Full | `rounded-full` | `--radius-full: 9999px` |

Same effective values, but React uses CSS tokens for consistency.

---

## 7. Typography

| Feature | Next.js | React |
|---------|---------|-------|
| **Font** | Inter via `next/font/google` (variable `--font-inter`) | System stack (`system-ui, -apple-system, sans-serif`) — Inter not loaded |
| **Hero H1** | `text-4xl sm:text-5xl md:text-6xl lg:text-7xl` (36–72px) | `text-3xl sm:text-4xl md:text-5xl lg:text-6xl xl:text-7xl` (30–72px) |
| **Page H1** | `text-3xl font-bold` (30px) | `text-2xl font-bold` (24px) |
| **Section H2** | `text-xl font-semibold` (20px) | `text-2xl sm:text-3xl font-bold` (24–30px) |
| **Card title** | `text-lg font-semibold` (18px) | `text-lg font-semibold` (18px) — same |
| **Body** | `text-base` (16px) | `text-base` (16px) — same |
| **Text gradient** | Same 3-stop gradient (indigo→purple→cyan at 135deg) | Same gradient |
| **Line clamping** | `line-clamp-1`, `line-clamp-2` | `line-clamp-1`, `line-clamp-2` — same |

**Key differences:**
- Next.js loads Inter font explicitly; React relies on system fonts (faster initial load)
- Next.js page titles are larger (30px vs 24px)
- React section headings are larger and bolder (24–30px bold vs 20px semibold)

---

## 8. Animations

| Feature | Next.js | React |
|---------|---------|-------|
| **Framer Motion** | v12.29.3 | Present (same patterns) |
| **Animation library** | Centralized `lib/animations.ts` with named variants | Inline per-component, some shared patterns |
| **Page enter** | `initial={{ opacity: 0, y: 20 }}` → `animate={{ opacity: 1, y: 0 }}` | Same pattern |
| **Stagger delay** | `staggerChildren: 0.1` (100ms) | `staggerChildren: 0.1–0.15` (100–150ms) |
| **Card hover** | `whileHover={{ scale: 1.02 }}, whileTap={{ scale: 0.98 }}` (Framer) | `hover:scale-[1.02] transition-transform` (CSS) |
| **Brand icon** | `whileHover={{ rotate: 180 }}` (0.5s) | `whileHover={{ rotate: 180 }}` (0.5s) — same |
| **Scroll-triggered** | Used on some pages | `whileInView` on HomePage, AboutPage |
| **Background blobs** | Static (no animation) | `animation: pulse 4s ease-in-out infinite` |
| **Loading spinner** | HeroUI `isLoading` prop | `<Loader2>` with `animate={{ rotate: 360 }}` (1s repeat) |
| **2FA slide** | Not present | `AnimatePresence` with x-axis slide transitions |
| **Typing indicator** | Bouncing dots (CSS `animate-bounce` with staggered delays) | Not present in messages |
| **Transition tokens** | Not standardized | `--transition-fast: 150ms`, `--transition-base: 200ms`, `--transition-slow: 300ms` |

---

## 9. Component Patterns

### 9.1 Buttons

| Feature | Next.js | React |
|---------|---------|-------|
| **Primary CTA** | `bg-gradient-to-r from-indigo-500 via-purple-500 to-indigo-600` (3-stop) | `bg-gradient-to-r from-indigo-500 to-purple-600` (2-stop) |
| **CTA shadow** | `shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40` | Same |
| **Glass button** | Via `GlassButton` wrapper component | Via `.glass-button` / `.glass-button-primary` CSS class |
| **Custom spinner** | HeroUI built-in | `<Loader2 className="w-4 h-4 animate-spin" />` |

### 9.2 Inputs

| Feature | Next.js | React |
|---------|---------|-------|
| **Wrapper style** | `bg-white/5 border border-white/10` with hover/focus states | `glass-card` class (same glassmorphism) |
| **Label color** | `text-white/70` | `text-theme-muted` (adapts to theme) |
| **Placeholder** | `text-white/30` | `text-theme-subtle` (adapts to theme) |
| **Focus border** | `border-indigo-500/50` | `var(--border-focus): rgba(99,102,241,0.5)` — same |
| **Height (auth)** | `h-12` (48px) explicit | HeroUI default |

### 9.3 Cards

| Feature | Next.js | React |
|---------|---------|-------|
| **Implementation** | `GlassCard` wrapper around HeroUI `Card` | `GlassCard.tsx` with `glass-card` CSS class |
| **Hover lift** | `whileHover={{ scale: 1.02 }}` (Framer) | `hover:scale-[1.02] transition-transform` (CSS) or `whileHover={{ y: -4 }}` (Framer) |
| **Hover glow** | `hover:shadow-[0_0_30px_rgba(99,102,241,0.15)]` | `.glow-primary` with dual `0 0 20px` + `0 0 40px` shadow |

### 9.4 Avatars

| Feature | Next.js | React |
|---------|---------|-------|
| **Ring** | `ring-2 ring-white/10` or `ring-white/20 hover:ring-indigo-500/50` | `ring-2 ring-transparent hover:ring-indigo-500/50` or `ring-theme-muted/20` |
| **Online indicator** | Green dot (`w-3 h-3 bg-green-500 border-2 border-[#0a0a0f]`) | Not present |
| **Unread badge** | Custom absolute positioned (`bg-indigo-500 rounded-full`) | HeroUI `<Badge>` component |
| **Fallback** | `showFallback` with `User` icon fallback | `showFallback` with name-based initials |
| **Group fallback** | N/A | Gradient background with first letter |

---

## 10. Empty States

| Feature | Next.js | React |
|---------|---------|-------|
| **Icon container** | `w-16 h-16 rounded-full bg-white/5` (circle) | `w-20 h-20 rounded-2xl bg-theme-elevated border border-theme-default` (rounded square) |
| **Title** | `text-xl font-semibold text-white` | `text-xl font-semibold text-theme-primary` |
| **Description** | `text-white/50` | `text-theme-subtle` |
| **Component** | Inline per page | Reusable `EmptyState.tsx` component |
| **Animation** | None | `motion.div` with fade-up |

---

## 11. Loading States

| Feature | Next.js | React |
|---------|---------|-------|
| **Skeleton** | HeroUI `<Skeleton />` component | Custom `.skeleton` CSS class with shimmer keyframe |
| **Shimmer effect** | HeroUI built-in | `@keyframes shimmer { 0%: 200% → 100%: -200% }` (1.5s) |
| **Dark mode skeleton** | Default Skeleton | Custom dark gradient (`gray-700 → gray-600 → gray-700`) |
| **Reduced motion** | Not handled | `@media (prefers-reduced-motion) { animation: none }` |
| **Full page loading** | None | `LoadingScreen.tsx` with rotating `Loader2` on gradient background |

---

## 12. Error States

| Feature | Next.js | React |
|---------|---------|-------|
| **Alert style** | `bg-red-500/10 border border-red-500/20` with `AlertCircle` icon | Same styling with `motion.div` fade-in |
| **Dashboard error** | Simple alert + retry | GlassCard with `AlertTriangle w-12 h-12 text-amber-500` + retry button |
| **Success state** | `bg-emerald-500/20` with `CheckCircle` | Same pattern |
| **Toast system** | Not present | `ToastContext` with toast notifications |

---

## 13. Accessibility (WCAG 2.1 AA)

| Feature | Next.js | React |
|---------|---------|-------|
| **Skip link** | None | Present — hidden, visible on focus, jumps to `#main-content` |
| **Focus indicators** | Browser defaults | Custom `focus-visible` with indigo outline + box-shadow glow |
| **Dark mode focus** | N/A | Lighter indigo (`#818cf8`) with adjusted glow |
| **Touch targets** | Not enforced | `min-width: 44px, min-height: 44px` on all interactive elements |
| **Reduced motion** | Not handled | Full `prefers-reduced-motion` support (disables all animations) |
| **ARIA labels** | Minimal | Extensive — `role`, `aria-label`, `aria-describedby`, `aria-live` |
| **iOS input zoom** | Not handled | `font-size: 16px !important` on mobile inputs |
| **Safe area insets** | Not handled | `env(safe-area-inset-top/bottom)` |
| **Color contrast (subtle text)** | ~3.2:1 (fails AA) | Fixed to 4.52:1 (passes AA) |

---

## 14. Mobile-Specific

| Feature | Next.js | React |
|---------|---------|-------|
| **Bottom tab bar** | None | `<MobileTabBar />` persistent bottom navigation |
| **Touch feedback** | None | `.touch-scale:active { scale(0.97) }`, `.touch-highlight:active` |
| **Tap highlight** | Browser default | `-webkit-tap-highlight-color: transparent` |
| **Safe areas** | Not handled | `env(safe-area-inset-*)` CSS |
| **iOS zoom prevention** | Not handled | `font-size: 16px !important` on inputs at `max-width: 768px` |

---

## 15. Pages Present in Each Frontend

### Next.js Only (11 pages total)
| Page | Route |
|------|-------|
| Dashboard | `/dashboard` |
| Listings | `/listings`, `/listings/[id]` |
| Messages | `/messages` |
| Feed | `/feed` |
| Events | `/events` |
| Members | `/members`, `/members/[id]` |
| Wallet | `/wallet` |
| Groups | `/groups` |
| Search | `/search` |
| Profile | `/profile` |
| AI Chat | `/ai-assistant` |
| Connections | `/connections` |
| Admin | `/admin` |
| Auth | `/login`, `/register` |
| About | `/about` |

### React Only (27+ pages)
All Next.js pages plus:
| Page | Route | Gate |
|------|-------|------|
| Feed | `/feed` | Module: `feed` |
| Blog | `/blog`, `/blog/:slug` | Feature: `blog` |
| Resources | `/resources` | Feature: `resources` |
| Achievements | `/achievements` | Feature: `gamification` |
| Leaderboard | `/leaderboard` | Feature: `gamification` |
| Goals | `/goals` | Feature: `goals` |
| Volunteering | `/volunteering` | Feature: `volunteering` |
| Organisations | `/organisations`, `/organisations/:id` | Feature: `organisations` |
| Exchanges | `/exchanges`, `/exchanges/:id` | Feature: `exchange_workflow` |
| Help Center | `/help` | Public |
| Contact | `/contact` | Public |
| Federation | `/federation/*` | Feature: `federation` |
| Legal Pages | `/terms`, `/privacy`, `/cookies`, `/accessibility` | Public |
| Settings | `/settings` | Protected |
| Notifications | `/notifications` | Protected |

---

## 16. Features Only in Next.js (Candidates for React Port)

| Feature | Location | Effort to Port |
|---------|----------|----------------|
| **Inter font** | `layout.tsx` via `next/font/google` | Low — add `<link>` to `index.html` |
| **Typing indicator** | Messages page — bouncing dots | Low — CSS animation component |
| **Online status dot** | Avatar wrapper — green dot | Low — small CSS component |
| **AI Chat page** | `/ai-assistant` | Medium — needs API integration |
| **Centralized animations** | `lib/animations.ts` | Low — create shared variants file |
| **XP History tab** | Profile page — transaction history | Medium — API endpoint exists |
| **LevelProgress component** | `glass-progress.tsx` — glassmorphism XP bar | Low — pure UI component |
| **Admin dashboard** | Stats grid, tools grid | Medium — already have PHP admin |

---

## 17. Features Only in React (Already Ahead)

| Feature | Why It Matters |
|---------|---------------|
| Full light/dark/system theming | Production-quality UX |
| WCAG 2.1 AA compliance | Legal/accessibility requirement |
| Mobile bottom tab bar | Standard mobile UX pattern |
| Touch feedback CSS | Native-feel mobile interactions |
| Skip-to-content link | Screen reader support |
| `prefers-reduced-motion` | Motion sensitivity support |
| Reusable `EmptyState` component | Consistent empty states |
| `LoadingScreen` component | Branded loading experience |
| Toast notification system | User feedback on actions |
| Session expired modal | Graceful auth expiry handling |
| Offline indicator | PWA network status |
| Back-to-top button | Long page navigation |
| Breadcrumbs | Wayfinding on detail pages |
| `Cmd+K` search overlay | Power-user search UX |
| Feature/module gating | Per-tenant feature control |
| Tenant-aware routing | Multi-tenant URL support |
| 2FA login flow | Two-factor authentication UI |
| Community selector | Multi-tenant login support |

---

## 18. Summary Scorecard

| Category | Next.js | React | Winner |
|----------|---------|-------|--------|
| Theme support | Dark only | Light/Dark/System | **React** |
| Glass CSS system | Inline classes | Tokenized CSS | **React** |
| Background effects | Static | Animated blobs | **React** |
| Navbar (fixed) | No | Yes | **React** |
| Mobile navigation | Full-screen menu | Drawer + bottom tabs | **React** |
| Breadcrumbs | None | 8+ pages | **React** |
| Typography (font) | Inter loaded | System fonts | **Next.js** |
| Typography (scale) | Larger page titles | Larger section headings | Tie |
| Animations (centralized) | `lib/animations.ts` | Inline per-component | **Next.js** |
| Card hover (Framer vs CSS) | Framer Motion | CSS transitions | Tie |
| Avatar online status | Green dot | None | **Next.js** |
| Typing indicator | Bouncing dots | None | **Next.js** |
| Empty states | Inline | Reusable component | **React** |
| Loading (skeleton) | HeroUI built-in | Custom shimmer + reduced-motion | **React** |
| Loading (full page) | None | LoadingScreen | **React** |
| Error handling | Basic alerts | Animated alerts + toast | **React** |
| Accessibility | Minimal | Full WCAG 2.1 AA | **React** |
| Mobile UX | Basic responsive | Tab bar, touch feedback, safe areas | **React** |
| Color contrast | Fails AA (~3.2:1) | Passes AA (4.52:1) | **React** |
| Page count | ~11 | 27+ | **React** |
| Feature gating | None | Per-tenant toggles | **React** |
| Multi-tenant | None | Full slug/domain routing | **React** |

**Overall: React frontend is the production-ready UI.** The Next.js app contributes a few nice UX touches (Inter font, typing indicator, online status, centralized animation variants) that could be ported with low effort.
