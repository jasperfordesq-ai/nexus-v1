-- Migration: Notification Reminder Tracking Tables
-- Date: 2026-03-01
-- Purpose: Create tracking tables for N2 (event reminders), N3 (listing expiry reminders),
--          and N4 (match notifications) to prevent duplicate notifications.

-- =============================================
-- N2: Event Reminder Tracking
-- Tracks which event reminders have been sent to which users
-- =============================================
CREATE TABLE IF NOT EXISTS event_reminder_sent (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    event_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    reminder_type ENUM('24h', '1h') NOT NULL DEFAULT '24h',
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_reminder_lookup (tenant_id, event_id, user_id, reminder_type),
    INDEX idx_event_reminder_cleanup (sent_at),
    UNIQUE KEY uq_event_reminder (tenant_id, event_id, user_id, reminder_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- N3: Listing Expiry Reminder Tracking
-- Tracks which listing expiry reminders have been sent
-- =============================================
CREATE TABLE IF NOT EXISTS listing_expiry_reminders_sent (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    listing_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    days_before_expiry INT NOT NULL DEFAULT 3,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_listing_expiry_lookup (tenant_id, listing_id, user_id),
    INDEX idx_listing_expiry_cleanup (sent_at),
    UNIQUE KEY uq_listing_expiry_reminder (tenant_id, listing_id, user_id, days_before_expiry)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- N4: Match Notification Tracking
-- Tracks real-time match notifications sent when new listings are created
-- (Separate from the existing match_cache which tracks digest-style match notifications)
-- =============================================
CREATE TABLE IF NOT EXISTS match_notification_sent (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    listing_id INT UNSIGNED NOT NULL COMMENT 'The newly created listing that triggered the match',
    matched_user_id INT UNSIGNED NOT NULL COMMENT 'The user who was notified about the match',
    match_score INT UNSIGNED NOT NULL DEFAULT 0,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_match_notif_lookup (tenant_id, listing_id, matched_user_id),
    INDEX idx_match_notif_cleanup (sent_at),
    UNIQUE KEY uq_match_notification (tenant_id, listing_id, matched_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Add expires_at column to listings if not present
-- (Used by N3 for listing expiry reminders)
-- =============================================
ALTER TABLE listings ADD COLUMN IF NOT EXISTS expires_at DATETIME NULL DEFAULT NULL AFTER updated_at;
ALTER TABLE listings ADD INDEX IF NOT EXISTS idx_listings_expires_at (expires_at);
