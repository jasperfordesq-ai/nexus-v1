# CivicOne GOV.UK Migration Plan

## Executive Summary

The CivicOne theme currently uses **250+ custom CSS classes** across **55+ files** that do not conform to the GOV.UK Design System. This document outlines a phased migration plan to achieve full GOV.UK compliance.

**Audit Date:** 2026-01-22
**Target Completion:** Phased over 6 phases
**Risk Level:** Medium-High (significant visual changes expected)

---

## Current State vs Target State

| Aspect | Current State | Target State |
|--------|---------------|--------------|
| Card Components | `civic-card`, `fb-card`, `news-card` | `govuk-summary-card` or semantic HTML |
| Hero Banners | Gradient backgrounds, decorative orbs | Plain `govuk-heading-xl` + `govuk-body-l` |
| Buttons | `nexus-btn-*`, `fds-btn-*`, `civic-button` | `govuk-button` variants only |
| Badges/Tags | Custom `badge-*` system (80+ classes) | `govuk-tag` with colour variants |
| Modals | Custom overlay systems | GOV.UK pattern: separate pages |
| Feed System | `fds-*`, `fb-card`, `post-card` | Summary lists or custom accessible pattern |
| Colours | Gradients, glassmorphism, custom vars | GOV.UK colour palette only |
| Dark Mode | Supported | **Removed** (GOV.UK is light-only) |

---

## Phase Overview

| Phase | Focus | Files | Priority | Estimated Scope |
|-------|-------|-------|----------|-----------------|
| 1 | Foundation & Buttons | 15 | CRITICAL | Remove remaining hero patterns, standardise buttons |
| 2 | Card Components | 70+ | CRITICAL | Replace `civic-card` with `govuk-summary-card` |
| 3 | Feed & Post System | 10 | HIGH | Refactor feed items to GOV.UK patterns |
| 4 | Badge & Tag System | 6 | MEDIUM | Replace custom badges with `govuk-tag` |
| 5 | Modals & Overlays | 5 | MEDIUM | Convert to separate pages or GOV.UK patterns |
| 6 | Cleanup & Validation | All | LOW | Remove unused CSS, final WCAG audit |

---

## Phase 1: Foundation & Buttons

### Objective
Complete the hero removal and standardise all buttons to GOV.UK patterns.

### Tasks

#### 1.1 Remove Remaining Hero Gradients
**Files affected:** 30+ files using `*-hero-gradient` variables

```
views/civicone/volunteering/show_org.php
views/civicone/volunteering/edit_org.php
views/civicone/volunteering/edit_opp.php
views/civicone/volunteering/create_opp.php
views/civicone/groups/invite.php
views/civicone/groups/edit.php
views/civicone/groups/discussions/*.php
views/civicone/matches/*.php
views/civicone/dashboard/*.php
views/civicone/achievements/*.php
views/civicone/organizations/*.php
views/civicone/master/*.php
views/civicone/ai/index.php
views/civicone/auth/*.php
```

**Action:**
- Remove all `$hero_gradient` PHP variables
- Remove all `htb-hero-gradient-*`, `mt-hero-gradient-*` CSS classes
- Replace with plain `govuk-heading-xl` headers

#### 1.2 Standardise Button Classes
**Current patterns to replace:**

| Current Class | Replace With |
|---------------|--------------|
| `nexus-btn` | `govuk-button` |
| `nexus-btn-primary` | `govuk-button` |
| `nexus-btn-secondary` | `govuk-button govuk-button--secondary` |
| `nexus-btn-danger` | `govuk-button govuk-button--warning` |
| `nexus-btn-sm` | `govuk-button` (GOV.UK has no size variants) |
| `fds-btn-primary` | `govuk-button` |
| `fds-btn-secondary` | `govuk-button govuk-button--secondary` |
| `civic-button` | `govuk-button` |
| `civicone-button` | `govuk-button` |

