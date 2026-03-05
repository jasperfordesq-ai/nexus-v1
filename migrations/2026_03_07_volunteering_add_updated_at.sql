-- Migration: Add missing updated_at columns to volunteering tables
-- Purpose: Ensure all vol_* tables have updated_at for audit trails
-- Date: 2026-03-07
-- All ALTER TABLE statements are idempotent via IF NOT EXISTS.

-- vol_shift_checkins — has created_at
ALTER TABLE `vol_shift_checkins`
    ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- vol_shift_waitlist — has created_at
ALTER TABLE `vol_shift_waitlist`
    ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- vol_certificates — uses generated_at (no created_at); append without AFTER
ALTER TABLE `vol_certificates`
    ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- vol_emergency_alerts — has created_at
ALTER TABLE `vol_emergency_alerts`
    ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- vol_emergency_alert_recipients — no created_at; append without AFTER
ALTER TABLE `vol_emergency_alert_recipients`
    ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- vol_mood_checkins — has created_at
ALTER TABLE `vol_mood_checkins`
    ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- staffing_predictions — has created_at
ALTER TABLE `staffing_predictions`
    ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;
