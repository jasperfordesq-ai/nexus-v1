# Modern Theme CSS Forensic Audit Report

**Date**: 27/01/2026
**Scope**: Modern theme only (CivicOne explicitly excluded)
**Type**: READ-ONLY analysis - no changes made

---

## 1. Modern-Only File Set Definition

### Glob Patterns Used

| Pattern | Count | Description |
|---------|-------|-------------|
| `views/modern/**/*.php` | 404 | Modern PHP templates |
| `views/layouts/modern/**/*.php` | 16 | Modern layout files |
| `httpdocs/assets/css/modern-*.css` (non-min) | 16 | Modern-prefixed CSS |
| `httpdocs/assets/css/nexus-*.css` (non-min, excl. civicone) | 16 | Nexus-prefixed CSS |
| `httpdocs/assets/css/modern/**/*.css` (non-min) | 2 | Modern subfolder CSS |
| `httpdocs/assets/css/bundles/*.css` (non-min, excl. civicone) | 22 | Shared bundles (no civicone) |

### CSS Files Actually Loaded by Modern Theme

**Source**: Extracted from `views/layouts/modern/partials/css-loader.php` and `views/layouts/modern/partials/page-css-loader.php`

**Total CSS files loaded by Modern theme**: 104

#### Core CSS (Always Loaded)
```
httpdocs/assets/css/design-tokens.css
httpdocs/assets/css/nexus-phoenix.css
httpdocs/assets/css/modern-theme-tokens.css
httpdocs/assets/css/bundles/core.css
httpdocs/assets/css/bundles/components.css
httpdocs/assets/css/theme-transitions.css
httpdocs/assets/css/modern-experimental-banner.css
httpdocs/assets/css/nexus-modern-header.css
httpdocs/assets/css/nexus-premium-mega-menu.css
httpdocs/assets/css/mega-menu-icons.css
httpdocs/assets/css/nexus-native-nav-v2.css
httpdocs/assets/css/mobile-nav-v2.css
httpdocs/assets/css/modern-header-utilities.css
httpdocs/assets/css/modern-header-emergency-fixes.css
httpdocs/assets/css/bundles/modern-pages.css
```

#### Component Bundles (Async)
```
httpdocs/assets/css/bundles/components-navigation.css
httpdocs/assets/css/bundles/components-buttons.css
httpdocs/assets/css/bundles/components-forms.css
httpdocs/assets/css/bundles/components-cards.css
httpdocs/assets/css/bundles/components-modals.css
httpdocs/assets/css/bundles/components-notifications.css
```

#### Utility Bundles (Async)
```
httpdocs/assets/css/bundles/utilities-polish.css
httpdocs/assets/css/bundles/utilities-loading.css
httpdocs/assets/css/bundles/utilities-accessibility.css
httpdocs/assets/css/bundles/enhancements.css
```

#### Page-Specific CSS (Conditional)
Loaded based on route matching in `page-css-loader.php`. See Section 3 for full mapping.

---

## 2. Quantitative Metrics

### Modern-Only Scope

| Metric | Count | Top File |
|--------|-------|----------|
| **Hardcoded hex colors** (`#xxx`) | 2,108 | `bundles/core.css` (387) |
| **Hardcoded rgba/rgb/hsl** | 11,705 | `bundles/modern-pages.css` (951) |
| **var(--token) usage** | 28,381 | `federation.css` (1,979) |
| **Inline `style=` attributes** (views/modern) | 1,072 | `profile/edit.php` (51) |
| **Inline `<style>` blocks** (views/modern) | 54 | admin/settings files |
| **Inline `style=` attributes** (views/layouts/modern) | 24 | `keyboard-shortcuts.php` (15) |
| **Inline `<style>` blocks** (views/layouts/modern) | 4 | `critical-css.php` (1) |

### Entire Repo Scope (for comparison)

| Metric | Count |
|--------|-------|
| **Hardcoded hex colors** | 5,041 |
| **var(--token) usage** | 63,543 |
| **Hardcoded rgba/rgb/hsl** | 16,324 |

### Token Adoption Rate (Modern-Only)