**Files affected:**
```
views/civicone/master/dashboard.php
views/civicone/master/users.php
views/civicone/master/edit-tenant.php
views/civicone/partials/home-composer.php
views/civicone/partials/feed_item.php
```

#### 1.3 Remove Decorative Elements
**Elements to remove:**
- `holo-orb-*` classes (holographic orbs)
- `org-hero-pattern` (decorative patterns)
- `news-hero-divider` (decorative dividers)

### Deliverables
- [ ] All `*-hero-gradient` variables removed
- [ ] All pages use `govuk-heading-xl` for titles
- [ ] All buttons use `govuk-button` classes
- [ ] All decorative elements removed
- [ ] CSS files cleaned of hero/gradient rules

### Validation
```bash
# Should return 0 results after Phase 1
grep -r "hero-gradient" views/civicone/
grep -r "nexus-btn" views/civicone/
grep -r "fds-btn" views/civicone/
grep -r "holo-orb" views/civicone/
```

---

## Phase 2: Card Components

### Objective
Replace all custom card patterns with GOV.UK Summary Card or semantic HTML.

### GOV.UK Summary Card Pattern

```html
<!-- GOV.UK Summary Card -->
<div class="govuk-summary-card">
  <div class="govuk-summary-card__title-wrapper">
    <h2 class="govuk-summary-card__title">Card Title</h2>
    <ul class="govuk-summary-card__actions">
      <li class="govuk-summary-card__action">
        <a class="govuk-link" href="#">Action<span class="govuk-visually-hidden"> for Card Title</span></a>
      </li>
    </ul>
  </div>
  <div class="govuk-summary-card__content">
    <dl class="govuk-summary-list">
      <div class="govuk-summary-list__row">
        <dt class="govuk-summary-list__key">Key</dt>
        <dd class="govuk-summary-list__value">Value</dd>
      </div>
    </dl>
  </div>
</div>
```

### Tasks

#### 2.1 Create GOV.UK Card Component
**File:** `views/civicone/components/govuk/summary-card.php`

```php
<?php
/**
 * GOV.UK Summary Card Component
 *
 * @param string $title - Card title
 * @param array $actions - Array of ['label' => 'Edit', 'url' => '/edit', 'hidden_text' => 'item name']
 * @param array $rows - Array of ['key' => 'Label', 'value' => 'Content']
 */
function govuk_summary_card($title, $actions = [], $rows = []) {
    // Implementation
}
```

#### 2.2 Replace civic-card Usage
**Files affected (by priority):**

**Priority 1 - Static Pages (10 files):**
```
views/civicone/pages/about.php
views/civicone/pages/about-story.php
views/civicone/pages/accessibility.php
views/civicone/pages/contact.php
views/civicone/pages/faq.php
views/civicone/pages/how-it-works.php
views/civicone/pages/partner.php
views/civicone/pages/terms.php
views/civicone/pages/impact-summary.php
views/civicone/pages/strategic-plan.php
```

**Priority 2 - Feature Pages (15 files):**
```
views/civicone/wallet/index.php
views/civicone/help/index.php
views/civicone/help/show.php
views/civicone/resources/index.php
views/civicone/polls/index.php
views/civicone/polls/show.php
views/civicone/polls/create.php
views/civicone/goals/index.php
views/civicone/goals/create.php
views/civicone/messages/index.php
views/civicone/messages/thread.php
views/civicone/events/show.php
views/civicone/groups/show.php
views/civicone/consent/decline.php
views/civicone/dashboard/partials/_overview.php
```

**Priority 3 - Complex Pages (10 files):**
```
views/civicone/volunteering/create_opp.php
views/civicone/demo/home.php
views/civicone/organizations/*.php
```

#### 2.3 Card Mapping Guide

| Current Pattern | GOV.UK Replacement |
|-----------------|-------------------|
| `<div class="civic-card">` | `<div class="govuk-summary-card">` |
| `<div class="civic-card-header">` | `<div class="govuk-summary-card__title-wrapper">` |
| `<h3 class="civic-card-title">` | `<h2 class="govuk-summary-card__title">` |
| `<div class="civic-card-body">` | `<div class="govuk-summary-card__content">` |
| `<div class="civic-card-footer">` | Actions in `__title-wrapper` or remove |

