# Body/HTML CSS Rules Analysis

## Current CSS Cascade (Modern Layout)

### Load Order:
1. nexus-phoenix.css (line 116)
2. Various page-specific CSS
3. scroll-fix-emergency.css (line 305)
4. federation.css (only on /federation/* pages)

### Computed Styles on BODY:

**From nexus-phoenix.css:**
```css
body {
    max-width: 100% !important;
    overflow-x: hidden !important;  /* ← WINS (has !important) */
    position: relative;              /* ← overridden later */
}
```

**From scroll-fix-emergency.css:**
```css
body {
    overflow-y: auto;                /* ← WINS (phoenix doesn't set) */
    overflow-x: hidden;              /* ← ignored (phoenix has !important) */
    position: static;                /* ← WINS (no !important on phoenix) */
    height: auto;                    /* ← WINS */
    max-height: none;                /* ← WINS */
}
```

**From federation.css (federation pages only):**
```css
body {
    overflow-y: auto !important;     /* ← WINS on /federation/* */
    overflow-x: hidden !important;   /* ← WINS on /federation/* */
    position: static !important;     /* ← WINS on /federation/* */
    height: auto !important;         /* ← WINS on /federation/* */
    min-height: 100% !important;     /* ← WINS on /federation/* */
}
```

### Final Computed Style:

**On non-federation pages:**
```css
body {
    overflow-y: auto;        /* ✓ ALLOWS SCROLLING */
    overflow-x: hidden;      /* ✓ PREVENTS HORIZONTAL SCROLL */
    position: static;        /* ✓ NORMAL POSITIONING */
    height: auto;            /* ✓ FLEXIBLE HEIGHT */
    max-height: none;        /* ✓ NO HEIGHT LIMIT */
}
```

**On /federation/* pages:**
```css
body {
    overflow-y: auto;        /* ✓ ALLOWS SCROLLING */
    overflow-x: hidden;      /* ✓ PREVENTS HORIZONTAL SCROLL */
    position: static;        /* ✓ NORMAL POSITIONING */
    height: auto;            /* ✓ FLEXIBLE HEIGHT */
    min-height: 100%;        /* ✓ FULL HEIGHT */
}
```

## Potential Conflicts:

### ❌ REMOVED - Fixed in commits:
1. **nexus-instant-load.js** - Was setting `overflow: visible` inline (FIXED)
2. **scroll-fix-emergency.css** - Had `overflow-y: visible` (FIXED)

### ✅ ACCEPTABLE - Not conflicts:
1. **phoenix** vs **scroll-fix-emergency** - Work together fine
2. **messages-index.css** - Only applies to `.messages-page` class
3. **messages-thread.css** - Only applies to `.chat-page` class
4. **body.mobile-menu-open** - Only when mobile menu is open (intentional scroll lock)

## Conclusion:

✅ No conflicting rules found
✅ Body scrolling works correctly
✅ All CSS properly cascades
