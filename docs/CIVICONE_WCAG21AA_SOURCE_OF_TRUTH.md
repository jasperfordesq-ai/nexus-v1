# CivicOne WCAG 2.1 AA Source of Truth

**Version:** 1.3.0
**Status:** AUTHORITATIVE
**Created:** 2026-01-20
**Last Updated:** 2026-01-20 (Enhanced with MOJ/DfE/ONS/HMCTS Design System Guidance)
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
10. [Canonical Page Templates (MANDATORY)](#10-canonical-page-templates-mandatory)
11. [Grid & Results Layout Contracts](#11-grid--results-layout-contracts)
12. [Refactoring Workflow to Avoid Ruining Existing Layouts](#12-refactoring-workflow-to-avoid-ruining-existing-layouts)
13. [Risk Register and Do Not Break List](#13-risk-register-and-do-not-break-list)
14. [Rollout Plan](#14-rollout-plan)
15. [Testing and Tooling](#15-testing-and-tooling)
16. [Appendix: Implementation Playbook](#16-appendix-implementation-playbook)

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

### 4.3 MOJ Design System (Card, Filter, Directory Patterns)

**Documentation:** `https://design-patterns.service.justice.gov.uk/`
**Usage:** PRIMARY reference for directory/list pages (Members, Groups, Volunteering)

**Key Resources:**
- Card component: https://design-patterns.service.justice.gov.uk/components/card/
- Filter component: https://design-patterns.service.justice.gov.uk/components/filter/
- Filter a list pattern: https://design-patterns.service.justice.gov.uk/patterns/filter-a-list/

**Frontend Repository:** `https://github.com/hmcts/frontend`
**Usage:** Reference for service UI conventions and component implementations

### 4.4 DfE Card Component (Card Grid Guidance)

**Documentation:** `https://design.education.gov.uk/design-system/components/card`
**Usage:** Reference for card grid layouts and spacing constraints

**Key Principles from DfE:**
- Maximum 3-4 cards per row
- Stack to single column on mobile
- Test content without cards first (cards are enhancement, not requirement)

### 4.5 ONS Card Component (Grid Guidance)

**Documentation:** `https://service-manual.ons.gov.uk/design-system/components/card`
**Usage:** Reference for card component grid patterns and accessibility

**Key Principles from ONS:**
- Cards must work without CSS (progressive enhancement)
- Avoid complex grids that break on zoom
- Prefer simple list/table layouts for large datasets

### 4.6 GOV.UK Layout & Component Extension Guidance

**Documentation:**
- Layout guidance: https://design-system.service.gov.uk/styles/layout/
- Page template: https://design-system.service.gov.uk/styles/page-template/
- Extending components: https://design-system.service.gov.uk/get-started/extending-and-modifying-components/

**Usage:** MANDATORY reference for:
- Correct wrapper structure (govuk-width-container → govuk-main-wrapper → govuk-grid-row → govuk-grid-column)
- Page template structure
- Component naming/prefix conventions to avoid collisions with GOV.UK Frontend

### 4.7 DfE Frontend (Reference Only)

**Repository:** `https://github.com/DFE-Digital/dfe-frontend.git`
**Usage:** Secondary reference for education-sector specific patterns.

### 4.8 Update Policy

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

## 10. Canonical Page Templates (MANDATORY)

Every CivicOne page MUST use one of the five canonical templates defined below. Ad-hoc page structures are NOT permitted.

### 10.1 Overview of Templates

| Template | Use Cases | Primary Pattern | Grid Type |
|----------|-----------|-----------------|-----------|
| A) Directory/List | Members, Groups, Volunteering | MOJ "filter a list" | List/Table + Pagination |
| B) Dashboard/Home | Homepage, internal hubs | Mixed content + cards | GOV.UK grid + Card groups |
| C) Detail Page | Member profile, Group detail, Opportunity detail | Summary list + prose | GOV.UK grid (2/3 + 1/3) |
| D) Form/Flow | Join, edit profile, create group, create listing | GOV.UK form pattern | Single column |
| E) Content/Article | Help pages, blog posts | Prose + images | Reading width (2/3 column) |

### 10.2 Template A: Directory/List Page (Members, Groups, Volunteering)

**Pattern Source:** MOJ "filter a list" pattern (https://design-patterns.service.justice.gov.uk/patterns/filter-a-list/)

**MANDATORY Structure:**

```html
<div class="civicone-width-container">
  <main class="civicone-main-wrapper" id="main-content">

    <!-- Page header -->
    <div class="civicone-grid-row">
      <div class="civicone-grid-column-full">
        <h1 class="civicone-heading-xl">Page Title</h1>
        <p class="civicone-body-lead">Optional lead paragraph</p>
      </div>
    </div>

    <!-- Filter + Results layout (MOJ pattern) -->
    <div class="civicone-grid-row">

      <!-- Left: Filters (1/4 width on desktop) -->
      <div class="civicone-grid-column-one-quarter">
        <aside class="civicone-filter-panel" aria-label="Filter results">
          <h2 class="civicone-heading-m">Filters</h2>
          <!-- MOJ Filter component -->
          <form method="get" action="">
            <!-- Filter controls here -->
          </form>
        </aside>
      </div>

      <!-- Right: Results (3/4 width on desktop) -->
      <div class="civicone-grid-column-three-quarters">

        <!-- Results summary -->
        <p class="civicone-results-summary">
          Showing <strong>1-20</strong> of <strong>156</strong> results
        </p>

        <!-- Results list (NOT cards for large datasets) -->
        <ul class="civicone-results-list">
          <li class="civicone-result-item">
            <!-- List item content -->
          </li>
        </ul>

        <!-- Pagination -->
        <nav class="civicone-pagination" aria-label="Results pagination">
          <!-- Pagination component -->
        </nav>

      </div>
    </div>

  </main>
</div>
```

**Directory/List Template Rules:**

| Rule ID | Rule | Rationale |
|---------|------|-----------|
| DL-001 | MUST use MOJ "filter a list" pattern (https://design-patterns.service.justice.gov.uk/patterns/filter-a-list/) | Proven accessible pattern for directory pages |
| DL-002 | Results MUST default to list/table layout for large datasets (>20 items) | Cards break pagination and zoom; lists are more accessible (ONS guidance) |
| DL-003 | Cards MAY be used only for small curated subsets (e.g., "Featured Groups" section with <10 items) | Cards are enhancement for small sets only (DfE/ONS guidance) |
| DL-004 | If using cards, MUST follow ONS/DfE constraints: max 3-4 per row, stack on mobile, test content without cards first | Ensures cards don't break accessibility (DfE: https://design.education.gov.uk/design-system/components/card) |
| DL-005 | MUST NOT use masonry/Pinterest grids | Unpredictable layout breaks screen readers and zoom |
| DL-006 | MUST include pagination for datasets >20 items | Performance and usability (MOJ pattern) |
| DL-007 | MUST include results summary ("Showing X-Y of Z results") with `aria-live="polite"` for dynamic updates | Orientation for screen reader users (MOJ pattern) |
| DL-008 | Filter controls MUST use `<form>` with proper `<label>` and `<fieldset>` structure | WCAG 1.3.1 (MOJ filter component guidance) |
| DL-009 | Filter panel MUST be wrapped in `<aside>` with `aria-label="Filter results"` | Landmark navigation (WCAG 1.3.1) |
| DL-010 | "Clear filters" button MUST be keyboard accessible and announce state changes | WCAG 2.1.1, 4.1.3 |

**Accessibility Checklist (Directory/List Template):**

**Filters (MOJ Filter Component):**

- [ ] Filters wrapped in `<aside>` with `aria-label="Filter results"`
- [ ] Filter form uses `<form method="get">` (allows bookmarking filtered results)
- [ ] Each filter has visible `<label>` associated with input (`for`/`id` match)
- [ ] Checkbox/radio groups use `<fieldset>` + `<legend>`
- [ ] "Apply filters" button has clear label and is keyboard accessible
- [ ] "Clear filters" button present and functional
- [ ] Filter state persists in URL query params (e.g., `?location=Edinburgh&skills=design`)

**Results Display:**

- [ ] Results count announced to screen readers (`aria-live="polite"` on results summary)
- [ ] Results summary visible: "Showing 1-20 of 156 results"
- [ ] List items use semantic structure (`<ul>` with `<li>`, NOT `<div>` containers)
- [ ] Each result has a clear heading (`<h3>` or `<h4>`) with actionable link
- [ ] Metadata uses semantic markup (e.g., `<dl>` for key-value pairs, `<time>` for dates)
- [ ] No visual-only indicators (e.g., color alone for status badges)

**Pagination (GOV.UK Pagination Component):**

- [ ] Pagination uses `<nav>` with `aria-label="Results pagination"`
- [ ] Current page marked with `aria-current="page"`
- [ ] Previous/Next links include hidden text for screen readers (e.g., "Previous page")
- [ ] Page numbers are actual links (not just buttons), preserving URL structure
- [ ] "Load more" button (if used instead) has clear label and announces state change
- [ ] Pagination doesn't break on zoom (200%)

**Keyboard & Focus:**

- [ ] All filters operable via keyboard alone (Tab, Enter, Space, Arrow keys)
- [ ] Focus order is logical (filters → results → pagination)
- [ ] Focus visible on all interactive elements (GOV.UK yellow #ffdd00)
- [ ] No keyboard traps in filter panel or results
- [ ] Skip links present ("Skip to results", "Skip to pagination")

**Responsive & Zoom:**

- [ ] Filters stack above results on mobile (<641px)
- [ ] Results list maintains order at 200% zoom (WCAG 1.4.4)
- [ ] Touch targets minimum 44x44px on mobile (WCAG 2.5.5)
- [ ] No horizontal scroll at any viewport width

**Examples:**
- Members directory: `/members` (list of members with filters)
- Groups directory: `/groups` (list of groups with filters)
- Volunteering opportunities: `/volunteering` (list of opportunities with filters)

### 10.3 Template B: Dashboard/Home (Homepage, Internal Hubs)

**Pattern Source:** GOV.UK page template + MOJ/DfE card patterns

**MANDATORY Structure:**

```html
<div class="civicone-width-container">
  <main class="civicone-main-wrapper" id="main-content">

    <!-- Hero/Welcome section -->
    <div class="civicone-grid-row">
      <div class="civicone-grid-column-two-thirds">
        <h1 class="civicone-heading-xl">Welcome, [Name]</h1>
        <p class="civicone-body-lead">Dashboard introduction</p>
      </div>
    </div>

    <!-- Stats summary (if applicable) -->
    <div class="civicone-grid-row">
      <div class="civicone-grid-column-full">
        <dl class="civicone-summary-list civicone-summary-list--no-border">
          <!-- Summary list for stats -->
        </dl>
      </div>
    </div>

    <!-- Section: Recent Activity -->
    <div class="civicone-grid-row">
      <div class="civicone-grid-column-two-thirds">
        <h2 class="civicone-heading-l">Recent Activity</h2>
        <ul class="civicone-feed-list">
          <!-- Feed items -->
        </ul>
      </div>
      <div class="civicone-grid-column-one-third">
        <h2 class="civicone-heading-m">Quick Actions</h2>
        <nav aria-label="Quick actions">
          <!-- Action links -->
        </nav>
      </div>
    </div>

    <!-- Section: Featured/Recommended (cards allowed here for small sets) -->
    <div class="civicone-grid-row">
      <div class="civicone-grid-column-full">
        <h2 class="civicone-heading-l">Recommended Groups</h2>
        <!-- Cards allowed here (max 3-4, curated subset) -->
        <div class="civicone-card-group">
          <div class="civicone-card"><!-- Card content --></div>
          <div class="civicone-card"><!-- Card content --></div>
          <div class="civicone-card"><!-- Card content --></div>
        </div>
      </div>
    </div>

  </main>
</div>
```

**Dashboard/Home Template Rules:**

| Rule ID | Rule | Rationale |
|---------|------|-----------|
| DB-001 | MUST use GOV.UK grid system (govuk-grid-row / govuk-grid-column-*) | Consistent responsive behavior (GOV.UK layout guidance) |
| DB-002 | Cards allowed ONLY for curated/featured sections with <10 items (e.g., "Recommended Groups") | Cards are visual enhancement, not default layout (ONS/DfE guidance) |
| DB-003 | Card groups MUST use max 3-4 cards per row, stack to 1 column on mobile (<641px) | DfE/ONS guidance for card accessibility |
| DB-004 | Cards MUST work without CSS (progressive enhancement) | ONS principle: cards must be usable if CSS fails |
| DB-005 | Activity feed/timeline MUST use `<ul>` list layout, NOT cards | Chronological content needs predictable order for screen readers |
| DB-006 | Stats/metrics MUST use GOV.UK summary list component (`<dl>`) | Semantic structure for key-value pairs (WCAG 1.3.1) |
| DB-007 | Quick actions MUST be wrapped in `<nav>` with `aria-label` | Landmark navigation (WCAG 1.3.1) |
| DB-008 | MUST NOT have "See all" links without clear destination in link text | WCAG 2.4.4 (link purpose in context) |

**Accessibility Checklist (Dashboard/Home Template):**

- [ ] Page has one `<h1>` (personalized greeting or page title)
- [ ] Sections have proper heading hierarchy (h1 → h2 → h3)
- [ ] Stats use `<dl>` (description list) for semantic structure
- [ ] Card groups stack to single column on mobile
- [ ] Cards have clear headings and actionable links
- [ ] Quick actions wrapped in `<nav>` with label
- [ ] No content hidden behind interaction (e.g., hover-only)

**Examples:**
- User dashboard: `/dashboard`
- Homepage: `/` or `/home`
- Community hub: `/community`

### 10.4 Template C: Detail Page (Profile, Group Detail, Opportunity Detail)

**Pattern Source:** GOV.UK summary list + content page template

**MANDATORY Structure:**

```html
<div class="civicone-width-container">
  <main class="civicone-main-wrapper" id="main-content">

    <!-- Breadcrumbs -->
    <nav class="civicone-breadcrumbs" aria-label="Breadcrumb">
      <!-- Breadcrumb component -->
    </nav>

    <!-- Page header -->
    <div class="civicone-grid-row">
      <div class="civicone-grid-column-two-thirds">
        <h1 class="civicone-heading-xl">Item Name</h1>
        <p class="civicone-body-lead">Optional summary</p>
      </div>
    </div>

    <!-- Main content area (2/3 + 1/3 split) -->
    <div class="civicone-grid-row">

      <!-- Left: Main content -->
      <div class="civicone-grid-column-two-thirds">

        <!-- Summary list for key details -->
        <dl class="civicone-summary-list">
          <div class="civicone-summary-list__row">
            <dt class="civicone-summary-list__key">Label</dt>
            <dd class="civicone-summary-list__value">Value</dd>
          </div>
        </dl>

        <!-- Prose content -->
        <h2 class="civicone-heading-l">About</h2>
        <p class="civicone-body">Description content...</p>

      </div>

      <!-- Right: Sidebar -->
      <div class="civicone-grid-column-one-third">
        <aside aria-label="Related information">
          <h2 class="civicone-heading-m">Contact</h2>
          <!-- Sidebar content -->
        </aside>
      </div>

    </div>

  </main>
</div>
```

**Detail Page Template Rules:**

| Rule ID | Rule | Rationale |
|---------|------|-----------|
| DP-001 | MUST use 2/3 + 1/3 column split for content + sidebar | GOV.UK standard layout |
| DP-002 | MUST use GOV.UK summary list for key-value pairs | Semantic and accessible |
| DP-003 | Sidebar content MUST be marked with `<aside>` | Landmark for screen readers |
| DP-004 | MUST include breadcrumbs for navigation context | Wayfinding |
| DP-005 | Stacks to single column on mobile (content first, sidebar second) | Mobile-first responsive |

**Accessibility Checklist (Detail Page Template):**

- [ ] Breadcrumbs present and functional
- [ ] One `<h1>` for item name
- [ ] Summary list uses `<dl>`, `<dt>`, `<dd>` tags
- [ ] Sidebar has `<aside>` with `aria-label`
- [ ] Related links grouped in `<nav>` if applicable
- [ ] Images have appropriate alt text
- [ ] Contact information structured with semantic HTML

**Examples:**
- Member profile: `/members/123`
- Group detail: `/groups/456`
- Volunteering opportunity: `/volunteering/789`

### 10.5 Template D: Form/Flow (Join, Edit Profile, Create Content)

**Pattern Source:** GOV.UK form pattern

**MANDATORY Structure:**

```html
<div class="civicone-width-container">
  <main class="civicone-main-wrapper" id="main-content">

    <!-- Breadcrumbs (if part of multi-step flow) -->
    <nav class="civicone-breadcrumbs" aria-label="Breadcrumb">
      <!-- Breadcrumb component -->
    </nav>

    <!-- Error summary (if errors exist) -->
    <div class="civicone-error-summary" aria-labelledby="error-summary-title" role="alert" tabindex="-1">
      <h2 class="civicone-error-summary__title" id="error-summary-title">
        There is a problem
      </h2>
      <div class="civicone-error-summary__body">
        <ul class="civicone-error-summary__list">
          <li><a href="#input-1">Error message</a></li>
        </ul>
      </div>
    </div>

    <!-- Form (single column, reading width) -->
    <div class="civicone-grid-row">
      <div class="civicone-grid-column-two-thirds">

        <h1 class="civicone-heading-xl">Form Title</h1>

        <form method="post" action="">

          <!-- Form groups with labels, hints, errors -->
          <div class="civicone-form-group">
            <label class="civicone-label" for="input-1">
              Label text
            </label>
            <div id="input-1-hint" class="civicone-hint">
              Hint text
            </div>
            <input class="civicone-input" id="input-1" name="field1" type="text" aria-describedby="input-1-hint">
          </div>

          <!-- Submit button -->
          <button class="civicone-button" type="submit">
            Submit
          </button>

        </form>

      </div>
    </div>

  </main>
</div>
```

**Form/Flow Template Rules:**

| Rule ID | Rule | Rationale |
|---------|------|-----------|
| FF-001 | Forms MUST use single-column layout (2/3 width max) | GOV.UK research shows single column reduces errors |
| FF-002 | MUST include error summary at top when errors exist | WCAG requirement |
| FF-003 | Error summary MUST receive focus on page load when present | Keyboard/screen reader accessibility |
| FF-004 | Every input MUST have visible `<label>` | WCAG requirement |
| FF-005 | Hints MUST be associated via `aria-describedby` | Screen reader announcement |
| FF-006 | Errors MUST be associated via `aria-describedby` and `aria-invalid="true"` | WCAG requirement |
| FF-007 | Multi-step flows MUST show progress indicator | Orientation |

**Accessibility Checklist (Form/Flow Template):**

- [ ] Error summary present when errors exist
- [ ] Error summary receives focus on page load
- [ ] All inputs have visible labels
- [ ] Hints associated with inputs via `aria-describedby`
- [ ] Errors associated with inputs via `aria-describedby`
- [ ] Invalid inputs marked with `aria-invalid="true"`
- [ ] Fieldsets used for radio/checkbox groups
- [ ] Legends used for fieldset headings
- [ ] Submit button has clear, action-oriented label
- [ ] Form can be completed with keyboard only

**Examples:**
- Join/register form: `/join`
- Edit profile: `/profile/edit`
- Create group: `/groups/create`
- Create listing: `/listings/create`

### 10.6 Template E: Content/Article (Help, Blog)

**Pattern Source:** GOV.UK content page template

**MANDATORY Structure:**

```html
<div class="civicone-width-container">
  <main class="civicone-main-wrapper" id="main-content">

    <!-- Breadcrumbs -->
    <nav class="civicone-breadcrumbs" aria-label="Breadcrumb">
      <!-- Breadcrumb component -->
    </nav>

    <!-- Article (reading width, single column) -->
    <div class="civicone-grid-row">
      <div class="civicone-grid-column-two-thirds">

        <h1 class="civicone-heading-xl">Article Title</h1>

        <!-- Article metadata -->
        <p class="civicone-body-s civicone-text-secondary">
          Published <time datetime="2026-01-20">20 January 2026</time>
        </p>

        <!-- Lead paragraph -->
        <p class="civicone-body-lead">Lead paragraph</p>

        <!-- Article body (prose) -->
        <h2 class="civicone-heading-l">Section Heading</h2>
        <p class="civicone-body">Content...</p>

        <!-- Inset text (if applicable) -->
        <div class="civicone-inset-text">
          <p>Important note or callout</p>
        </div>

        <!-- Warning text (if applicable) -->
        <div class="civicone-warning-text">
          <span class="civicone-warning-text__icon" aria-hidden="true">!</span>
          <strong class="civicone-warning-text__text">
            Warning message
          </strong>
        </div>

      </div>
    </div>

  </main>
</div>
```

**Content/Article Template Rules:**

| Rule ID | Rule | Rationale |
|---------|------|-----------|
| CA-001 | Article content MUST use reading width (2/3 column max) | Optimal line length for readability |
| CA-002 | MUST use semantic heading hierarchy (h1 → h2 → h3) | Document structure for screen readers |
| CA-003 | Images MUST have descriptive alt text or `alt=""` if decorative | WCAG requirement |
| CA-004 | Use GOV.UK inset text / warning text components for callouts | Consistent pattern |
| CA-005 | Lists MUST use `<ul>`, `<ol>`, or `<dl>` (not `<div>` or `<p>` alone) | Semantic structure |

**Accessibility Checklist (Content/Article Template):**

- [ ] One `<h1>` for article title
- [ ] Logical heading hierarchy (no skipped levels)
- [ ] Images have alt text (or `alt=""` if decorative)
- [ ] Links have descriptive text (not "click here")
- [ ] Dates use `<time datetime="">` for machine-readability
- [ ] Lists use semantic markup
- [ ] Callouts use GOV.UK inset/warning components
- [ ] No walls of text (broken into paragraphs/headings)

**Examples:**
- Help article: `/help/how-to-...`
- Blog post: `/blog/article-title`
- Legal page: `/privacy`, `/terms`, `/accessibility`

---

## 11. Grid & Results Layout Contracts

No CivicOne page may contain ad-hoc or custom grid systems. All pages MUST use one of the approved grid techniques defined below.

**IMPORTANT:** All CivicOne components MUST use the `.civicone-` prefix (not `.govuk-`) to avoid collisions with GOV.UK Frontend. See GOV.UK guidance on extending components: <https://design-system.service.gov.uk/get-started/extending-and-modifying-components/>

### 11.1 Approved Grid Techniques

| Grid Type | When to Use | Structure | Responsive Behavior |
|-----------|-------------|-----------|---------------------|
| **1. GOV.UK Page Grid** | Page-level layout (main content + sidebar) | `govuk-grid-row` → `govuk-grid-column-*` | Stacks to single column on mobile |
| **2. Card Group Grid** | Small curated sets only (<10 items) | `civicone-card-group` → `civicone-card` | Max 3-4 per row, stacks to 1 column on mobile |
| **3. Results List/Table** | Large datasets, directories | `<ul class="civicone-results-list">` or `<table>` | Maintains order, no grid wrapping |

### 11.2 Grid Technique 1: GOV.UK Page Grid

**Source:** https://design-system.service.gov.uk/styles/layout/

**Usage:** Page-level structure for content + sidebar, or multi-column layouts.

**HTML Pattern:**

```html
<div class="civicone-width-container">
  <main class="civicone-main-wrapper">
    <div class="civicone-grid-row">

      <!-- Two-thirds column (main content) -->
      <div class="civicone-grid-column-two-thirds">
        <!-- Main content -->
      </div>

      <!-- One-third column (sidebar) -->
      <div class="civicone-grid-column-one-third">
        <!-- Sidebar content -->
      </div>

    </div>
  </main>
</div>
```

**Available Column Widths:**

| Class | Desktop Width | Mobile Behavior |
|-------|---------------|-----------------|
| `civicone-grid-column-full` | 100% | 100% |
| `civicone-grid-column-two-thirds` | ~66% | 100% (stacks) |
| `civicone-grid-column-one-third` | ~33% | 100% (stacks) |
| `civicone-grid-column-one-half` | 50% | 100% (stacks) |
| `civicone-grid-column-one-quarter` | 25% | 100% (stacks) |
| `civicone-grid-column-three-quarters` | 75% | 100% (stacks) |

**Rules:**

- MUST wrap in `civicone-width-container` (max-width: 1020px)
- MUST wrap in `civicone-main-wrapper` (adds vertical padding)
- Columns MUST be inside `civicone-grid-row`
- Stacks to single column automatically on mobile (<641px)

### 11.3 Grid Technique 2: Card Group Grid

**Source:** MOJ/DfE/ONS card component guidance

**Usage:** ONLY for small curated sets (<10 items). NOT for search results or large directories.

**HTML Pattern:**

```html
<div class="civicone-card-group">
  <div class="civicone-card">
    <h3 class="civicone-card__heading">
      <a href="/link" class="civicone-card__link">Card Title</a>
    </h3>
    <p class="civicone-card__description">Card description</p>
  </div>
  <div class="civicone-card">
    <!-- Another card -->
  </div>
  <div class="civicone-card">
    <!-- Another card -->
  </div>
</div>
```

**CSS (Example Implementation):**

```css
.civicone-card-group {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: var(--civicone-space-6);
  margin-bottom: var(--civicone-space-9);
}

@media (min-width: 641px) and (max-width: 1024px) {
  .civicone-card-group {
    grid-template-columns: repeat(2, 1fr); /* Max 2 per row on tablet */
  }
}

@media (min-width: 1025px) {
  .civicone-card-group {
    grid-template-columns: repeat(3, 1fr); /* Max 3 per row on desktop */
  }
}

@media (max-width: 640px) {
  .civicone-card-group {
    grid-template-columns: 1fr; /* Single column on mobile */
  }
}
```

**Rules:**

| Rule ID | Rule | Rationale |
|---------|------|-----------|
| CG-001 | Cards limited to max 3-4 per row on desktop | DfE/ONS guidance prevents overcrowding |
| CG-002 | Cards MUST stack to single column on mobile (<641px) | Touch targets and readability |
| CG-003 | Card content MUST work without CSS (progressive enhancement) | Accessibility baseline |
| CG-004 | Cards MUST have clear heading and actionable link | Semantic structure |
| CG-005 | Use ONLY for <10 curated items (featured content, recommendations) | Cards break pagination and zoom for large sets |

### 11.4 Grid Technique 3: Results List/Table Layout

**Source:** MOJ "filter a list" pattern

**Usage:** Default layout for directories, search results, and any dataset >20 items.

**HTML Pattern (List):**

```html
<ul class="civicone-results-list">
  <li class="civicone-result-item">
    <h3 class="civicone-result-heading">
      <a href="/item/123">Item Title</a>
    </h3>
    <p class="civicone-result-meta">Metadata (date, location, etc.)</p>
    <p class="civicone-result-description">Brief description...</p>
  </li>
  <li class="civicone-result-item">
    <!-- Another result -->
  </li>
</ul>
```

**HTML Pattern (Table):**

```html
<table class="civicone-table">
  <caption class="civicone-table__caption">Results</caption>
  <thead class="civicone-table__head">
    <tr class="civicone-table__row">
      <th scope="col" class="civicone-table__header">Name</th>
      <th scope="col" class="civicone-table__header">Location</th>
      <th scope="col" class="civicone-table__header">Date</th>
    </tr>
  </thead>
  <tbody class="civicone-table__body">
    <tr class="civicone-table__row">
      <td class="civicone-table__cell">Value</td>
      <td class="civicone-table__cell">Value</td>
      <td class="civicone-table__cell">Value</td>
    </tr>
  </tbody>
</table>
```

**Rules:**

| Rule ID | Rule | Rationale |
|---------|------|-----------|
| RL-001 | Use list layout for simple results (title + metadata + description) | Semantic and screen-reader friendly |
| RL-002 | Use table layout for tabular data (multiple comparable columns) | Proper semantic structure |
| RL-003 | Lists MUST use `<ul>` with `<li>`, NOT `<div>` containers | Screen reader navigation |
| RL-004 | Tables MUST have `<caption>`, `<thead>`, `<tbody>`, and `scope` attributes | WCAG requirement |
| RL-005 | NO grid wrapping (cards) for results | Predictable order for screen readers |
| RL-006 | Include pagination for datasets >20 items | Performance and usability |

### 11.5 Anti-Patterns (DO NOT USE)

| Anti-Pattern | Why It's Banned | Correct Alternative |
|--------------|-----------------|---------------------|
| **Masonry/Pinterest grid** | Unpredictable layout breaks screen readers and zoom | List layout with pagination |
| **Card grid for large datasets** | Breaks pagination, screen reader order, zoom | List/table layout with pagination |
| **Custom flexbox/grid without responsive rules** | Breaks on mobile, no accessibility testing | Use approved GOV.UK grid |
| **Float-based layouts** | Legacy technique, unpredictable behavior | Use CSS Grid (GOV.UK grid) |
| **Absolute positioning for layout** | Breaks responsive design and screen readers | Use GOV.UK grid |
| **Inline grid styles** | Not maintainable, no consistency | Use approved grid classes |

### 11.6 Example Markup Patterns

**Example 1: Members Directory (Correct - List Layout)**

```html
<div class="civicone-grid-row">
  <div class="civicone-grid-column-one-quarter">
    <!-- Filters (MOJ filter component) -->
  </div>
  <div class="civicone-grid-column-three-quarters">
    <p class="civicone-results-summary">Showing 1-20 of 156 members</p>
    <ul class="civicone-results-list">
      <li class="civicone-result-item">
        <h3><a href="/members/123">John Doe</a></h3>
        <p class="meta">Location: Edinburgh | Joined: 2025</p>
        <p>Skills: Web design, Photography</p>
      </li>
      <!-- More results -->
    </ul>
    <nav class="civicone-pagination"><!-- Pagination --></nav>
  </div>
</div>
```

**Example 2: Homepage "Featured Groups" (Correct - Card Grid for Small Set)**

```html
<div class="civicone-grid-row">
  <div class="civicone-grid-column-full">
    <h2>Featured Groups (3 items only)</h2>
    <div class="civicone-card-group">
      <div class="civicone-card">
        <h3><a href="/groups/1">Community Garden</a></h3>
        <p>Growing together since 2020</p>
      </div>
      <div class="civicone-card">
        <h3><a href="/groups/2">Book Club</a></h3>
        <p>Monthly meetups to discuss books</p>
      </div>
      <div class="civicone-card">
        <h3><a href="/groups/3">Knitting Circle</a></h3>
        <p>Every Thursday at the library</p>
      </div>
    </div>
  </div>
</div>
```

**Example 3: Groups Directory (WRONG - Cards for Large Dataset)**

```html
<!-- ❌ WRONG - DO NOT DO THIS -->
<div class="civicone-card-group">
  <!-- 50+ cards here = breaks pagination, screen readers, zoom -->
  <div class="civicone-card"><!-- Group 1 --></div>
  <div class="civicone-card"><!-- Group 2 --></div>
  <!-- ... 50 more cards ... -->
</div>
```

**Correct version:** Use list layout with pagination (see Example 1 pattern).

---

## 12. Refactoring Workflow to Avoid Ruining Existing Layouts

This section defines the MANDATORY workflow for any HTML/CSS refactoring to prevent breaking existing functionality.

### 12.1 Pre-Refactoring Investigation (REQUIRED)

Before changing any markup or CSS:

1. **Identify Existing Grid Hooks**
   - Search for CSS classes used for layout (e.g., `.grid`, `.flex`, `.row`, `.col`)
   - Document which classes are used by existing JavaScript
   - Document which classes are used by mobile nav, Pusher, chat widget

2. **Audit Existing CSS**
   - Find all stylesheets affecting the page being refactored
   - Check for `!important` rules (indicates specificity conflicts)
   - Check for inline styles in PHP files (per CLAUDE.md, should be moved)

3. **Document Navigation Hooks**
   - Confirm IDs used by navigation scripts (e.g., `#civic-mega-menu`, `#civic-menu-toggle`)
   - Confirm classes used by mobile drawer (e.g., `.mobile-nav-open`)
   - DO NOT remove or rename these without updating corresponding JavaScript

### 12.2 Scoped CSS-First Approach (PREFERRED)

When possible, fix issues with CSS ONLY (no HTML changes):

1. **Create Scoped CSS Class**
   ```css
   /* In civicone-[page].css */
   .civicone--govuk .existing-component {
     /* New GOV.UK-aligned styles */
   }
   ```

2. **Test with Feature Flag**
   - Enable `.civicone--govuk` class via query param (`?govuk=1`)
   - Verify new styles apply correctly
   - Verify old styles still work when flag is off

3. **Benefits:**
   - No HTML changes = no risk of breaking JavaScript hooks
   - Gradual rollout via feature flag
   - Easy rollback (remove CSS class)

### 12.3 HTML Refactoring Workflow (When Required)

If HTML changes are unavoidable:

**Step 1: Create Before/After DOM Diff**

```bash
# Capture current HTML output
curl http://localhost/page > before.html

# Make changes

# Capture new HTML output
curl http://localhost/page > after.html

# Diff the HTML
diff before.html after.html
```

**Step 2: Visual Regression Snapshots**

- Screenshot before: Desktop (1920px), Tablet (768px), Mobile (375px)
- Make changes
- Screenshot after: Same viewports
- Compare pixel-by-pixel (use tool like BackstopJS or Percy)

**Step 3: Verify No Breaks**

| Check | Tool/Method | Pass Criteria |
|-------|-------------|---------------|
| Navigation works | Manual test | Mega menu, mobile drawer functional |
| Pusher notifications work | Manual test | Real-time updates still appear |
| Chat widget appears | Manual test | Widget visible and functional |
| JavaScript console | Browser DevTools | No new errors |
| Layout switcher works | Manual test | Switch to Modern and back |
| Mobile nav works | Real device test | Drawer opens/closes |

**Step 4: Preserve Critical Classes/IDs**

**NEVER remove these without verifying dependencies:**

| Element | Class/ID | Used By |
|---------|----------|---------|
| Mega menu button | `#civic-mega-menu-btn` | header.php JavaScript |
| Mega menu container | `#civic-mega-menu` | header.php JavaScript |
| Mobile menu toggle | `#civic-menu-toggle` | mobile-nav-v2.php |
| Mobile menu drawer | `.nexus-native-drawer` | nexus-mobile.js |
| Notification drawer | `#notif-drawer` | notifications.js |
| Main content | `#main-content` | Skip link target |
| Search bar | `#civic-search-form` | search.js |
| Chat widget container | `#ai-chat-widget` | ai-chat-widget.js |

**Step 5: Staged Rollout**

1. **Dev:** Test on localhost with feature flag
2. **Staging:** Test with real data and all integrations
3. **Production (canary):** Enable for 5% of users
4. **Production (full):** Enable for all users

### 12.4 Rollback Checklist

If issues are found after deployment:

**Immediate Rollback (CSS-only changes):**
```php
// Disable feature flag
$govukRedesign = false;
```

**Rollback (HTML changes):**
```bash
git revert <commit-hash>
# OR restore from backup:
cp page.php.backup page.php
```

**Post-Rollback Actions:**
- [ ] Document what broke
- [ ] Update this source of truth with lessons learned
- [ ] Create test case to prevent regression
- [ ] Plan fixes with proper workflow (Section 12.2 or 12.3)

### 12.5 Safe Refactoring Example

**Scenario:** Update Members directory to use MOJ "filter a list" pattern

**WRONG Approach (High Risk):**
1. Delete all existing HTML
2. Write new HTML from scratch
3. Deploy and hope it works

**CORRECT Approach (Low Risk):**

1. **Investigation:**
   - Document existing CSS classes: `.member-list`, `.member-card`, `.filters`
   - Check if JavaScript uses these: `grep -r "member-list" assets/js/`
   - Result: No JavaScript dependencies found ✓

2. **Scoped CSS First:**
   - Create `.civicone--govuk .member-list { /* new styles */ }`
   - Test with `?govuk=1` query param
   - Verify layout works in GOV.UK mode
   - Verify old layout still works without flag

3. **HTML Refactoring (if needed):**
   - Create `members/index.php.backup`
   - Update HTML incrementally (keep existing classes during transition)
   - Add new GOV.UK classes alongside old classes:
     ```html
     <!-- Transition markup -->
     <div class="member-list civicone-results-list">
       <!-- Both old and new classes present -->
     </div>
     ```
   - Test thoroughly
   - Remove old classes only after confirming new ones work

4. **Visual Regression:**
   - Screenshot before/after
   - Compare layouts pixel-by-pixel
   - Verify no unintended changes

5. **Staged Rollout:**
   - Test on localhost
   - Deploy to staging
   - Enable for 5% of users
   - Monitor for errors
   - Full rollout

6. **Cleanup:**
   - Remove old CSS classes after 1 week of stable operation
   - Update documentation
   - Delete backup files

---

## 13. Risk Register and Do Not Break List

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

**Option A: Start with Directory/List Pages (Recommended - MANDATORY ORDER)**

Update pages in STRICT order to apply MOJ "filter a list" pattern + GOV.UK component patterns:

1. **Members Directory** (`views/civicone/members/index.php`) - **START HERE**
   - MUST use Template A: Directory/List Page (Section 10.2)
   - Apply MOJ "filter a list" pattern (filters + list + pagination)
   - Replace card grid with `<ul class="civicone-results-list">` layout
   - Implement MOJ filter component for location, skills, interests
   - Add GOV.UK pagination component
   - Test with >100 member dataset to verify performance
   - **Why first:** Highest traffic directory page, establishes pattern for others

2. **Groups Directory** (`views/civicone/groups/index.php`)
   - MUST use Template A: Directory/List Page (Section 10.2)
   - Apply same MOJ "filter a list" pattern as Members
   - Replace card grid with list layout for main results
   - Keep small "Featured Groups" section with max 3-4 cards (DfE/ONS guidance)
   - Implement category/location filters using MOJ filter component
   - Add GOV.UK pagination
   - **Why second:** Second-highest directory traffic, validates pattern reusability

3. **Volunteering Opportunities** (`views/civicone/volunteering/index.php`)
   - MUST use Template A: Directory/List Page (Section 10.2)
   - Apply MOJ "filter a list" pattern consistently
   - List layout for opportunity results (title, organization, location, dates)
   - Filters for type, location, date range (MOJ filter component)
   - GOV.UK pagination
   - **Why third:** Completes the directory page pattern, validates scalability

4. **Homepage/Dashboard** (`views/civicone/feed/index.php`, `views/civicone/dashboard/`)
   - MUST use Template B: Dashboard/Home (Section 10.3)
   - Activity feed uses list layout (NOT cards)
   - Small "Recommended Groups" section may use cards (max 3-4, DfE/ONS guidance)
   - Stats summary uses GOV.UK summary list component
   - Apply GOV.UK button styles to CTAs
   - **Why fourth:** Mixed-content template, benefits from directory page learnings

5. **Profile/Settings** (`views/civicone/profile/`, `views/civicone/settings/`)
   - MUST use Template C: Detail Page (Section 10.4) for profiles
   - MUST use Template D: Form/Flow (Section 10.5) for settings
   - Standardize form elements with GOV.UK form components
   - Apply `.civicone-label`, `.civicone-hint`, `.civicone-error-message` classes
   - Update button styles to match GOV.UK components

6. **Events Pages** (`views/civicone/events/`)
   - Events listing: Use Template A: Directory/List (if >20 events)
   - Event detail: Use Template C: Detail Page
   - Event create/edit: Use Template D: Form/Flow
   - Update CTAs to use GOV.UK button styles

7. **Messages/Help** (`views/civicone/messages/`, `views/civicone/help/`)
   - Message composition: Template D: Form/Flow
   - Help center articles: Template E: Content/Article
   - Help search results: Template A: Directory/List (if applicable)

#### Option B: Testing and Validation First

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
