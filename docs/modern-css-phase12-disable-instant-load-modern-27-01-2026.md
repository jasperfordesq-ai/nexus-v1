# Phase 12: Disable nexus-instant-load.js Body Hiding (Modern Theme Only)

**Date:** 27 January 2026
**Scope:** Modern theme only (CivicOne unchanged)
**Status:** IMPLEMENTED

---

## Summary

Disabled the `nexus-instant-load.js` script for the Modern theme by commenting out its script tag in `header.php`. This eliminates the artificial body hiding mechanism that was identified in Phase 11 as the PRIMARY cause of the visible "snap" (FOUC) on page load.

---

## Problem

The Phase 11 root cause audit identified `nexus-instant-load.js` as the single largest contributor to FOUC:

1. **What the script does:**
   - Injects `body { opacity: 0; visibility: hidden; }` immediately on load
   - Polls for CSS loading every 50ms
   - After CSS detected OR 1.5s timeout, fades body in over 250ms

2. **Why it's counterproductive now:**
   - Phases 8-11 established proper sync CSS loading for critical routes
   - Blog, auth, and members pages now load their CSS in `<head>` before first paint
   - The artificial hiding creates the very flash it was meant to prevent

---

## Change Made

### File: `views/layouts/modern/header.php`

**Before (line 178-179):**
```html
<!-- CRITICAL: Instant Load Script (deferred to not block CSS) -->
<script defer src="/assets/js/nexus-instant-load.min.js?v=<?= $cssVersionTimestamp ?>"></script>
```

**After:**
```html
<!-- PHASE 12 (2026-01-27): nexus-instant-load.js DISABLED
     Root cause analysis (Phase 11 audit) identified this script as the PRIMARY cause of FOUC.
     The script artificially hides body with opacity:0, then fades in after CSS check.
     With Phases 8-11 CSS optimizations, sync CSS now loads before first paint - hiding is counterproductive.
     To re-enable: uncomment the script tag below.
-->
<!-- <script defer src="/assets/js/nexus-instant-load.min.js?v=<?= $cssVersionTimestamp ?>"></script> -->
```

---

## Expected Result

- **No artificial blank screen** - Page content visible immediately
- **No 250ms fade-in** - Content renders naturally without transition
- **Faster perceived load** - Users see content sooner
- **Eliminates "snap"** - No sudden opacity change from 0 to 1

---

## Reversibility

To re-enable the script if needed:

1. Open `views/layouts/modern/header.php`
2. Find the commented script tag (search for "PHASE 12")
3. Uncomment the `<script>` line

---

## Scope Verification

| Theme | Affected | Notes |
|-------|----------|-------|
| Modern | YES | Script disabled in `views/layouts/modern/header.php` |
| CivicOne | NO | Has its own header at `views/layouts/civicone/header.php` |

---

## Related Phases

| Phase | Change | Relationship |
|-------|--------|--------------|
| Phase 8 | Sync CSS for blog/auth routes | Prerequisite - makes instant-load unnecessary |
| Phase 10 | Sync utilities-polish.css for blog | Prerequisite - further CSS optimization |
| Phase 11 | Content-first load for /news | Prerequisite - removed skeleton swap |
| Phase 11 Audit | Identified root causes | Diagnostic that led to this fix |

---

## Files Modified

| File | Change |
|------|--------|
| `views/layouts/modern/header.php` | Commented out nexus-instant-load.min.js script tag |

---

## Files NOT Modified (Preserved)

- `httpdocs/assets/js/nexus-instant-load.js` - Source file preserved
- `httpdocs/assets/js/nexus-instant-load.min.js` - Minified file preserved
- All CSS files - No CSS changes in this phase
- CivicOne theme files - Unaffected

---

## Testing Checklist

- [ ] /news page loads without blank flash
- [ ] /login page loads without blank flash
- [ ] Home page loads without blank flash
- [ ] Dashboard loads without blank flash
- [ ] No JavaScript errors in console
- [ ] Page scrolling works immediately
- [ ] Theme switching still works
- [ ] Mobile navigation still works

---

**Report Generated:** 27 January 2026
**Phase 12 Status:** COMPLETE - Body hiding disabled for Modern theme
