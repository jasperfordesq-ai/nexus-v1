# Mobile Interactions Guide

Complete guide to using the mobile micro-interactions system.

## Overview

The mobile interactions system provides native app-like feedback through:
- **Ripple Effects** - Material Design touch feedback
- **Haptic Feedback** - Device vibration coordination
- **Loading States** - Button and form loading indicators
- **Swipe Gestures** - Left/right swipe actions
- **Animations** - Toggles, checkboxes, badges, snackbars

## Quick Start

The system auto-initializes on page load. Access the API via `window.MobileInteractions`.

```javascript
// Show success notification
MobileInteractions.snackbar.show('Profile updated!', 'success');

// Trigger haptic feedback
MobileInteractions.haptic.trigger('medium');

// Show button loading state
MobileInteractions.loading.showButton(button);
```

---

## 1. Ripple Effects

### Basic Usage

Add `mobile-ripple-container` class or `data-ripple="true"` to any element:

```html
<button class="mobile-ripple-container">
    Click me
</button>

<div class="card mobile-ripple-container">
    Clickable card
</div>

<a href="#" data-ripple="true">Link with ripple</a>
```

### Ripple Variants

Control ripple color with variants:

```html
<!-- Light ripple (default) -->
<button class="mobile-ripple-container">Default</button>

<!-- Primary button ripple (white) -->
<button class="btn-primary mobile-ripple-container">Primary</button>

<!-- Secondary button ripple (primary color) -->
<button class="btn-secondary mobile-ripple-container">Secondary</button>

<!-- Custom variant -->
<button class="mobile-ripple-container" data-ripple-variant="dark">Dark</button>
```

### CSS Classes

- `.mobile-ripple-container` - Enables ripple effect
- `.mobile-ripple` - The ripple element (auto-generated)
- `.mobile-ripple.primary` - White ripple (buttons)
- `.mobile-ripple.secondary` - Primary color ripple
- `.mobile-ripple.dark` - Dark ripple

---

## 2. Haptic Feedback

### Intensity Levels

```javascript
// Light tap (selection changed)
MobileInteractions.haptic.trigger('light');

// Medium tap (button press)
MobileInteractions.haptic.trigger('medium');

// Heavy tap (important action)
MobileInteractions.haptic.trigger('heavy');

// Success notification
MobileInteractions.haptic.trigger('success');

// Error notification
MobileInteractions.haptic.trigger('error');

// Warning notification
MobileInteractions.haptic.trigger('warning');
```

### Add to Element

Automatically trigger haptic on click:

```javascript
const button = document.querySelector('.important-btn');
MobileInteractions.haptic.addToElement(button, 'medium');
```

### CSS Animation Classes

Haptic feedback adds visual animation classes:

```html
<!-- These classes are auto-added when haptic triggers -->
<button class="mobile-haptic-success">Success!</button>
<button class="mobile-haptic-error">Error!</button>
<button class="mobile-haptic-light">Light tap</button>
<button class="mobile-haptic-medium">Medium tap</button>
<button class="mobile-haptic-heavy">Heavy tap</button>
```

### Device Support

- **Capacitor Native Apps**: Full haptic support (recommended)
- **Web (iOS/Android)**: Web Vibration API fallback
- **Desktop/Unsupported**: Gracefully degrades (no vibration)

---

## 3. Button Press Animations

### Usage

Add these classes to elements for scale-based press feedback:

```html
<!-- Standard buttons -->
<button class="mobile-interactive-press">
    Standard Button (0.96x scale)
</button>

<!-- Icon buttons -->
<button class="mobile-icon-press">
    <i class="fa-solid fa-heart"></i>
</button>

<!-- FAB buttons -->
<button class="mobile-fab mobile-fab-press">
    <i class="fa-solid fa-plus"></i>
</button>

<!-- List items -->
<div class="mobile-list-item-press">
    Clickable list item
</div>

<!-- Cards -->
<div class="mobile-card-press">
    Pressable card
</div>
```

