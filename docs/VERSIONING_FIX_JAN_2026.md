# Versioning System Integration - Terms Page

**Date:** January 24, 2026
**Changes:** Integrated terms-page.css and terms-page.js into centralized versioning system

---

## Summary

The terms page CSS and JavaScript files have been integrated into the project's centralized cache-busting versioning system. Both files now automatically receive version query strings from the deployment version configuration.

---

## Changes Made

### 1. **Page-Specific CSS Loader** ✅

**File:** `views/layouts/modern/partials/page-css-loader.php`

**Added:**
```php
// Terms page
'terms' => [
    'condition' => strpos($normPath, '/terms') !== false,
    'files' => ['terms-page.css']
]
```

**Result:**
- terms-page.css now loads automatically when `/terms` route is accessed
- Receives version parameter from `$cssVersionTimestamp`
- Example output: `/assets/css/terms-page.css?v=1769271191`

---

### 2. **Removed Hardcoded CSS Link** ✅

**File:** `views/tenants/hour-timebank/modern/pages/terms.php`

**Before:**
```php
<link rel="stylesheet" href="/assets/css/terms-page.css">
```

**After:**
```php
<!-- Terms Page Styles - Loaded via page-specific CSS loader in header.php -->
```

**Reason:** CSS is now automatically loaded by the page-specific CSS loader with proper versioning

---

### 3. **Added Versioned JavaScript** ✅

**File:** `views/tenants/hour-timebank/modern/pages/terms.php`

**Before:**
```php
<script src="/assets/js/terms-page.js"></script>
```

**After:**
```php
<?php
// Use deployment version for cache busting (same pattern as footer.php)
$deploymentVersion = file_exists(__DIR__ . '/../../../../config/deployment-version.php')
    ? require __DIR__ . '/../../../../config/deployment-version.php'
    : ['version' => time()];
$jsVersion = $deploymentVersion['version'] ?? time();
?>
<script src="/assets/js/terms-page.js?v=<?= $jsVersion ?>" defer></script>
```

**Result:**
- JavaScript file now receives version parameter
- Uses same deployment-version.php as all other assets
- Added `defer` attribute for better performance
- Example output: `/assets/js/terms-page.js?v=1769271191`

---

## How It Works Now

### **Version Flow**

```
config/deployment-version.php
    │
    ├─> header.php → $cssVersionTimestamp
    │       │
    │       └─> css-loader.php
    │               │
    │               └─> page-css-loader.php
    │                       │
    │                       └─> terms-page.css?v=1769271191
    │
    └─> terms.php → $jsVersion
            │
            └─> terms-page.js?v=1769271191
```

### **Automatic Cache Busting**

When you update `config/deployment-version.php`:

```php
return [
    'version' => '2.0.6',  // Change this number
    'timestamp' => time(),
    'description' => 'Terms page update'
];
```

**Result:**
- All CSS files get new version: `?v=2.0.6`
- All JS files get new version: `?v=2.0.6`
- Browsers automatically reload assets (no manual cache clearing)

---

## Verification

### **Test Results**

**URL:** `http://staging.timebank.local/hour-timebank/terms`

**CSS Loading:**
```html
<link rel="stylesheet" href="/assets/css/terms-page.css?v=1769271191">
```
✅ Version parameter present
✅ Loaded via page-specific loader
✅ Matches other asset versions

**JavaScript Loading:**
```html
<script src="/assets/js/terms-page.js?v=1769271191" defer></script>
```
✅ Version parameter present
✅ Uses deployment-version.php
✅ Includes `defer` attribute for performance

**All Assets Using Same Version:**
```
v=1769271191
```
✅ Consistent across all assets

---

## Benefits

### **Before:**
- ❌ terms-page.css had no version → cached indefinitely
- ❌ terms-page.js had no version → cached indefinitely
- ❌ Required manual cache clearing after updates
- ❌ Inconsistent with rest of platform

### **After:**
- ✅ Both files use centralized versioning
- ✅ Automatic cache busting on deployment
- ✅ Consistent with all other platform assets
- ✅ No manual intervention required
- ✅ Better performance with `defer` attribute on JS

---

## Deployment Impact

### **How to Update in Production**

**Option 1: Update deployment-version.php**
```bash
# SSH to server
ssh jasper@35.205.239.67

# Navigate to project
cd /var/www/vhosts/project-nexus.ie

# Update version
nano config/deployment-version.php
# Change: 'version' => '2.0.6'
# Save and exit

# All users get new assets on next page load
```

**Option 2: Use Deployment Script**
```bash
# In your local deployment script
echo "<?php return ['version' => '$(date +%s)', 'timestamp' => $(date +%s), 'description' => 'Automated deploy'];" > config/deployment-version.php

# Then deploy as normal
npm run deploy
```

---

## Files Modified

1. ✅ `views/layouts/modern/partials/page-css-loader.php` - Added terms CSS config
2. ✅ `views/tenants/hour-timebank/modern/pages/terms.php` - Removed hardcoded CSS, added versioned JS
3. ✅ `purgecss.config.js` - Previously added terms-page.css (already done)

---

## Technical Notes

### **Why Different Patterns for CSS vs JS?**

**CSS:**
- Managed by centralized `page-css-loader.php`
- Conditional loading based on route
- All CSS uses same `$cssVersionTimestamp` variable from header.php

**JavaScript:**
- No centralized JS loader exists (yet)
- Each page loads JS individually
- Uses same deployment version file for consistency

**Future Enhancement:**
Consider creating a `page-js-loader.php` similar to CSS loader for better consistency.

---

## Testing Checklist

- [x] Terms page loads correctly
- [x] terms-page.css has version parameter
- [x] terms-page.js has version parameter
- [x] Version matches other assets
- [x] Page content displays properly
- [x] Smooth scrolling works (JavaScript functional)
- [x] CSS styling intact (glassmorphism effects)

---

## Related Documentation

- **Versioning System Report:** `docs/VERSIONING_REPORT_JAN_2026.md` (if exists)
- **Terms Update Report:** `docs/INSURANCE_TERMS_UPDATE_JAN_2026.md`
- **Deployment Version:** `config/deployment-version.php`

---

**Updated By:** Claude Sonnet 4.5
**Verified On:** January 24, 2026
