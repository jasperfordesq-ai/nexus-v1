# Deployment Scripts Cleanup Plan

## Problem
We have 10+ deployment scripts with multiple competing approaches, causing confusion about which script to use and when.

## Current State Analysis

### Scripts to KEEP
1. **`deploy-production.bat`** - Primary deployment from Windows → Azure (git-based)
2. **`safe-deploy.sh`** - Server-side deployment script (git-based)

### Scripts to ARCHIVE (move to `scripts/archive/`)
- `claude-deploy.sh` - File-sync approach, not documented
- `deploy.sh` - Generic file sync, superseded by git-based
- `deploy.bat` - Old Windows deploy, superseded
- `deploy.ps1` - PowerShell variant, not documented
- `quick-deploy.ps1` - Quick deploy variant
- `deploy-clean.sh` - Overlaps with `safe-deploy.sh`
- `deploy-production.sh` - Older version, superseded by .bat

### Scripts to ENHANCE
- `verify-deploy.sh` - **Keep**, useful standalone verification tool

## Proposed Single Deployment Workflow

### From Development Machine (Windows)
```bash
# 1. Push to GitHub (pre-push hook validates build)
git push origin main

# 2. Deploy to production
scripts\deploy-production.bat           # Full deploy (rebuild)
scripts\deploy-production.bat quick     # Quick deploy (restart only)
scripts\deploy-production.bat status    # Check status
```

### On Production Server (if needed)
```bash
# Manual deployment (SSH to server)
sudo bash scripts/safe-deploy.sh        # Quick: git pull + restart
sudo bash scripts/safe-deploy.sh full   # Full: git pull + rebuild

# Verification only (no changes)
sudo bash scripts/verify-deploy.sh
```

## Improvements Needed

### 1. Add Rollback Capability
Neither script can rollback to previous version if deployment fails.

**Proposed:** Add rollback function to `safe-deploy.sh`:
```bash
# Save current commit before deploy
echo $CURRENT_COMMIT > .last-successful-deploy

# If deploy fails:
sudo bash scripts/safe-deploy.sh rollback
```

### 2. Add Pre-Deploy Validation
Run checks BEFORE deploying to avoid partial failures:
- Check disk space
- Check if containers are healthy
- Verify .env exists
- Test database connectivity

### 3. Add Post-Deploy Smoke Tests
After deployment, automatically verify:
- API endpoints respond (not just health.php)
- Database migrations applied
- Redis cache accessible
- Frontend routes load

### 4. Add Deployment Locks
Prevent concurrent deployments:
```bash
# Create lock file at start
# Remove at end
# Fail if lock exists
```

### 5. Unified Logging
All deployment output should go to:
- `/opt/nexus-php/logs/deploy-YYYY-MM-DD-HH-MM-SS.log`

## Implementation Steps

1. **Create archive directory**
   ```bash
   mkdir scripts/archive
   ```

2. **Move old scripts**
   ```bash
   mv scripts/claude-deploy.sh scripts/archive/
   mv scripts/deploy.sh scripts/archive/
   mv scripts/deploy.bat scripts/archive/
   mv scripts/deploy.ps1 scripts/archive/
   mv scripts/quick-deploy.ps1 scripts/archive/
   mv scripts/deploy-clean.sh scripts/archive/
   mv scripts/deploy-production.sh scripts/archive/
   ```

3. **Enhance remaining scripts**
   - Add rollback to `safe-deploy.sh`
   - Add pre-deploy validation
   - Add post-deploy smoke tests
   - Add deployment locking

4. **Update documentation**
   - Update CLAUDE.md with enhanced workflow
   - Add troubleshooting section for common deploy failures
   - Document rollback procedure

## Benefits

- ✅ Single, clear deployment path (no confusion)
- ✅ Rollback capability (safety net)
- ✅ Pre-flight checks (fail fast)
- ✅ Post-deploy validation (confidence)
- ✅ Deployment history (audit trail)
- ✅ Concurrent deploy prevention (safety)

## Timeline

- **Phase 1** (1 hour): Archive old scripts, document current workflow
- **Phase 2** (2 hours): Add rollback + validation to `safe-deploy.sh`
- **Phase 3** (1 hour): Add smoke tests + logging
- **Phase 4** (30 min): Update CLAUDE.md documentation
