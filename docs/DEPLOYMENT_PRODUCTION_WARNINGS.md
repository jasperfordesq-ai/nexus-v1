# ⚠️ PRODUCTION DEPLOYMENT WARNINGS - READ BEFORE DEPLOYING

**Status:** 🔴 **NOT YET TESTED ON PRODUCTION**

**Date:** 2026-02-18

## Critical: Do Not Deploy to Production Yet

The deployment scripts have been:
- ✅ Tested locally on Windows Docker
- ✅ Verified for logic correctness
- ✅ Syntax validated

But they have **NOT** been:
- ❌ Tested on the actual production server
- ❌ Verified with production paths (`/opt/nexus-php/`)
- ❌ Tested with production `.env` file
- ❌ Tested on Linux (production runs Ubuntu/Debian, local is Windows)
- ❌ Verified that git operations work correctly on production

## What Could Go Wrong 🚨

### 1. Destructive Git Operations

The `safe-deploy.sh` script uses these **DESTRUCTIVE** git commands:

```bash
# Line 233, 259: Overwrites all tracked files
git reset --hard origin/main

# Line 300: Switches to different commit
git checkout "$LAST_COMMIT"
```

**Risk:** If paths are wrong or `.env`/`compose.yml` restoration fails, you could:
- ❌ Overwrite production `.env` with dev version (lose secrets)
- ❌ Overwrite production `compose.yml` with dev version (wrong ports/config)
- ❌ Delete production files
- ❌ Break the entire production site

### 2. Hardcoded Paths

The script assumes these paths exist:
- `/opt/nexus-php/` - Deploy directory
- `/opt/nexus-php/.env` - Production secrets
- `/opt/nexus-php/compose.prod.yml` - Production compose template
- `/opt/nexus-php/logs/` - Log directory (created if missing)

**Risk:** If paths don't exist or are wrong, script will fail or create files in wrong location.

### 3. Database Password

The script reads `DB_PASSWORD` from `.env` file:

```bash
DB_PASS=$(grep "^DB_PASSWORD=" "$DEPLOY_DIR/.env" ...)
```

**Risk:** If `.env` doesn't have `DB_PASSWORD` field, falls back to `nexus_secret` which might be wrong.

### 4. Container Names

The script assumes these containers exist:
- `nexus-php-app` - PHP application
- `nexus-php-db` - MariaDB database
- `nexus-php-redis` - Redis cache
- `nexus-react-prod` - React frontend
- `nexus-sales-site` - Sales site

**Risk:** If container names are different, validation and smoke tests will fail.

## Required Pre-Production Checklist

Before deploying to production, you **MUST** complete ALL these steps:

### ☐ Step 1: Run Production Readiness Check

```bash
# SSH to production
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253

# Upload scripts (if not already there)
cd /opt/nexus-php
sudo git fetch origin main
sudo git status

# Run readiness check
sudo bash scripts/verify-production-ready.sh
```

**Expected output:**
```
✅ Production is READY for safe-deploy.sh
```

If you see any `[FAIL]` messages, **STOP** and fix them first.

### ☐ Step 2: Verify Git Safety

```bash
# Check current git status
sudo git status
sudo git log --oneline -3

# Verify compose.prod.yml exists
ls -lh compose.prod.yml

# Verify .env exists and has DB_PASSWORD
grep "^DB_PASSWORD=" .env
```

### ☐ Step 3: Test Status Mode (SAFE - No Changes)

```bash
sudo bash scripts/safe-deploy.sh status
```

**Expected output:**
- Shows current commit
- Shows container status
- Shows recent logs
- No errors

### ☐ Step 4: Backup Before Testing

```bash
# Backup critical files
sudo cp .env .env.backup-$(date +%Y%m%d)
sudo cp compose.yml compose.yml.backup-$(date +%Y%m%d)

# Save current commit
git rev-parse HEAD > .last-known-good-commit
```

### ☐ Step 5: Test During Low-Traffic Time

Pick a low-traffic time (e.g., 3am) for first test.

```bash
# Quick deploy (restart only)
sudo bash scripts/safe-deploy.sh quick
```

**Monitor:**
- Watch logs: `sudo docker logs -f nexus-php-app`
- Test API: `curl http://127.0.0.1:8090/health.php`
- Test frontend: `curl http://127.0.0.1:3000/`

### ☐ Step 6: Test Rollback

**CRITICAL:** Test rollback BEFORE you need it in emergency.

```bash
# Make a small change, push to GitHub, deploy
# Then rollback to previous commit
sudo bash scripts/safe-deploy.sh rollback
```

**Verify rollback worked:**
```bash
sudo bash scripts/safe-deploy.sh status
# Should show previous commit
```

## Known Issues

### 1. Windows vs Linux Line Endings

The scripts were created on Windows. Line endings might need conversion:

```bash
# On production, if script fails with "command not found"
sudo dos2unix scripts/safe-deploy.sh
sudo dos2unix scripts/verify-production-ready.sh
```

### 2. Port Numbers

Production uses different ports than documented:

| Service | Expected Port | Actual Port? |
|---------|---------------|--------------|
| PHP API | 8090 | Verify in compose.prod.yml |
| React Frontend | 3000 | Verify in compose.prod.yml |
| Sales Site | 3003 | Verify in compose.prod.yml |

Check `compose.prod.yml` on production to verify.

### 3. Database Password Field Name

The script looks for `DB_PASSWORD=` in `.env`. Production might use:
- `DB_PASS=`
- `DATABASE_PASSWORD=`
- `MYSQL_PASSWORD=`

Check your production `.env` file.

## Rollback Plan If Deploy Breaks Production

If deployment breaks the site:

### Option 1: Use Built-in Rollback (If Available)

```bash
sudo bash scripts/safe-deploy.sh rollback
```

### Option 2: Manual Git Rollback

```bash
cd /opt/nexus-php

# Find last good commit
cat .last-known-good-commit

# Or check git log
sudo git log --oneline -10

# Checkout last good commit
sudo git checkout <commit-hash>

# Restore compose.yml
sudo cp compose.prod.yml compose.yml

# Restart containers
sudo docker restart nexus-php-app
```

### Option 3: Restore from Backup

```bash
# Restore .env
sudo cp .env.backup-YYYYMMDD .env

# Restore compose.yml
sudo cp compose.yml.backup-YYYYMMDD compose.yml

# Restart containers
sudo docker compose restart
```

## When Production Testing is Complete

After successful production testing, update:
1. `docs/DEPLOYMENT_PRODUCTION_WARNINGS.md` - Mark as tested
2. `docs/DEPLOYMENT_TEST_REPORT.md` - Add production test results
3. `CLAUDE.md` - Remove warnings about untested deployment
4. `MEMORY.md` - Note that deployment scripts are production-ready

## Emergency Contact

If deployment breaks production and you can't fix it:
1. Check logs: `sudo docker logs nexus-php-app --tail=100`
2. Check container status: `sudo docker ps -a`
3. Restore from backup (see above)
4. Consider reverting to old deployment method temporarily

## Files to Review Before Deploying

1. `scripts/safe-deploy.sh` - Main deployment script (420 lines)
2. `scripts/verify-production-ready.sh` - Pre-deployment checks (130 lines)
3. `scripts/deploy-production.bat` - Windows remote deploy (232 lines)
4. `compose.prod.yml` - Production Docker Compose config

---

**SUMMARY:** The deployment scripts are theoretically sound but **UNTESTED ON PRODUCTION**. Complete the 6-step checklist above before deploying, and test during low-traffic time with backups ready.
