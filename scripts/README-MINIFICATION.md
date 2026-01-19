# CSS Minification Scripts - Usage Guide

## TL;DR - Which Script Should I Use?

**For daily development:** Use `php scripts/minify.php`

## Available Scripts

### 1. `minify.php` ⭐ **RECOMMENDED**
**Purpose:** Auto-discovers and minifies all CSS/JS files
**Best for:** Daily development, after any CSS changes

```bash
# Minify everything
php scripts/minify.php

# CSS only
php scripts/minify.php --css

# Check what needs updating
php scripts/minify.php --check
```

**Advantages:**
- ✅ Auto-discovers all `.css` files (never needs manual updates)
- ✅ Only updates changed files (fast)
- ✅ No dependencies (pure PHP)
- ✅ Works immediately after CSS extraction

---

### 2. `minify-css.js`
**Purpose:** Fast CSS-only minification using Node.js
**Best for:** When you want slightly better minification speed

```bash
node scripts/minify-css.js
```

**Advantages:**
- Faster than PHP version
- Maintained file list (updated 2026-01-19 with all Phase 1-14 files)

**Disadvantages:**
- Requires Node.js
- Manual file list (could get out of sync if new CSS files added)

---

### 3. `build-css.js`
**Purpose:** Production optimization with PurgeCSS
**Best for:** Production builds (removes unused CSS)

```bash
node scripts/build-css.js
```

**What it does:**
- Reads from `purgecss.config.js`
- Removes unused CSS selectors
- Outputs to `httpdocs/assets/css/purged/` directory

**Important:** This outputs to a **different directory** (`purged/`) and is not used by the live site. Only use for production optimization.

---

## Current Status (2026-01-19)

All scripts are up-to-date with Phase 1-14 CSS extraction work:

**Files Added (27 new CSS files):**
- Phase 1: `components.css`, `partials.css`, `feed-show.css`
- Phase 2: `federation.css`, `federation-reviews.css`
- Phase 3: `volunteering.css`
- Phase 4: `groups.css`
- Phase 5: `goals.css`
- Phase 6: `polls.css`
- Phase 7: `resources.css`
- Phase 8: `matches.css`
- Phase 9: `organizations.css`
- Phase 10: `help.css`
- Phase 11: `wallet.css`
- Phase 13: `static-pages.css`
- Phase 14: `scattered-singles.css`

Plus page-specific files: `groups-show.css`, `events-*.css`, `blog-*.css`, `listings-*.css`, `messages-*.css`, `notifications.css`, `auth.css`, `post-card.css`, `feed-item.css`, `feed-page.css`, `profile-edit.css`, `nexus-modern-footer.css`

---

## Workflow

After making CSS changes:

```bash
# 1. Edit your CSS file (e.g., groups-show.css)
# 2. Minify it
php scripts/minify.php --css
# 3. Done! The .min.css file is updated
```

---

## Why Multiple Scripts?

- **`minify.php`** - Auto-discovery, no maintenance needed
- **`minify-css.js`** - Faster Node.js alternative
- **`build-css.js`** - Production optimization (different output location)

Each serves a different purpose. Use `minify.php` for daily work.
