# Layout System Test Checklist

**Purpose:** Verify the dual-theme system is working correctly before deployment.

**Date:** 2026-01-22

---

## Pre-Flight Checks (Local Environment)

Run these tests on your local environment (`staging.timebank.local`) BEFORE deploying.

### 1. Database Verification

Open phpMyAdmin or MySQL and run these queries:

```sql
-- Check tenants have default_layout column
DESCRIBE tenants;
-- Should show: default_layout varchar(50) or similar

-- Check what each tenant's default is set to
SELECT id, name, slug, default_layout FROM tenants;
-- Expected: Most tenants = NULL or 'modern', nexuscivic = 'civicone'

-- Check users table has preferred_layout column
DESCRIBE users;
-- Should show: preferred_layout varchar(50) or similar

-- Check if tenant_settings table exists and has layout_banner
SELECT * FROM tenant_settings WHERE setting_key = 'feature.layout_banner';
-- May return empty (that's OK - defaults to showing banner)
```

**Pass criteria:** All queries run without errors. Columns exist.

---

### 2. Anonymous User Tests (Logged Out)

Clear your browser cookies/session or use incognito mode.

#### Test 2.1: Default Tenant (modern default)
- [ ] Visit: `http://staging.timebank.local/`
- [ ] **Expected:** Modern theme loads (purple/gradient header)
- [ ] **Expected:** Banner shows "MODERN (Stable)" with "Switch to Accessible" button

#### Test 2.2: Hour Timebank Tenant (modern default)
- [ ] Visit: `http://staging.timebank.local/hour-timebank/`
- [ ] **Expected:** Modern theme loads
- [ ] **Expected:** Banner visible

#### Test 2.3: Theme Switch (Anonymous)
- [ ] Click "Switch to Accessible" button
- [ ] **Expected:** Page reloads with CivicOne theme (green header, GOV.UK style)
- [ ] **Expected:** Banner now shows "DEVELOPMENT" with "Switch to Modern" button
- [ ] Refresh the page
- [ ] **Expected:** CivicOne theme persists (session remembers choice)

#### Test 2.4: Switch Back
- [ ] Click "Switch to Modern" button
- [ ] **Expected:** Modern theme loads
- [ ] Refresh page
- [ ] **Expected:** Modern theme persists

---

### 3. Logged-In User Tests

Log in with a test account.

#### Test 3.1: Fresh User (No Preference Set)
First, clear any existing preference:
```sql
UPDATE users SET preferred_layout = NULL WHERE id = YOUR_USER_ID;
```

- [ ] Log in
- [ ] **Expected:** Theme matches tenant default (modern for most tenants)

#### Test 3.2: User Switches Theme
- [ ] Click the banner switch button
- [ ] **Expected:** Theme changes
- [ ] Check database:
```sql
SELECT id, username, preferred_layout FROM users WHERE id = YOUR_USER_ID;
```
- [ ] **Expected:** `preferred_layout` column now has value ('modern' or 'civicone')

#### Test 3.3: User Preference Persists
- [ ] Log out
- [ ] Log back in
- [ ] **Expected:** Your previously selected theme loads (from DB, not session)

#### Test 3.4: User Preference Survives Browser Close
- [ ] Close browser completely
- [ ] Open browser, visit site
- [ ] Log in
- [ ] **Expected:** Your preferred theme loads

---

### 4. Tenant Isolation Tests

This is the critical test that was failing before.

#### Test 4.1: Different Tenants, Different Defaults
You need two tenants with different default_layout values.

Set up (if not already):
```sql
-- Set one tenant to civicone default
UPDATE tenants SET default_layout = 'civicone' WHERE slug = 'your-test-tenant';
-- Keep another tenant as modern (NULL or 'modern')
UPDATE tenants SET default_layout = 'modern' WHERE slug = 'hour-timebank';
```

- [ ] Open incognito window
- [ ] Visit tenant with modern default: `http://staging.timebank.local/hour-timebank/`
- [ ] **Expected:** Modern theme
- [ ] In SAME browser, visit tenant with civicone default
- [ ] **Expected:** CivicOne theme (NOT modern!)

#### Test 4.2: Switching on One Tenant Doesn't Affect Another
- [ ] On tenant A (modern default), switch to civicone
- [ ] Visit tenant B (also modern default)
- [ ] **Expected:** Tenant B shows modern (not affected by tenant A switch)

---

### 5. Feature Flag Tests

#### Test 5.1: Banner Can Be Disabled
```sql
-- Disable banner for a specific tenant
INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, setting_type)
VALUES (1, 'feature.layout_banner', '0', 'boolean')
ON DUPLICATE KEY UPDATE setting_value = '0';
```

- [ ] Visit that tenant's site
- [ ] **Expected:** NO layout banner appears at top of page
- [ ] **Expected:** Skip link still works (accessibility)

#### Test 5.2: Re-enable Banner
```sql
UPDATE tenant_settings SET setting_value = '1'
WHERE tenant_id = 1 AND setting_key = 'feature.layout_banner';
```

- [ ] Refresh page
- [ ] **Expected:** Banner reappears

---

### 6. Edge Case Tests

#### Test 6.1: Invalid Layout Value
```sql
-- Temporarily set invalid value
UPDATE users SET preferred_layout = 'invalid_theme' WHERE id = YOUR_USER_ID;
```

- [ ] Log in
- [ ] **Expected:** Falls back to tenant default or 'modern' (no error)
- [ ] **Expected:** No PHP errors in logs

#### Test 6.2: Missing Tenant Default
```sql
UPDATE tenants SET default_layout = NULL WHERE id = TENANT_ID;
```

- [ ] Visit that tenant (logged out)
- [ ] **Expected:** Falls back to 'modern'

---

## Results Summary

| Test Category | Pass | Fail | Notes |
|---------------|------|------|-------|
| Database Verification | | | |
| Anonymous User Tests | | | |
| Logged-In User Tests | | | |
| Tenant Isolation Tests | | | |
| Feature Flag Tests | | | |
| Edge Case Tests | | | |

---

## If Tests Fail

### Common Issues and Fixes

**Theme not switching:**
- Check browser console for JavaScript errors
- Verify `layout-switch-helper.js` is loaded
- Check network tab for failed API calls to `/api/layout-switch`

**Wrong theme loading:**
- Check `$_SESSION` contents (add temporary debug)
- Verify `LayoutHelper::get()` is being called
- Check tenant's `default_layout` value in database

**Banner not showing/hiding:**
- Verify `tenant_settings` table exists
- Check `feature.layout_banner` value for that tenant_id
- Look for PHP errors in logs

**Database errors:**
- Run the migration: `migrations/create_tenant_settings_table.sql`
- Verify `preferred_layout` column exists on users table

---

## Post-Deployment Verification

After deploying, run these quick checks on production:

1. [ ] Visit https://project-nexus.ie/ (logged out) → Modern theme
2. [ ] Visit https://nexuscivic.ie/ (logged out) → CivicOne theme
3. [ ] Switch themes → Works and persists
4. [ ] Log in → Theme preference respected
5. [ ] Check error logs: `ssh jasper@35.205.239.67 "tail -20 /var/www/vhosts/project-nexus.ie/logs/error.log"`

---

## Sign-Off

- [ ] All local tests passed
- [ ] Deployed to production
- [ ] Production tests passed
- [ ] No errors in logs

**Tested by:** _________________
**Date:** _________________

---

*This checklist verifies the layout system changes from 2026-01-22*
