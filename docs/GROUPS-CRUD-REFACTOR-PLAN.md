# Groups CRUD Refactor Plan

**Date**: 2026-01-21
**Priority**: P1 - Complete Groups CRUD pattern
**Files**: `groups/show.php`, `groups/edit.php`

---

## Current Status

| File | Status | Issues |
|------|--------|--------|
| `groups/index.php` | ✅ COMPLETE | Template A implemented |
| `groups/create.php` | ✅ COMPLETE | Template D annotation |
| `groups/show.php` | ❌ NEEDS REFACTOR | Massive inline styles, no Template C |
| `groups/edit.php` | ❌ NEEDS REFACTOR | Massive inline `<style>` block, no Template D |

---

## Issues: Group Show (`groups/show.php`)

### CLAUDE.md Violations

1. **Lines 14-236**: Massive inline `style=""` attributes
   - `style="max-width: 1200px; margin: 0 auto; padding: 20px;"`
   - `style="width: 100%; height: 100%; object-fit: cover; opacity: 0.8;"`
   - Dozens more throughout the file

2. **Lines 238-250**: Inline `<script>` block (13 lines)
   - Tab switching logic should be external

3. **Using wrong CSS classes**:
   - `.civic-container` → should be `.civicone-width-container`
   - `.civic-group-header` → should be `.civicone-group-header`
   - `.civic-card` → should be `.civicone-card`

### Template C Violations

1. **Missing GOV.UK boilerplate**:
   - No `civicone-width-container`
   - No `civicone-main-wrapper`
   - No `id="main-content"`

2. **Missing proper grid structure**:
   - Should use 2/3 + 1/3 column split
   - Currently uses custom `.civic-group-content-grid`

3. **Tabs without proper ARIA**:
   - Missing `role="tablist"`, `role="tab"`, `role="tabpanel"`
   - Missing `aria-selected`, `aria-controls`, `aria-labelledby`
   - Click-only interaction (should support Enter/Space)
   - Missing keyboard navigation (Arrow keys)

4. **Missing breadcrumbs**:
   - Should have: Home → Groups → [Group Name]

5. **Missing hero**:
   - Should use `render-hero.php` partial

### Accessibility Issues

1. **Tab implementation**: Not keyboard accessible
2. **Focus states**: Inline styles override GOV.UK focus pattern
3. **Semantic HTML**: Using `<div>` where `<aside>` should be used
4. **Button styles**: Inline styles on buttons instead of GOV.UK button classes

---

## Issues: Group Edit (`groups/edit.php`)

### CLAUDE.md Violations

1. **Lines 17+**: Massive inline `<style>` block
   - 100+ lines of CSS in the PHP file
   - Should be in `civicone-groups.css`

2. **Hero variables**: Using custom hero variables instead of `render-hero.php`

### Template D Violations

1. **Missing GOV.UK form pattern**:
   - No GOV.UK form components
   - No error summary pattern
   - No proper label/hint/error structure

2. **Missing form validation display**:
   - Should follow GOV.UK error message pattern
   - Should have error summary at top

3. **Missing shared partial**:
   - Create/Edit should share a `_form.php` partial
   - Currently duplicating form markup

---

## Refactor Plan: Group Show

### Step 1: Extract All Inline Styles

**Create/Update**: `httpdocs/assets/css/civicone-groups-show.css`

Move all inline styles to external CSS file with proper scoping:

```css
.nexus-skin-civicone .civicone-group-show {
  max-width: 1200px;
  margin: 0 auto;
  padding: var(--space-5);
}

.nexus-skin-civicone .civicone-group-header {
  /* Styles from lines 17-32 */
}

.nexus-skin-civicone .civicone-group-cover {
  /* Styles from line 18 */
}

/* ... etc for all inline styles */
```

### Step 2: Implement GOV.UK Boilerplate

