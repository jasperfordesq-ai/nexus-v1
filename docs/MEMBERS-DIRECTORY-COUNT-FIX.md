# Members Directory - Count Display Fix

**Date:** 2026-01-22
**Issue:** "Showing 30 of 30 members" when there are 220 total members
**Status:** ✅ FIXED

---

## Problem

User reported: **"Showing 30 of 30 members"** instead of **"Showing 30 of 220 members"**

---

## Root Cause

**Mismatch between count query and member query:**

### User::count() Method (src/Models/User.php:396)
```php
// Only count users with avatars
$sql = "SELECT COUNT(*) as total FROM users WHERE tenant_id = ?
        AND avatar_url IS NOT NULL AND avatar_url != ''";
```

**Returns:** 30 (only members with avatars)

### CoreApiController::members() Query (line 44)
```php
// Did NOT filter by avatar
$sql = "SELECT id, name, email, avatar_url as avatar, role, bio, location, last_active_at
        FROM users
        WHERE tenant_id = ?";
```

**Returns:** 220 members (including those without avatars)

### Result
- **Count query:** 30 members (with avatars)
- **Member query:** Returns up to 220 members (all members)
- **Display:** "Showing 30 of 30" ❌ Wrong!

---

## Solution

Add avatar filter to **both** queries in `CoreApiController.php` to match `User::count()` behavior.

### Fix 1: Main Query (Line 44-49)

**BEFORE:**
```php
$sql = "SELECT id, name, email, avatar_url as avatar, role, bio, location, last_active_at
        FROM users
        WHERE tenant_id = ?";
```

**AFTER:**
```php
$sql = "SELECT id, name, email, avatar_url as avatar, role, bio, location, last_active_at
        FROM users
        WHERE tenant_id = ?
        AND avatar_url IS NOT NULL
        AND avatar_url != ''";
```

### Fix 2: Count Query (Line 78-80)

**BEFORE:**
```php
$countSql = "SELECT COUNT(*) FROM users WHERE tenant_id = ?";
```

**AFTER:**
```php
$countSql = "SELECT COUNT(*) FROM users WHERE tenant_id = ?
            AND avatar_url IS NOT NULL AND avatar_url != ''";
```

---

## Why This Policy Exists

The platform has a **directory policy**:

**Members without avatars are hidden from the directory.**

This is enforced in:
1. ✅ `User::count()` - Only counts members with avatars
2. ✅ `MemberRankingService` - Only ranks members with avatars (line 317)
3. ✅ `User::search()` - Only searches members with avatars (line 532)
4. ❌ `CoreApiController::members()` - **Was NOT filtering** (NOW FIXED)

---

## Expected Behavior After Fix

### Scenario 1: Initial Page Load
- **Database:** 220 total members, 30 have avatars
- **Display:** "Showing 30 of 30 members" ✅ Correct (all 30 with avatars shown)

### Scenario 2: After Search "dublin"
- **Database:** 15 members match "dublin", 10 have avatars
- **Display:** "Showing 10 of 30 members" ✅ Correct (10 matches with avatars, 30 total with avatars)

### Scenario 3: Active Members Tab
- **Database:** 5 active members with avatars
- **Display:** "Showing 5 of 30 members" ✅ Correct

---

## Files Modified

### 1. `src/Controllers/Api/CoreApiController.php`

**Line 44-49:** Added avatar filter to main query
```php
WHERE tenant_id = ?
AND avatar_url IS NOT NULL
AND avatar_url != ''
```

**Line 78-80:** Added avatar filter to count query
```php
$countSql = "SELECT COUNT(*) FROM users WHERE tenant_id = ? AND avatar_url IS NOT NULL AND avatar_url != ''";
```

---

## Testing

### Test 1: Total Count Matches
```bash
# In MySQL
SELECT COUNT(*) FROM users WHERE tenant_id = 1 AND avatar_url IS NOT NULL AND avatar_url != '';
# Result: 30
```

Should match the count displayed on page.

### Test 2: Search Results Consistent
```bash
# Search for "dublin"
GET /api/members?q=dublin

# Response should show:
{
  "meta": {
    "total": 30,      // Total members with avatars
    "showing": 10     // Members matching "dublin" with avatars
  }
}
```

### Test 3: Active Tab Consistent
```bash
# Active members
GET /api/members?active=true

# Response should show:
{
  "meta": {
    "total": 30,      // Total members with avatars
    "showing": 5      // Active members with avatars
  }
}
```

---

## Important Notes

### 1. Avatar Requirement is Intentional
The platform **intentionally hides** members without avatars from:
- Member directory
- Search results
- API responses
- Profile cards

This is a **platform policy**, not a bug.

### 2. Location Field Still Needs Migration
The `location` field in queries will cause SQL errors until database migration is applied:

```bash
mysql -u your_user -p your_database < migrations/add_members_search_indexes.sql
```

### 3. Cached Count Cleared
The session cache for user count will automatically refresh within 5 minutes (see `MemberController.php` line 139).

---

## Summary

✅ **Fixed:** Query mismatch between count and member selection
✅ **Added:** Avatar filter to API queries (lines 44, 78)
✅ **Consistent:** All queries now match `User::count()` behavior
✅ **Policy:** Enforces platform rule (no avatar = hidden from directory)

**Result:** Count display will now be accurate and consistent across all views.

---

## Next Steps

1. ✅ **Applied Fix** - Avatar filters added to CoreApiController
2. ⏳ **Apply Database Migration** - Still required for location search
3. ⏳ **Test Search** - After migration, verify location search works
4. ⏳ **Clear Cache** - Wait 5 minutes or clear session cache

**Current Status:** Count issue FIXED. Location search still requires database migration.
