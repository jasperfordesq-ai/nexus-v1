# Modern Theme CSS - Deployment Readiness Checklist

**Date:** 27 January 2026
**Deployment Target:** Production (project-nexus.ie)
**Scope:** Modern theme only (CivicOne unchanged)

---

## 1. Release Notes Summary

### Phase Overview (26-27 January 2026)

| Phase | Description | Key Commits |
|-------|-------------|-------------|
| **Phase 1** | CSS audit scripts and documentation | `3b719c4` |
| **Phase 2** | Modern theme tokenization (97.4% rgba reduction in strict files) | `b622229` |
| **Phase 4** | CSS color linter and guardrails | `4976f3e` |
| **Phase 6A** | Hex warning reduction (51.1% reduction: 1,252 → 612) | Part of `2269311` |
| **Phase 6B** | RGBA warning reduction (20.8% reduction: 6,773 → 5,362) | `2269311` |
| **Phase 7** | Modern UI Primitives (new file: modern-primitives.css) | `2269311` |
| **Phase 7.1** | PurgeCSS safety for primitives | `2269311` |

### What Changed

#### 1.1 Token Consolidation
- **modern-theme-tokens.css** (70.6 KB) - Single source of truth for :root variables
- Loaded AFTER design-tokens.css and nexus-phoenix.css to win cascade
- Eliminates FOUC from 188 files redefining :root variables

#### 1.2 Literal Color Replacements
- Hardcoded `#hex` values replaced with `var(--color-*)` tokens
- Hardcoded `rgba(r,g,b,a)` values replaced with `var(--effect-*)` tokens
- Dynamic patterns like `rgba(var(--htb-primary-rgb), 0.2)` preserved (correct usage)

#### 1.3 New Primitives File
- **modern-primitives.css** (11.6 KB source, 4.9 KB minified)
- Layout: `.container`, `.stack`, `.cluster`, `.grid`, `.sidebar`
- Spacing: `.gap-*`, `.p-*`, `.px-*`, `.py-*`, `.mt-*`, `.mb-*`
- Typography: `.text-*`, `.font-*`
- Accessibility: `.sr-only`, `.sr-only-focusable`, `.focus-ring`
- Documentation: `docs/modern-ui-primitives.md`

#### 1.4 Linting Infrastructure
- **lint-modern-colors.js** - Color literal detection
- **lint-modern-colors.baseline.json** - Current baseline (5,362 warnings)
- Pre-commit hooks for CSS validation

#### 1.5 PurgeCSS Configuration
- Added safelist entries for all primitives
- Regex patterns for numeric utilities (gap-1, p-4, etc.)
- Verified primitives preserved after purge