```php
<?php
/**
 * CivicOne Group Detail Page
 * Template C: Detail Page (Section 10.4)
 * With Page Hero (Section 9C)
 */

require __DIR__ . '/../../layouts/civicone/header.php';
?>

<!-- GOV.UK Page Template Boilerplate (Section 10.0) -->
<div class="civicone-width-container">
    <main class="civicone-main-wrapper" id="main-content">

        <!-- Breadcrumbs -->
        <?php
        $breadcrumbs = [
            ['label' => 'Home', 'url' => '/'],
            ['label' => 'Groups', 'url' => '/groups'],
            ['label' => htmlspecialchars($group['name'])]
        ];
        require __DIR__ . '/../../layouts/civicone/partials/breadcrumb.php';
        ?>

        <!-- Hero (auto-resolves from config/heroes.php) -->
        <?php require __DIR__ . '/../../layouts/civicone/partials/render-hero.php'; ?>

        <!-- Group Header with Cover Image -->
        <div class="civicone-group-header">
            <!-- Cover image, title, member count, join/leave button -->
        </div>

        <!-- Main content area (2/3 + 1/3 split) -->
        <div class="civicone-grid-row">

            <!-- Left: Main content (2/3) -->
            <div class="civicone-grid-column-two-thirds">

                <!-- Tabs (ARIA-compliant) -->
                <div class="civicone-tabs" data-module="civicone-tabs">
                    <h2 class="civicone-tabs__title">Group content</h2>
                    <ul class="civicone-tabs__list" role="tablist">
                        <li class="civicone-tabs__list-item" role="presentation">
                            <a class="civicone-tabs__tab" href="#tab-feed"
                               role="tab"
                               aria-selected="true"
                               aria-controls="tab-feed">
                                Activity
                            </a>
                        </li>
                        <li class="civicone-tabs__list-item" role="presentation">
                            <a class="civicone-tabs__tab" href="#tab-about"
                               role="tab"
                               aria-selected="false"
                               aria-controls="tab-about">
                                About
                            </a>
                        </li>
                        <li class="civicone-tabs__list-item" role="presentation">
                            <a class="civicone-tabs__tab" href="#tab-members"
                               role="tab"
                               aria-selected="false"
                               aria-controls="tab-members">
                                Members (<?= count($members) ?>)
                            </a>
                        </li>
                    </ul>

                    <div class="civicone-tabs__panel" id="tab-feed" role="tabpanel" aria-labelledby="tab-feed-tab">
                        <!-- Feed content -->
                    </div>

                    <div class="civicone-tabs__panel civicone-tabs__panel--hidden" id="tab-about" role="tabpanel" aria-labelledby="tab-about-tab">
                        <!-- About content using GOV.UK Summary list -->
                        <dl class="civicone-summary-list">
                            <div class="civicone-summary-list__row">
                                <dt class="civicone-summary-list__key">Description</dt>
                                <dd class="civicone-summary-list__value"><?= nl2br(htmlspecialchars($group['description'])) ?></dd>
                            </div>
                            <!-- More details -->
                        </dl>
                    </div>

                    <div class="civicone-tabs__panel civicone-tabs__panel--hidden" id="tab-members" role="tabpanel" aria-labelledby="tab-members-tab">
                        <!-- Members list (NOT card grid) -->
                        <ul class="civicone-results-list">
                            <?php foreach ($members as $member): ?>
                                <li class="civicone-member-item">
                                    <!-- Member item (reuse pattern from members/index.php) -->
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

            </div><!-- /two-thirds -->

            <!-- Right: Sidebar (1/3) -->
            <div class="civicone-grid-column-one-third">
                <aside aria-label="Group information">

                    <!-- Manager card -->
                    <div class="civicone-card">
                        <h2 class="civicone-heading-m">Group Manager</h2>
                        <!-- Manager info -->
                        <a href="<?= $basePath ?>/messages/compose?to=<?= $group['owner_id'] ?>"
                           class="civicone-button civicone-button--secondary">
                            Contact Manager
                        </a>
                    </div>

                </aside>
            </div>

        </div><!-- /grid-row -->

    </main>
</div><!-- /width-container -->

<!-- Tab functionality loaded from external file per CLAUDE.md -->
<script src="<?= $basePath ?? '' ?>/assets/js/civicone-tabs.min.js" defer></script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
```

### Step 3: Create Tabs JavaScript File

**Create**: `httpdocs/assets/js/civicone-tabs.js`

