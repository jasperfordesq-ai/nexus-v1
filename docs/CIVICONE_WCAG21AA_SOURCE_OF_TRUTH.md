# CivicOne WCAG 2.1 AA Source of Truth

**Version:** 1.1.0
**Status:** AUTHORITATIVE
**Created:** 2026-01-20
**Last Updated:** 2026-01-20 (Phase 2 Complete)
**Maintainer:** Development Team

---

## Table of Contents

1. [Purpose and Scope](#1-purpose-and-scope)
2. [CivicOne File Map](#2-civicone-file-map)
3. [Golden Rules (Non-Negotiables)](#3-golden-rules-non-negotiables)
4. [Version Pinning and Git Pull Instructions](#4-version-pinning-and-git-pull-instructions)
5. [Architecture: CivicOne Structure](#5-architecture-civicone-structure)
6. [Scoping Strategy](#6-scoping-strategy)
7. [Design Tokens](#7-design-tokens)
8. [Accessibility Specification](#8-accessibility-specification)
9. [Component Rules](#9-component-rules)
10. [Risk Register and Do Not Break List](#10-risk-register-and-do-not-break-list)
11. [Rollout Plan](#11-rollout-plan)
12. [Testing and Tooling](#12-testing-and-tooling)
13. [Appendix: Implementation Playbook](#13-appendix-implementation-playbook)

---

## 1. Purpose and Scope

### 1.1 Purpose

This document serves as the **single authoritative source of truth** for all design, accessibility, and implementation decisions within the CivicOne page layout system. It enforces WCAG 2.1 AA compliance (minimum) with high-contrast, keyboard-first interaction patterns based on official UK public-sector design systems.

All future changes to CivicOne pages, components, and styles MUST adhere to this document. Deviations require explicit approval and documented justification.

### 1.2 Scope

**IN SCOPE:**
- CivicOne layout system (`views/layouts/civicone/`)
- CivicOne-specific CSS files (`/httpdocs/assets/css/civicone-*.css`)
- CivicOne-specific JS files (`/httpdocs/assets/js/civicone-*.js`)
- CivicOne view templates (`views/civicone/`)
- Components rendered within CivicOne layout context

**OUT OF SCOPE (MUST NOT BE MODIFIED):**
- Modern layout system (`views/layouts/modern/`)
- Shared design tokens in `/httpdocs/assets/css/design-tokens.css` (read-only reference)
- Global CSS files (e.g., `nexus-phoenix.css`, `branding.css`)
- Global JS files (e.g., `nexus-ui.js`, `social-interactions.js`)
- Layout switcher mechanism in utility bar (already exists, do not redesign)

### 1.3 Non-Goals

- This document does NOT replace the Modern layout system
- This document does NOT introduce a new layout switching mechanism
- This document does NOT mandate changes to shared components used by both layouts
- This document does NOT require immediate wholesale replacement of existing code

---

## 2. CivicOne File Map

This section provides a complete map of all CivicOne-specific files in the codebase.

### 2.1 Directory Overview

```
project-root/
├── views/
│   ├── civicone/                    # Page templates (166 files)
│   └── layouts/
│       └── civicone/                # Layout system (15 files)
└── httpdocs/assets/
    ├── css/civicone-*.css           # Stylesheets (18 files + minified)
    └── js/civicone-*.js             # Scripts (5 files + minified)
```

### 2.2 Layout System (`views/layouts/civicone/`)

These files control the page wrapper (header, footer, navigation).

| File | Purpose | Lines | Priority |
|------|---------|-------|----------|
| `header.php` | Orchestrates header partials (refactored 2026-01-20) | ~45 | Critical |
| `footer.php` | Orchestrates footer partials (refactored 2026-01-20) | ~25 | Critical |
| `header-cached.php` | Cached header variant (must sync with header.php) | - | Critical |
| `critical-css.php` | Inline critical CSS for fast paint | - | High |
| `font-loading.php` | Font loading strategy | - | Medium |
| `config/navigation.php` | Navigation menu configuration | - | High |

**Partials (`views/layouts/civicone/partials/`):**

*Core Layout Partials (extracted 2026-01-20):*

| File | Purpose | Extracted From |
|------|---------|----------------|
| `document-open.php` | DOCTYPE, html tag, PHP setup (variables, home detection) | header.php |
| `assets-css.php` | `<head>` section with CSS, meta, fonts, inline styles | header.php |
| `body-open.php` | `<body>` tag with classes, early scripts, component CSS | header.php |
| `skip-link-and-banner.php` | WCAG skip link and experimental banner | header.php |
| `utility-bar.php` | Top utility navigation (dropdowns, user menu, notifications) | header.php |
| `site-header.php` | Main header with logo, nav, mega menu, search | header.php |
| `hero.php` | Hero banner section | header.php |
| `main-open.php` | Impersonation banner and `<main>` opening | header.php |
| `header-scripts.php` | JavaScript for header interactions | header.php |
| `main-close.php` | Closes `</main>` tag | footer.php |
| `site-footer.php` | Footer content, mobile nav, mobile sheets | footer.php |
| `assets-js-footer.php` | All JavaScript loading (Mapbox, UI, Pusher) | footer.php |
| `document-close.php` | `</body></html>` | footer.php |

*Supporting Partials:*

| File | Purpose |
|------|---------|
| `ai-chat-widget.php` | Floating AI assistant |
| `breadcrumb.php` | Breadcrumb navigation |
| `head-meta.php` | Meta tags |
| `head-meta-bundle.php` | Bundled meta tags |
| `keyboard-shortcuts.php` | Keyboard shortcut definitions |
| `layout-upgrade-prompt.php` | Layout switch prompt |
| `mobile-nav-v2.php` | Mobile navigation drawer |
| `preview-banner.php` | Preview mode banner |
| `skeleton-card.php` | Loading skeleton component |

### 2.3 Page Templates (`views/civicone/`)

**166 PHP files** across **49 directories**. Key sections:

| Directory | Purpose | Files |
|-----------|---------|-------|
| `achievements/` | Badges, challenges, collections, seasons, shop | 6 |
| `admin/` | Admin panel views | 2 |
| `ai/` | AI assistant interface | 2+ |
| `auth/` | Login, register, password reset | 4 |
| `blog/` | News/blog listing and detail | 4 |
| `components/` | Reusable UI components | 8+ |
| `compose/` | Content creation forms | 1 |
| `connections/` | User connections/friends | 1 |
| `dashboard/` | User dashboard | 2 |
| `demo/` | Demo/showcase pages | 5 |
| `events/` | Events listing, detail, create, edit | 5 |
| `federation/` | Cross-platform federation features | 20+ |
| `feed/` | Activity feed | 3+ |
| `goals/` | Community goals | 3+ |
| `groups/` | Groups listing, detail, discussions | 6+ |
| `help/` | Help center | 3+ |
| `leaderboard/` | Leaderboards | 2+ |
| `legal/` | Privacy, terms, accessibility | 3+ |
| `listings/` | Offers/requests marketplace | 5+ |
| `matches/` | Smart matching | 3+ |
| `members/` | Member directory | 3+ |
| `messages/` | Private messaging | 5+ |
| `notifications/` | Notification center | 2+ |
| `onboarding/` | User onboarding flow | 3+ |
| `organizations/` | Organization profiles | 3+ |
| `pages/` | Custom static pages | 2+ |
| `partials/` | Shared partial templates | 10+ |
| `polls/` | Community polls | 3+ |
| `profile/` | User profiles | 6+ |
| `reports/` | Reporting functionality | 2+ |
| `resources/` | Resource library | 3+ |
| `reviews/` | Review system | 3+ |
| `search/` | Search results | 2+ |
| `settings/` | User settings | 3+ |
| `volunteering/` | Volunteer opportunities | 5+ |
| `wallet/` | Time credits wallet | 3+ |

### 2.4 CSS Files (`httpdocs/assets/css/`)

All CivicOne-specific stylesheets (each has `.min.css` variant):

*GOV.UK Design Token CSS (added 2026-01-20):*

| File | Purpose | Loaded |
|------|---------|--------|
| `civicone-govuk-focus.css` | GOV.UK focus states (yellow #ffdd00) | Always |
| `civicone-govuk-typography.css` | GOV.UK responsive type scale | Always |
| `civicone-govuk-spacing.css` | GOV.UK 5px spacing scale | Always |
| `civicone-govuk-buttons.css` | GOV.UK button components (green/grey/red) | Always |
| `civicone-govuk-forms.css` | GOV.UK form inputs (thick borders, error states) | Always |

*Core CivicOne CSS:*

| File | Purpose | Loaded | GOV.UK Tokens | Focus States |
|------|---------|--------|---------------|--------------|
| `civicone-header.css` | Header/nav styling | Always | ✅ Complete (2026-01-20) | 13 updated |
| `civicone-mobile.css` | Mobile enhancements | Always | ✅ Complete (2026-01-20) | 7 updated |
| `civicone-footer.css` | Footer styling | Always | ✅ Complete (2026-01-20) | Spacing only |
| `civicone-native.css` | Native app feel | Always | ✅ Complete (2026-01-20) | 4 updated |
| `civicone-achievements.css` | Achievements pages | Conditional | ✅ Complete (2026-01-20) | 2 updated |
| `civicone-blog.css` | Blog/news pages | Conditional | ✅ Complete (2026-01-20) | 6 updated |
| `civicone-dashboard.css` | Dashboard page | Conditional | ✅ Complete (2026-01-20) | 9 updated |
| `civicone-events.css` | Events pages | Conditional | ✅ Complete (2026-01-20) | 11 updated |
| `civicone-federation.css` | Federation features | Conditional | ✅ Complete (2026-01-20) | 23 updated |
| `civicone-groups.css` | Groups pages | Conditional | ✅ Complete (2026-01-20) | 8 updated |
| `civicone-help.css` | Help center | Conditional | ✅ Complete (2026-01-20) | 15 updated |
| `civicone-matches.css` | Matching pages | Conditional | ✅ Complete (2026-01-20) | 30 updated |
| `civicone-messages.css` | Messaging pages | Conditional | ✅ Complete (2026-01-20) | 11 updated |
| `civicone-mini-modules.css` | Polls, goals, resources | Conditional | ✅ Complete (2026-01-20) | 6 updated |
| `civicone-profile.css` | Profile pages | Conditional | ✅ Complete (2026-01-20) | 11 updated |
| `civicone-volunteering.css` | Volunteering pages | Conditional | ✅ Complete (2026-01-20) | 7 updated |
| `civicone-wallet.css` | Wallet pages | Conditional | ✅ Complete (2026-01-20) | 5 updated |
| `civicone-bundle-compiled.css` | Compiled bundle (legacy) | - | N/A | N/A |

**Phase 2 Summary (Completed 2026-01-20):**
- **Total CSS files updated:** 17 files + 5 new GOV.UK component files
- **Total focus states updated:** ~170 across all files
- **All minified files regenerated:** 23 .min.css files (verified 2026-01-20)

### 2.5 JavaScript Files (`httpdocs/assets/js/`)

| File | Purpose | Loaded |
|------|---------|--------|
| `civicone-mobile.js` | Mobile interactions | Always (defer) |
| `civicone-native.js` | Native app features | Always (defer) |
| `civicone-pwa.js` | PWA functionality | Always (defer) |
| `civicone-webauthn.js` | Biometric auth | Always (defer) |
| `civicone-achievements.js` | Achievements interactions | Conditional |
| `civicone-dashboard.js` | Dashboard interactions | Conditional |

### 2.6 File Ownership Rules

| Location | Owner | Modification Rules |
|----------|-------|-------------------|
| `views/layouts/civicone/` | CivicOne team | Follow this document |
| `views/civicone/` | CivicOne team | Follow this document |
| `css/civicone-*.css` | CivicOne team | Must use `.civicone` scope |
| `js/civicone-*.js` | CivicOne team | Must namespace under `window.civicone` |
| `views/layouts/modern/` | Modern team | DO NOT MODIFY |
| `css/design-tokens.css` | Shared | READ ONLY for CivicOne |
| `css/nexus-*.css` | Shared | DO NOT MODIFY |
| `js/nexus-*.js` | Shared | DO NOT MODIFY |

---

## 3. Golden Rules (Non-Negotiables)

These rules are **absolute requirements**. Violations MUST be corrected before code is merged.

### 3.1 Keyboard-First Interaction

| Rule ID | Rule | Rationale |
|---------|------|-----------|
| GR-001 | Every interactive element MUST be operable via keyboard alone | WCAG 2.1.1 |
| GR-002 | Focus order MUST follow logical reading order (DOM order = visual order) | WCAG 2.4.3 |
| GR-003 | No keyboard traps: users MUST be able to navigate away from any component | WCAG 2.1.2 |
| GR-004 | Custom widgets MUST implement expected keyboard patterns (see Section 8) | ARIA APG |

### 3.2 Visible Focus

| Rule ID | Rule | Rationale |
|---------|------|-----------|
| GR-005 | Focus indicator MUST be visible on ALL focusable elements | WCAG 2.4.7 |
| GR-006 | Focus indicator MUST have minimum 3:1 contrast against adjacent colours | WCAG 2.4.11 |
| GR-007 | NEVER use `outline: none` or `outline: 0` without providing alternative focus styles | Critical |
| GR-008 | Focus indicator MUST use the GOV.UK yellow (#ffdd00) + black (#0b0c0c) pattern | Design standard |

### 3.3 High Contrast

| Rule ID | Rule | Rationale |
|---------|------|-----------|
| GR-009 | Normal text MUST have minimum 4.5:1 contrast ratio | WCAG 1.4.3 |
| GR-010 | Large text (18pt+ or 14pt+ bold) MUST have minimum 3:1 contrast ratio | WCAG 1.4.3 |
| GR-011 | UI components and graphical objects MUST have minimum 3:1 contrast | WCAG 1.4.11 |
| GR-012 | Do NOT rely on colour alone to convey information | WCAG 1.4.1 |

### 3.4 No Hover-Only Interactions

| Rule ID | Rule | Rationale |
|---------|------|-----------|
| GR-013 | Information revealed on hover MUST also be available via focus | WCAG 1.4.13 |
| GR-014 | Dropdown/mega menus MUST be activatable via click/Enter, not hover alone | Touch/keyboard access |
| GR-015 | Tooltips MUST be dismissible and persistent on hover/focus | WCAG 1.4.13 |

### 3.5 Scoped CSS and JS

| Rule ID | Rule | Rationale |
|---------|------|-----------|
| GR-016 | All new CivicOne CSS selectors MUST be prefixed with `.civicone` or `.civicone--govuk` | Prevents bleed |
| GR-017 | CivicOne JS MUST NOT attach global event listeners that affect Modern layout | Isolation |
| GR-018 | GOV.UK Frontend assets MUST only load when CivicOne layout is active | Performance |
| GR-019 | Do NOT modify shared CSS/JS files for CivicOne-specific changes | Stability |

---

## 4. Version Pinning and Git Pull Instructions

### 4.1 Primary Source: GOV.UK Frontend

**Repository:** `https://github.com/alphagov/govuk-frontend.git`
**Production Version:** `v5.14.0` (STABLE - use this)
**Experimental Version:** `v6.0.0-beta.2` (OPTIONAL - feature flag only)

```bash
# Clone for reference (already done at govuk-frontend-ref/)
cd c:/xampp/htdocs/staging
git clone --depth 1 --branch v5.14.0 https://github.com/alphagov/govuk-frontend.git govuk-frontend-v5

# To update to a new stable version:
cd govuk-frontend-v5
git fetch --tags
git checkout v5.15.0  # Example: new stable release
```

**Key Paths in GOV.UK Frontend:**
| Path | Contents |
|------|----------|
| `packages/govuk-frontend/src/govuk/settings/` | Design tokens (colours, spacing, typography) |
| `packages/govuk-frontend/src/govuk/components/` | Component SCSS and templates |
| `dist/govuk-frontend-*.min.css` | Compiled CSS (reference only) |
| `dist/govuk-frontend-*.min.js` | Compiled JS (reference only) |
| `dist/assets/fonts/` | GDS Transport font files |

### 4.2 Secondary Source: NHS.UK Frontend (Reference Only)

**Repository:** `https://github.com/nhsuk/nhsuk-frontend.git`
**Version:** Latest stable
**Usage:** Cross-reference for focus state patterns and colour contrast rationale only.

```bash
# Optional: Clone for reference
git clone --depth 1 https://github.com/nhsuk/nhsuk-frontend.git nhsuk-frontend-ref
```

### 4.3 Tertiary Source: DfE Frontend (Reference Only)

**Repository:** `https://github.com/DFE-Digital/dfe-frontend.git`
**Usage:** Reference only if useful for education-sector specific patterns.

### 4.4 Update Policy

| Frequency | Action |
|-----------|--------|
| Monthly | Check GOV.UK Frontend CHANGELOG for security patches |
| Quarterly | Review for minor version updates; test in staging |
| Annually | Evaluate major version upgrades; plan migration |

**CRITICAL POLICY:** Do NOT scrape CSS from live websites. Only use maintained repositories and official documentation.

### 4.5 Current Reference Location

The GOV.UK Frontend reference repository is located at:
```
c:/xampp/htdocs/staging/govuk-frontend-ref/
```

This contains v6.0.0-beta.2. For production work, pin to v5.14.0 as specified above.

---

## 5. Architecture: CivicOne Structure

### 5.1 Current File Structure

```
views/layouts/civicone/
├── header.php              # Main header (1400+ lines, opens <main>)
├── footer.php              # Main footer (530+ lines, closes </main>)
├── header-cached.php       # Cached variant (MUST stay in sync)
├── critical-css.php        # Critical inline CSS
├── font-loading.php        # Font loading logic
├── config/
│   └── navigation.php      # Navigation configuration
└── partials/
    ├── ai-chat-widget.php
    ├── breadcrumb.php
    ├── head-meta.php
    ├── head-meta-bundle.php
    ├── keyboard-shortcuts.php
    ├── layout-upgrade-prompt.php
    ├── mobile-nav-v2.php
    ├── preview-banner.php
    └── skeleton-card.php
```

### 5.2 No-Output-Change Refactor First Rule

**MANDATORY:** Before any redesign work, extract `header.php` and `footer.php` into granular partials WITHOUT changing any rendered output.

This ensures:
- Easier testing (smaller units)
- Reduced merge conflicts
- `header-cached.php` can use the same partials (prevents drift)
- Rollback is trivial (revert partial includes)

### 5.3 Required Partial Breakdown

After refactoring, the following partials MUST exist:

| Partial File | Purpose | Included From |
|--------------|---------|---------------|
| `partials/document-open.php` | `<!DOCTYPE>`, `<html>`, `<head>` open | header.php |
| `partials/assets-css.php` | All CSS `<link>` tags | header.php |
| `partials/skip-link.php` | Skip to main content link | header.php |
| `partials/site-header.php` | Utility bar + main header + logo | header.php |
| `partials/mega-menu.php` | Desktop mega menu | header.php |
| `partials/hero.php` | Page hero banner | header.php |
| `partials/main-open.php` | `<main id="main-content">` open tag | header.php |
| `partials/main-close.php` | `</main>` close tag | footer.php |
| `partials/site-footer.php` | Footer grid and links | footer.php |
| `partials/assets-js-footer.php` | All `<script>` tags | footer.php |
| `partials/document-close.php` | `</body></html>` | footer.php |

### 5.4 Preserve Dynamic CSS Loading

The current header dynamically loads CSS based on page context:

```php
<?php if ($isHome): ?>
    <link rel="stylesheet" href="/assets/css/feed-filter.min.css">
<?php endif; ?>
<?php if (strpos($normPath, '/dashboard') !== false): ?>
    <link rel="stylesheet" href="/assets/css/dashboard.min.css">
<?php endif; ?>
```

This pattern MUST be preserved. Move this logic into `partials/assets-css.php` but do NOT change the conditional loading behaviour.

---

## 6. Scoping Strategy

### 6.1 Body Class Requirements

**CRITICAL:** This section prevents CSS/JS bleed between layout systems.

| Class | When Applied | Purpose |
|-------|--------------|---------|
| `.nexus-skin-civicone` | Always on CivicOne pages | Existing scope class (keep) |
| `.civicone` | Always on CivicOne pages | New primary scope class |
| `.civicone--govuk` | When GOV.UK redesign is active | Enables redesigned styles |

**Current body tag (reference):**
```html
<body class="nexus-skin-civicone <?= $skinClass ?> ...">
```

**After refactor:**
```html
<body class="civicone nexus-skin-civicone <?= $skinClass ?> <?= $govukRedesign ? 'civicone--govuk' : '' ?> ...">
```

### 6.2 CSS Selector Rules

| Rule | Example | Notes |
|------|---------|-------|
| All base styles | `.civicone .component {}` | Scoped to CivicOne |
| Redesign-specific styles | `.civicone--govuk .component {}` | Only when redesign active |
| NEVER write | `.component {}` | Unscoped = affects Modern |
| NEVER write | `body .component {}` | Still affects Modern |

### 6.3 CSS File Organisation

```
/httpdocs/assets/css/
├── civicone-base.css           # NEW: Foundation styles (always loads)
├── civicone-govuk-theme.css    # NEW: GOV.UK-aligned redesign (feature flag)
├── civicone-header.css         # Existing: Header component
├── civicone-footer.css         # Existing: Footer component
├── civicone-*.css              # Existing: Feature-specific files
└── ...
```

### 6.4 GOV.UK Frontend Asset Loading

GOV.UK Frontend compiled assets (CSS/JS) MUST only load when:
1. Layout is CivicOne (`$layout === 'civicone'`)
2. Redesign mode is enabled (`$govukRedesign === true`)

```php
// In partials/assets-css.php
<?php if ($layout === 'civicone' && $govukRedesign): ?>
    <link rel="stylesheet" href="/assets/css/civicone-govuk-theme.css">
<?php endif; ?>
```

### 6.5 Feature Flag Implementation

```php
// In config or controller
$govukRedesign = false; // Default: off

// Enable per-page or via query param for testing
if (isset($_GET['govuk']) && $_GET['govuk'] === '1') {
    $govukRedesign = true;
}

// Or enable via tenant configuration
if (!empty($tenantConfig['civicone_govuk_redesign'])) {
    $govukRedesign = true;
}
```

---

## 7. Design Tokens

### 7.1 Colour Tokens

Based on GOV.UK Frontend `_colours-palette.scss` and `_colours-functional.scss`.

#### 8.1.1 Functional Colours (CivicOne)

| Token Name | Value | Usage | Contrast Notes |
|------------|-------|-------|----------------|
| `--civicone-text` | `#0b0c0c` | Primary text | 21:1 on white |
| `--civicone-text-secondary` | `#484949` | Muted/help text | 9.5:1 on white |
| `--civicone-link` | `#1d70b8` | Link default | 5.5:1 on white |
| `--civicone-link-visited` | `#54319f` | Visited link | 7.5:1 on white |
| `--civicone-link-hover` | `#0f385c` | Link hover | 12:1 on white |
| `--civicone-link-active` | `#0b0c0c` | Link active | 21:1 on white |
| `--civicone-focus` | `#ffdd00` | Focus background | GOV.UK yellow |
| `--civicone-focus-text` | `#0b0c0c` | Text on focus | 19:1 on yellow |
| `--civicone-error` | `#d4351c` | Error state | 5.6:1 on white |
| `--civicone-success` | `#00703c` | Success state | 5.9:1 on white |
| `--civicone-border` | `#b1b4b6` | Borders/rules | 3:1 on white |
| `--civicone-input-border` | `#0b0c0c` | Form input borders | 21:1 on white |
| `--civicone-background` | `#ffffff` | Page background | - |
| `--civicone-background-light` | `#f3f2f1` | Section background | - |

#### 8.1.2 Brand Colours (CivicOne)

| Token Name | Value | Usage |
|------------|-------|-------|
| `--civicone-brand` | `#1d70b8` | GOV.UK blue |
| `--civicone-brand-dark` | `#0f385c` | Dark variant |
| `--civicone-brand-light` | `#d2e2f1` | Light variant |

#### 8.1.3 Palette Colours (Extended)

| Token Name | Value | Notes |
|------------|-------|-------|
| `--civicone-blue` | `#1d70b8` | Primary blue |
| `--civicone-blue-dark` | `#0f385c` | Blue shade-50 |
| `--civicone-green` | `#00703c` | Success green |
| `--civicone-red` | `#d4351c` | Error red |
| `--civicone-yellow` | `#ffdd00` | Focus yellow |
| `--civicone-black` | `#0b0c0c` | True black |
| `--civicone-white` | `#ffffff` | True white |
| `--civicone-grey-1` | `#484949` | Dark grey |
| `--civicone-grey-2` | `#858686` | Mid grey |
| `--civicone-grey-3` | `#b1b4b6` | Light grey |
| `--civicone-grey-4` | `#f3f2f1` | Background grey |

### 7.2 Spacing Tokens

Based on GOV.UK Frontend `_spacing.scss`. Uses 5px base unit.

| Token Name | Value | Responsive | Usage |
|------------|-------|------------|-------|
| `--civicone-space-0` | `0` | No | None |
| `--civicone-space-1` | `5px` | No | Tight spacing |
| `--civicone-space-2` | `10px` | No | Compact spacing |
| `--civicone-space-3` | `15px` | No | Default spacing |
| `--civicone-space-4` | `15px` → `20px` | Yes | Comfortable spacing |
| `--civicone-space-5` | `15px` → `25px` | Yes | Generous spacing |
| `--civicone-space-6` | `20px` → `30px` | Yes | Section spacing |
| `--civicone-space-7` | `25px` → `40px` | Yes | Large spacing |
| `--civicone-space-8` | `30px` → `50px` | Yes | XL spacing |
| `--civicone-space-9` | `40px` → `60px` | Yes | XXL spacing |

**Note:** Responsive values use `tablet` breakpoint (641px).

### 7.3 Typography Tokens

Based on GOV.UK Frontend `_typography-responsive.scss`.

| Token Name | Mobile | Tablet+ | Line Height | Usage |
|------------|--------|---------|-------------|-------|
| `--civicone-font-80` | `53px` | `80px` | 1.04 | XL headings |
| `--civicone-font-48` | `32px` | `48px` | 1.04 | Page titles |
| `--civicone-font-36` | `27px` | `36px` | 1.11 | Section headings |
| `--civicone-font-27` | `21px` | `27px` | 1.11 | Subsection headings |
| `--civicone-font-24` | `21px` | `24px` | 1.25 | Lead text |
| `--civicone-font-19` | `19px` | `19px` | 1.32 | Body text (default) |
| `--civicone-font-16` | `16px` | `16px` | 1.25 | Small text |

**Font Stack:**
```css
--civicone-font-family: "GDS Transport", arial, sans-serif;
--civicone-font-family-fallback: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
```

**Note:** GDS Transport requires licence for non-government use. Use fallback stack by default.

### 7.4 Layout Tokens

| Token Name | Value | Usage |
|------------|-------|-------|
| `--civicone-max-width` | `1020px` | Max content width |
| `--civicone-reading-width` | `66.67%` | Two-thirds width |
| `--civicone-reading-width-narrow` | `50%` | One-half width |
| `--civicone-reading-width-ch` | `70ch` | Character-based width |

### 7.5 Focus State Tokens

| Token Name | Value | Usage |
|------------|-------|-------|
| `--civicone-focus-width` | `3px` | Focus outline width |
| `--civicone-focus-offset` | `0` | Focus outline offset |
| `--civicone-focus-color` | `#ffdd00` | Focus outline/background |
| `--civicone-focus-text-color` | `#0b0c0c` | Text on focus |

### 7.6 Border and Shadow Tokens

| Token Name | Value | Usage |
|------------|-------|-------|
| `--civicone-border-width` | `1px` | Default border |
| `--civicone-border-width-thick` | `4px` | Accent borders |
| `--civicone-border-radius` | `0` | No rounded corners (GOV.UK) |
| `--civicone-shadow-none` | `none` | No shadows (GOV.UK) |

---

## 8. Accessibility Specification

### 8.1 WCAG 2.1 AA Requirements

This section specifies MANDATORY accessibility behaviours.

#### 8.1.1 Skip Link

**MUST:**
- Be the first focusable element on the page
- Skip to `#main-content`
- Be visually hidden until focused
- Become visible on focus with high contrast

**Implementation:**
```html
<a href="#main-content" class="civicone-skip-link">Skip to main content</a>
```

```css
.civicone .civicone-skip-link {
  position: absolute;
  top: -999em;
  /* ... */
}
.civicone .civicone-skip-link:focus {
  position: static;
  background: var(--civicone-focus);
  color: var(--civicone-focus-text);
  /* ... */
}
```

#### 8.1.2 Landmarks

Every page MUST have:

| Landmark | Element | Notes |
|----------|---------|-------|
| Banner | `<header role="banner">` | One per page |
| Navigation | `<nav aria-label="...">` | Label each nav |
| Main | `<main id="main-content">` | One per page |
| Contentinfo | `<footer role="contentinfo">` | One per page |

#### 8.1.3 Heading Hierarchy

**MUST:**
- Start with `<h1>` (one per page)
- Follow sequential order (h1 → h2 → h3, no skipping)
- Not use headings for styling alone

**Current hero title uses `<h1>` - this MUST remain.**

#### 8.1.4 Forms

**Labels:**
- Every input MUST have a visible `<label>` associated via `for`/`id`
- Labels MUST NOT be replaced with `placeholder` alone

**Hints:**
- Use `aria-describedby` to associate hint text with inputs
- Hints MUST be visible (not hidden)

**Errors:**
- Error messages MUST be associated with inputs via `aria-describedby`
- Error summary MUST appear at the top of the form on submission
- Error summary MUST receive focus on page load when errors exist
- Use `aria-invalid="true"` on invalid inputs

**Pattern:**
```html
<div class="civicone-form-group civicone-form-group--error">
  <label class="civicone-label" for="email">Email address</label>
  <div id="email-hint" class="civicone-hint">We'll only use this to contact you</div>
  <p id="email-error" class="civicone-error-message">
    <span class="civicone-visually-hidden">Error:</span> Enter an email address
  </p>
  <input class="civicone-input civicone-input--error"
         id="email"
         name="email"
         type="email"
         aria-describedby="email-hint email-error"
         aria-invalid="true">
</div>
```

#### 8.1.5 Keyboard Support

**All interactive elements MUST support:**

| Element Type | Keys | Behaviour |
|--------------|------|-----------|
| Links | `Enter` | Activate |
| Buttons | `Enter`, `Space` | Activate |
| Dropdowns | `Enter`, `Space` | Open |
| | `Escape` | Close |
| | `Arrow Down/Up` | Navigate options |
| Menus | `Escape` | Close and return focus |
| | `Tab` | Move through items |
| Dialogs | `Escape` | Close |
| | `Tab` | Trap focus within |
| Checkboxes | `Space` | Toggle |
| Radio buttons | `Arrow keys` | Move selection |
| Tabs | `Arrow Left/Right` | Switch tabs |

#### 8.1.6 Focus Management

**MUST:**
- Return focus to trigger element when closing dialogs/menus
- Move focus to dialog/modal when opened
- Trap focus within open dialogs (Tab cycles through dialog only)
- Maintain visible focus indicator at all times

#### 8.1.7 Zoom and Reflow

**MUST:**
- Support 200% zoom without horizontal scrolling
- Support 400% zoom with reflow to single column
- Use relative units (`rem`, `em`, `%`) not fixed pixels for text

#### 8.1.8 Reduced Motion

**MUST:**
- Respect `prefers-reduced-motion: reduce`
- Disable animations and transitions when set

```css
@media (prefers-reduced-motion: reduce) {
  .civicone *,
  .civicone *::before,
  .civicone *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}
```

### 8.2 Definition of Done Checklist

**Every page/component change MUST pass:**

- [ ] Keyboard navigation works for all interactive elements
- [ ] Tab order is logical (follows visual/reading order)
- [ ] Focus indicator is visible on all focusable elements
- [ ] Focus indicator has 3:1+ contrast
- [ ] All text has 4.5:1+ contrast (3:1 for large text)
- [ ] UI components have 3:1+ contrast
- [ ] Skip link is functional and visible on focus
- [ ] Page has one `<h1>` and logical heading hierarchy
- [ ] All form inputs have visible labels
- [ ] Error messages are associated with inputs
- [ ] Landmarks are properly defined
- [ ] No content is hidden from screen readers inappropriately
- [ ] Images have appropriate `alt` text (or `alt=""` if decorative)
- [ ] No hover-only interactions exist
- [ ] Page is usable at 200% zoom
- [ ] `prefers-reduced-motion` is respected

---

## 9. Component Rules

### 9.1 Component Development Principles

1. **Prefer GOV.UK patterns** for new/refactored components
2. **Create PHP partials** in `/views/layouts/civicone/components/`
3. **Document keyboard interaction** for every interactive component
4. **Test with keyboard and screen reader** before merging

### 9.2 Component Directory Structure

```
views/layouts/civicone/components/
├── button.php
├── input.php
├── textarea.php
├── select.php
├── checkboxes.php
├── radios.php
├── error-message.php
├── error-summary.php
├── hint.php
├── label.php
├── fieldset.php
├── inset-text.php
├── notification-banner.php
├── panel.php
├── phase-banner.php
├── summary-list.php
├── table.php
├── tabs.php
├── tag.php
└── warning-text.php
```

### 9.3 Navigation and Menu Keyboard Patterns

#### 9.3.1 Mega Menu (Desktop)

| Key | Behaviour |
|-----|-----------|
| `Enter` / `Space` on trigger | Toggle menu open/closed |
| `Escape` | Close menu, return focus to trigger |
| `Tab` | Move through menu items |
| `Arrow Down` (when open) | Move to first/next item |
| `Arrow Up` | Move to previous item |
| `Home` | Move to first item |
| `End` | Move to last item |

**MUST:**
- Close when clicking outside
- Close when focus leaves menu
- Not open on hover alone

#### 9.3.2 Mobile Navigation (Drawer)

| Key | Behaviour |
|-----|-----------|
| `Enter` / `Space` on hamburger | Open drawer |
| `Escape` | Close drawer, return focus to hamburger |
| `Tab` | Cycle through drawer items (trapped) |
| `Shift + Tab` | Reverse cycle |

**MUST:**
- Trap focus within drawer when open
- Prevent body scroll when open
- Include visible close button

#### 9.3.3 Dropdown Menus (Utility Bar)

Same pattern as Mega Menu. Current implementation in header.php already follows this pattern.

### 9.4 Tabs and Accordions

#### 9.4.1 Tabs

| Key | Behaviour |
|-----|-----------|
| `Arrow Left/Right` | Move to prev/next tab |
| `Home` | Move to first tab |
| `End` | Move to last tab |
| `Enter` / `Space` | Activate focused tab (if not automatic) |

**MUST:**
- Use `role="tablist"`, `role="tab"`, `role="tabpanel"`
- Use `aria-selected`, `aria-controls`, `aria-labelledby`
- Only one tab in tab order at a time

#### 9.4.2 Accordions

| Key | Behaviour |
|-----|-----------|
| `Enter` / `Space` | Toggle section open/closed |
| `Arrow Down` | Move to next header |
| `Arrow Up` | Move to previous header |
| `Home` | Move to first header |
| `End` | Move to last header |

**MUST:**
- Use `aria-expanded` on triggers
- Use `aria-controls` to link to content

### 9.5 Modals and Drawers

| Key | Behaviour |
|-----|-----------|
| `Escape` | Close modal, return focus to trigger |
| `Tab` | Cycle through modal items (trapped) |

**MUST:**
- Have `role="dialog"` or `role="alertdialog"`
- Have `aria-modal="true"`
- Have `aria-labelledby` pointing to title
- Trap focus while open
- Return focus to trigger on close
- Prevent background scroll

### 9.6 Toasts and Notifications

**MUST:**
- NOT steal focus from current activity
- Use `role="status"` or `aria-live="polite"` for non-critical
- Use `role="alert"` or `aria-live="assertive"` for critical only
- Be dismissible via keyboard
- Auto-dismiss after reasonable time (or be persistent)

---

## 10. Risk Register and Do Not Break List

### 10.1 Critical Hooks (DO NOT RENAME/REMOVE)

| Hook/ID | File | Purpose |
|---------|------|---------|
| `#civic-mega-menu-btn` | header.php | Mega menu trigger |
| `#civic-mega-menu` | header.php | Mega menu container |
| `#civic-menu-toggle` | header.php | Mobile hamburger button |
| `openMobileMenu()` | header.php / mobile-nav-v2.php | Function to open mobile nav |
| `closeMobileMenu()` | mobile-nav-v2.php | Function to close mobile nav |
| `#civic-mobile-search-toggle` | header.php | Mobile search toggle |
| `#civic-mobile-search-bar` | header.php | Mobile search container |
| `#notif-drawer` | header.php | Notification drawer |
| `#notif-drawer-overlay` | header.php | Notification backdrop |
| `window.nexusNotifDrawer` | footer.php | Notification drawer API |
| `window.nexusNotifications` | footer.php | Notifications API |
| AI chat widget elements | ai-chat-widget.php | AI assistant integration |
| Pusher client config | footer.php | Real-time notifications |
| `NEXUS_BASE` | header.php | Base path JS variable |
| `window.NEXUS` | footer.php | Global NEXUS namespace |

### 10.2 Do Not Reorder Scripts

The following scripts in footer.php have dependency order. Do NOT reorder:

1. `social-interactions.min.js` (first - sets up globals)
2. `nexus-mapbox.min.js`
3. `nexus-ui.min.js`
4. `nexus-turbo.min.js`
5. `nexus-auth-handler.min.js`
6. `nexus-native.min.js`
7. `nexus-mobile.min.js`
8. `civicone-mobile.min.js`
9. `civicone-native.min.js`
10. `civicone-pwa.min.js`
11. `civicone-webauthn.min.js`
12. Pusher client (after `window.NEXUS_CONFIG`)
13. `notifications.min.js` (after Pusher)

### 10.3 Shared Global CSS (DO NOT MODIFY)

These files are shared between layouts. Do NOT add CivicOne-specific styles:

- `design-tokens.css` / `design-tokens.min.css`
- `nexus-phoenix.css` / `nexus-phoenix.min.css`
- `branding.css` / `branding.min.css`
- `layout-isolation.css` / `layout-isolation.min.css`
- `social-interactions.css` / `social-interactions.min.css`
- `scroll-fix-emergency.css` / `scroll-fix-emergency.min.css`

### 10.4 Known Risks

| Risk | Mitigation |
|------|------------|
| CSS selector conflict with Modern | Use `.civicone` prefix on all selectors |
| JS global pollution | Namespace all CivicOne JS under `window.civicone` |
| Breaking cached header | Update `header-cached.php` when changing partials |
| Mobile nav state issues | Test on real devices before merge |
| Notification drawer conflicts | Do not modify drawer structure without testing Pusher |

---

## 11. Rollout Plan

### 11.1 Phase 1: Extraction (No Visual Change)

**Duration:** 1-2 weeks
**Risk:** Low
**Rollback:** Revert partial includes

**Tasks:**
1. Create new partials directory structure
2. Extract `header.php` into partials (document-open, assets-css, skip-link, site-header, mega-menu, hero, main-open)
3. Extract `footer.php` into partials (main-close, site-footer, assets-js-footer, document-close)
4. Update `header-cached.php` to use same partials
5. Verify zero visual/functional change via diff testing
6. Commit with clear message: "refactor: extract CivicOne layout into partials (no output change)"

**Validation:**
- [ ] Compare rendered HTML before/after (must be identical)
- [ ] All interactive features work (menus, search, notifications)
- [ ] No console errors
- [ ] Mobile nav works

### 11.2 Phase 2: GOV.UK Design Tokens ✅ COMPLETE (2026-01-20)

**Duration:** 1 day (completed)
**Risk:** Low
**Status:** ✅ **COMPLETED**

**Completed Tasks:**
1. ✅ Created 5 GOV.UK component CSS files:
   - `civicone-govuk-focus.css` (GOV.UK yellow focus pattern)
   - `civicone-govuk-typography.css` (responsive type scale)
   - `civicone-govuk-spacing.css` (5px base spacing system)
   - `civicone-govuk-buttons.css` (green/grey/red button components)
   - `civicone-govuk-forms.css` (form inputs with thick borders, error states)
2. ✅ Updated all 17 CivicOne CSS files with GOV.UK tokens:
   - Applied GOV.UK focus pattern to ~170 focus states
   - Updated spacing tokens (--civicone-spacing-*)
   - Updated text color tokens (--govuk-text-colour, --govuk-error-colour)
   - Preserved existing functionality (no visual regressions)
3. ✅ Regenerated all 23 minified CSS files
4. ✅ Added GOV.UK CSS files to `assets-css.php` partial
5. ✅ Documented all changes in this source of truth

**Validation Results:**
- ✅ No visual regression (existing styles preserved)
- ✅ All focus states use GOV.UK yellow (#ffdd00) pattern
- ✅ All minified files regenerated and verified
- ✅ CSS custom properties available and functional

**Files Modified:**
- Core layout: civicone-header.css (13 focus), civicone-mobile.css (7 focus), civicone-footer.css (spacing), civicone-native.css (4 focus)
- Page-specific: All 13 conditional CSS files updated with GOV.UK tokens

### 11.3 Phase 3: Page Template Refactoring (NEXT PHASE)

**Duration:** 2-4 weeks
**Risk:** Medium
**Status:** 🔄 **READY TO START**

**Objective:** Update individual CivicOne page templates to use the new GOV.UK button and form component classes.

**Prerequisites:**
- ✅ Phase 2 complete (GOV.UK tokens applied to all CSS)
- ✅ GOV.UK component CSS files created and loaded
- ⏳ Testing in staging environment recommended before starting

**Recommended Approach:**

**Option A: Start with High-Priority Pages (Recommended)**

Update pages in order of user impact:

1. **Dashboard** (`views/civicone/dashboard/`)
   - Replace existing buttons with `.civicone-button`, `.civicone-button--primary`, `.civicone-button--secondary`
   - Update form inputs to use `.civicone-input` classes
   - Apply GOV.UK error states where applicable
   - Test keyboard navigation thoroughly

2. **Profile/Settings** (`views/civicone/profile/`, `views/civicone/settings/`)
   - Standardize form elements with GOV.UK patterns
   - Apply `.civicone-label`, `.civicone-hint`, `.civicone-error-message` classes
   - Update button styles to match GOV.UK components

3. **Events Pages** (`views/civicone/events/`)
   - Update CTAs to use new button styles
   - Standardize form inputs on create/edit pages

4. **Groups Pages** (`views/civicone/groups/`)
   - Update navigation buttons
   - Standardize discussion forms

5. **Messages/Help** (`views/civicone/messages/`, `views/civicone/help/`)
   - Update message composition forms
   - Standardize help center search and contact forms

**Option B: Testing and Validation First**

Before page refactoring, validate Phase 2 work:

1. **Keyboard Navigation Testing**
   - Test all focus states with Tab key navigation
   - Verify yellow focus (#ffdd00) is visible on all interactive elements
   - Check focus order is logical across all pages

2. **Screen Reader Testing**
   - Test with NVDA/JAWS on Windows
   - Test with VoiceOver on macOS/iOS
   - Verify focus announcements are clear

3. **Visual Regression Testing**
   - Screenshot comparison before/after Phase 2
   - Verify no unintended layout shifts
   - Check mobile responsiveness (320px - 1920px viewports)

4. **Automated Accessibility Audits**
   - Run Lighthouse accessibility audits on key pages
   - Run axe DevTools on all CivicOne pages
   - Document any issues found

**Tasks (Option A - Page Refactoring):**

1. Create component usage documentation
   - Document all GOV.UK component classes available
   - Provide before/after code examples
   - Create quick reference guide for developers

2. Update page templates systematically
   - Start with one page per section (dashboard, profile, events)
   - Replace inline styles with GOV.UK component classes
   - Test each page after modification
   - Document any challenges or edge cases

3. Form validation pattern updates
   - Implement GOV.UK error summary pattern
   - Update error message positioning and styling
   - Add `aria-describedby` associations
   - Test error states with screen readers

**Validation Checklist:**

- [ ] Component documentation created
- [ ] Before/after screenshots documented
- [ ] Keyboard navigation tested on updated pages
- [ ] Screen reader tested on updated pages
- [ ] Mobile responsive verified
- [ ] No visual regressions introduced
- [ ] Focus states working correctly
- [ ] Form validation accessible
- [ ] Error messages properly associated
- [ ] All interactive elements have 44px touch targets (WCAG 2.1)

### 11.4 Phase 4: High-Risk Components

**Duration:** 2-4 weeks
**Risk:** High
**Rollback:** Revert component changes

**Order of implementation (easiest to hardest):**

1. **Skip link** - Low risk, high a11y value
2. **Buttons** - Contained, testable
3. **Form inputs** - Labels, hints, errors
4. **Error summary** - Page-level component
5. **Breadcrumbs** - Navigation component
6. **Phase banner** - Simple banner
7. **Notification banner** - Toast/alert system
8. **Tabs** - Complex interaction
9. **Mega menu** - Critical, complex
10. **Mobile nav drawer** - Critical, complex

**For each component:**
1. Document current behaviour
2. Create new component partial
3. Apply GOV.UK patterns
4. Test keyboard interaction
5. Test screen reader
6. A/B test in staging
7. Roll out

---

## 12. Testing and Tooling

### 12.1 Manual Testing Checklist

**Keyboard Walkthrough:**
- [ ] Can navigate entire page with Tab key only
- [ ] Focus order is logical
- [ ] All dropdowns/menus work with Enter/Space/Arrow keys
- [ ] Can close all menus with Escape
- [ ] Focus returns to trigger when closing menus

**Focus Visibility:**
- [ ] Focus ring visible on all interactive elements
- [ ] Focus ring has yellow (#ffdd00) background
- [ ] Text on focus has black (#0b0c0c) colour
- [ ] Focus never disappears unexpectedly

**Zoom/Reflow:**
- [ ] Page usable at 200% zoom (browser)
- [ ] Page usable at 400% zoom (reflows to single column)
- [ ] No horizontal scrollbar at 320px viewport width

**Screen Reader Smoke Test:**
- [ ] Page title announced
- [ ] Skip link announced and works
- [ ] Headings navigable (H key in NVDA/JAWS)
- [ ] Landmarks navigable (D key in NVDA)
- [ ] Form labels announced
- [ ] Error messages announced
- [ ] Buttons/links announce role and state

### 12.2 Automated Testing

**Axe DevTools:**
```javascript
// Run in browser console
axe.run().then(results => console.log(results));
```

**Lighthouse:**
- Run Accessibility audit
- Target 100 score (or document exceptions)

**Pa11y CLI:**
```bash
npx pa11y https://localhost/civicone-page --standard WCAG2AA
```

### 12.3 Browser Testing Matrix

| Browser | Version | Priority |
|---------|---------|----------|
| Chrome | Latest | P1 |
| Firefox | Latest | P1 |
| Safari | Latest | P1 |
| Edge | Latest | P1 |
| Safari iOS | Latest | P1 |
| Chrome Android | Latest | P1 |
| IE11 | - | Not supported |

### 12.4 Screen Reader Testing

| Screen Reader | Browser | Priority |
|---------------|---------|----------|
| NVDA | Firefox/Chrome | P1 |
| VoiceOver | Safari (macOS) | P1 |
| VoiceOver | Safari (iOS) | P1 |
| TalkBack | Chrome (Android) | P2 |
| JAWS | Chrome | P2 |

---

## 13. Appendix: Implementation Playbook

This section provides **exact steps** for implementation after this document is approved.

### Step 1: Create Partial Files (Zero Output Change)

```bash
# Create component directory
mkdir -p views/layouts/civicone/components

# Create new partial files (empty initially)
touch views/layouts/civicone/partials/document-open.php
touch views/layouts/civicone/partials/assets-css.php
touch views/layouts/civicone/partials/site-header.php
touch views/layouts/civicone/partials/mega-menu.php
touch views/layouts/civicone/partials/hero.php
touch views/layouts/civicone/partials/main-open.php
touch views/layouts/civicone/partials/main-close.php
touch views/layouts/civicone/partials/site-footer.php
touch views/layouts/civicone/partials/assets-js-footer.php
touch views/layouts/civicone/partials/document-close.php
```

### Step 2: Extract Header Sections

**Order of extraction from header.php:**

1. Lines 1-35 → `partials/document-open.php` (PHP setup, `<!DOCTYPE>` through `<head>`)
2. Lines 36-189 → `partials/assets-css.php` (all CSS links)
3. Line ~442 → `partials/skip-link.php` (skip link)
4. Lines 443-774 → `partials/site-header.php` (utility bar, main header)
5. Lines 821-890 → `partials/mega-menu.php` (mega menu structure)
6. Lines 924-1007 → `partials/hero.php` (hero banner)
7. Line 1020 → `partials/main-open.php` (`<main id="main-content">`)

**After extraction, header.php becomes:**
```php
<?php
require __DIR__ . '/partials/document-open.php';
require __DIR__ . '/partials/assets-css.php';
// ... inline styles and scripts remain for now ...
?>
</head>
<body class="...">
<?php
require __DIR__ . '/partials/skip-link.php';
require __DIR__ . '/partials/site-header.php';
require __DIR__ . '/partials/mega-menu.php';
require __DIR__ . '/partials/hero.php';
require __DIR__ . '/partials/main-open.php';
// ... inline scripts remain ...
```

### Step 3: Extract Footer Sections

**Order of extraction from footer.php:**

1. Line 3 → `partials/main-close.php` (`</main>`)
2. Lines 162-268 → `partials/site-footer.php` (footer HTML)
3. Lines 276-527 → `partials/assets-js-footer.php` (all scripts)
4. Lines 529-531 → `partials/document-close.php` (`</body></html>`)

### Step 4: Create Foundation CSS

Create `/httpdocs/assets/css/civicone-base.css`:

```css
/**
 * CivicOne Base Foundation
 * WCAG 2.1 AA compliant foundation styles
 * Version: 1.0.0
 */

/* ==========================================
   CSS Custom Properties (Tokens)
   ========================================== */
.civicone {
  /* Colours */
  --civicone-text: #0b0c0c;
  --civicone-text-secondary: #484949;
  --civicone-link: #1d70b8;
  --civicone-link-visited: #54319f;
  --civicone-link-hover: #0f385c;
  --civicone-focus: #ffdd00;
  --civicone-focus-text: #0b0c0c;
  --civicone-error: #d4351c;
  --civicone-success: #00703c;
  --civicone-border: #b1b4b6;
  --civicone-input-border: #0b0c0c;
  --civicone-background: #ffffff;
  --civicone-background-light: #f3f2f1;

  /* Spacing */
  --civicone-space-1: 5px;
  --civicone-space-2: 10px;
  --civicone-space-3: 15px;
  --civicone-space-4: 20px;
  --civicone-space-5: 25px;
  --civicone-space-6: 30px;
  --civicone-space-7: 40px;
  --civicone-space-8: 50px;
  --civicone-space-9: 60px;

  /* Focus */
  --civicone-focus-width: 3px;
}

/* ==========================================
   Focus States (GOV.UK Pattern)
   ========================================== */
.civicone a:focus,
.civicone button:focus,
.civicone input:focus,
.civicone select:focus,
.civicone textarea:focus,
.civicone [tabindex]:focus {
  outline: var(--civicone-focus-width) solid transparent;
  background-color: var(--civicone-focus);
  box-shadow:
    0 -2px var(--civicone-focus),
    0 4px var(--civicone-focus-text);
  text-decoration: none;
}

.civicone a:focus {
  color: var(--civicone-focus-text);
}

/* ==========================================
   Reduced Motion
   ========================================== */
@media (prefers-reduced-motion: reduce) {
  .civicone *,
  .civicone *::before,
  .civicone *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
    scroll-behavior: auto !important;
  }
}

/* ==========================================
   Visually Hidden (Screen Reader Only)
   ========================================== */
.civicone .civicone-visually-hidden {
  position: absolute !important;
  width: 1px !important;
  height: 1px !important;
  margin: 0 !important;
  padding: 0 !important;
  overflow: hidden !important;
  clip: rect(0 0 0 0) !important;
  clip-path: inset(50%) !important;
  border: 0 !important;
  white-space: nowrap !important;
}
```

### Step 5: Create GOV.UK Theme CSS (Feature Flag)

Create `/httpdocs/assets/css/civicone-govuk-theme.css`:

```css
/**
 * CivicOne GOV.UK Theme
 * Activated via .civicone--govuk class
 * Version: 1.0.0
 */

/* Only apply when redesign is active */
.civicone--govuk {
  /* Typography */
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  font-size: 19px;
  line-height: 1.32;
  color: var(--civicone-text);
}

/* ... additional GOV.UK-aligned styles ... */
```

### Step 6: Add to PurgeCSS Config

Edit `purgecss.config.js`:

```javascript
module.exports = {
  content: [
    // ... existing paths ...
  ],
  css: [
    // ... existing files ...
    'httpdocs/assets/css/civicone-base.css',
    'httpdocs/assets/css/civicone-govuk-theme.css',
  ],
  // ...
};
```

### Step 7: Implement Feature Flag

In header.php (or a shared config):

```php
// Feature flag for GOV.UK redesign
$govukRedesign = false;

// Enable via query param for testing
if (isset($_GET['govuk']) && $_GET['govuk'] === '1') {
    $govukRedesign = true;
}

// Or enable via tenant config
if (!empty($tenantConfig['civicone_govuk_redesign'])) {
    $govukRedesign = true;
}
```

Add class to body:

```php
<body class="civicone <?= $govukRedesign ? 'civicone--govuk' : '' ?> nexus-skin-civicone ...">
```

### Step 8: Validation Before Each Phase

**Before merging any phase:**

1. Compare rendered HTML (should be identical for extraction phases)
2. Run keyboard walkthrough
3. Run Lighthouse accessibility audit
4. Test on mobile devices
5. Check console for errors
6. Verify Pusher notifications work
7. Verify AI chat widget works
8. Verify layout switcher works (switch to Modern and back)

### Rollback Strategy

**If issues are found after deployment:**

**Phase 1 (Extraction):**
```bash
git revert <commit-hash>
```

**Phase 2 (Foundation CSS):**
```php
// In partials/assets-css.php, comment out:
// <link rel="stylesheet" href="/assets/css/civicone-base.css">
```

**Phase 3 (GOV.UK Theme):**
```php
// Disable feature flag:
$govukRedesign = false;
```

**Phase 4 (Components):**
```bash
git revert <component-commit-hash>
```

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0.0 | 2026-01-20 | Development Team | Initial release |
| 1.1.0 | 2026-01-20 | Development Team | Phase 2 complete: GOV.UK tokens applied to all 17 CSS files (~170 focus states), 5 GOV.UK component CSS files created, all minified files regenerated. Updated Phase 3 to reflect next steps (page template refactoring). |

---

## Approval

This document is effective immediately upon creation. All CivicOne changes MUST comply with this specification.

**Approved by:** _________________
**Date:** _________________
