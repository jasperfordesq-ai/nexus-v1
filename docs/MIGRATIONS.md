# Database Migrations

Project NEXUS maintains **two parallel migration systems** for historical reasons. This document explains the current state, which system to use for new work, and how the schema dump bridges both.

## TL;DR

- **New schema changes → Laravel migrations** (`database/migrations/`, via `php artisan make:migration`).
- **Legacy SQL migrations** (`migrations/*.sql`) are **FROZEN**. Do not add new `.sql` files here.
- A CI guard (`scripts/check-no-new-legacy-sql.sh`) blocks new legacy SQL files.
- The committed schema dump (`database/schema/mysql-schema.sql`) gives fresh contributors a one-shot working database.

## The Two Systems

| System | Location | Tracker Table | Status |
|--------|----------|---------------|--------|
| **Laravel migrations** (primary) | `database/migrations/*.php` | `laravel_migrations` | ACTIVE — use for all new work |
| **Legacy SQL migrations** | `migrations/*.sql` | `migrations` | FROZEN — baked into schema dump |

The legacy system predates the Laravel migration (merged to main 2026-03-19). All ~190 legacy `.sql` files are still tracked for audit history and are applied on fresh environments via the schema dump, but no new files should be added there.

## Creating a New Migration (ALWAYS Laravel)

```bash
# From host, targeting the running PHP container
docker exec nexus-php-app php artisan make:migration add_foo_to_bar_table
```

The generated file lands in `database/migrations/YYYY_MM_DD_HHMMSS_add_foo_to_bar_table.php`.

### Required patterns

1. **Use idempotent guards** so the migration is safe to re-run:

   ```php
   public function up(): void
   {
       if (! Schema::hasColumn('bar', 'foo')) {
           Schema::table('bar', function (Blueprint $table) {
               $table->string('foo')->nullable();
           });
       }
   }
   ```

2. **Tenant-scoped tables must include `tenant_id`** (unless explicitly exempt: `tenants`, `sessions`, `cache`, `jobs`, `failed_jobs`, `migrations`, `laravel_migrations`, `system_settings`, `global_*`).

3. **Run locally and verify before committing:**

   ```bash
   docker exec nexus-php-app php artisan migrate
   ```

4. **Refresh the schema dump and commit both files together** (see below).

## The Schema Dump Bridge

`database/schema/mysql-schema.sql` is a **full database schema dump** (legacy + Laravel tables, plus seed data in `laravel_migrations`). It lets new contributors boot a working database with one command:

```bash
docker compose up -d
docker exec nexus-php-app php artisan migrate
#   → loads the schema dump, then runs any migrations newer than the dump
```

Without this dump, new contributors would need to run all ~270 legacy + Laravel migrations in order, which is slow and fragile. The dump is our "baseline".

### Refreshing the dump

After running any new Laravel migration (or legacy `.sql` file during emergency fixes), refresh the dump and commit it alongside your migration:

```bash
# Local Docker environment
bash scripts/refresh-schema-dump.sh

# Production (run on the Azure VM over SSH — only in an emergency)
bash scripts/refresh-schema-dump.sh --production

# Commit both the migration AND the updated dump
git add database/migrations/... database/schema/mysql-schema.sql
```

The deploy script (`scripts/safe-deploy.sh`) regenerates the dump automatically when `--migrate` is used.

### Drift detection

`scripts/check-schema-drift.sh` compares the committed dump against a fresh `php artisan schema:dump`. CI runs this as a **warning-only** gate for now (`|| true`). Promote it to blocking once dumps are stable across contributors.

## CI Enforcement

| Check | Script | Gate |
|-------|--------|------|
| Block new legacy `.sql` files | `scripts/check-no-new-legacy-sql.sh` | **Blocking** |
| Existing migration safety (idempotency, tenant scoping) | `scripts/check-migration-ci.sh` | **Blocking** |
| Schema dump drift | `scripts/check-schema-drift.sh` | Warning-only |

All three run in `.github/workflows/ci.yml` under the `migration-gate` job.

## Running Migrations on Production

**Laravel (preferred):**

```bash
ssh -i "C:\ssh-keys\project-nexus.pem" -o RequestTTY=force azureuser@20.224.171.253 \
    "sudo docker exec nexus-php-app php artisan migrate --force"
```

**Legacy SQL (only for pre-existing files — never new ones):**

```bash
# 1. SCP to server
scp -i "C:\ssh-keys\project-nexus.pem" migrations/your_file.sql \
    azureuser@20.224.171.253:/opt/nexus-php/migrations/

# 2. Execute via DB container
ssh -i "C:\ssh-keys\project-nexus.pem" -o RequestTTY=force azureuser@20.224.171.253 \
    "sudo docker exec -i nexus-php-db mysql -u nexus -p\$DB_PASS nexus \
    < /opt/nexus-php/migrations/your_file.sql"
```

After any production migration, refresh the dump and commit it:

```bash
bash scripts/refresh-schema-dump.sh --production
git add database/schema/mysql-schema.sql
```

## FAQ

**Q: Why keep the legacy SQL files around?**
A: They document the exact historical sequence of schema changes and are baked into the schema dump. Removing them would break the audit trail and any fresh environment that hasn't yet consumed the dump.

**Q: Can I port a legacy .sql file to a Laravel migration?**
A: Only if there's a concrete reason (e.g. you need PHP logic inside the migration). Otherwise leave it — the schema dump already captures its effect.

**Q: What if the CI guard blocks me but I genuinely need to run raw SQL?**
A: Use a Laravel migration with `DB::statement('…')` inside `up()`. You get the same power plus the tracker row, plus idempotency guards via `Schema::hasTable()` / `Schema::hasColumn()`.