#### 2.4 Alternative: Simple Bordered Sections
For content that doesn't fit Summary Card pattern:

```html
<!-- Simple bordered section -->
<div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6;">
  <h2 class="govuk-heading-m">Section Title</h2>
  <p class="govuk-body">Content here...</p>
</div>
```

### Deliverables
- [ ] `govuk/summary-card.php` component created
- [ ] All `civic-card` replaced in static pages
- [ ] All `civic-card` replaced in feature pages
- [ ] All `civic-card` replaced in complex pages
- [ ] CSS `civic-card` rules removed

### Validation
```bash
# Should return 0 results after Phase 2
grep -r "civic-card" views/civicone/
grep -r "civicone-card" views/civicone/
```

---

## Phase 3: Feed & Post System

### Objective
Refactor the feed/post display system to use GOV.UK-compliant patterns.

### Challenge
GOV.UK does not have a "social feed" pattern. Options:

1. **Summary List** - For structured data display
2. **Custom accessible pattern** - Following GOV.UK principles
3. **Simple list with dividers** - Minimal styling

### Tasks

#### 3.1 Analyse Current Feed Structure
**Files to audit:**
```
views/civicone/partials/feed_item.php
views/civicone/components/shared/post-card.php
views/civicone/feed/index.php
views/civicone/blog/partials/feed-items.php
views/civicone/home.php
```

**Current classes to replace:**
- `fb-card` - Main feed card container
- `post-card` - Post display wrapper
- `post-header`, `post-avatar`, `post-content`, `post-actions`
- `fds-create-post` - Composer box
- `news-card-*` - Blog/news items

#### 3.2 Design GOV.UK-Compliant Feed Pattern

**Option A: Summary List Pattern**
```html
<dl class="govuk-summary-list">
  <div class="govuk-summary-list__row">
    <dt class="govuk-summary-list__key">
      <span class="govuk-visually-hidden">Posted by</span>
      John Smith
    </dt>
    <dd class="govuk-summary-list__value">
      <p class="govuk-body">Post content here...</p>
      <p class="govuk-body-s govuk-!-margin-bottom-0">
        <time datetime="2026-01-22">22 January 2026</time>
      </p>
    </dd>
    <dd class="govuk-summary-list__actions">
      <a class="govuk-link" href="#">View</a>
    </dd>
  </div>
</dl>
```

**Option B: Simple List with Cards**
```html
<ul class="govuk-list">
  <li>
    <div class="govuk-summary-card">
      <div class="govuk-summary-card__title-wrapper">
        <h3 class="govuk-summary-card__title">John Smith</h3>
        <p class="govuk-body-s">22 January 2026</p>
      </div>
      <div class="govuk-summary-card__content">
        <p class="govuk-body">Post content...</p>
      </div>
    </div>
  </li>
</ul>
```

#### 3.3 Create Feed Components
**New files:**
```
views/civicone/components/govuk/feed-item.php
views/civicone/components/govuk/feed-list.php
views/civicone/components/govuk/post-composer.php
```

#### 3.4 Replace News Card System
**Current:** `news-card-*` classes in blog
**Replace with:** Summary cards or simple article list

```html
<article class="govuk-!-margin-bottom-6">
  <h2 class="govuk-heading-m">
    <a class="govuk-link" href="/blog/123">Article Title</a>
  </h2>
  <p class="govuk-body-s govuk-!-margin-bottom-2">
    <time datetime="2026-01-22">22 January 2026</time> | 5 min read
  </p>
  <p class="govuk-body">Article excerpt...</p>
</article>
```

### Deliverables
- [ ] Feed item component using GOV.UK patterns
- [ ] Post composer using GOV.UK form patterns
- [ ] Blog/news items refactored
- [ ] All `fb-card`, `post-card`, `news-card-*` removed
- [ ] All `fds-*` classes removed

