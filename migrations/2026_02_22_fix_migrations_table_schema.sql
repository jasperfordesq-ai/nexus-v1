-- ============================================================
-- FIX MIGRATIONS TRACKING TABLE
-- Rename 'backups' column to 'migration_name' for clarity
-- Add proper column for tracking migration filenames
-- Date: 2026-02-22
-- ============================================================

-- Add proper migration_name column
ALTER TABLE migrations
    ADD COLUMN IF NOT EXISTS migration_name VARCHAR(255) NULL AFTER id;

-- Copy existing data from 'backups' column (which was used as migration_name)
UPDATE migrations SET migration_name = backups WHERE migration_name IS NULL AND backups IS NOT NULL;
