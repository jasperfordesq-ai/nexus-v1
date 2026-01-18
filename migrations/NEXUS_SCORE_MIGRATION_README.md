# Nexus Score System - Database Migration Guide

## 📋 Overview

This guide helps you set up the database for the Nexus Score System (1000-point scoring).

---

## 🗂️ Migration Files

1. **`fix_database_errors_jan_12_2026.sql`** ✅ (Already created)
   - Fixes critical errors (giver_id, post_likes, user_blocks, etc.)
   - **Run this FIRST** if you haven't already

2. **`create_nexus_score_tables.sql`** ⭐ (NEW - for Nexus Score)
   - Creates score cache tables
   - Adds optional transaction_type column
   - Sets up milestone tracking
   - **Run this SECOND**

---

## 🚀 Quick Start (Run Migrations)

### Option 1: MySQL Command Line

```bash
# Navigate to migrations directory
cd "c:\Home Directory\migrations"

# Run fix errors migration (if not done already)
mysql -u your_username -p your_database_name < fix_database_errors_jan_12_2026.sql

# Run Nexus Score migration
mysql -u your_username -p your_database_name < create_nexus_score_tables.sql
```

### Option 2: phpMyAdmin

1. Log in to phpMyAdmin
2. Select your database
3. Go to "SQL" tab
4. Copy contents of `create_nexus_score_tables.sql`
5. Paste and click "Go"

### Option 3: MySQL Workbench

1. Open MySQL Workbench
2. Connect to your database
3. File → Open SQL Script
4. Select `create_nexus_score_tables.sql`
5. Execute (lightning bolt icon)

### Option 4: PHP Script (Create a run script)

Create `run-nexus-score-migration.php` in your httpdocs folder:

```php
<?php
require_once __DIR__ . '/../bootstrap.php';

// Read and execute migration
$sql = file_get_contents(__DIR__ . '/../migrations/create_nexus_score_tables.sql');
$db = \Nexus\Core\Database::getInstance();

try {
    // Split by semicolons and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $statement) {
        if (!empty($statement) && stripos($statement, 'DELIMITER') === false) {
            $db->exec($statement);
        }
    }

    echo "✅ Nexus Score migration completed successfully!\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
```

Then visit: `http://your-site.com/run-nexus-score-migration.php`

---

## 📊 What Gets Created

### Required Tables (Core)

1. **`nexus_score_cache`**
   - Stores calculated scores for performance
   - Avoids recalculating on every page load
   - Includes all 6 category scores
   - Tracks tier and percentile

2. **`post_likes`** (if not exists)
   - Tracks likes on posts
   - Used for Social Impact scoring
   - Links posts to users

### Optional Tables (Enhanced Features)

3. **`nexus_score_history`**
   - Daily score snapshots
   - Track progress over time
   - Generate trend charts

4. **`nexus_score_milestones`**
   - Records achievement milestones
   - "You reached 500 points!"
   - "You achieved Expert tier!"

### Optional Columns

5. **`transactions.transaction_type`**
   - Enum: 'exchange', 'volunteer', 'donation', 'other'
   - Allows filtering volunteer hours from regular exchanges
   - **If you skip this, all transactions count as volunteer hours**

6. **`user_badges.is_showcased`**
   - Allows users to pin 3 favorite badges
   - Shows featured badges on profile
   - Optional showcase_order field

---

## ⚙️ Configuration After Migration

### Step 1: Enable Score Caching (Recommended)

Update your code to use cached scores:

```php
// In NexusScoreService.php, add this method:
public function getCachedScore($userId, $tenantId) {
    $stmt = $this->db->prepare("
        SELECT * FROM nexus_score_cache
        WHERE user_id = ? AND tenant_id = ?
        AND calculated_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$userId, $tenantId]);
    $cached = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cached) {
        // Return cached score
        return $this->formatCachedScore($cached);
    }

    // Calculate and cache
    $score = $this->calculateNexusScore($userId, $tenantId);
    $this->cacheScore($userId, $tenantId, $score);
    return $score;
}
```

### Step 2: Set Up Nightly Recalculation (Optional)

Create a cron job to recalculate scores:

```bash
# Run every night at 2 AM
0 2 * * * php /path/to/scripts/recalculate-nexus-scores.php
```

Create `scripts/recalculate-nexus-scores.php`:

