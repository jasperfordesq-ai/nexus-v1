# PostgreSQL Migration - ABANDONED

## ⚠️ IMPORTANT: PostgreSQL is OUT OF SCOPE for this project

**Status: ABANDONED**
**Date: 2026-02-02**

This project MUST use **MariaDB/MySQL** as its database.

---

## Why PostgreSQL Migration Was Abandoned

An experimental PostgreSQL migration was attempted but revealed significant incompatibilities:

| Issue | Count | Description |
|-------|-------|-------------|
| Boolean comparisons | 1,355 | `= 1` / `= 0` syntax incompatible with PostgreSQL |
| Date/Time functions | 607 | MySQL-specific functions (NOW(), DATE_FORMAT, etc.) |
| MySQL-specific functions | 6 | IFNULL(), IF(), CONCAT_WS() |
| GROUP_CONCAT | 3 | Needs STRING_AGG() in PostgreSQL |
| DDL syntax | 49 | AUTO_INCREMENT, UNSIGNED keywords |

**262+ PHP files would need refactoring.** This is not feasible without a dedicated project effort.

---

## Correct Database Configuration

### Production & Local Development

| Setting | Value |
|---------|-------|
| **DB_TYPE** | `mysql` |
| **DB_HOST** | `127.0.0.1` |
| **DB_PORT** | `3306` |
| **DB_NAME** | `truth_` |
| **DB_USER** | `root` |
| **DB_PASS** | (empty) |

### Configuration File
- **Active config**: `.env`
- **Backup**: `.env.mariadb.backup`

---

## Experimental PostgreSQL Artifacts (Reference Only)

These files are kept ONLY as reference artifacts. They are NOT to be used:

| File | Description |
|------|-------------|
| `docs/experimental_postgresql_schema.sql` | PostgreSQL schema dump (535 KB) |
| `docs/experimental_postgresql_full.sql` | Full dump with data (6.1 MB) |
| `.env.postgresql` | PostgreSQL config (DO NOT USE) |

### PostgreSQL Container (if still running)
- Container: `timebank-postgres`
- Database: `nexus_dev`
- Port: `5433`

To stop and remove:
```bash
docker stop timebank-postgres
docker rm timebank-postgres
```

---

## DO NOT

- ❌ Do NOT attempt to "finish" the PostgreSQL migration
- ❌ Do NOT refactor PHP SQL for PostgreSQL compatibility
- ❌ Do NOT use ORMs or query builders to abstract differences
- ❌ Do NOT point this application at PostgreSQL

---

## Verification

To verify the application is correctly configured:

```bash
php -r "
chdir('c:/xampp/htdocs/staging');
require 'bootstrap.php';
\$pdo = Nexus\Core\Database::getInstance();
echo 'Driver: ' . \$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . PHP_EOL;
"
```

Expected output: `Driver: mysql`

---

*Last updated: 2026-02-02*
