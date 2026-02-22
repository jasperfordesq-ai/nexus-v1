# Database Migrations

## Quick Reference

| Command                                  | What it does                                                   |
| ---------------------------------------- | -------------------------------------------------------------- |
| `make migrate FILE=<name>.sql`           | Apply migration **locally** (with backup + safety checks)      |
| `make migrate-dry FILE=<name>.sql`       | Preview migration locally (no changes)                         |
| `make migrate-prod FILE=<name>.sql`      | Apply migration to **production** (backup + apply + verify)    |
| `make migrate-prod-dry FILE=<name>.sql`  | Preview what would run on production                           |
| `make backup-prod-db`                    | Take a timestamped production database backup                  |
| `make drift-check`                       | Compare local vs production migration history                  |

---

## Architecture

| Component           | Details                                                                    |
| ------------------- | -------------------------------------------------------------------------- |
| **Database**        | MariaDB 10.11 (Docker container `nexus-php-db`)                           |
| **Migration files** | `migrations/` directory, named `YYYY_MM_DD_description.sql`               |
| **Local runner**    | `scripts/safe_migrate.php` (backup, dry-run, danger detection)            |
| **Production runner** | `scripts/migrate-production.sh` (SSH, backup, apply, verify)            |
| **Tracking table**  | `migrations` (`id`, `migration_name`, `created_at`, `executed_at`)        |
| **CI gate**         | `scripts/check-migration-ci.sh` — blocks PR if schema changes lack migrations |
| **Drift checker**   | `scripts/check-migration-drift.sh` — compares local vs production         |

## Environment Variables

The production scripts read SSH/DB credentials from environment variables. Set these in your `.env` (never committed):

```bash
PROD_SSH_KEY=C:\ssh-keys\project-nexus.pem   # Path to SSH private key
PROD_SSH_HOST=azureuser@20.224.171.253        # SSH user@host
PROD_DB_NAME=nexus                            # Production DB name
# PROD_DB_USER and PROD_DB_PASS are auto-read from server's .env by default.
# Set them here only to skip the SSH credential lookup.
```

---

## Workflow: Creating a Migration

### Step 1: Write the SQL

Create a file in `migrations/` with timestamp naming:

```text
migrations/2026_02_22_add_user_preferences.sql
```

**Required conventions:**

- Use `CREATE TABLE IF NOT EXISTS` (not bare `CREATE TABLE`)
- Use `ADD COLUMN IF NOT EXISTS` (not bare `ADD COLUMN`)
- Use `DROP ... IF EXISTS` for any destructive ops
- Include `tenant_id` column for any tenant-scoped table
- Include comments explaining what and why

**Example:**

```sql
-- ============================================================
-- ADD USER PREFERENCES TABLE
-- Stores per-user UI/notification preferences
-- Date: 2026-02-22
-- ============================================================

CREATE TABLE IF NOT EXISTS user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    preference_key VARCHAR(100) NOT NULL,
    preference_value JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_user (tenant_id, user_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Step 2: Apply locally

```bash
# Preview first
make migrate-dry FILE=2026_02_22_add_user_preferences.sql

# Apply
make migrate FILE=2026_02_22_add_user_preferences.sql
```

This runs `safe_migrate.php` which:

1. Scans for dangerous operations (DROP, TRUNCATE, DELETE)
2. Creates an automatic backup in `backups/`
3. Executes the SQL
4. Records in the `migrations` table

### Step 3: Commit the migration

```bash
git add migrations/2026_02_22_add_user_preferences.sql
git commit -m "feat(db): add user_preferences table"
```

### Step 4: Apply to production

```bash
# Preview first
make migrate-prod-dry FILE=2026_02_22_add_user_preferences.sql

