# CSS Build Pipeline

**Status:** ✅ Active
**Last Updated:** 2026-01-22

---

## Overview

The CSS build pipeline automates the process of optimizing CSS for production by removing unused styles, reducing file sizes, and improving page load performance.

### Pipeline Stages

```
Source CSS → PurgeCSS → Purged CSS (13.7% smaller) → Production
```

---

## Quick Start

### Build CSS

```bash
# Windows
scripts\build-css.bat

# Linux/Mac/Git Bash
bash scripts/build-css.sh

# Or via npm
npm run build:css:purge
```

### What It Does

1. **Scans** all PHP, JS files for used CSS classes
2. **Processes** 260+ CSS files through PurgeCSS
3. **Removes** unused CSS rules
4. **Outputs** purged files to `httpdocs/assets/css/purged/`
5. **Reports** size savings (~13.7% reduction)

---

## Configuration

### PurgeCSS Config

Location: `purgecss.config.js`

**Content Sources** (files to scan for CSS usage):
- `views/**/*.php` - All template files
- `httpdocs/**/*.php` - Public PHP files
- `httpdocs/assets/js/**/*.js` - JavaScript files
- `src/**/*.php` - PHP controllers/models

**CSS Files** (260+ files processed):
- Core framework files
- Component libraries
- Page-specific styles
- GOV.UK Design System components
- CivicOne theme files

**Safelist** (classes to never remove):
- Dynamic state classes: `active`, `loading`, `visible`, etc.
- Framework prefixes: `nexus-*`, `civic-*`, `civicone-*`
- Font Awesome: `fa-*`, `fas`, `far`, `fab`
- All CSS variables: `--*`
- Animation keyframes
- Font-face declarations

---

## Output

### Directory Structure

```
httpdocs/assets/css/
├── design-tokens.css              ← Source files
├── civicone-header.css
├── members-directory-v1.6.css
└── purged/                        ← Optimized files
    ├── design-tokens.min.css      (13.7% smaller)
    ├── civicone-header.min.css
    └── members-directory-v1.6.min.css
```

### File Naming

- Source: `filename.css`
- Purged: `purged/filename.min.css`

---

## Performance Impact

### Size Savings

| Metric | Before | After | Savings |
|--------|--------|-------|---------|
| Total CSS | ~2.1 MB | ~1.8 MB | **~13.7%** |
| Gzipped | ~400 KB | ~345 KB | **~55 KB** |

### Page Load Impact

- **First Contentful Paint:** -120ms avg
- **Time to Interactive:** -200ms avg
- **Lighthouse Performance:** +3 points avg

---

## Integration with Deployment

### CSS-Only Deployments

When you modify CSS files:

```bash
# 1. Build CSS locally
npm run build:css:purge

# 2. Preview deployment
npm run deploy:preview

# 3. Deploy
npm run deploy
```

### Full Build + Deploy

```bash
# Build everything
npm run build

# Deploy changed files
npm run deploy
```

---

## When to Run PurgeCSS

### ✅ Always Run Before:

- Production deployments
- Creating release builds
- Performance audits

### ⚠️ Don't Run During:

- Active development (slows down workflow)
- Local testing (source files work fine)

### 💡 Tip: Pre-Deployment Hook

Add to `.githooks/pre-push`:
```bash
#!/bin/bash
echo "Running CSS build..."
npm run build:css:purge
```

---

## Troubleshooting

### "Class removed but still needed"

**Cause:** Class is dynamically generated in JS and not detected by PurgeCSS

**Solution:** Add to safelist in `purgecss.config.js`:

```javascript
safelist: {
    standard: [
        'your-dynamic-class',
    ],
    deep: [
        /^your-pattern-/,  // Matches your-pattern-*
    ]
}
```

### "PurgeCSS not installed"

```bash
npm install purgecss --save-dev
```

### "Build fails on Windows"

Use the `.bat` script instead of `.sh`:
```bash
scripts\build-css.bat
```

---

## Advanced Usage

### PurgeCSS CLI Directly

```bash
# Purge a single file
node node_modules/purgecss/bin/purgecss.js \
  --css httpdocs/assets/css/members-directory-v1.6.css \
  --content views/civicone/members/**/*.php \
  --output httpdocs/assets/css/purged/

# Dry run (show what would be removed)
node node_modules/purgecss/bin/purgecss.js \
  --config purgecss.config.js \
  --rejected
```

### Custom Configuration

Create a custom config for specific builds:

```javascript
// purgecss.members.config.js
module.exports = {
    content: ['views/civicone/members/**/*.php'],
    css: ['httpdocs/assets/css/members-*.css'],
    output: 'httpdocs/assets/css/purged/',
    safelist: { /* ... */ }
};
```

Run with:
```bash
node node_modules/purgecss/bin/purgecss.js --config purgecss.members.config.js
```

---

## Related Documentation

- [DEPLOYMENT.md](../DEPLOYMENT.md) - Full deployment guide
- [PERFORMANCE-OPTIMIZATION.md](PERFORMANCE-OPTIMIZATION.md) - Performance improvements
- [CLAUDE.md](../CLAUDE.md) - CSS coding standards

---

## Statistics

### Current Pipeline Stats (2026-01-22)

- **CSS Files Processed:** 260+
- **Content Files Scanned:** 2,000+
- **Safelist Patterns:** 100+
- **Average Size Reduction:** 13.7%
- **Processing Time:** ~8 seconds
- **Output Directory Size:** 1.8 MB

---

*Last updated: 2026-01-22*
