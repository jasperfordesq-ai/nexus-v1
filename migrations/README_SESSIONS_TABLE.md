# Sessions Table Migration

## Overview
The `sessions` table is required for tracking active users and providing real-time analytics in the Enterprise dashboard.

## What This Table Does
- Tracks active user sessions
- Stores session lifecycle data (creation, expiry, last activity)
- Enables "users online" and "active sessions" metrics
- Supports multi-tenancy with tenant_id

## How to Install

### Option 1: Via MySQL Command Line
```bash
mysql -u your_username -p your_database < migrations/create_sessions_table.sql
```

### Option 2: Via phpMyAdmin or Database Tool
1. Open phpMyAdmin or your preferred database tool
2. Select your database
3. Go to the SQL tab
4. Copy and paste the contents of `create_sessions_table.sql`
5. Click "Go" or "Execute"

### Option 3: Via PHP Script
```php
<?php
require_once 'bootstrap.php';

$sql = file_get_contents(__DIR__ . '/migrations/create_sessions_table.sql');
$pdo = \Nexus\Core\Database::getInstance();
$pdo->exec($sql);

echo "Sessions table created successfully!\n";
```

## Verification
After running the migration, verify the table was created:

```sql
SHOW TABLES LIKE 'sessions';
DESCRIBE sessions;
```

## Integration with PHP Sessions (Optional)
If you want to use this table for PHP session storage, you'll need to implement a custom session handler. See: https://www.php.net/manual/en/class.sessionhandlerinterface.php

## Maintenance
It's recommended to periodically clean up old expired sessions:

```sql
-- Delete sessions expired more than 30 days ago
DELETE FROM sessions WHERE expires_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

You can set this up as a cron job or scheduled task.

## Related Features
- Enterprise Dashboard Real-time Stats
- User Activity Tracking
- Session Management
- Multi-tenant Session Isolation