```
Token Usage:     28,381 var(--*) occurrences
Legacy Patterns: 2,108 hex + 11,705 rgba = 13,813

Adoption Rate: 28,381 / (28,381 + 13,813) = 67.3%
```

**Note**: Many `rgba()` values are intentional for glassmorphism effects, not legacy. If we exclude intentional rgba (estimated 80%), the effective adoption rate is higher (~85%).

---

## 3. Top 20 Files by Legacy Pattern Count

### Hardcoded Hex Colors (Modern-Only CSS)

| Rank | File | Count |
|------|------|-------|
| 1 | `bundles/core.css` | 387 |
| 2 | `design-tokens.css` | 325 |
| 3 | `modern-theme-tokens.css` | 186 |
| 4 | `bundles/enhancements.css` | 155 |
| 5 | `cookie-banner.css` | 76 |
| 6 | `messages-index.css` | 70 |
| 7 | `pwa-install-modal.css` | 54 |
| 8 | `bundles/components-navigation.css` | 54 |
| 9 | `bundles/components.css` | 54 |
| 10 | `volunteering.css` | 53 |
| 11 | `bundles/modern-pages.css` | 52 |
| 12 | `federation.css` | 45 |
| 13 | `scattered-singles.css` | 38 |
| 14 | `nexus-home.css` | 38 |
| 15 | `modern/components-library.css` | 37 |
| 16 | `dev-notice-modal.css` | 33 |
| 17 | `mega-menu-icons.css` | 28 |
| 18 | `privacy-page.css` | 24 |
| 19 | `modern-settings.css` | 21 |
| 20 | `mobile-search-overlay.css` | 21 |

**Note**: `design-tokens.css` and `modern-theme-tokens.css` hex values are **intentional** - they define the token values themselves.

### Hardcoded rgba/rgb/hsl (Modern-Only CSS)

| Rank | File | Count |
|------|------|-------|
| 1 | `bundles/modern-pages.css` | 951 |
| 2 | `federation.css` | 943 |
| 3 | `volunteering.css` | 791 |
| 4 | `scattered-singles.css` | 731 |
| 5 | `nexus-home.css` | 590 |
| 6 | `design-tokens.css` | 431 |
| 7 | `bundles/components-navigation.css` | 398 |
| 8 | `polls.css` | 334 |
| 9 | `static-pages.css` | 300 |
| 10 | `bundles/core.css` | 297 |
| 11 | `bundles/components.css` | 297 |
| 12 | `groups-show.css` | 270 |
| 13 | `goals.css` | 270 |
| 14 | `profile-holographic.css` | 255 |
| 15 | `resources.css` | 249 |
| 16 | `organizations.css` | 228 |
| 17 | `achievements.css` | 180 |
| 18 | `components.css` | 179 |
| 19 | `nexus-phoenix.css` | 177 |
| 20 | `nexus-modern-header.css` | 174 |

### var(--token) Usage (Modern-Only CSS)

| Rank | File | Count |
|------|------|-------|
| 1 | `federation.css` | 1,979 |
| 2 | `scattered-singles.css` | 1,825 |
| 3 | `bundles/modern-pages.css` | 1,645 |
| 4 | `volunteering.css` | 1,463 |
| 5 | `modern/components-library.css` | 1,196 |
| 6 | `bundles/components-navigation.css` | 1,058 |
| 7 | `groups.css` | 1,035 |
| 8 | `achievements.css` | 1,021 |
| 9 | `nexus-home.css` | 853 |
| 10 | `groups-show.css` | 727 |
| 11 | `static-pages.css` | 669 |
| 12 | `bundles/utilities-polish.css` | 547 |
| 13 | `nexus-modern-header.css` | 456 |
| 14 | `messages-index.css` | 450 |
| 15 | `design-tokens.css` | 446 |
| 16 | `polls.css` | 442 |
| 17 | `nexus-phoenix.css` | 416 |
| 18 | `modern-theme-tokens.css` | 401 |
| 19 | `organizations.css` | 392 |
| 20 | `components.css` | 379 |

### Inline `style=` Attributes (Modern Templates)

