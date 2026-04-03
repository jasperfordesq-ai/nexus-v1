-- Migration: Add status column for group lifecycle management
-- Date: 2026-04-03
-- Idempotent: uses IF NOT EXISTS

ALTER TABLE `groups` ADD COLUMN IF NOT EXISTS status VARCHAR(30) NOT NULL DEFAULT 'active' AFTER is_active;
ALTER TABLE `groups` ADD INDEX IF NOT EXISTS idx_groups_status (tenant_id, status);