### Scale Values

- `.mobile-interactive-press` - 0.96x scale (standard buttons)
- `.mobile-icon-press` - 0.90x scale (small icons)
- `.mobile-fab-press` - 0.92x scale (FAB with shadow)
- `.mobile-list-item-press` - 0.98x scale (subtle)
- `.mobile-card-press` - 0.97x scale (cards)

---

## 4. Loading States

### Button Loading

```javascript
const button = document.querySelector('.submit-btn');

// Show loading spinner
MobileInteractions.loading.showButton(button);

// Perform async operation
await saveData();

// Hide loading spinner
MobileInteractions.loading.hideButton(button);
```

HTML:

```html
<button class="btn-primary">
    Submit
</button>

<!-- When loading, becomes: -->
<button class="btn-primary mobile-btn-loading" disabled aria-busy="true">
    Submit <!-- Text hidden, spinner shown -->
</button>
```

### Form Loading

```javascript
const form = document.querySelector('form');

// Disable entire form
MobileInteractions.loading.showForm(form);

await submitForm();

// Re-enable form
MobileInteractions.loading.hideForm(form);
```

### Input Loading

```javascript
const input = document.querySelector('#email');

// Show spinner on input
MobileInteractions.loading.showInput(input);

await validateEmail();

MobileInteractions.loading.hideInput(input);
```

---

## 5. Swipe Gestures

### Basic Setup

```html
<div class="mobile-swipe-container">
    <div class="swipe-content">
        Swipe me left or right
    </div>
    <div class="mobile-swipe-actions left">
        <i class="fa-solid fa-trash mobile-swipe-icon"></i>
    </div>
    <div class="mobile-swipe-actions right">
        <i class="fa-solid fa-archive mobile-swipe-icon"></i>
    </div>
</div>
```

### Listen to Swipe Events

```javascript
const container = document.querySelector('.mobile-swipe-container');

// Swipe left event
container.addEventListener('swipeleft', (event) => {
    console.log('Swiped left - delete action');
    deleteItem();
});

// Swipe right event
container.addEventListener('swiperight', (event) => {
    console.log('Swiped right - archive action');
    archiveItem();
});
```

### Configuration

- **Threshold**: 80px minimum swipe distance
- **Max Swipe**: 80px (prevents over-swiping)
- **Auto-reset**: 2 seconds after swipe
- **Haptic**: Medium haptic on swipe trigger

---

## 6. Toggle Switches

### HTML Structure

```html
<div class="mobile-toggle-switch" data-setting="notifications">
    <div class="mobile-toggle-thumb"></div>
</div>

<!-- Active state -->
<div class="mobile-toggle-switch active">
    <div class="mobile-toggle-thumb"></div>
</div>
```

### JavaScript

```javascript
const toggle = document.querySelector('.mobile-toggle-switch');

// Listen for toggle events
toggle.addEventListener('toggle', (event) => {
    console.log('Toggle state:', event.detail.active);

    // Save setting
    saveSetting('notifications', event.detail.active);
});

// Programmatically toggle
toggle.classList.toggle('active');
```

---

## 7. Checkboxes & Radio Buttons

### Checkbox

```html
<div class="mobile-checkbox" data-field="terms">
    <div class="mobile-checkbox-checkmark"></div>
</div>

<!-- Checked state -->
<div class="mobile-checkbox checked">
    <div class="mobile-checkbox-checkmark"></div>
</div>
```

### Radio Button

```html
<div class="mobile-radio" data-group="color" data-value="red">
    <div class="mobile-radio-dot"></div>
</div>

<div class="mobile-radio" data-group="color" data-value="blue">
    <div class="mobile-radio-dot"></div>
</div>

<div class="mobile-radio checked" data-group="color" data-value="green">
    <div class="mobile-radio-dot"></div>
</div>
```

### JavaScript

```javascript
const checkbox = document.querySelector('.mobile-checkbox');

checkbox.addEventListener('change', (event) => {
    console.log('Checked:', event.detail.checked);
});

// Programmatically check
checkbox.classList.add('checked');
```