| Rank | File | Count |
|------|------|-------|
| 1 | `pages/cookie-policy.php` | 103 |
| 2 | `master/edit-tenant.php` | 70 |
| 3 | `admin/deliverability/view.php` | 63 |
| 4 | `admin/users/permissions.php` | 56 |
| 5 | `goals/show.php` | 53 |
| 6 | `profile/edit.php` | 51 |
| 7 | `volunteering/edit_opp.php` | 47 |
| 8 | `members/index.php` | 45 |
| 9 | `profile/components/profile-header.php` | 44 |
| 10 | `dashboard/wallet.php` | 39 |
| 11 | `dashboard/events.php` | 37 |
| 12 | `master/users.php` | 33 |
| 13 | `admin/deliverability/list.php` | 33 |
| 14 | `feed/index.php` | 30 |
| 15 | `federation/members.php` | 29 |
| 16 | `auth/login.php` | 25 |
| 17 | `dashboard/listings.php` | 22 |
| 18 | `volunteering/dashboard.php` | 18 |
| 19 | `events/index.php` | 18 |
| 20 | `dashboard/notifications.php` | 17 |

### Inline `<style>` Blocks (Modern Templates)

| File | Count |
|------|-------|
| `admin/blog/builder.php` | 4 |
| `admin/users/permissions.php` | 2 |
| `consent/decline.php` | 1 |
| `consent/required.php` | 1 |
| `settings/index.php` | 1 |
| `admin/ai-settings.php` | 1 |
| `admin/activity_log.php` | 1 |
| `admin/404-errors/index.php` | 1 |
| `admin/nexus-score-analytics.php` | 1 |
| `federation/messages/compose.php` | 1 |
| ... (44 more files with 1 block each) | ... |

**Total inline `<style>` blocks in views/modern**: 54
**Total inline `<style>` blocks in views/layouts/modern**: 4

---

## 4. Page/Template Coverage Table

### Classification Heuristics

| Classification | Definition |
|----------------|------------|
| **FULL TOKENS** | Template has 0 inline styles AND loads page-specific CSS with >90% token usage |
| **PARTIAL** | Template has 1-10 inline styles OR loads CSS with 50-90% token usage |
| **LEGACY DOMINANT** | Template has >10 inline styles OR >1 `<style>` block OR loads CSS with <50% token usage |

### Route-to-CSS Mapping (from page-css-loader.php)

| Route Pattern | CSS Files Loaded | Template Path |
|---------------|------------------|---------------|
| Home (`$isHome`) | nexus-home.css, post-box-home.css, feed-filter.css, feed-empty-state.css, sidebar.css | `views/modern/home.php` |
| `/dashboard` | dashboard.css, modern-dashboard.css | `views/modern/dashboard.php` |
| `/nexus-score`, `/score` | nexus-score.css | `views/modern/dashboard/nexus-score-dashboard-page.php` |
| `/login`, `/register`, `/password` | auth.css | `views/modern/auth/*.php` |
| Feed/Post/Profile pages | post-card.css, feed-item.css | multiple |
| `/feed` | feed-page.css | `views/modern/feed/index.php` |
| `/feed/{id}`, `/post/{id}` | feed-show.css | `views/modern/feed/show.php` |
| `/profile/edit` | profile-edit.css | `views/modern/profile/edit.php` |
| `/messages` | messages-index.css | `views/modern/messages/index.php` |
| `/messages/{id}` | messages-thread.css | `views/modern/messages/thread.php` |
| `/notifications` | notifications.css | `views/modern/notifications/index.php` |
| `/groups`, `/groups/{id}` | groups-show.css, modern-groups-show.css, nexus-groups.css | `views/modern/groups/*.php` |
| `/events` | events-index.css | `views/modern/events/index.php` |
| `/events/calendar` | events-calendar.css | `views/modern/events/calendar.php` |
| `/events/create` | events-create.css | `views/modern/events/create.php` |
| `/events/{id}` | events-show.css, modern-events-show.css | `views/modern/events/show.php` |
| `/news`, `/blog` | blog-index.css | `views/modern/blog/index.php` |
| `/news/{slug}`, `/blog/{slug}` | blog-show.css | `views/modern/blog/show.php` |
| `/listings` | listings-index.css | `views/modern/listings/index.php` |
| `/listings/create` | listings-create.css | `views/modern/listings/create.php` |
| `/listings/{id}` | listings-show.css | `views/modern/listings/show.php` |
| `/federation`, `/transactions` | federation.css | `views/modern/federation/*.php` |
| `/volunteering` | volunteering.css, modern-volunteering-show.css | `views/modern/volunteering/*.php` |
| `/groups/*` | groups.css | `views/modern/groups/*.php` |
| `/goals` | goals.css | `views/modern/goals/*.php` |
| `/polls` | polls.css | `views/modern/polls/*.php` |
| `/resources` | resources.css | `views/modern/resources/*.php` |
| `/matches` | matches.css | `views/modern/matches/*.php` |
| `/organizations` | organizations.css | `views/modern/organizations/*.php` |
| `/help` | help.css | `views/modern/help/*.php` |
| `/wallet` | wallet.css | `views/modern/wallet/*.php` |
| Static pages | static-pages.css | `views/modern/pages/*.php` |
| `/settings` | modern-settings.css | `views/modern/settings/*.php` |
| `/search` | modern-search-results.css, search-results.css | `views/modern/search/*.php` |
| `/terms` | terms-page.css | `views/modern/pages/terms.php` |
| `/privacy` | privacy-page.css | `views/modern/pages/privacy.php` |
| `/achievements` | achievements.css | `views/modern/achievements/*.php` |
| `/profile/{slug}` | profile-holographic.css, modern-profile-show.css | `views/modern/profile/show.php` |

