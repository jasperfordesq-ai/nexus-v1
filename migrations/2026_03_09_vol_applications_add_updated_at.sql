-- Migration: Add updated_at column to vol_applications
-- Purpose: vol_applications was missing updated_at; the controller UPDATE sets it
-- Date: 2026-03-09
-- Idempotent: uses IF NOT EXISTS

ALTER TABLE `vol_applications`
    ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;