Radio buttons auto-uncheck others in the same `data-group`.

---

## 8. Badge Animations

### Update Count

```javascript
const badge = document.querySelector('.notification-badge');

// Animate count change (from 3 to 5)
MobileInteractions.badge.updateCount(badge, 5);
// Triggers count-up animation and haptic feedback
```

### Show New Badge

```javascript
const badge = document.querySelector('.new-badge');

// Animate badge entrance
MobileInteractions.badge.show(badge);
// Triggers pop animation and haptic feedback
```

### CSS Classes

```html
<!-- Manually trigger animations -->
<span class="badge mobile-badge-pop">1</span>
<span class="badge mobile-badge-count-up">5</span>
<span class="badge mobile-badge-wiggle">New</span>
```

---

## 9. Snackbar/Toast Notifications

### Show Notification

```javascript
// Default (dark background)
MobileInteractions.snackbar.show('Changes saved');

// Success (green)
MobileInteractions.snackbar.show('Profile updated!', 'success');

// Error (red)
MobileInteractions.snackbar.show('Failed to save', 'error');

// Warning (orange)
MobileInteractions.snackbar.show('Please verify email', 'warning');

// Custom duration (default 3000ms)
MobileInteractions.snackbar.show('Quick message', 'default', 1500);
```

### Features

- Auto-dismiss after duration
- Slide-bounce entrance animation
- Positioned above mobile tab bar
- Includes haptic feedback
- ARIA live region for screen readers

---

## 10. Page Loading Bar

### Auto-Initialize

The page loading bar automatically shows on:
- Page navigation (link clicks)
- Page unload
- Form submissions

### Manual Control

```javascript
// Show loading bar
const { PageLoadingBar } = window.MobileInteractions;
PageLoadingBar.show();

// Hide loading bar
PageLoadingBar.hide();
```

---

## API Reference

### `MobileInteractions.haptic`

| Method | Parameters | Description |
|--------|------------|-------------|
| `trigger(type)` | `'light' \| 'medium' \| 'heavy' \| 'success' \| 'error' \| 'warning'` | Trigger haptic feedback |
| `addToElement(element, type)` | `HTMLElement, string` | Add haptic to element click |
| `isSupported()` | - | Check if haptics supported |

### `MobileInteractions.loading`

| Method | Parameters | Description |
|--------|------------|-------------|
| `showButton(button)` | `HTMLElement` | Show button loading state |
| `hideButton(button)` | `HTMLElement` | Hide button loading state |
| `showForm(form)` | `HTMLElement` | Disable form with loading |
| `hideForm(form)` | `HTMLElement` | Re-enable form |
| `showInput(input)` | `HTMLElement` | Show input spinner |
| `hideInput(input)` | `HTMLElement` | Hide input spinner |

### `MobileInteractions.badge`

| Method | Parameters | Description |
|--------|------------|-------------|
| `updateCount(badge, count)` | `HTMLElement, number` | Animate count change |
| `show(badge)` | `HTMLElement` | Animate badge entrance |

### `MobileInteractions.snackbar`

| Method | Parameters | Description |
|--------|------------|-------------|
| `show(message, type, duration)` | `string, string?, number?` | Show toast notification |

---

## Examples

### Form Submission with Loading

```javascript
const form = document.querySelector('form');
const submitBtn = form.querySelector('button[type="submit"]');

form.addEventListener('submit', async (e) => {
    e.preventDefault();

    // Show loading state
    MobileInteractions.loading.showButton(submitBtn);
    MobileInteractions.haptic.trigger('medium');

    try {
        const response = await fetch('/api/save', {
            method: 'POST',
            body: new FormData(form)
        });

        if (response.ok) {
            MobileInteractions.snackbar.show('Saved successfully!', 'success');
            MobileInteractions.haptic.trigger('success');
        } else {
            throw new Error('Save failed');
        }
    } catch (error) {
        MobileInteractions.snackbar.show('Failed to save', 'error');
        MobileInteractions.haptic.trigger('error');
    } finally {
        MobileInteractions.loading.hideButton(submitBtn);
    }
});
```