### Validation
```bash
# Should return 0 results after Phase 3
grep -r "fb-card" views/civicone/
grep -r "post-card" views/civicone/
grep -r "news-card" views/civicone/
grep -r "fds-" views/civicone/
```

---

## Phase 4: Badge & Tag System

### Objective
Replace the custom badge/achievement system with GOV.UK Tag component.

### GOV.UK Tag Component

```html
<!-- Standard tag -->
<strong class="govuk-tag">Completed</strong>

<!-- Colour variants -->
<strong class="govuk-tag govuk-tag--grey">Inactive</strong>
<strong class="govuk-tag govuk-tag--green">Active</strong>
<strong class="govuk-tag govuk-tag--turquoise">New</strong>
<strong class="govuk-tag govuk-tag--blue">Pending</strong>
<strong class="govuk-tag govuk-tag--purple">Received</strong>
<strong class="govuk-tag govuk-tag--pink">Sent</strong>
<strong class="govuk-tag govuk-tag--red">Urgent</strong>
<strong class="govuk-tag govuk-tag--orange">Warning</strong>
<strong class="govuk-tag govuk-tag--yellow">Delayed</strong>
```

### Tasks

#### 4.1 Map Badge Types to Tags

| Current Badge | GOV.UK Tag |
|---------------|------------|
| `rarity-common` | `govuk-tag govuk-tag--grey` |
| `rarity-rare` | `govuk-tag govuk-tag--blue` |
| `rarity-epic` | `govuk-tag govuk-tag--purple` |
| `rarity-legendary` | `govuk-tag govuk-tag--yellow` |
| `badge-earned` | `govuk-tag govuk-tag--green` |
| `badge-locked` | `govuk-tag govuk-tag--grey` |
| `item-new` | `govuk-tag govuk-tag--turquoise` |
| `item-limited` | `govuk-tag govuk-tag--orange` |

#### 4.2 Simplify Achievement Display
**Current:** Complex badge modals with icons, progress bars, rarity systems
**Target:** Simple GOV.UK patterns

```html
<!-- Achievement as Summary Card -->
<div class="govuk-summary-card">
  <div class="govuk-summary-card__title-wrapper">
    <h3 class="govuk-summary-card__title">First Post</h3>
    <strong class="govuk-tag govuk-tag--green">Earned</strong>
  </div>
  <div class="govuk-summary-card__content">
    <p class="govuk-body">Created your first community post.</p>
    <p class="govuk-body-s">Earned: 22 January 2026</p>
  </div>
</div>
```

#### 4.3 Files to Refactor
```
views/civicone/achievements/badges.php (30+ badge classes)
views/civicone/achievements/index.php (badge modal system)
views/civicone/achievements/shop.php (item badges)
views/civicone/achievements/challenges.php
views/civicone/achievements/collections.php
views/civicone/achievements/seasons.php
```

#### 4.4 Remove Badge Modal System
**Current:** `badge-modal-overlay`, `badge-modal-content`, etc.
**Target:** Link to separate badge detail page (GOV.UK pattern)

### Deliverables
- [ ] Badge rarity mapped to `govuk-tag` colours
- [ ] Achievement display simplified
- [ ] Badge modal removed (use separate pages)
- [ ] All `badge-*` custom classes removed
- [ ] All `rarity-*` classes removed

### Validation
```bash
# Should return 0 results after Phase 4
grep -r "badge-modal" views/civicone/
grep -r "rarity-" views/civicone/
grep -r "badge-icon" views/civicone/
```

---

## Phase 5: Modals & Overlays

### Objective
Replace custom modal systems with GOV.UK pattern (separate pages or in-page expansion).

### GOV.UK Modal Philosophy
GOV.UK **does not use modals**. Instead:
- Confirmation actions → Separate confirmation page
- Detail views → Link to detail page
- Forms → Dedicated form pages
- Expandable content → `govuk-details` component

