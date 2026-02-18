# Archived Deployment Scripts

These scripts have been archived on **2026-02-18** during the deployment cleanup initiative.

## Why These Were Archived

The project had **10+ deployment scripts** with multiple competing approaches:
- Git-based deployment (documented, preferred)
- File-sync deployment (undocumented, ad-hoc)
- Various Windows/PowerShell variants

This caused confusion about which script to use and when. We consolidated to a **single git-based deployment workflow** with enhanced safety features.

## Archived Scripts

| Script | Original Purpose | Why Archived |
|--------|-----------------|--------------|
| `claude-deploy.sh` | File-sync deployment for Claude Code | Superseded by git-based `deploy-production.bat` |
| `deploy.sh` | Generic deployment script | Superseded by `safe-deploy.sh` |
| `deploy.bat` | Old Windows deployment | Superseded by `deploy-production.bat` |
| `deploy.ps1` | PowerShell deployment variant | Not documented, superseded |
| `quick-deploy.ps1` | Quick PowerShell deploy | Superseded by `deploy-production.bat quick` |
| `deploy-clean.sh` | Server-side rebuild script | Overlaps with `safe-deploy.sh full` |
| `deploy-production.sh` | Older production deploy | Superseded by `deploy-production.bat` |

## Current Deployment Scripts

**Use these instead:**

| Script | Purpose |
|--------|---------|
| `deploy-production.bat` | Windows → Azure deployment (git-based) |
| `safe-deploy.sh` | Server-side deployment with safety features |
| `verify-deploy.sh` | Verification only (no changes) |

See [../DEPLOYMENT_README.md](../DEPLOYMENT_README.md) for the current deployment workflow.

## Can These Be Deleted?

**Yes**, these scripts can be safely deleted. They are kept here only for:
1. Reference (if someone needs to understand the old deployment approach)
2. Temporary safety net (in case we discover a use case we missed)

**Recommended:** Delete after 30 days (2026-03-20) if no issues arise with the new deployment workflow.
