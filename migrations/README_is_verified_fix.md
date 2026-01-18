# Fix for Missing `is_verified` Column Error

## Problem

The application is experiencing SQL errors when querying the `users` table:

```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'u.is_verified' in 'SELECT'
```

This error appears in:
- CommunityRank sidebar queries ([MemberRankingService.php:719](../src/Services/MemberRankingService.php#L719))
- SmartMatchingEngine queries ([SmartMatchingEngine.php:721](../src/Services/SmartMatchingEngine.php#L721))
- SmartSegmentSuggestion queries ([SmartSegmentSuggestionService.php:1233](../src/Services/SmartSegmentSuggestionService.php#L1233))

## Root Cause

The `users` table is missing the `is_verified` column that is being referenced in multiple SQL queries across the codebase.

**Note:** This is different from `email_verified_at`:
- `email_verified_at` - Timestamp when user verified their email address
- `is_verified` - Boolean flag indicating if the user account itself is verified (could be admin verification, ID verification, etc.)

## Solution

Run the migration: [add_is_verified_to_users.sql](./add_is_verified_to_users.sql)

```bash
mysql -u your_username -p your_database < migrations/add_is_verified_to_users.sql
```

### What the Migration Does

1. **Adds `is_verified` column** to the `users` table
   - Type: `TINYINT(1)` (boolean: 0 or 1)
   - Default: `0` (unverified)
   - Not null

2. **Creates performance index** on the `is_verified` column for faster queries

3. **Provides optional update queries** (commented out by default):
   - Auto-verify users who have verified their email
   - Auto-verify all existing users

### Post-Migration Decisions

After running the migration, you need to decide on your verification policy:

**Option 1: Auto-verify all existing users** (recommended for existing communities)
```sql
UPDATE users SET is_verified = 1 WHERE created_at < NOW();
```

**Option 2: Link to email verification**
```sql
UPDATE users SET is_verified = 1 WHERE email_verified_at IS NOT NULL;
```

**Option 3: Manual verification only**
- Leave all users as unverified (is_verified = 0)
- Create admin interface to manually verify users
- Update registration flow to set is_verified = 1 for new users

## Implementation Recommendations

### 1. Update Registration Flow

Modify your user registration code to set `is_verified` appropriately:

```php
// After successful email verification
$db->query("UPDATE users SET is_verified = 1, email_verified_at = NOW() WHERE id = ?", [$userId]);
```

### 2. Add Admin Controls

Create admin interface to:
- View verified/unverified users
- Manually verify/unverify user accounts
- Bulk verify users

### 3. Update Query Logic

Ensure queries using `is_verified` handle both verified and unverified users appropriately:

```php
// For public profiles - show only verified users
$sql = "SELECT * FROM users WHERE is_verified = 1";

// For member ranking - boost verified users
$sql = "... + CASE WHEN u.is_verified = 1 THEN 0.15 ELSE 0 END ...";
```

## Verification

After running the migration, verify it worked:

```sql
-- Check column exists
SHOW COLUMNS FROM users LIKE 'is_verified';

-- Check counts
SELECT is_verified, COUNT(*) as count FROM users GROUP BY is_verified;
```

## Related Files

- Migration: [add_is_verified_to_users.sql](./add_is_verified_to_users.sql)
- Previous fix: [fix_missing_columns_jan_2026.sql](./fix_missing_columns_jan_2026.sql) (added `email_verified_at`)
- Code references:
  - [src/Services/MemberRankingService.php:719](../src/Services/MemberRankingService.php#L719)
  - [src/Services/SmartMatchingEngine.php:721](../src/Services/SmartMatchingEngine.php#L721)
  - [src/Services/SmartSegmentSuggestionService.php:1233](../src/Services/SmartSegmentSuggestionService.php#L1233)