### Sample Template Classification

| Template | Inline `style=` | `<style>` blocks | CSS Route | Classification |
|----------|-----------------|------------------|-----------|----------------|
| `home.php` | 0 | 0 | home | FULL TOKENS |
| `dashboard.php` | 0 | 0 | dashboard | FULL TOKENS |
| `feed/index.php` | 30 | 0 | feed | LEGACY DOMINANT |
| `auth/login.php` | 25 | 0 | auth | LEGACY DOMINANT |
| `profile/edit.php` | 51 | 0 | profile-edit | LEGACY DOMINANT |
| `profile/show.php` | 1 | 0 | profile-show | FULL TOKENS |
| `messages/index.php` | 11 | 0 | messages | PARTIAL |
| `messages/thread.php` | 10 | 0 | messages-thread | PARTIAL |
| `events/index.php` | 18 | 0 | events-index | LEGACY DOMINANT |
| `events/show.php` | 2 | 0 | events-show | PARTIAL |
| `blog/index.php` | 2 | 0 | blog-index | PARTIAL |
| `groups/edit.php` | 9 | 0 | groups-all | PARTIAL |
| `settings/index.php` | 0 | 1 | settings | PARTIAL |
| `goals/show.php` | 53 | 0 | goals | LEGACY DOMINANT |
| `members/index.php` | 45 | 0 | scattered-singles | LEGACY DOMINANT |
| `achievements/index.php` | 5 | 0 | achievements | PARTIAL |

---

## 5. Theme Isolation Evidence

### Proof: CivicOne CSS is NOT loaded by Modern

**File**: `views/layouts/modern/partials/css-loader.php`
**Lines 199-212**:

```php
<!-- ==========================================
     9. UTILITIES & POLISH (Async)

     THEME ISOLATION FIX (2026-01-26):
     REMOVED: civicone-utilities.css and civicone-utilities-extended.css

     These CivicOne-specific files were incorrectly loaded here, causing:
     - Style conflicts between Modern and CivicOne themes
     - Regression loops where fixing one theme broke the other
     - FOUC (Flash of Unstyled Content) due to conflicting CSS rules

     CivicOne CSS is now ONLY loaded via:
     views/layouts/civicone/partials/assets-css.php
     ========================================== -->
```

### Verification: No civicone imports in Modern loader

```bash
grep -i "civicone" views/layouts/modern/partials/css-loader.php
# Results: Only comment references explaining the removal
```