### Tasks

#### 5.1 Identify All Modals
```
views/civicone/achievements/shop.php - purchase-modal
views/civicone/achievements/index.php - badge-modal-overlay
views/civicone/compose/index.php - multidraw-overlay
```

#### 5.2 Replace with GOV.UK Patterns

**Purchase Modal → Confirmation Page**
```
Current: JavaScript modal for purchase confirmation
Target: /achievements/shop/{id}/confirm page
```

**Badge Modal → Detail Page**
```
Current: JavaScript overlay showing badge details
Target: /achievements/badges/{id} page
```

**Compose Overlay → Dedicated Page**
```
Current: Overlay drawer for composing posts
Target: /compose page (already exists, ensure it's primary)
```

#### 5.3 Use Details Component for Expandable Content
```html
<details class="govuk-details">
  <summary class="govuk-details__summary">
    <span class="govuk-details__summary-text">View badge details</span>
  </summary>
  <div class="govuk-details__text">
    Badge description and requirements...
  </div>
</details>
```

### Deliverables
- [ ] Purchase modal converted to confirmation page
- [ ] Badge modal converted to detail page
- [ ] Compose overlay uses dedicated page
- [ ] All `*-modal-*` classes removed
- [ ] All `*-overlay` classes removed

### Validation
```bash
# Should return 0 results after Phase 5
grep -r "modal-overlay" views/civicone/
grep -r "modal-content" views/civicone/
grep -r "purchase-modal" views/civicone/
```

---

## Phase 6: Cleanup & Validation

### Objective
Remove all unused CSS, validate WCAG 2.1 AA compliance, and document the final state.

### Tasks

#### 6.1 Remove Unused CSS Files
**Files to audit/remove:**
```
httpdocs/assets/css/civicone-hero.css (already removed)
httpdocs/assets/css/civicone-hero-govuk.css (already removed)
httpdocs/assets/css/feed-item.css (if fb-card removed)
httpdocs/assets/css/post-card.css (if exists)
httpdocs/assets/css/civicone-achievements.css (simplify)
```

#### 6.2 Audit Remaining Custom CSS
Run discovery to find any remaining non-GOV.UK patterns:
```bash
npm run css:discover
grep -r "civicone-" httpdocs/assets/css/*.css | grep -v "govuk"
```

#### 6.3 WCAG 2.1 AA Validation
- [ ] All interactive elements keyboard accessible
- [ ] All images have alt text
- [ ] Colour contrast meets 4.5:1 minimum
- [ ] Focus states visible (GOV.UK yellow)
- [ ] No content conveyed by colour alone
- [ ] All forms have labels
- [ ] Error messages linked to fields

#### 6.4 Remove Dark Mode
GOV.UK does not support dark mode. Remove:
- `[data-theme="dark"]` CSS rules
- Theme toggle functionality
- Dark mode CSS variables

#### 6.5 Final Documentation
Update `CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md` with:
- Completed migration status
- GOV.UK components in use
- Any custom patterns with justification

### Deliverables
- [ ] All unused CSS files removed
- [ ] Dark mode completely removed
- [ ] WCAG 2.1 AA audit passed
- [ ] Documentation updated
- [ ] PurgeCSS config updated

### Validation
```bash
# Final validation commands
npm run lint:css
npm run build:css:purge

# Check for any remaining custom patterns
grep -rE "civic-|nexus-|fds-|fb-card|post-card" views/civicone/ | wc -l
# Target: 0
```

---

## CSS Files to Create/Modify

### New GOV.UK Component Files
```
httpdocs/assets/css/civicone-govuk-summary-card.css
httpdocs/assets/css/civicone-govuk-feed.css (if custom feed needed)
```

### Files to Remove After Migration
```
httpdocs/assets/css/civicone-hero.css ✓ (removed)
httpdocs/assets/css/civicone-hero-govuk.css ✓ (removed)
httpdocs/assets/css/civicone-hero.min.css ✓ (removed)
httpdocs/assets/css/feed-item.css (Phase 3)
httpdocs/assets/css/post-card.css (Phase 3)
httpdocs/assets/css/*-achievements*.css (Phase 4 - simplify)
```

