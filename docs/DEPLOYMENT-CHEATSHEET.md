# Deployment Cheatsheet

**Quick reference for deploying to project-nexus.ie**

---

## 🚀 Quick Deploy Commands

### CSS Changes Only

```bash
# 1. Build CSS
npm run build:css:purge

# 2. Preview what will deploy
npm run deploy:preview

# 3. Deploy
npm run deploy
```

### PHP/Controller Changes

```bash
# Preview then deploy
npm run deploy:preview
npm run deploy
```

### Full Deploy (Everything)

```bash
npm run deploy:full
```

---

## 📋 Complete Workflows

### Workflow 1: CSS Update

```bash
# Make CSS changes in: httpdocs/assets/css/
# Test locally at localhost

# Build optimized CSS
npm run build:css:purge

# Preview deployment (shows which files)
npm run deploy:preview

# Commit changes
git add httpdocs/assets/css/
git commit -m "feat: Update members directory mobile styles"

# Deploy only changed files
npm run deploy

# Verify site
curl -I https://project-nexus.ie/
```

**Deploys:** Only CSS files that changed
**Downtime:** None
**Duration:** ~10 seconds

---

### Workflow 2: PHP/View Update

```bash
# Make changes in: src/ or views/
# Test locally at localhost

# Commit changes
git add src/ views/
git commit -m "fix: Update members API filtering"

# Preview what will deploy
npm run deploy:preview

# Deploy
npm run deploy

# Check for PHP errors
ssh jasper@35.205.239.67 "tail -20 /var/www/vhosts/project-nexus.ie/logs/error.log"
```

**Deploys:** Changed PHP files only
**Downtime:** None
**Duration:** ~15 seconds

---

### Workflow 3: Major Update (Multiple Areas)

```bash
# Make changes across: src/, views/, httpdocs/, config/
# Test thoroughly locally

# Commit all changes
git add .
git commit -m "feat: Members directory v1.7"

# Preview EVERYTHING that will deploy
npm run deploy:preview

# Deploy all changed files with verification
npm run deploy

# Monitor site
curl -s -o /dev/null -w "Status: %{http_code}\n" https://project-nexus.ie/
```

**Deploys:** All changed files
**Downtime:** None (or enable maintenance mode)
**Duration:** ~30-60 seconds

---

## 🔧 Available Commands

### Build Commands

| Command | Purpose |
|---------|---------|
| `npm run build:css:purge` | Run PurgeCSS on all CSS files |
| `npm run minify:css` | Minify CSS files |
| `npm run minify:js` | Minify JavaScript files |
| `npm run build` | Full build (CSS, JS, images) |

### Deployment Commands

| Command | Purpose |
|---------|---------|
| `npm run deploy:preview` | Preview what will deploy (dry run) |
| `npm run deploy` | Deploy committed changes with verification |
| `npm run deploy:changed` | Deploy uncommitted changes |
| `npm run deploy:full` | Deploy all folders (full deploy) |

### Manual Deployment (Git Bash)

```bash
# Deploy changed files from last commit
bash scripts/claude-deploy.sh --last-commit

# Deploy uncommitted changes
bash scripts/claude-deploy.sh --changed

# Deploy with verification
bash scripts/claude-deploy.sh --last-commit --verify

# Deploy specific file
bash scripts/claude-deploy.sh --file src/Controllers/MembersController.php
```

---

## 🎯 What Gets Deployed

### Automatic Detection

The deployment script automatically detects changes in these folders:

✅ **Deployed:**
- `src/` - Controllers, models, services
- `views/` - PHP template files
- `httpdocs/` - Public files, routes, assets
- `config/` - Configuration files
- `migrations/` - Database migrations
- `scripts/` - Cron jobs, utilities

❌ **Never Deployed:**
- `.env` - Contains secrets (server has its own)
- `vendor/` - Install via composer on server
- `node_modules/` - Not needed on server
- `backups/`, `exports/` - Local data only
- `.git/` - Version control
- `*.log`, `*.md` - Logs and documentation

---

## 🛡️ Safety Features

### Built-In Safeguards

