-- Copyright © 2024–2026 Jasper Ford
-- SPDX-License-Identifier: AGPL-3.0-or-later
-- Author: Jasper Ford
-- See NOTICE file for attribution and acknowledgements.

-- ============================================================================
-- Fix migrations tracking table schema
-- ============================================================================
-- Ensures migration_name column exists and has a UNIQUE constraint so
-- safe_migrate.php can reliably track which migrations have been applied.
-- The original schema used a `backups` column for the filename; this migration
-- normalises it to `migration_name` (already partially done by
-- 2026_02_22_fix_migrations_table_schema.sql).
-- ============================================================================

-- Add migration_name column if missing
ALTER TABLE migrations
    ADD COLUMN IF NOT EXISTS migration_name VARCHAR(255) NULL AFTER id;

-- Backfill from backups column for any rows that pre-date 2026-02-22 fix
UPDATE migrations
SET migration_name = backups
WHERE migration_name IS NULL AND backups IS NOT NULL;

-- Add unique index to prevent duplicate tracking entries
ALTER TABLE migrations
    ADD UNIQUE INDEX IF NOT EXISTS idx_migration_name (migration_name);