Implement GOV.UK Tabs component with proper keyboard support:
- Arrow Left/Right to move between tabs
- Home/End to jump to first/last tab
- Enter/Space to activate tab
- Proper focus management

### Step 4: Update CSS Loading

Add to `views/layouts/civicone/partials/assets-css.php`:

```php
<?php if (strpos($normPath, '/groups/show') !== false || strpos($normPath, '/groups/') !== false && preg_match('/\/\d+$/', $normPath)): ?>
    <link rel="stylesheet" href="/assets/css/civicone-groups-show.min.css">
<?php endif; ?>
```

---

## Refactor Plan: Group Edit

### Step 1: Extract Inline Styles

Move all CSS from `<style>` block to `civicone-groups-edit.css`

### Step 2: Create Shared Form Partial

**Create**: `views/civicone/groups/_form.php`

```php
<?php
/**
 * Shared Group Form Partial
 * Used by both create.php and edit.php
 * Follows GOV.UK Form pattern (Template D)
 */

$isEdit = !empty($group['id']);
$formAction = $isEdit ? "/groups/{$group['id']}/update" : "/groups";
?>

<form method="post" action="<?= $basePath ?><?= $formAction ?>" enctype="multipart/form-data" novalidate>
    <?= \Nexus\Core\Csrf::input() ?>

    <!-- Group Name -->
    <div class="civicone-form-group <?= !empty($errors['name']) ? 'civicone-form-group--error' : '' ?>">
        <label class="civicone-label" for="group-name">
            Group name
        </label>
        <div id="group-name-hint" class="civicone-hint">
            Choose a clear, descriptive name for your group
        </div>
        <?php if (!empty($errors['name'])): ?>
            <p id="group-name-error" class="civicone-error-message">
                <span class="civicone-visually-hidden">Error:</span>
                <?= htmlspecialchars($errors['name']) ?>
            </p>
        <?php endif; ?>
        <input
            class="civicone-input <?= !empty($errors['name']) ? 'civicone-input--error' : '' ?>"
            id="group-name"
            name="name"
            type="text"
            value="<?= htmlspecialchars($oldInput['name'] ?? $group['name'] ?? '') ?>"
            aria-describedby="group-name-hint <?= !empty($errors['name']) ? 'group-name-error' : '' ?>"
            <?= !empty($errors['name']) ? 'aria-invalid="true"' : '' ?>
        >
    </div>

    <!-- Description (Character count component) -->
    <div class="civicone-form-group <?= !empty($errors['description']) ? 'civicone-form-group--error' : '' ?>">
        <label class="civicone-label" for="group-description">
            Description
        </label>
        <div id="group-description-hint" class="civicone-hint">
            Describe what your group is about and what activities you do
        </div>
        <?php if (!empty($errors['description'])): ?>
            <p id="group-description-error" class="civicone-error-message">
                <span class="civicone-visually-hidden">Error:</span>
                <?= htmlspecialchars($errors['description']) ?>
            </p>
        <?php endif; ?>
        <textarea
            class="civicone-textarea civicone-js-character-count <?= !empty($errors['description']) ? 'civicone-textarea--error' : '' ?>"
            id="group-description"
            name="description"
            rows="8"
            maxlength="1000"
            aria-describedby="group-description-hint group-description-info <?= !empty($errors['description']) ? 'group-description-error' : '' ?>"
            <?= !empty($errors['description']) ? 'aria-invalid="true"' : '' ?>
        ><?= htmlspecialchars($oldInput['description'] ?? $group['description'] ?? '') ?></textarea>
        <div id="group-description-info" class="civicone-hint civicone-character-count__message" aria-live="polite">
            You can enter up to 1000 characters
        </div>
    </div>

    <!-- More fields... -->

    <!-- Submit Button -->
    <button type="submit" class="civicone-button">
        <?= $isEdit ? 'Update group' : 'Create group' ?>
    </button>

</form>
```

### Step 3: Update create.php and edit.php

Both files should just include the partial:

