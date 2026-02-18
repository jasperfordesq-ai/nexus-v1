# Project NEXUS - Deployment Scripts

## Overview

This directory contains **production-ready deployment scripts** for Project NEXUS. Old/deprecated scripts have been archived in `scripts/archive/`.

## Active Deployment Scripts

| Script | Location | Purpose |
|--------|----------|---------|
| `deploy-production.bat` | **Windows** (run locally) | Primary deployment from dev machine → Azure |
| `safe-deploy.sh` | **Azure server** (run via SSH) | Server-side deployment with safety features |
| `verify-deploy.sh` | **Azure server** (run via SSH) | Verification only (no changes) |

## Quick Start

### From Your Windows Machine

```bash
# 1. Commit and push changes to GitHub
git add .
git commit -m "Your changes"
git push origin main

# 2. Deploy to production
scripts\deploy-production.bat           # Full deployment (rebuild)
scripts\deploy-production.bat quick     # Quick deployment (restart only)

# 3. Check status
scripts\deploy-production.bat status

# 4. Rollback if needed
scripts\deploy-production.bat rollback

# 5. View logs
scripts\deploy-production.bat logs
```

### Directly on Azure Server (SSH)

```bash
# SSH to server
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253

# Quick deploy (most common)
cd /opt/nexus-php
sudo bash scripts/safe-deploy.sh quick

# Full deploy (rebuild containers)
sudo bash scripts/safe-deploy.sh full

# Rollback to last successful
sudo bash scripts/safe-deploy.sh rollback

# Check status
sudo bash scripts/safe-deploy.sh status

# Verify deployment (no changes)
sudo bash scripts/verify-deploy.sh
```

## Deployment Modes

### Quick Deploy (Default)
- **Use when:** Code changes only (PHP, React source)
- **What it does:** Git pull → Restart PHP container (clears OPCache)
- **Duration:** ~10 seconds
- **Command:** `deploy-production.bat quick` or `safe-deploy.sh quick`

### Full Deploy
- **Use when:** Dockerfile changes, dependency updates, major changes
- **What it does:** Git pull → Rebuild containers (`--no-cache`) → Start containers
- **Duration:** ~5 minutes
- **Command:** `deploy-production.bat` or `safe-deploy.sh full`

### Rollback
- **Use when:** Deployment failed or introduced bugs
- **What it does:** Reverts to last successful commit → Restarts containers
- **Duration:** ~10 seconds
- **Command:** `deploy-production.bat rollback` or `safe-deploy.sh rollback`

### Status Check
- **Use when:** Want to check current deployment state
- **What it does:** Shows commit, containers, health checks (no changes)
- **Duration:** ~2 seconds
- **Command:** `deploy-production.bat status` or `safe-deploy.sh status`

## Safety Features

### 1. **Deployment Locking**
- Prevents concurrent deployments
- Creates `.deploy.lock` file with PID
- Auto-removes stale locks

### 2. **Rollback Capability**
- Saves last successful commit to `.last-successful-deploy`
- One-command rollback: `deploy-production.bat rollback`
- Works even if current deploy is broken

### 3. **Pre-Deploy Validation**
- ✅ Checks disk space (minimum 1GB free)
- ✅ Verifies critical files (`.env`, `compose.prod.yml`)
- ✅ Confirms containers are running
- ✅ Tests database connectivity
- ✅ Tests Redis connectivity
- ❌ **Fails fast** if validation fails (no partial deploys)

### 4. **Post-Deploy Smoke Tests**
- ✅ API health endpoint (`/health.php`)
- ✅ API bootstrap endpoint (`/api/v2/tenant/bootstrap`)
- ✅ Frontend homepage
- ✅ Sales site homepage
- ✅ Database connectivity
- ✅ Container health status
- ⚠️ **Warns if tests fail** (suggests rollback)

### 5. **Comprehensive Logging**
- All deployments logged to `/opt/nexus-php/logs/deploy-YYYY-MM-DD_HH-MM-SS.log`
- Timestamped entries with color-coded status
- View logs: `deploy-production.bat logs`

### 6. **Production File Protection**
- **NEVER** overwrites `.env` (production secrets)
- **ALWAYS** restores `compose.yml` from `compose.prod.yml` after git pull
- **SAFE** handling of 1173+ untracked files (uploads, vendor, etc.)

## Deployment Workflow

### Normal Deployment

