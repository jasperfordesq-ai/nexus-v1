# CSS Tracking Solution - Complete

**Automated system to track new CSS files**

---

## ✅ Problem Solved

When creating new CSS files like `civicone-new-feature.css`, they're now automatically detected and you'll be warned if they're not in the build pipeline.

---

## 🎯 Quick Commands

```bash
# Check if any CSS files are missing from config
npm run css:discover

# Auto-add ALL CSS files to config
npm run css:auto-config
```

---

## 📋 Daily Workflow

### Creating a New CSS File:

```bash
# 1. Create your CSS file
touch httpdocs/assets/css/civicone-my-feature.css

# 2. Write your styles
# ... edit file ...

# 3. Check if it's tracked
npm run css:discover

# 4. If missing, auto-add it
npm run css:auto-config

# 5. Build and deploy
npm run build:css:purge
npm run deploy
```

---

## 🔍 Discovery Tool

**Command:** `npm run css:discover`

**What it does:**
- Scans `httpdocs/assets/css/**/*.css`
- Compares against `purgecss.config.js`
- Reports missing files

**Example output:**
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

---

## 🤖 Auto-Config Tool

**Command:** `npm run css:auto-config`

**What it does:**
1. Finds ALL CSS files automatically
2. Backs up current config to `purgecss.config.js.backup`
3. Generates new config with all files
4. Preserves safelist patterns

**When to use:**
- After creating multiple new CSS files
- After major refactoring
- When discovery shows many missing files

**What it excludes:**
- `purged/` - Output directory
- `*.min.css` - Minified files
- `_archive/`, `_archived/` - Old files
- `node_modules/`, `vendor/` - Dependencies

---

## 🔔 Git Hook (Optional)

A pre-commit hook is available to automatically check for untracked CSS files before commits.

**Setup:**

```bash
# Option 1: Use githooks directory
git config core.hooksPath .githooks

# Option 2: Copy to .git/hooks
cp .githooks/pre-commit-css-check .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit
```

**What it does:**
- Runs before every commit
- Checks if new CSS files are tracked
- Warns you if files are missing
- Lets you continue or cancel commit

---

## 📊 File Organization

```
httpdocs/assets/css/
├── civicone-*.css           ← Auto-discovered
├── nexus-*.css              ← Auto-discovered
├── modern-*.css             ← Auto-discovered
├── design-tokens.css        ← Auto-discovered
├── purged/                  ← Excluded (output)
│   └── *.min.css
└── _archived/               ← Excluded (old files)
    └── *.css
```

---

## 🎯 Best Practices

### 1. **Check Before Building**

```bash
npm run css:discover          # Check
npm run build:css:purge       # Build
```

### 2. **Weekly Maintenance**

```bash
# Run discovery every week
npm run css:discover
```

### 3. **Before Production Deploy**

```bash
# Ensure all files tracked
npm run css:discover

# If missing files found
npm run css:auto-config

# Then build and deploy
npm run build:css:purge
npm run deploy
```

### 4. **Archive Old Files**

Move old CSS files to `_archived/` so they're auto-excluded:

```bash
mkdir -p httpdocs/assets/css/_archived
mv httpdocs/assets/css/old-file.css httpdocs/assets/css/_archived/
```

---

## 🚨 Common Scenarios

### Scenario 1: Created One New File

```bash
# Check if tracked
npm run css:discover

# If missing, manually add to purgecss.config.js:
# Line ~260, add:
# 'httpdocs/assets/css/my-new-file.css',
```

### Scenario 2: Created Many New Files

```bash
# Auto-regenerate entire config
npm run css:auto-config
```

### Scenario 3: Refactored CSS Structure

```bash
# Auto-regenerate to pick up all changes
npm run css:auto-config
```

---

## 📖 Tools Created

| Tool | Location | Purpose |
|------|----------|---------|
| `css:discover` | `scripts/discover-css.js` | Find missing CSS files |
| `css:auto-config` | `scripts/auto-discover-css.js` | Auto-generate config |
| Pre-commit hook | `.githooks/pre-commit-css-check` | Check before commits |

---

## 💡 Pro Tips

### Tip 1: Run Discovery After Git Pull

```bash
git pull
npm run css:discover
```

### Tip 2: Add to CI/CD Pipeline

```yaml
# .github/workflows/build.yml
- name: Check CSS tracking
  run: npm run css:discover
```

### Tip 3: Document New Files

When manually adding to config, add comments:

```javascript
// Members Directory v1.7 (2026-01-22)
'httpdocs/assets/css/members-directory-v1.7.css',
'httpdocs/assets/css/members-mobile-fixes.css',
```

---

## 🔬 Technical Details

### Discovery Logic

```javascript
// Find all CSS files
glob.sync('httpdocs/assets/css/**/*.css', {
    ignore: [
        '**/purged/**',
        '**/*.min.css',
        '**/node_modules/**',
        '**/vendor/**',
        '**/_archive/**',
        '**/_archived/**'
    ]
})

// Compare with purgecss.config.js
const configured = require('./purgecss.config.js').css;
const missing = found.filter(f => !configured.includes(f));
```

### Auto-Config Generation

```javascript
// Generate config programmatically
const config = {
    content: ['views/**/*.php', ...],
    css: discoveredFiles,
    safelist: { ... },
    output: 'httpdocs/assets/css/purged/'
};

fs.writeFileSync('purgecss.config.js', template);
```

---

## 📚 Related Documentation

- [CSS-BUILD-PIPELINE.md](CSS-BUILD-PIPELINE.md) - Full pipeline docs
- [CSS-FILE-TRACKING.md](CSS-FILE-TRACKING.md) - Detailed tracking guide
- [DEPLOYMENT-CHEATSHEET.md](DEPLOYMENT-CHEATSHEET.md) - Deploy commands

---

## ✅ Summary

**You now have 3 ways to ensure CSS files are tracked:**

1. **Manual:** Check `purgecss.config.js` when creating files
2. **Discovery:** Run `npm run css:discover` to find missing files
3. **Automatic:** Use git hook to check on every commit

**Recommended approach:**
- Use discovery tool weekly
- Use auto-config after major refactoring
- Set up git hook for automatic checking

---

*Last updated: 2026-01-22*
*System implemented and tested*