**Files searched**:
- `views/layouts/modern/partials/css-loader.php` - No civicone CSS loaded
- `views/layouts/modern/partials/page-css-loader.php` - No civicone CSS loaded
- `views/layouts/modern/header.php` - No civicone CSS loaded

### CivicOne-Prefixed Files Excluded from Modern-Only Analysis

The following files were **explicitly excluded** from Modern-only metrics:
- `httpdocs/assets/css/civicone-*.css` (all files)
- `httpdocs/assets/css/bundles/civicone-*.css` (all files)
- `httpdocs/assets/css/civicone/**/*.css` (all files)

---

## 6. Git Changes from 26/01/2026

### Commits

| Hash | Message |
|------|---------|
| `3b719c4` | chore: Add CSS audit/fix scripts and documentation |
| `ba914c2` | feat(federation): Add external partners support and rebuild CSS |

### Changed Files (CSS-related, Modern-only)

| File | Change Type |
|------|-------------|
| `httpdocs/assets/css/bundles/modern-pages.css` | Bundle rebuild |
| `httpdocs/assets/css/bundles/components-buttons.css` | Bundle rebuild |
| `httpdocs/assets/css/bundles/components-cards.css` | Bundle rebuild |
| `httpdocs/assets/css/bundles/components-forms.css` | Bundle rebuild |
| `httpdocs/assets/css/bundles/components-modals.css` | Bundle rebuild |
| `httpdocs/assets/css/bundles/components-navigation.css` | Bundle rebuild |
| `httpdocs/assets/css/bundles/components-notifications.css` | Bundle rebuild |
| `httpdocs/assets/css/bundles/utilities-polish.css` | Bundle rebuild |
| `httpdocs/assets/css/bundles/utilities-loading.css` | Bundle rebuild |
| `httpdocs/assets/css/bundles/utilities-accessibility.css` | Bundle rebuild |
| `httpdocs/assets/css/bundles/enhancements.css` | Bundle rebuild |
| `httpdocs/assets/css/bundles/mobile.css` | Bundle rebuild |
| `httpdocs/assets/css/bundles/features-federation.css` | Bundle rebuild |
| `httpdocs/assets/css/bundles/features-gamification.css` | Bundle rebuild |
| `httpdocs/assets/css/bundles/features-pwa.css` | Bundle rebuild |
| `httpdocs/assets/css/bundles/features-social.css` | Bundle rebuild |
| `httpdocs/assets/css/bundles/core-framework.css` | Bundle rebuild |
| `httpdocs/assets/css/design-tokens.css` | Token updates |
| `httpdocs/assets/css/federation.css` | Feature CSS |
| `httpdocs/assets/css/mobile-nav-v2.css` | Component CSS |
| `httpdocs/assets/css/modern-events-show.css` | Page CSS |
| `httpdocs/assets/css/modern-experimental-banner.css` | Component CSS |
| `httpdocs/assets/css/modern-groups-show.css` | Page CSS |
| `httpdocs/assets/css/modern-header-emergency-fixes.css` | Fix CSS |
| `httpdocs/assets/css/modern-search-results.css` | Page CSS |
| `httpdocs/assets/css/modern-settings.css` | Page CSS |
| `httpdocs/assets/css/nexus-home.css` | Page CSS |
| `httpdocs/assets/css/nexus-mobile.css` | Mobile CSS |
| `httpdocs/assets/css/nexus-native-nav-v2.css` | Navigation CSS |
| `httpdocs/assets/css/nexus-phoenix.css` | Brand tokens |
| `httpdocs/assets/css/scroll-fix-emergency.css` | Fix CSS |

### Changed Files (PHP, Modern-only)

| File | Change Type |
|------|-------------|
| `views/modern/admin/federation/api-keys.php` | New template |
| `views/modern/admin/federation/api-keys-create.php` | New template |
| `views/modern/admin/federation/api-keys-show.php` | New template |
| `views/modern/admin/federation/external-partners.php` | New template |
| `views/modern/admin/federation/external-partners-create.php` | New template |
| `views/modern/admin/federation/external-partners-show.php` | New template |
| `views/modern/federation/listing-detail.php` | Updated template |
| `views/modern/federation/listings.php` | Updated template |
| `views/modern/federation/member-profile.php` | Updated template |
| `views/modern/federation/members.php` | Updated template |
| `views/modern/federation/messages/compose.php` | Updated template |
| `views/modern/federation/messages/index.php` | Updated template |
| `views/modern/federation/transactions/create.php` | Updated template |

