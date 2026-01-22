# Deployment: Members Directory v1.6.0 Mobile Fixes + Offline Indicator Fix

**Date**: 2026-01-22
**Deployment Type**: CSS + JavaScript Bug Fixes
**Risk Level**: Low (cosmetic and UX fixes)

## Changes Being Deployed

### 1. Mobile Layout Fixes for Members Directory
**Files Modified:**
- `httpdocs/assets/css/civicone-members-directory.css`
- `httpdocs/assets/css/members-directory-v1.6.css`
- `httpdocs/assets/css/purged/civicone-members-directory.min.css`
- `httpdocs/assets/css/members-directory-v1.6.min.css`

**What was fixed:**
- Member cards now stack vertically on mobile (<640px)
- Content is centered on mobile
- Avatars centered at top
- Full-width action buttons (better touch targets)
- Search bar full-width on mobile
- Action bar stacks properly
- Grid view converts to single column

### 2. Offline Indicator Bug Fix
**Files Modified:**
- `httpdocs/assets/js/nexus-mobile.js`
- `httpdocs/assets/js/nexus-mobile.min.js`

**What was fixed:**
- Offline indicator no longer shows incorrectly on desktop PCs
- Increased desktop threshold from 768px to 1024px
- Added mobile user agent detection
- Only shows on actual mobile devices or when truly offline

## Files to Deploy

```
httpdocs/assets/css/civicone-members-directory.css
httpdocs/assets/css/members-directory-v1.6.css
httpdocs/assets/css/purged/civicone-members-directory.min.css
httpdocs/assets/css/members-directory-v1.6.min.css
httpdocs/assets/js/nexus-mobile.js
httpdocs/assets/js/nexus-mobile.min.js
```

## Testing Checklist

After deployment, test:
- [ ] Visit Members page on desktop - should display correctly
- [ ] No "You're offline" message on desktop when online
- [ ] Resize browser to mobile width - member cards stack vertically
- [ ] On actual mobile device - cards display properly
- [ ] Search bar full width on mobile
- [ ] View toggle buttons work (list/grid)
- [ ] Tabs display correctly on mobile
- [ ] Offline indicator only shows when actually offline

## Rollback Plan

If issues occur, restore from backup:
```bash
ssh jasper@35.205.239.67 "cd /var/www/vhosts/project-nexus.ie && tar -xzf backup_YYYYMMDD_HHMMSS.tar.gz"
```

## Deployment Commands

### Quick Deploy (Recommended)
```bash
# Deploy just the changed asset files
scp /c/xampp/htdocs/staging/httpdocs/assets/css/civicone-members-directory.css jasper@35.205.239.67:/var/www/vhosts/project-nexus.ie/httpdocs/assets/css/

scp /c/xampp/htdocs/staging/httpdocs/assets/css/members-directory-v1.6.css jasper@35.205.239.67:/var/www/vhosts/project-nexus.ie/httpdocs/assets/css/

scp /c/xampp/htdocs/staging/httpdocs/assets/css/purged/civicone-members-directory.min.css jasper@35.205.239.67:/var/www/vhosts/project-nexus.ie/httpdocs/assets/css/purged/

scp /c/xampp/htdocs/staging/httpdocs/assets/css/members-directory-v1.6.min.css jasper@35.205.239.67:/var/www/vhosts/project-nexus.ie/httpdocs/assets/css/

scp /c/xampp/htdocs/staging/httpdocs/assets/js/nexus-mobile.js jasper@35.205.239.67:/var/www/vhosts/project-nexus.ie/httpdocs/assets/js/

scp /c/xampp/htdocs/staging/httpdocs/assets/js/nexus-mobile.min.js jasper@35.205.239.67:/var/www/vhosts/project-nexus.ie/httpdocs/assets/js/
```

### One-Liner Deploy
```bash
scp /c/xampp/htdocs/staging/httpdocs/assets/css/civicone-members-directory.css /c/xampp/htdocs/staging/httpdocs/assets/css/members-directory-v1.6.css jasper@35.205.239.67:/var/www/vhosts/project-nexus.ie/httpdocs/assets/css/ && scp /c/xampp/htdocs/staging/httpdocs/assets/css/purged/civicone-members-directory.min.css /c/xampp/htdocs/staging/httpdocs/assets/css/members-directory-v1.6.min.css jasper@35.205.239.67:/var/www/vhosts/project-nexus.ie/httpdocs/assets/css/ && scp /c/xampp/htdocs/staging/httpdocs/assets/js/nexus-mobile.js /c/xampp/htdocs/staging/httpdocs/assets/js/nexus-mobile.min.js jasper@35.205.239.67:/var/www/vhosts/project-nexus.ie/httpdocs/assets/js/ && echo "Deploy complete!"
```

## Cache Busting

Users may need to hard refresh (Ctrl+Shift+R) to see changes, or wait for browser cache expiration.

To force immediate refresh for all users, bump the version:
```bash
node scripts/bump-version.js "Members directory mobile fixes and offline indicator fix"
```

## Impact Assessment

- **User Impact**: Minimal - visual improvements only
- **Downtime**: None expected
- **Cache Clear Needed**: No (CSS/JS changes)
- **Database Changes**: None
- **Breaking Changes**: None

## Documentation Updated

- [MEMBERS-DIRECTORY-V1.6-MOBILE-FIXES.md](MEMBERS-DIRECTORY-V1.6-MOBILE-FIXES.md)
