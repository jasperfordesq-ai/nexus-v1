-- Migration: Enhance group_files table with folder, description, download_count
-- Date: 2026-04-03
-- Idempotent: uses IF NOT EXISTS / column checks

ALTER TABLE group_files ADD COLUMN IF NOT EXISTS folder VARCHAR(255) NULL DEFAULT NULL AFTER file_size;
ALTER TABLE group_files ADD COLUMN IF NOT EXISTS description TEXT NULL AFTER folder;
ALTER TABLE group_files ADD COLUMN IF NOT EXISTS download_count INT NOT NULL DEFAULT 0 AFTER description;

CREATE INDEX IF NOT EXISTS idx_group_files_folder ON group_files (group_id, folder);
