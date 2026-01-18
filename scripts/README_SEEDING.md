# Database Seeding System

A comprehensive database seeding system for generating realistic test data in your Nexus platform.

## Quick Start

```bash
# Seed with defaults (50 users, 10 groups, etc.)
php scripts/seed_database.php

# Seed for demo with more data
php scripts/seed_database.php --env=demo --users=100 --groups=20

# Clear and reseed
php scripts/seed_database.php --clear --users=20
```

## Features

✅ **Realistic Data** - Names, emails, posts, and interactions feel authentic
✅ **Configurable** - Control exactly how much data to generate
✅ **Safe** - Won't run on production databases
✅ **Fast** - Seeds 100+ records in seconds
✅ **Comprehensive** - Covers all major tables

## What Gets Seeded

| Data Type | Default Count | Description |
|-----------|---------------|-------------|
| Users | 50 | Including admin and test users |
| Groups | 10 | Community groups with members |
| Posts | 100 | Social feed posts with likes |
| Events | 20 | Past and future community events |
| Listings | 30 | Offers and requests |
| Transactions | 50 | Time credit exchanges |
| Badges | Variable | Random badges awarded to users |
| Notifications | 50 | Recent notifications |

## Command Line Options

### Basic Options

```bash
--env=<environment>     # dev, demo, or test (default: dev)
--users=<number>        # Number of users to create (default: 50)
--groups=<number>       # Number of groups to create (default: 10)
--posts=<number>        # Number of posts to create (default: 100)
--events=<number>       # Number of events to create (default: 20)
--listings=<number>     # Number of listings to create (default: 30)
--transactions=<number> # Number of transactions (default: 50)
```

### Advanced Options

```bash
--clear                 # Clear existing data before seeding
--tenant=<id>           # Seed for specific tenant (default: 1)
--help                  # Show help message
```

## Usage Examples

### Development Environment

```bash
# Small dataset for quick testing
php scripts/seed_database.php --users=20 --groups=5 --posts=30
```

### Demo Environment

```bash
# Larger dataset for demonstrations
php scripts/seed_database.php --env=demo --users=200 --groups=30 --posts=500 --events=50
```

### Clean Slate

```bash
# Remove all test data and start fresh
php scripts/seed_database.php --clear
```

### Specific Tenant

```bash
# Seed for tenant ID 2
php scripts/seed_database.php --tenant=2
```

## Test User Accounts

The seeder automatically creates several test accounts:

| Email | Password | Role | Notes |
|-------|----------|------|-------|
| admin@nexus.test | password | admin | Full admin access |
| user1@nexus.test | password | user | Test user 1 |
| user2@nexus.test | password | user | Test user 2 |
| user3@nexus.test | password | user | Test user 3 |
| user4@nexus.test | password | user | Test user 4 |
| user5@nexus.test | password | user | Test user 5 |

All other users have random email addresses like `john.smith123@example.com`.

## Data Relationships

The seeder creates realistic relationships between data:

- **Groups** → Members (3-15 members per group)
- **Posts** → Likes (0-20 likes per post)
- **Posts** → Groups (30% of posts are in groups)
- **Events** → RSVPs (5-30 RSVPs per event)
- **Events** → Groups (Events can belong to groups)
- **Users** → Badges (1-4 badges per user)
- **Users** → Transactions (Random pairings)

## Seeder Classes

The system is modular with individual seeder classes:

| Seeder | File | Purpose |
|--------|------|---------|
| UserSeeder | `seeders/UserSeeder.php` | Creates users with profiles |
| GroupSeeder | `seeders/GroupSeeder.php` | Creates groups and memberships |
| PostSeeder | `seeders/PostSeeder.php` | Creates posts and likes |
| EventSeeder | `seeders/EventSeeder.php` | Creates events and RSVPs |
| ListingSeeder | `seeders/ListingSeeder.php` | Creates offers and requests |
| TransactionSeeder | `seeders/TransactionSeeder.php` | Creates time credit transactions |
| BadgeSeeder | `seeders/BadgeSeeder.php` | Awards badges to users |
| NotificationSeeder | `seeders/NotificationSeeder.php` | Creates notifications |

## Creating Custom Seeders

You can add new seeders by creating a class in `scripts/seeders/`:

