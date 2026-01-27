# Phase 15A: Remaining Flash Isolation Audit (Post Phases 12-14)

**Date:** 27 January 2026
**Scope:** Modern theme, /news and /login routes
**Status:** AUDIT COMPLETE - No changes made

---

## 1. Current Async Component Bundles

From `views/layouts/modern/partials/css-loader.php` (lines 127-138):

| Bundle | Line | Loading | Above-Fold Impact |
|--------|------|---------|-------------------|
| `components-navigation.css` | 128 | **ASYNC** | HIGH - header/nav styling |
| `components-buttons.css` | 130 | **ASYNC** | MEDIUM - button ripple/effects |
| `components-forms.css` | 132 | **ASYNC** | HIGH (auth) - form validation styles |
| `components-cards.css` | 134 | ASYNC | LOW - card components |
| `components-modals.css` | 136 | ASYNC | NONE - below fold |
| `components-notifications.css` | 138 | ASYNC | NONE - triggered later |

---

## 2. Above-Fold Dependencies by Route

### 2.1 /news Page

| Element | Async Bundle Dependency | Evidence |
|---------|------------------------|----------|
| `.nexus-utility-bar` | components-navigation.css | Line 29-59 of bundle |
| `.nav-link` | components-navigation.css | Line 94+ of bundle |
| `.nexus-brand-link` | components-navigation.css | Part of header styles |
| Header buttons | components-buttons.css | Ripple effects, `.btn` styling |
| `.news-btn` (Read Article) | components-buttons.css | Button effects |

**Note:** components-forms.css is NOT used above-fold on /news.

### 2.2 /login Page

| Element | Async Bundle Dependency | Evidence |
|---------|------------------------|----------|
| `.nexus-utility-bar` | components-navigation.css | Header styling |
| `.nav-link` | components-navigation.css | Navigation links |
| Form inputs | components-forms.css | Validation states, transitions |
| Submit button | components-buttons.css | Button effects |
| `.auth-card` | **NONE** - inline styles | login.php uses inline `style=""` |

**Critical finding:** The login page (login.php) uses extensive inline styles for:
- Form inputs (lines 73-75, 83-85)
- Submit button (lines 88-90)
- Labels (lines 72, 80)
- Error/success alerts (lines 29, 35)

This means **components-forms.css and components-buttons.css have minimal above-fold impact on /login** - they only add polish (validation animations, ripple effects) not core styling.

---

## 3. Bundle Content Analysis

### components-navigation.css (7 files bundled)
- `nexus-modern-header.css` - Header layout, utility bar, brand
- Navigation links, mega menu triggers
- Mobile nav styles
- **Size:** ~15-20KB estimated

### components-buttons.css (3 files bundled)
- `button-ripple.css` - Material Design ripple effects
- Button state transitions
- `.btn`, `.glass-btn` classes
- **Size:** ~8-12KB estimated

### components-forms.css (4 files bundled)
- `form-validation.css` - Shake animations, error states
- Input focus transitions
- Success/error visual feedback
- **Size:** ~10-15KB estimated

---

## 4. Root Cause: What's Still Async?

After Phases 12-14, the remaining flash sources are:

| Resource | Status | Impact |
|----------|--------|--------|
| nexus-instant-load.js | DISABLED (Phase 12) | FIXED |
| Font Awesome | SYNC on blog/auth (Phase 13) | FIXED |
| Roboto font | SYNC on blog/auth (Phase 14) | FIXED |
| components-navigation.css | **ASYNC** | Header styling delay |
| components-buttons.css | **ASYNC** | Button ripple delay |
| components-forms.css | **ASYNC** | Form validation delay |

**The header navigation styles are the most visible remaining flash.**

---

## 5. Options Analysis

### Option A: Sync components-forms.css + components-buttons.css on Auth Routes

**Scope:** /login, /register, /password only

**Change:**
```php
<?php if ($isAuthPage): ?>
    <?= syncCss('/assets/css/bundles/components-forms.css', $cssVersion, $assetBase) ?>
    <?= syncCss('/assets/css/bundles/components-buttons.css', $cssVersion, $assetBase) ?>
<?php else: ?>
    <?= asyncCss('/assets/css/bundles/components-forms.css', $cssVersion, $assetBase) ?>
    <?= asyncCss('/assets/css/bundles/components-buttons.css', $cssVersion, $assetBase) ?>
<?php endif; ?>
```