### What Did NOT Change
- CivicOne theme (completely excluded)
- Template HTML structure (no PHP changes for styles)
- JavaScript functionality
- Design token values (only how they're referenced)

---

## 2. QA Checklist - Modern Theme

### Pre-Deployment Verification

#### 2.1 Build Validation
- [ ] `npm run build:css` completes without errors
- [ ] `npm run build:css:purge` completes without errors
- [ ] `npm run lint:css` shows no new errors (warnings are informational)
- [ ] `npm run validate:design-tokens` passes
- [ ] All .min.css files generated correctly

#### 2.2 Critical Routes - Visual Inspection

Test each route in **both light and dark modes** on **desktop and mobile**.

| Route | URL Pattern | Check Items |
|-------|-------------|-------------|
| **Home** | `/{tenant}/` | Hero section, cards, navigation, footer |
| **Dashboard** | `/{tenant}/dashboard` | Stats cards, activity feed, sidebar |
| **Profile** | `/{tenant}/profile/{id}` | Avatar, bio section, tabs, badges |
| **Messages** | `/{tenant}/messages` | Thread list, message bubbles, compose |
| **Groups** | `/{tenant}/groups` | Group cards, filters, create modal |
| **Events** | `/{tenant}/events` | Event cards, calendar, RSVP buttons |
| **Volunteering** | `/{tenant}/volunteering` | Opportunity cards, application forms |
| **Federation** | `/{tenant}/federation` | Partner cards, connection status |
| **Settings** | `/{tenant}/settings` | Form inputs, toggles, save buttons |
| **Auth** | `/{tenant}/login` | Login form, social buttons, errors |

#### 2.3 Visual State Checks

| Component | States to Verify |
|-----------|------------------|
| **Buttons** | Default, hover, focus, active, disabled |
| **Links** | Default, hover, focus, visited |
| **Form Inputs** | Default, focus, error, disabled, placeholder |
| **Cards** | Default, hover elevation, focus outline |
| **Modals** | Backdrop, content, close button, focus trap |
| **Dropdowns** | Closed, open, item hover, selected |
| **Navigation** | Desktop menu, mobile menu, mega menu |
| **Cookie Banner** | Initial display, preferences modal |

#### 2.4 Functional Checks

| Feature | Test Actions |
|---------|--------------|
| **Header Navigation** | Click all main nav items, verify mega menu opens |
| **Mobile Navigation** | Open/close hamburger, verify slide panel |
| **Theme Toggle** | Switch light/dark, verify colors update |
| **Forms** | Submit with validation errors, verify error styling |
| **Notifications** | Trigger toast notification, verify visibility |
| **Pagination** | Click page numbers, verify active state |
| **Search** | Open search overlay, verify input focus |
| **Modals** | Open/close modal, verify backdrop blur |

#### 2.5 Accessibility Checks

- [ ] Focus indicators visible on all interactive elements
- [ ] Skip links appear on keyboard focus
- [ ] Color contrast meets WCAG AA (4.5:1 for text)
- [ ] No content shift on page load (no FOUC)
- [ ] Screen reader announces dynamic content changes

#### 2.6 Browser Testing Matrix

| Browser | Desktop | Mobile |
|---------|---------|--------|
| Chrome 120+ | [ ] | [ ] |
| Firefox 120+ | [ ] | [ ] |
| Safari 17+ | [ ] | [ ] |
| Edge 120+ | [ ] | [ ] |
| iOS Safari | N/A | [ ] |
| Chrome Android | N/A | [ ] |

---

## 3. Rollback Plan

### 3.1 Quick Rollback - Disable Primitives Only

If `modern-primitives.css` causes issues but other changes are fine:

**Step 1:** Comment out in `views/layouts/modern/partials/css-loader.php`:
```php
<!-- TEMPORARILY DISABLED
<?= syncCss('/assets/css/modern-primitives.css', $cssVersion, $assetBase) ?>
-->
```

**Step 2:** Deploy css-loader.php only:
```bash
scp -i ~/.ssh/id_ed25519 "views/layouts/modern/partials/css-loader.php" \
  jasper@35.205.239.67:/var/www/vhosts/project-nexus.ie/views/layouts/modern/partials/
```

**Restore:** Uncomment the line and redeploy.

### 3.2 Partial Rollback - Revert Phase 7 Only

If primitives + safelist changes cause issues:

```bash
# Revert to before Phase 7 commit
git revert 2269311 --no-commit

# Or selectively restore files:
git checkout 4976f3e -- httpdocs/assets/css/modern-primitives.css
git checkout 4976f3e -- httpdocs/assets/css/modern-primitives.min.css
git checkout 4976f3e -- purgecss.config.js
git checkout 4976f3e -- views/layouts/modern/partials/css-loader.php
```

### 3.3 Full Rollback - Revert All CSS Phases

If all recent CSS changes cause issues:

**Option A - Revert commits:**
```bash
# Revert in reverse order
git revert 2269311  # Phase 6B/7/7.1
git revert 4976f3e  # Phase 4
git revert b622229  # Phase 2
git revert 3b719c4  # Phase 1
```

**Option B - Restore pre-Phase bundles:**
```bash
# Find commit before CSS phases
git log --oneline --before="2026-01-26" -5

# Hard reset to that commit (DESTRUCTIVE)
git reset --hard <commit-hash>
```

### 3.4 Emergency CSS Fix

If a specific CSS rule breaks something:

**Step 1:** Identify the problematic rule in browser DevTools

**Step 2:** Add override to `scroll-fix-emergency.css` (loaded last):
```css
/* EMERGENCY: Override broken rule from <file> */
.broken-selector {
    property: correct-value !important;
}
```

**Step 3:** Deploy emergency file only:
```bash
scp -i ~/.ssh/id_ed25519 "httpdocs/assets/css/scroll-fix-emergency.css" \
  jasper@35.205.239.67:/var/www/vhosts/project-nexus.ie/httpdocs/assets/css/
```

### 3.5 Rollback Verification

After any rollback, verify:
- [ ] Site loads without console errors
- [ ] No missing styles (check Network tab for 404s)
- [ ] Colors render correctly
- [ ] Hover/focus states work
- [ ] Dark mode toggle functions

---

## 4. Monitoring List

### 4.1 CSS Metrics to Track

| Metric | Baseline | Alert Threshold |
|--------|----------|-----------------|
| **Total CSS bundle size** | Track after deploy | +10% increase |
| **Lint warnings** | 5,362 | Any increase |
| **Lint errors** | 0 | Any > 0 |
| **Console CSS errors** | 0 | Any > 0 |
| **CSS 404s** | 0 | Any > 0 |

### 4.2 Performance Metrics

| Metric | Where to Check | Watch For |
|--------|----------------|-----------|
| **CLS (Cumulative Layout Shift)** | PageSpeed Insights | > 0.1 |
| **FCP (First Contentful Paint)** | Lighthouse | Regression from baseline |
| **FOUC indicators** | Visual inspection | Flash of wrong colors |
| **CSS load time** | Network tab | Individual file > 500ms |

### 4.3 Error Monitoring

**Console Errors:**
```javascript
// Check for CSS-related console errors
window.addEventListener('error', (e) => {
  if (e.message.includes('CSS') || e.message.includes('stylesheet')) {
    console.error('CSS Error:', e.message);
  }
});
```

**Network Errors:**
- Monitor for 404s on any `/assets/css/` path
- Monitor for slow responses (> 1s) on CSS files

### 4.4 User-Reported Issues

Watch for reports of:
- "Colors look wrong"
- "Page flashes before loading"
- "Button doesn't highlight on hover"
- "Can't see where I'm focused"
- "Dark mode doesn't work"

### 4.5 Automated Checks

**Post-Deploy Script:**
```bash
# Run lint and compare to baseline
npm run lint:css 2>&1 | grep "warnings"

# Check design tokens not corrupted
npm run validate:design-tokens

# Verify key files exist and have content
curl -s -o /dev/null -w "%{http_code}" https://project-nexus.ie/assets/css/modern-primitives.min.css
curl -s -o /dev/null -w "%{http_code}" https://project-nexus.ie/assets/css/modern-theme-tokens.css
```

---

## 5. Deployment Commands

### 5.1 Standard Deployment

```bash
# Preview what will be deployed
npm run deploy:preview

# Deploy last commit
npm run deploy

# Or deploy specific files
npm run deploy:changed
```

### 5.2 CSS-Only Deployment

If only CSS files need updating:

```bash
# Deploy all CSS assets
scp -i ~/.ssh/id_ed25519 -r httpdocs/assets/css/* \
  jasper@35.205.239.67:/var/www/vhosts/project-nexus.ie/httpdocs/assets/css/
```

### 5.3 Post-Deploy Verification

```bash
# Clear CloudFlare cache (if applicable)
# Check site loads
curl -I https://project-nexus.ie/

# Check CSS file headers
curl -I https://project-nexus.ie/assets/css/modern-primitives.min.css

# Check server logs
ssh -i ~/.ssh/id_ed25519 jasper@35.205.239.67 \
  "tail -20 /var/www/vhosts/project-nexus.ie/logs/error.log"
```

---

## 6. Sign-Off Checklist

### Pre-Deploy Approvals

- [ ] **Build passes:** All npm scripts complete without errors
- [ ] **QA complete:** All 10 critical routes tested
- [ ] **Visual review:** Light/dark modes verified
- [ ] **Mobile verified:** Responsive behavior confirmed
- [ ] **Accessibility:** Focus states and contrast verified

### Deploy Authorization

- [ ] **Deployer:** _________________
- [ ] **Date/Time:** _________________
- [ ] **Commit hash:** `2269311` (or latest)

### Post-Deploy Confirmation

- [ ] Site loads correctly
- [ ] No console errors
- [ ] No CSS 404s
- [ ] Performance metrics stable
- [ ] User feedback positive

---

## 7. Contact & Escalation

| Issue Type | Action |
|------------|--------|
| **Visual bug** | Check DevTools, add emergency override |
| **Missing styles** | Check Network tab, verify file deployed |
| **Performance regression** | Check Lighthouse, consider rollback |
| **Critical breakage** | Execute rollback plan section 3.2 or 3.3 |

---

**Document Created:** 27 January 2026
**Status:** Ready for Deployment