```php
<?php

class MyCustomSeeder
{
    private $pdo;
    private $tenantId;
    private $userIds;

    public function __construct($pdo, $tenantId, $userIds = [])
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
        $this->userIds = $userIds;
    }

    public function seed($count = 10)
    {
        $ids = [];

        for ($i = 0; $i < $count; $i++) {
            // Create your records here
            $id = $this->createRecord([
                'tenant_id' => $this->tenantId,
                // ... other fields
            ]);

            if ($id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function createRecord($data)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO my_table (tenant_id, name)
                VALUES (:tenant_id, :name)
            ");

            $stmt->execute($data);
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            echo "Warning: {$e->getMessage()}\n";
            return null;
        }
    }
}
```

Then add it to `seed_database.php`:

```php
require_once __DIR__ . '/seeders/MyCustomSeeder.php';

// In the seeding section:
info("Seeding custom data...");
$customSeeder = new MyCustomSeeder($pdo, $config['tenant_id'], $userIds);
$customSeeder->seed(20);
success("Created custom records");
```

## Safety Features

### Production Protection

The seeder will **refuse to run** if `--env=production`:

```bash
php scripts/seed_database.php --env=production
# ERROR: Cannot seed production database!
```

### Confirmation Prompt

Before seeding, you must confirm:

```
This will add test data to your database.
Continue? (y/n):
```

Type `n` to abort safely.

### Clear Data Option

The `--clear` flag only removes data with matching `tenant_id`:

```bash
php scripts/seed_database.php --clear --tenant=1
# Only clears tenant 1 data, preserves others
```

## Performance

| Data Size | Time (approx) |
|-----------|---------------|
| 50 users, 10 groups, 100 posts | 2-3 seconds |
| 100 users, 20 groups, 200 posts | 4-6 seconds |
| 500 users, 50 groups, 1000 posts | 15-20 seconds |

Performance depends on:
- Database server speed
- Network latency
- Foreign key constraints
- Index rebuilding

## Troubleshooting

### "Table doesn't exist" errors

Make sure you've run all migrations first:

```bash
php scripts/run_migrations.php
```

### Foreign key constraint failures

Ensure your database has all required tables:
- users
- tenants
- groups
- feed_posts
- events
- listings
- transactions
- user_badges
- notifications

### "Cannot seed production database" error

This is intentional! Change `--env` to `dev` or `demo`:

```bash
php scripts/seed_database.php --env=dev
```

### Slow seeding

For large datasets, disable foreign key checks temporarily (done automatically for `--clear` mode).

## Integration with Testing

### PHPUnit Integration

You can use seeders in your tests:

```php
<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

class MyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Seed test data
        require_once __DIR__ . '/../scripts/seeders/UserSeeder.php';

        $pdo = Database::getInstance();
        $seeder = new \UserSeeder($pdo, 1);
        $this->userIds = $seeder->seed(10);
    }
}
```

### CI/CD Integration

Add to your CI pipeline:

```yaml
# .github/workflows/test.yml
- name: Seed test database
  run: php scripts/seed_database.php --env=test --users=20
```

## Maintenance

### Updating Seeders

To add new data types or improve realism:

1. Edit the seeder class in `scripts/seeders/`
2. Add new templates, names, or data
3. Test with `--clear` to verify changes
4. Update this README

### Clearing Specific Data

To clear only specific tables, modify the `$tables` array in `seed_database.php`:

```php
$tables = [
    'feed_posts',  // Keep this
    // 'users',    // Remove this to preserve users
];
```

## Best Practices

✅ **DO:**
- Use `--clear` when testing seeder changes
- Seed realistic amounts of data for testing
- Create test accounts with known credentials
- Document custom seeders

❌ **DON'T:**
- Run on production databases
- Commit generated data to version control
- Use real user emails or data
- Seed millions of records (performance impact)

## Future Enhancements

Planned improvements:

- [ ] Seed volunteer opportunities
- [ ] Seed reviews and ratings
- [ ] Seed direct messages
- [ ] Seed blog posts
- [ ] Seed polls
- [ ] Add `--preset` option (small, medium, large)
- [ ] Export/import seed configurations
- [ ] Generate seed reports

## Contributing

To add new seeders or improve existing ones:

1. Create seeder class in `scripts/seeders/`
2. Follow existing naming conventions
3. Add realistic sample data
4. Handle errors gracefully
5. Update this README
6. Test thoroughly

## Support

Issues? Questions?

- Check the troubleshooting section above
- Review existing seeder code for examples
- Check database schema matches seeder expectations

---

**Last Updated:** January 12, 2026
**Version:** 1.0
**Maintainer:** Nexus Development Team