```php
<?php
/**
 * CivicOne Group Edit
 * Template D: Form/Flow (Section 10.5)
 */

require __DIR__ . '/../../layouts/civicone/header.php';
?>

<div class="civicone-width-container">
    <main class="civicone-main-wrapper" id="main-content">

        <!-- Hero -->
        <?php require __DIR__ . '/../../layouts/civicone/partials/render-hero.php'; ?>

        <!-- Error Summary (if errors exist) -->
        <?php if (!empty($errors)): ?>
            <div class="civicone-error-summary" aria-labelledby="error-summary-title" role="alert" tabindex="-1">
                <h2 class="civicone-error-summary__title" id="error-summary-title">
                    There is a problem
                </h2>
                <div class="civicone-error-summary__body">
                    <ul class="civicone-error-summary__list">
                        <?php foreach ($errors as $field => $error): ?>
                            <li><a href="#<?= $field ?>"><?= htmlspecialchars($error) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <!-- Form (2/3 column) -->
        <div class="civicone-grid-row">
            <div class="civicone-grid-column-two-thirds">
                <?php require __DIR__ . '/_form.php'; ?>
            </div>
        </div>

    </main>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
```

---

## Implementation Steps

1. ✅ Create this plan document
2. ⏳ Refactor Group Show:
   - Extract inline styles to `civicone-groups-show.css`
   - Implement Template C structure
   - Create `civicone-tabs.js` with ARIA support
   - Minify and test
3. ⏳ Refactor Group Edit:
   - Extract inline `<style>` to `civicone-groups-edit.css`
   - Create shared `_form.php` partial
   - Implement GOV.UK form pattern
   - Update create.php to use shared partial
4. ⏳ Test both pages:
   - Keyboard navigation
   - Screen reader
   - Visual regression
   - Axe audit
5. ⏳ Commit changes

---

## Testing Checklist

### Group Show

- [ ] Breadcrumbs present and functional
- [ ] Hero renders correctly
- [ ] Tabs keyboard accessible (Arrow keys, Enter, Space)
- [ ] Tabs have proper ARIA (`role="tablist"`, etc.)
- [ ] Focus visible on all interactive elements
- [ ] Members list uses list layout (NOT cards)
- [ ] 2/3 + 1/3 grid on desktop, stacks on mobile
- [ ] Join/Leave button keyboard accessible
- [ ] Contact button functional
- [ ] No inline styles remain
- [ ] JavaScript external and minified

### Group Edit

- [ ] Error summary appears when errors exist
- [ ] Error summary receives focus on page load
- [ ] All form fields have visible labels
- [ ] Hints associated via `aria-describedby`
- [ ] Errors associated via `aria-describedby`
- [ ] Invalid fields marked with `aria-invalid="true"`
- [ ] Character count component functional
- [ ] Form submits correctly
- [ ] No inline `<style>` block remains
- [ ] Shared `_form.php` partial used by both create and edit

---

## Estimated Effort

- Group Show refactor: 2-3 hours
- Group Edit refactor: 1.5-2 hours
- Testing: 1 hour
- **Total**: 4.5-6 hours

---

## Files to Create/Modify

**Create**:
- `httpdocs/assets/css/civicone-groups-show.css`
- `httpdocs/assets/css/civicone-groups-edit.css`
- `httpdocs/assets/js/civicone-tabs.js`
- `views/civicone/groups/_form.php`

**Modify**:
- `views/civicone/groups/show.php` (complete rewrite)
- `views/civicone/groups/edit.php` (complete rewrite)
- `views/civicone/groups/create.php` (update to use `_form.php`)
- `views/layouts/civicone/partials/assets-css.php` (add conditional CSS loading)

**Minify**:
- `civicone-groups-show.min.css`
- `civicone-groups-edit.min.css`
- `civicone-tabs.min.js`

---

## Success Criteria

- [ ] Zero inline `style=""` attributes
- [ ] Zero inline `<style>` blocks
- [ ] Zero inline `<script>` blocks >10 lines
- [ ] GOV.UK boilerplate structure present
- [ ] Template C pattern correctly implemented (Group Show)
- [ ] Template D pattern correctly implemented (Group Edit)
- [ ] Shared `_form.php` partial working for create/edit
- [ ] ARIA-compliant tabs with keyboard support
- [ ] Passes Axe audit (0 violations)
- [ ] Keyboard navigable (all interactions)
- [ ] Screen reader tested (NVDA/JAWS)