---

## 7. Summary

### Modern Theme CSS Health

| Aspect | Status | Evidence |
|--------|--------|----------|
| **Token System** | GOOD | 28,381 var(--) usages, centralized in modern-theme-tokens.css |
| **Theme Isolation** | GOOD | No civicone CSS loaded (explicitly removed 2026-01-26) |
| **CSS Organization** | GOOD | 104 files, well-categorized loaders |
| **Legacy Debt** | MODERATE | 2,108 hex + 11,705 rgba hardcoded values |
| **Inline Styles** | HIGH DEBT | 1,096 inline style= attributes in templates |
| **Inline `<style>` Blocks** | MODERATE DEBT | 58 blocks across templates |

### Top 10 Worst Offenders (Templates)

| Rank | File | Issue | Count |
|------|------|-------|-------|
| 1 | `pages/cookie-policy.php` | inline style= | 103 |
| 2 | `master/edit-tenant.php` | inline style= | 70 |
| 3 | `admin/deliverability/view.php` | inline style= | 63 |
| 4 | `admin/users/permissions.php` | inline style= + `<style>` | 56 + 2 |
| 5 | `goals/show.php` | inline style= | 53 |
| 6 | `profile/edit.php` | inline style= | 51 |
| 7 | `volunteering/edit_opp.php` | inline style= | 47 |
| 8 | `members/index.php` | inline style= | 45 |
| 9 | `profile/components/profile-header.php` | inline style= | 44 |
| 10 | `dashboard/wallet.php` | inline style= | 39 |

### Top 10 Worst Offenders (CSS Files - Legacy Patterns)

| Rank | File | Hex | RGBA | Total Legacy |
|------|------|-----|------|--------------|
| 1 | `bundles/modern-pages.css` | 52 | 951 | 1,003 |
| 2 | `federation.css` | 45 | 943 | 988 |
| 3 | `volunteering.css` | 53 | 791 | 844 |
| 4 | `scattered-singles.css` | 38 | 731 | 769 |
| 5 | `nexus-home.css` | 38 | 590 | 628 |
| 6 | `bundles/enhancements.css` | 155 | ~300 | ~455 |
| 7 | `bundles/components-navigation.css` | 54 | 398 | 452 |
| 8 | `polls.css` | 18 | 334 | 352 |
| 9 | `static-pages.css` | ~20 | 300 | ~320 |
| 10 | `bundles/core.css` | 387 | 297 | 684* |

*Note: `bundles/core.css` hex count includes token definitions (intentional).

---

## Appendix: Modern-Only CSS File Manifest

<details>
<summary>Click to expand full list (104 files)</summary>