```
1. Developer commits + pushes to GitHub
   ↓
2. deploy-production.bat checks for uncommitted/unpushed changes
   ↓
3. safe-deploy.sh acquires deployment lock
   ↓
4. Pre-deploy validation runs (disk, files, containers, database)
   ↓
5. Git pull + reset to origin/main
   ↓
6. Restore compose.yml from compose.prod.yml
   ↓
7. Quick: Restart containers | Full: Rebuild + restart
   ↓
8. Post-deploy smoke tests run
   ↓
9. If all tests pass → Save commit to .last-successful-deploy
   ↓
10. Release deployment lock
```

### Rollback Workflow

```
1. deploy-production.bat rollback (confirms with user)
   ↓
2. safe-deploy.sh reads .last-successful-deploy
   ↓
3. Git checkout to last successful commit
   ↓
4. Restore compose.yml from compose.prod.yml
   ↓
5. Restart containers
   ↓
6. Smoke tests confirm rollback success
```

## Troubleshooting

### Deployment Failed Mid-Way

```bash
# 1. Check what went wrong
scripts\deploy-production.bat logs

# 2. Rollback to last known good state
scripts\deploy-production.bat rollback

# 3. Verify rollback succeeded
scripts\deploy-production.bat status
```

### "Another deployment is running" Error

```bash
# Check if deploy is actually running
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253
ps aux | grep safe-deploy

# If no deploy is running (stale lock):
cd /opt/nexus-php
sudo rm -f .deploy.lock

# Try deploy again
```

### Smoke Tests Failed

```bash
# Check recent logs
scripts\deploy-production.bat logs

# Check container status
scripts\deploy-production.bat status

# If containers are unhealthy:
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253
cd /opt/nexus-php
sudo docker compose logs app --tail=50

# Rollback if needed
scripts\deploy-production.bat rollback
```

### Out of Disk Space

```bash
# SSH to server
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253

# Check disk usage
df -h /opt/nexus-php

# Clean old Docker images/containers (SAFE - only unused)
sudo docker system prune -f

# Clean old deployment logs
cd /opt/nexus-php/logs
sudo rm -f deploy-*.log

# Clean Docker build cache (if desperate)
sudo docker builder prune -f
```

## File Locations

### On Production Server (`/opt/nexus-php/`)

| File/Directory | Purpose |
|----------------|---------|
| `.deploy.lock` | Deployment lock file (PID of running deploy) |
| `.last-successful-deploy` | Git commit hash of last successful deployment |
| `logs/deploy-*.log` | Timestamped deployment logs |
| `compose.yml` | Active Docker Compose config (copied from `compose.prod.yml`) |
| `compose.prod.yml` | Production Docker Compose template (tracked in git) |
| `compose.yml.pre-deploy-backup` | Backup of compose.yml before deploy |

## Archived Scripts

The following scripts have been moved to `scripts/archive/` and should **NOT** be used:

- ❌ `claude-deploy.sh` - File-sync approach, superseded by git-based
- ❌ `deploy.sh` - Generic deploy, superseded
- ❌ `deploy.bat` - Old Windows deploy
- ❌ `deploy.ps1` - PowerShell variant
- ❌ `quick-deploy.ps1` - Quick deploy variant
- ❌ `deploy-clean.sh` - Overlaps with `safe-deploy.sh full`
- ❌ `deploy-production.sh` - Older version

**These scripts are kept for reference only and may be deleted in the future.**

## Best Practices

1. **Always push to GitHub first**
   - The deploy script will warn if you have unpushed commits
   - Production deploys from GitHub, not your local machine

2. **Use quick deploy for most changes**
   - PHP code changes: `quick`
   - React code changes: `quick`
   - Database changes: `quick`

3. **Use full deploy sparingly**
   - Dockerfile changes: `full`
   - composer.json changes: `full`
   - package.json changes: `full`

4. **Test rollback in staging first**
   - Verify rollback works before you need it in production

5. **Monitor deployment logs**
   - Check logs after deployment: `deploy-production.bat logs`
   - Save logs locally if deployment fails

6. **Never run git commands directly on production**
   - Always use `safe-deploy.sh` which protects production files
   - Manual git commands can overwrite `.env`, `compose.yml`, etc.

## Support

For deployment issues:
1. Check `logs/deploy-*.log` on server
2. Run `scripts\deploy-production.bat status`
3. Review [docs/DEPLOYMENT.md](../docs/DEPLOYMENT.md)
4. Check container logs: `sudo docker compose logs app --tail=100`