```php
<?php
require_once __DIR__ . '/../bootstrap.php';

$db = \Nexus\Core\Database::getInstance();
$scoreService = new \Nexus\Services\NexusScoreService($db);

// Get all active users
$stmt = $db->query("SELECT id, tenant_id FROM users WHERE is_approved = 1");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($users as $user) {
    try {
        $score = $scoreService->calculateNexusScore($user['id'], $user['tenant_id']);
        // Cache would be updated automatically
        echo "✓ User {$user['id']}: {$score['total_score']}\n";
    } catch (Exception $e) {
        echo "✗ User {$user['id']}: {$e->getMessage()}\n";
    }
}

echo "\nCompleted recalculation for " . count($users) . " users.\n";
```

### Step 3: Add Transaction Types (Optional)

If you added the `transaction_type` column, update your transaction creation code:

```php
// When creating a transaction
$stmt = $db->prepare("
    INSERT INTO transactions (sender_id, receiver_id, amount, status, transaction_type, ...)
    VALUES (?, ?, ?, 'completed', 'volunteer', ...)
");

// Types:
// 'exchange' - Regular time credit exchange
// 'volunteer' - Volunteer hours tracking
// 'donation' - One-way donation
// 'other' - Miscellaneous
```

---

## ✅ Verify Migration Success

Run these queries to check:

```sql
-- Check nexus_score_cache table exists
SHOW TABLES LIKE 'nexus_score_cache';

-- Check post_likes table exists
SHOW TABLES LIKE 'post_likes';

-- Check transaction_type column (optional)
SHOW COLUMNS FROM transactions LIKE 'transaction_type';

-- Check is_showcased column in user_badges
SHOW COLUMNS FROM user_badges LIKE 'is_showcased';
```

Expected output: All tables/columns should exist.

---

## 🐛 Troubleshooting

### Error: "Unknown column 'transaction_type'"

**Solution**: The transaction_type column is optional. The system has been updated to work without it. All transactions will count toward volunteer hours.

**To fix permanently**: Run the migration which adds this column.

### Error: "Table 'post_likes' doesn't exist"

**Solution**: Run `fix_database_errors_jan_12_2026.sql` first, then `create_nexus_score_tables.sql`.

### Error: "Column 'is_showcased' not found"

**Solution**: Run the `create_nexus_score_tables.sql` migration to add this column.

### Slow Performance

**Solution**:
1. Ensure `nexus_score_cache` table exists
2. Implement caching in your score service
3. Set up nightly recalculation cron job

---

## 📈 Performance Tips

1. **Use Score Cache**: Always check cache before recalculating
2. **Batch Updates**: Recalculate scores in background, not on page load
3. **Indexes**: Migration adds all necessary indexes automatically
4. **History Cleanup**: Purge old score_history records (keep 90 days)

```sql
-- Clean up old history (run monthly)
DELETE FROM nexus_score_history
WHERE snapshot_date < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

---

## 🔄 Rolling Back (If Needed)

To remove Nexus Score tables:

```sql
-- Drop optional tables
DROP TABLE IF EXISTS nexus_score_milestones;
DROP TABLE IF EXISTS nexus_score_history;
DROP TABLE IF EXISTS nexus_score_cache;

-- Remove optional columns
ALTER TABLE transactions DROP COLUMN IF EXISTS transaction_type;
ALTER TABLE user_badges DROP COLUMN IF EXISTS is_showcased;
ALTER TABLE user_badges DROP COLUMN IF EXISTS showcase_order;
```

---

## ✨ Next Steps After Migration

1. ✅ Visit `/nexus-score` to see your dashboard
2. ✅ Check the leaderboard at `/nexus-score/leaderboard`
3. ✅ Generate an impact report at `/nexus-score/report`
4. ✅ (Admin) View analytics at `/admin/nexus-score/analytics`
5. ✅ Set up score caching for better performance
6. ✅ Configure nightly score recalculation

---

## 📚 Related Documentation

- **Full System Docs**: `docs/NEXUS_SCORE_SYSTEM.md`
- **Quick Start Guide**: `docs/QUICK_START_NEXUS_SCORE.md`
- **Integration Guide**: `docs/NEXUS_SCORE_INTEGRATION_COMPLETE.md`

---

**Questions?** Check the documentation or inspect the migration file for detailed comments.
