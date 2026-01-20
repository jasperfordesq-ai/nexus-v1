# PurgeCSS Issue with Profile Social CSS

**Date:** 2026-01-20
**File:** `httpdocs/assets/css/civicone-profile-social.css`
**Issue:** PurgeCSS over-aggressively removing base styles

---

## Problem

When running `npm run build:css`, PurgeCSS is removing the base styles for `.civic-*` classes in `civicone-profile-social.css`, keeping only pseudo-selector variations (`:hover`, `:focus`, `:active`).

**Example:**
- ✅ Keeps: `.civicone .civic-action-btn:hover`
- ❌ Removes: `.civicone .civic-action-btn`

This results in a broken minified file where elements have no base styling, only hover/focus states.

---

## Root Cause

PurgeCSS is configured to scan:
- `views/**/*.php` ✅
- `httpdocs/**/*.php` ✅
- Content files include `views/civicone/profile/show.php` ✅

The safelist includes:
- Pattern: `/^civic-/` in `deep` array ✅

**However**, PurgeCSS appears to not be applying the `deep` safelist pattern correctly for classes with the `.civicone` parent scope.

---

## Solution Implemented

**Decision: Use full CSS file (16KB) without PurgeCSS minification.**

The profile social classes have been added to the safelist in `purgecss.config.js` (lines 247-260), but PurgeCSS still only preserves pseudo-selectors when classes are scoped under `.civicone`.

Given that:

- 16KB is reasonable for a complete social interaction system
- The styles are scoped under `.civicone` so no global pollution
- Further debugging PurgeCSS isn't worth the development time
- The full CSS ensures all components work correctly

**The full CSS file is the intentional, permanent solution.**

---

## Permanent Solution Options

### Option 1: Add to Safelist Standard (Most Reliable)

Edit `purgecss.config.js` and add all profile social classes to the `standard` array:

```javascript
standard: [
    // ... existing classes ...

    // Profile social components
    'civic-composer',
    'civic-composer-actions',
    'civic-post-card',
    'civic-post-header',
    'civic-post-content',
    'civic-post-actions',
    'civic-post-image',
    'civic-avatar-sm',
    'civic-action-btn',
    'civic-comments-section',
    'civic-comment',
    'civic-comment-avatar',
    'civic-comment-bubble',
    'civic-comment-author',
    'civic-comment-text',
    'civic-comment-meta',
    'civic-comment-form',
    'civic-comment-input',
    'civic-comment-submit',
    'civic-reply-form',
    'civic-reply-input',
    'civic-reactions',
    'civic-reaction',
    'civic-reaction-picker',
    'civic-reaction-picker-menu',
    'civic-mention',
    'civic-toast',
    'civic-toast-content',
    'civic-toast-icon',
    'civic-toast-message',
    'civicone-related-content',
],
```

### Option 2: Add to Greedy Array

Add the civic pattern to the `greedy` array (line ~475):

```javascript
greedy: [
    /hover/,
    /focus/,
    /active/,
    /visited/,
    /mobile/,
    /desktop/,
    /tablet/,
    /^civic-/,  // Add this line
],
```

### Option 3: Disable PurgeCSS for This File

Add a skip condition in the build script for this specific CSS file.

### Option 4: Use Full CSS Permanently

Keep using the full CSS file (16KB) instead of minifying. The size is reasonable and the file is scoped under `.civicone` so there's minimal bloat.

---

## Recommendation

**Use Option 4** (keep full CSS) for now. The 16KB file size is acceptable for a complete social interaction system, and it avoids the complexity of maintaining a large safelist.

If file size becomes a concern in the future, implement Option 1 (add specific classes to safelist standard).

---

## Files Affected

- `httpdocs/assets/css/civicone-profile-social.css` (source)
- `httpdocs/assets/css/civicone-profile-social.min.css` (currently using full CSS)
- `httpdocs/assets/css/purged/civicone-profile-social.min.css` (purged version, not used)

---

## Testing

After any changes to PurgeCSS configuration:

1. Run `npm run build:css`
2. Check file size: `ls -lh httpdocs/assets/css/civicone-profile-social.min.css`
3. Verify base styles exist: `grep "civic-composer {" httpdocs/assets/css/civicone-profile-social.min.css`
4. Test in browser that post cards, buttons, and comments display correctly

---

**Status:** ✅ Resolved (using full CSS)
**Follow-up:** Optional optimization with safelist configuration
