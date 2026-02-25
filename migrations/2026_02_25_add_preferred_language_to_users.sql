-- Migration: Add preferred_language column to users table
-- Date: 2026-02-25
-- Purpose: Persist user language preference (EN/GA) across sessions and devices

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS preferred_language VARCHAR(5) NOT NULL DEFAULT 'en';
