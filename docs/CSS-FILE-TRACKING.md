# CSS File Tracking Guide

**How to ensure all CSS files are included in the build pipeline**

---

## Problem

When you create new CSS files (e.g., `civicone-new-feature.css`), they won't automatically be included in the PurgeCSS build pipeline unless you manually add them to `purgecss.config.js`.

---

## Solution: Automated Discovery

We've created tools to automatically discover and track CSS files.

---

## 🔍 Method 1: Check for Missing Files

**Run this regularly to see if any CSS files are missing from the config:**

```bash
npm run css:discover
```

**Output:**
```
===========================================
  CSS Discovery Report
===========================================

Total CSS files found: 185
Configured in purgecss.config.js: 182
Missing from config: 3

⚠️  Files NOT in purgecss.config.js:

  - httpdocs/assets/css/civicone-new-feature.css
  - httpdocs/assets/css/members-directory-v1.7.css
  - httpdocs/assets/css/experimental-tabs.css

Add these to purgecss.config.js to include them in the build.
```

**When to run:**
- After creating new CSS files
- Before building for production
- Weekly as part of maintenance

---

## 🤖 Method 2: Auto-Generate Config

**Automatically regenerate `purgecss.config.js` with ALL CSS files:**

```bash
npm run css:auto-config
```

**What it does:**
1. Finds all CSS files in `httpdocs/assets/css/`
2. Excludes: `purged/`, `*.min.css`, `_archive/`, `_archived/`
3. Backs up existing config to `purgecss.config.js.backup`
4. Generates new config with all discovered files

**When to run:**
- When you've created multiple new CSS files
- After major refactoring
- When you're unsure what's missing

**⚠️ Warning:** This regenerates the entire config. If you have custom comments or organization in `purgecss.config.js`, they'll be lost (but backed up).

---

## 📋 Recommended Workflow

### Daily Development:

```bash
# 1. Create new CSS file
touch httpdocs/assets/css/civicone-my-feature.css

# 2. Write your styles
# ...

# 3. Check if it's tracked
npm run css:discover

# 4. If missing, add manually to purgecss.config.js
# OR auto-regenerate config
npm run css:auto-config
```

### Weekly Maintenance:

```bash
# Check for orphaned or missing files
npm run css:discover
```

### Before Production Deploy:

```bash
# Ensure all files are tracked
npm run css:discover

# Build CSS
npm run build:css:purge

# Deploy
npm run deploy
```

---

## 🎯 Best Practices

### 1. **Use Consistent Naming**

All CSS files should follow the pattern:
- `civicone-*.css` - CivicOne theme files
- `nexus-*.css` - Nexus framework files
- `modern-*.css` - Modern theme files
- `[feature]-*.css` - Feature-specific files

### 2. **Document New Files**

When adding to `purgecss.config.js`, add a comment:

```javascript
css: [
    // ... existing files

    // My Feature Module (2026-01-22)
    'httpdocs/assets/css/civicone-my-feature.css',
    'httpdocs/assets/css/my-feature-mobile.css',
]
```

### 3. **Avoid Manual .min.css Files**

Never create `*.min.css` files manually. Always let the build system create them:
- Source: `my-file.css`
- Output: `purged/my-file.min.css` (auto-generated)

### 4. **Use Git Pre-Commit Hook**

Add to `.githooks/pre-commit`:

```bash
#!/bin/bash
# Check for missing CSS files before committing

echo "Checking CSS file tracking..."
MISSING=$(node scripts/discover-css.js | grep -c "Missing from config: [^0]")

if [ $MISSING -gt 0 ]; then
    echo "⚠️  Warning: Some CSS files are not tracked in purgecss.config.js"
    echo "Run: npm run css:discover"
    echo ""
    echo "Continue anyway? (y/n)"
    read -r response
    if [[ ! "$response" =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi
```

---

## 🛠️ Manual Method (Fallback)

If the automated tools don't work, manually add files to `purgecss.config.js`:

```javascript
module.exports = {
    css: [
        // ... existing files

        // Add your new file here
        'httpdocs/assets/css/your-new-file.css',
    ],
    // ...
};
```

**Location:** Line 12-260 in `purgecss.config.js`

---

## 🔬 How Discovery Works

### Files Scanned:
```bash
httpdocs/assets/css/**/*.css
```

### Files Excluded:
- `**/purged/**` - Output directory
- `**/*.min.css` - Minified files
- `**/node_modules/**` - Dependencies
- `**/vendor/**` - Third-party
- `**/_archive/**` - Archived files
- `**/_archived/**` - Archived files
- `**/bundles/**` - Compiled bundles (optional)

### Detection Logic:
1. Find all matching CSS files
2. Load `purgecss.config.js`
3. Compare found files vs configured files
4. Report differences

---

## 📊 File Organization

```
httpdocs/assets/css/
├── civicone-*.css           ← CivicOne theme files
├── nexus-*.css              ← Nexus framework
├── modern-*.css             ← Modern theme
├── [feature]-*.css          ← Feature modules
├── bundles/                 ← Compiled bundles (excluded)
│   └── *.css
├── purged/                  ← Build output (excluded)
│   └── *.min.css
└── _archived/               ← Old files (excluded)
    └── *.css
```

---

## 🚨 Common Issues

### Issue: "New file not being processed"

**Cause:** File not in `purgecss.config.js`

**Solution:**
```bash
npm run css:discover
# Then add file manually or run:
npm run css:auto-config
```

---

### Issue: "Too many files in config"

**Cause:** Old/archived files still listed

**Solution:**
1. Move old files to `_archived/` folder
2. Run: `npm run css:auto-config`
3. Files in `_archived/` are automatically excluded

---

### Issue: "Bundle files being processed twice"

**Cause:** Bundle files AND their source files both listed

**Solution:**
- Either include bundles OR individual files, not both
- Bundles (`bundles/*.css`) are auto-excluded by discovery tool

---

## 📖 Related Documentation

- [CSS-BUILD-PIPELINE.md](CSS-BUILD-PIPELINE.md) - CSS build process
- [CLAUDE.md](../CLAUDE.md) - CSS coding standards
- [DEPLOYMENT-CHEATSHEET.md](DEPLOYMENT-CHEATSHEET.md) - Deployment guide

---

## 🎯 Quick Reference

```bash
# Check for missing files
npm run css:discover

# Auto-regenerate config
npm run css:auto-config

# Build CSS
npm run build:css:purge

# Deploy
npm run deploy
```

---

*Last updated: 2026-01-22*