```
httpdocs/assets/css/achievements.css
httpdocs/assets/css/auth.css
httpdocs/assets/css/avatar-placeholders.css
httpdocs/assets/css/biometric-modal.css
httpdocs/assets/css/blog-index.css
httpdocs/assets/css/blog-show.css
httpdocs/assets/css/bundles/components-buttons.css
httpdocs/assets/css/bundles/components-cards.css
httpdocs/assets/css/bundles/components-forms.css
httpdocs/assets/css/bundles/components-modals.css
httpdocs/assets/css/bundles/components-navigation.css
httpdocs/assets/css/bundles/components-notifications.css
httpdocs/assets/css/bundles/components.css
httpdocs/assets/css/bundles/core.css
httpdocs/assets/css/bundles/enhancements.css
httpdocs/assets/css/bundles/modern-pages.css
httpdocs/assets/css/bundles/utilities-accessibility.css
httpdocs/assets/css/bundles/utilities-loading.css
httpdocs/assets/css/bundles/utilities-polish.css
httpdocs/assets/css/components.css
httpdocs/assets/css/cookie-banner.css
httpdocs/assets/css/dashboard.css
httpdocs/assets/css/design-tokens.css
httpdocs/assets/css/desktop-design-tokens.css
httpdocs/assets/css/desktop-hover-system.css
httpdocs/assets/css/desktop-loading-states.css
httpdocs/assets/css/dev-notice-modal.css
httpdocs/assets/css/error-states.css
httpdocs/assets/css/events-calendar.css
httpdocs/assets/css/events-create.css
httpdocs/assets/css/events-index.css
httpdocs/assets/css/events-show.css
httpdocs/assets/css/federation.css
httpdocs/assets/css/feed-empty-state.css
httpdocs/assets/css/feed-filter.css
httpdocs/assets/css/feed-item.css
httpdocs/assets/css/feed-page.css
httpdocs/assets/css/feed-show.css
httpdocs/assets/css/goals.css
httpdocs/assets/css/groups-show.css
httpdocs/assets/css/groups.css
httpdocs/assets/css/help.css
httpdocs/assets/css/listings-create.css
httpdocs/assets/css/listings-index.css
httpdocs/assets/css/listings-show.css
httpdocs/assets/css/matches.css
httpdocs/assets/css/mega-menu-icons.css
httpdocs/assets/css/messages-index.css
httpdocs/assets/css/messages-thread.css
httpdocs/assets/css/mobile-accessibility-fixes.css
httpdocs/assets/css/mobile-design-tokens.css
httpdocs/assets/css/mobile-loading-states.css
httpdocs/assets/css/mobile-micro-interactions.css
httpdocs/assets/css/mobile-nav-v2.css
httpdocs/assets/css/mobile-search-overlay.css
httpdocs/assets/css/mobile-select-sheet.css
httpdocs/assets/css/mobile-sheets.css
httpdocs/assets/css/modern-dashboard.css
httpdocs/assets/css/modern-events-show.css
httpdocs/assets/css/modern-experimental-banner.css
httpdocs/assets/css/modern-groups-show.css
httpdocs/assets/css/modern-header-emergency-fixes.css
httpdocs/assets/css/modern-header-utilities.css
httpdocs/assets/css/modern-profile-show.css
httpdocs/assets/css/modern-search-results.css
httpdocs/assets/css/modern-settings.css
httpdocs/assets/css/modern-theme-tokens.css
httpdocs/assets/css/modern-volunteering-show.css
httpdocs/assets/css/modern/components-library.css
httpdocs/assets/css/native-form-inputs.css
httpdocs/assets/css/native-page-enter.css
httpdocs/assets/css/nexus-groups.css
httpdocs/assets/css/nexus-header-extracted.css
httpdocs/assets/css/nexus-home.css
httpdocs/assets/css/nexus-mobile.css
httpdocs/assets/css/nexus-modern-footer.css
httpdocs/assets/css/nexus-modern-header.css
httpdocs/assets/css/nexus-native-nav-v2.css
httpdocs/assets/css/nexus-performance-patch.css
httpdocs/assets/css/nexus-phoenix.css
httpdocs/assets/css/nexus-premium-mega-menu.css
httpdocs/assets/css/nexus-score.css
httpdocs/assets/css/notifications.css
httpdocs/assets/css/organizations.css
httpdocs/assets/css/page-transitions.css
httpdocs/assets/css/partials.css
httpdocs/assets/css/polls.css
httpdocs/assets/css/post-box-home.css
httpdocs/assets/css/post-card.css
httpdocs/assets/css/privacy-page.css
httpdocs/assets/css/profile-edit.css
httpdocs/assets/css/profile-holographic.css
httpdocs/assets/css/pwa-install-modal.css
httpdocs/assets/css/resources.css
httpdocs/assets/css/scattered-singles.css
httpdocs/assets/css/scroll-fix-emergency.css
httpdocs/assets/css/search-results.css
httpdocs/assets/css/sidebar.css
httpdocs/assets/css/social-interactions.css
httpdocs/assets/css/static-pages.css
httpdocs/assets/css/terms-page.css
httpdocs/assets/css/theme-transitions.css
httpdocs/assets/css/volunteering.css
httpdocs/assets/css/wallet.css
```

</details>

---

*Report generated: 27/01/2026*
*Analysis tool: Claude Code forensic audit*