# Apply (includes automatic backup + verification)
make migrate-prod FILE=2026_02_22_add_user_preferences.sql
```

The production script (`scripts/migrate-production.sh`) automatically:

1. Reads production credentials from the server's `.env`
2. Tests database connectivity
3. Checks if already applied (skips if so)
4. Creates a timestamped backup
5. Copies the migration file via SCP
6. Applies the migration
7. Records in the tracking table
8. Verifies and shows migration history

### Step 5: Verify no drift

```bash
make drift-check
```

---

## CI Gate: Migration Safety

The CI pipeline (`.github/workflows/ci.yml`, Stage 5) automatically runs `scripts/check-migration-ci.sh` on every PR. It **blocks merge** if:

1. **Schema changes without migrations** — PHP source contains `CREATE TABLE`, `ALTER TABLE`, `ADD COLUMN`, etc. but no migration file was added in the PR
2. **Missing tenant_id** — New tables lack `tenant_id` column (tenant-scoped tables require it)

It **warns** (non-blocking) on:

- Missing `IF NOT EXISTS` / `IF EXISTS` (idempotency)
- Migration files not following the `YYYY_MM_DD_` naming convention

---

## Drift Check

The drift checker compares the `migrations` tracking table between local and production:

```bash
make drift-check
```

Output example:

```text
✓ Local: 5 migration(s) recorded
✓ Production: 5 migration(s) recorded
═══════════════════════════════════════════
✓ NO DRIFT — local and production migration histories match
═══════════════════════════════════════════
```

Or if drift exists:

```text
⚠ PENDING on production: 2026_02_22_add_user_preferences.sql
═══════════════════════════════════════════
✗ DRIFT DETECTED — run 'make migrate-prod FILE=<name>' for each pending migration
═══════════════════════════════════════════
```

---

## Backups

### Manual backup

```bash
make backup-prod-db
```

Backups are stored on the production server at `/opt/nexus-php/backups/` with timestamped names:

- `pre_migration_<name>_<timestamp>.sql` — auto-created before each production migration
- `manual_backup_<timestamp>.sql` — created by `make backup-prod-db`

### Restoring from backup

```bash
ssh -i "$PROD_SSH_KEY" "$PROD_SSH_HOST" \
  "sudo cat /opt/nexus-php/backups/<BACKUP_FILE>.sql \
   | sudo docker exec -i nexus-php-db mariadb -u '<DB_USER>' -p'<DB_PASS>' nexus"
```

---

## Safety Layers

| Layer                    | When          | What                                              |
| ------------------------ | ------------- | ------------------------------------------------- |
| **Idempotent SQL**       | Write time    | `IF [NOT] EXISTS` on all DDL statements            |
| **safe_migrate.php**     | Local apply   | Dangerous op detection, auto-backup, dry-run       |
| **migrate-production.sh** | Prod apply   | Auto-backup, skip-if-applied, verify              |
| **CI gate**              | PR review     | Block merge if schema changes lack migrations      |
| **Drift check**          | Manual/deploy | Compare local vs production tracking tables        |

---

## Troubleshooting

### Migration fails on production

The script prints the backup path. Restore with:

```bash
sudo cat /opt/nexus-php/backups/<backup>.sql \
  | sudo docker exec -i nexus-php-db mariadb -u '<user>' -p'<pass>' nexus
```

### Migration already applied

`migrate-production.sh` checks the tracking table and skips if already recorded. If the table exists but wasn't tracked, re-run the migration — all migrations use `IF NOT EXISTS` so they're safe to re-run.

### CI gate false positive

If the CI gate detects schema-changing SQL in PHP source that isn't actually a schema change (e.g., a comment or string literal), the migration file requirement can be satisfied by adding an empty migration:

```sql
-- No-op: schema reference in PHP source is not an actual schema change
SELECT 1;
```

### Cannot connect to production

Ensure your SSH key is accessible and the `PROD_SSH_KEY` env var is set. The drift-check script gracefully skips if it cannot reach production.

---

## Migration Log

### 2026-02-22: `2026_02_22_fix_migrations_table_schema.sql`

- Added `migration_name` column to `migrations` table for proper tracking
- Backfilled existing rows from legacy `backups` column

### 2026-02-22: `2026_02_22_broker_review_archives.sql`

- Created `broker_review_archives` table (immutable compliance snapshots)
- Added `archived_at`, `archive_id` columns to `broker_message_copies`
- Added index `idx_bmc_archived` on `broker_message_copies(tenant_id, archived_at)`
- Backup: `/opt/nexus-php/backups/pre_migration_2026_02_22_broker_archives.sql` (22 MB)