1. **PHP Syntax Check** - Won't deploy broken PHP files
2. **File Verification** - Confirms files deployed correctly
3. **Site Health Check** - Verifies site responds after deploy
4. **Deployment Log** - Records all deployments to `logs/deploy-manifest.log`
5. **Git-Aware** - Only deploys files you've changed

---

## 🚨 Emergency Procedures

### Site Down After Deploy

```bash
# Check what's wrong
ssh jasper@35.205.239.67 "tail -50 /var/www/vhosts/project-nexus.ie/logs/error.log"

# Quick rollback (if you have recent backup)
ssh jasper@35.205.239.67 "cd /var/www/vhosts/project-nexus.ie && tar -xzf backup_YYYYMMDD_HHMMSS.tar.gz"
```

### Deploy Wrong File

```bash
# Re-deploy the correct file
bash scripts/claude-deploy.sh --file path/to/correct/file.php

# Or restore from git
git checkout HEAD -- path/to/file.php
npm run deploy
```

### Clear Server Cache

```bash
ssh jasper@35.205.239.67 "rm -rf /var/www/vhosts/project-nexus.ie/cache/*"
```

---

## 📊 Deployment Logs

### View Recent Deployments

```bash
# Last 10 deployments
tail -10 logs/deploy-manifest.log

# Today's deployments
grep "$(date '+%Y-%m-%d')" logs/deploy-manifest.log
```

### Example Log Entry

```
[2026-01-22 14:30:45] Deployed 3 file(s): httpdocs/assets/css/purged/members-directory-v1.6.min.css views/civicone/members/directory.php src/Controllers/Api/MembersApiController.php
```

---

## 💡 Pro Tips

### Tip 1: Always Preview First

```bash
npm run deploy:preview  # See what will deploy
npm run deploy         # Actually deploy
```

### Tip 2: Deploy CSS Separately

CSS changes are low-risk and fast:

```bash
# Just CSS
bash scripts/claude-deploy.sh --file httpdocs/assets/css/purged/my-file.min.css
```

### Tip 3: Use Git Commits as Deploy Checkpoints

```bash
# Make changes
git add .
git commit -m "feat: New feature"

# Deploy exactly what you committed
npm run deploy
```

### Tip 4: Test Locally First

**Always test at localhost before deploying!**

```bash
# Start local server
php -S localhost:8000 -t httpdocs/

# Test changes
# Then commit and deploy
```

---

## 🔍 Verification Checklist

After every deployment:

- [ ] Site loads: `curl -I https://project-nexus.ie/`
- [ ] No PHP errors: Check error logs
- [ ] Test the feature you changed
- [ ] Check deployment log: `tail -1 logs/deploy-manifest.log`

---

## 📞 Quick Reference

| Server | Value |
|--------|-------|
| **Host** | 35.205.239.67 |
| **User** | jasper |
| **Path** | /var/www/vhosts/project-nexus.ie |
| **URL** | https://project-nexus.ie |
| **SSH** | `ssh jasper@35.205.239.67` |

### Common Paths

```
Server Path: /var/www/vhosts/project-nexus.ie/
├── src/                  # PHP controllers/models
├── views/                # Template files
├── httpdocs/             # Public web root
│   ├── assets/css/       # Stylesheets
│   ├── assets/js/        # JavaScript
│   └── routes.php        # Route definitions
├── config/               # Configuration
├── logs/                 # Error logs
└── cache/                # Server cache
```

---

## 🎓 Learning Resources

- [DEPLOYMENT.md](../DEPLOYMENT.md) - Complete deployment guide
- [CSS-BUILD-PIPELINE.md](CSS-BUILD-PIPELINE.md) - CSS build process
- [CLAUDE.md](../CLAUDE.md) - Coding standards

---

## ⚡ One-Liner Deployments

```bash
# CSS only
npm run build:css:purge && npm run deploy

# Quick check + deploy
npm run deploy:preview && npm run deploy

# Full build + deploy
npm run build && npm run deploy:full

# Deploy + check site
npm run deploy && curl -I https://project-nexus.ie/
```

---

*Last updated: 2026-01-22*
*Keep this file handy - bookmark it in your browser!*