### Swipeable List Item

```html
<div class="mobile-swipe-container" data-id="123">
    <div class="list-item">
        <h4>Item Title</h4>
        <p>Item description</p>
    </div>
    <div class="mobile-swipe-actions left">
        <i class="fa-solid fa-trash mobile-swipe-icon"></i>
    </div>
    <div class="mobile-swipe-actions right">
        <i class="fa-solid fa-check mobile-swipe-icon"></i>
    </div>
</div>

<script>
document.querySelectorAll('.mobile-swipe-container').forEach(item => {
    item.addEventListener('swipeleft', () => {
        const id = item.dataset.id;
        if (confirm('Delete this item?')) {
            deleteItem(id);
        }
    });

    item.addEventListener('swiperight', () => {
        const id = item.dataset.id;
        markComplete(id);
    });
});
</script>
```

### Custom Toggle with Settings

```html
<div class="setting-row">
    <label>Dark Mode</label>
    <div class="mobile-toggle-switch" id="darkModeToggle">
        <div class="mobile-toggle-thumb"></div>
    </div>
</div>

<script>
const toggle = document.getElementById('darkModeToggle');

// Load saved setting
if (localStorage.getItem('darkMode') === 'true') {
    toggle.classList.add('active');
}

// Save on change
toggle.addEventListener('toggle', (e) => {
    localStorage.setItem('darkMode', e.detail.active);
    document.documentElement.setAttribute('data-theme', e.detail.active ? 'dark' : 'light');
});
</script>
```

---

## Accessibility

All interactions are WCAG 2.1 AAA compliant:

- ✅ **Reduced Motion**: Animations disabled via `prefers-reduced-motion`
- ✅ **Screen Readers**: ARIA attributes (`aria-busy`, `aria-live`)
- ✅ **Keyboard Navigation**: All interactions keyboard accessible
- ✅ **Focus Management**: Clear focus indicators
- ✅ **Touch Targets**: 44x44px minimum (WCAG 2.1 Level AAA)

---

## Browser Support

| Feature | Support |
|---------|---------|
| Ripple Effects | All modern browsers |
| Haptic Feedback | iOS/Android (web), Capacitor (native) |
| Loading States | All browsers |
| Swipe Gestures | Touch devices (graceful desktop fallback) |
| Animations | All modern browsers |

---

## Performance

- **Bundle Size**: ~10.4KB minified
- **No Dependencies**: Vanilla JavaScript
- **Lazy Loading**: Deferred script loading
- **Hardware Acceleration**: GPU-accelerated animations
- **60fps Target**: All animations optimized for smooth 60fps

---

## Troubleshooting

### Ripple Not Showing

- Ensure element has `position: relative` (auto-applied)
- Check element has `.mobile-ripple-container` or `data-ripple="true"`
- Verify element is not `disabled`

### Haptic Not Working

- Check device support: `MobileInteractions.haptic.isSupported()`
- iOS requires HTTPS for Web Vibration API
- Capacitor apps have full haptic support

### Loading State Stuck

```javascript
// Force reset loading states
document.querySelectorAll('.mobile-btn-loading').forEach(btn => {
    MobileInteractions.loading.hideButton(btn);
});
```

### Swipe Not Triggering

- Ensure 80px minimum swipe distance
- Check swipe is primarily horizontal (not vertical)
- Verify touch event listeners are active

---

## Version

**Version**: 1.0
**Date**: 2026-01-21
**Compatibility**: Modern browsers (ES6+)

## Related Documentation

- [Mobile Design Tokens](./MOBILE-DESIGN-TOKENS.md)
- [Mobile Accessibility Fixes](./MOBILE-ACCESSIBILITY.md)
- [Mobile Loading States](./MOBILE-LOADING-STATES.md)