### Files to Heavily Modify
```
httpdocs/assets/css/civicone-header.css (Phase 1 - gradients)
httpdocs/assets/css/civicone-native.css (Phase 1 - transitions)
httpdocs/assets/css/civicone-feed.css (Phase 3)
httpdocs/assets/css/civicone-blog.css (Phase 3)
httpdocs/assets/css/civicone-achievements.css (Phase 4)
```

---

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Visual regression | HIGH | MEDIUM | Test each phase on staging |
| User confusion (UI change) | MEDIUM | LOW | Communicate changes |
| Broken functionality | MEDIUM | HIGH | Comprehensive testing |
| Increased page load | LOW | LOW | PurgeCSS optimization |
| Accessibility regression | LOW | HIGH | WCAG audit each phase |

---

## Testing Checklist (Per Phase)

- [ ] All pages render without errors
- [ ] No console JavaScript errors
- [ ] Keyboard navigation works
- [ ] Screen reader announces content correctly
- [ ] Mobile responsive layout works
- [ ] Print styles work
- [ ] Forms submit correctly
- [ ] Links navigate correctly

---

## Success Criteria

### Phase 1 Complete When:
- Zero `*-hero-gradient` in views/civicone/
- Zero `nexus-btn` or `fds-btn` in views/civicone/
- All pages use `govuk-button` for actions

### Phase 2 Complete When:
- Zero `civic-card` in views/civicone/
- All content containers use `govuk-summary-card` or semantic HTML

### Phase 3 Complete When:
- Zero `fb-card`, `post-card`, `news-card` in views/civicone/
- Feed displays using GOV.UK patterns

### Phase 4 Complete When:
- Zero custom badge classes in views/civicone/
- All status indicators use `govuk-tag`

### Phase 5 Complete When:
- Zero modal overlays in views/civicone/
- All detail views are separate pages

### Phase 6 Complete When:
- WCAG 2.1 AA audit passes
- No dark mode code remains
- CSS file count reduced by 30%+

---

## Appendix A: GOV.UK Component Reference

| Component | Documentation URL |
|-----------|-------------------|
| Summary Card | https://design-system.service.gov.uk/components/summary-list/#summary-cards |
| Tag | https://design-system.service.gov.uk/components/tag/ |
| Button | https://design-system.service.gov.uk/components/button/ |
| Details | https://design-system.service.gov.uk/components/details/ |
| Panel | https://design-system.service.gov.uk/components/panel/ |
| Notification Banner | https://design-system.service.gov.uk/components/notification-banner/ |
| Typography | https://design-system.service.gov.uk/styles/typography/ |
| Spacing | https://design-system.service.gov.uk/styles/spacing/ |
| Colour | https://design-system.service.gov.uk/styles/colour/ |

---

## Appendix B: Class Migration Quick Reference

```
civic-card           → govuk-summary-card
civic-card-header    → govuk-summary-card__title-wrapper
civic-card-body      → govuk-summary-card__content
civic-card-footer    → (remove, use actions in title-wrapper)

nexus-btn            → govuk-button
nexus-btn-primary    → govuk-button
nexus-btn-secondary  → govuk-button govuk-button--secondary
nexus-btn-danger     → govuk-button govuk-button--warning

badge-*              → govuk-tag govuk-tag--{colour}
rarity-common        → govuk-tag govuk-tag--grey
rarity-rare          → govuk-tag govuk-tag--blue
rarity-epic          → govuk-tag govuk-tag--purple
rarity-legendary     → govuk-tag govuk-tag--yellow

*-hero-gradient      → (remove entirely)
holo-orb-*           → (remove entirely)
*-modal-overlay      → (convert to separate page)
```

---

*Document created: 2026-01-22*
*Last updated: 2026-01-22*