**Pros:**
- Targets auth pages specifically
- ~20-25KB render-blocking added

**Cons:**
- Login page already uses inline styles - minimal visible improvement
- Doesn't fix /news header flash

**Risk:** LOW
**Expected improvement:** MINIMAL (login uses inline styles)

---

### Option B: Sync components-navigation.css + components-buttons.css on Blog Routes

**Scope:** /news, /blog, /news/{slug}, /blog/{slug}

**Change:**
```php
<?php if ($isBlogIndex || $isBlogShow): ?>
    <?= syncCss('/assets/css/bundles/components-navigation.css', $cssVersion, $assetBase) ?>
    <?= syncCss('/assets/css/bundles/components-buttons.css', $cssVersion, $assetBase) ?>
<?php else: ?>
    <?= asyncCss('/assets/css/bundles/components-navigation.css', $cssVersion, $assetBase) ?>
    <?= asyncCss('/assets/css/bundles/components-buttons.css', $cssVersion, $assetBase) ?>
<?php endif; ?>
```

**Pros:**
- Fixes header navigation styling flash on /news
- Fixes "Read Article" button styling flash
- ~25-30KB render-blocking added

**Cons:**
- Adds render-blocking CSS to blog routes
- components-navigation.css duplicates some styles from nexus-modern-header.css (already sync)

**Risk:** LOW
**Expected improvement:** MODERATE (header already partially styled by sync CSS)

---

### Option C: Inline Critical Subset

**Scope:** Both routes

**Change:** Extract minimal critical CSS from components-navigation.css and inline in critical-css.php

**Pros:**
- No additional HTTP requests
- Surgical precision

**Cons:**
- Maintenance burden (must keep inline CSS in sync with bundle)
- Risk of style conflicts
- Complex implementation

**Risk:** MEDIUM
**Expected improvement:** HIGH (if done correctly)

---

## 6. Recommendation

### Recommended: Option B (Sync navigation + buttons on Blog)

**Rationale:**

1. **Login page uses inline styles** - Option A would add ~25KB render-blocking CSS with minimal visible improvement because login.php already has inline `style=""` attributes on all form elements.

2. **Blog pages depend more on component bundles** - The /news page uses CSS classes (`.nav-link`, `.news-btn`, header components) that require components-navigation.css and components-buttons.css.

3. **Header flash is most visible** - Users notice header navigation styling shifts more than subtle form validation enhancements.

4. **Acceptable trade-off** - Adding ~25-30KB render-blocking CSS on blog routes is acceptable given:
   - Blog pages already have sync FA (~30KB) and sync Roboto
   - Blog is content-focused, slight delay acceptable for stable rendering
   - Other routes remain performant with async loading

### Alternative: Combined Option B + A

If login form polish is important despite inline styles:

```php
<?php if ($isBlogIndex || $isBlogShow || $isAuthPage): ?>
    <?= syncCss('/assets/css/bundles/components-navigation.css', $cssVersion, $assetBase) ?>
    <?= syncCss('/assets/css/bundles/components-buttons.css', $cssVersion, $assetBase) ?>
<?php endif; ?>
<?php if ($isAuthPage): ?>
    <?= syncCss('/assets/css/bundles/components-forms.css', $cssVersion, $assetBase) ?>
<?php endif; ?>
```

This would sync navigation/buttons for both routes, plus forms specifically for auth.

---

## 7. Summary

| Route | Current Flash Source | Recommended Fix |
|-------|---------------------|-----------------|
| /news | Header navigation styling | Sync components-navigation.css + components-buttons.css |
| /login | Minimal (uses inline styles) | No change needed OR sync forms for polish |

**Primary recommendation:** Implement Option B for blog routes only.

**Secondary consideration:** If auth page polish matters, add components-forms.css sync for auth routes.

---

**Report Generated:** 27 January 2026
**Phase 15A Status:** AUDIT COMPLETE - Recommendation: Option B (sync nav+buttons on blog)
