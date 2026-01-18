-- ============================================================================
-- FEDERATION PHASE 5: COMPLETE MIGRATION
-- ============================================================================
-- Run this file on your LIVE SERVER
-- Date: January 2026
-- ============================================================================
-- INSTRUCTIONS:
-- 1. Run each section separately in phpMyAdmin
-- 2. "Duplicate column name" errors are OK - column already exists
-- 3. "Duplicate key name" errors are OK - index already exists
-- 4. The CREATE TABLE uses IF NOT EXISTS so it's safe to run multiple times
-- ============================================================================


-- ============================================================================
-- PART 1: FEDERATED TRANSACTIONS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS federation_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_tenant_id INT NOT NULL,
    sender_user_id INT NOT NULL,
    receiver_tenant_id INT NOT NULL,
    receiver_user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    status ENUM('pending', 'completed', 'cancelled', 'disputed') NOT NULL DEFAULT 'pending',
    listing_id INT DEFAULT NULL,
    listing_tenant_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,
    cancelled_at DATETIME DEFAULT NULL,
    cancelled_by INT DEFAULT NULL,
    cancellation_reason VARCHAR(500) DEFAULT NULL,
    INDEX idx_sender (sender_tenant_id, sender_user_id),
    INDEX idx_receiver (receiver_tenant_id, receiver_user_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_listing (listing_tenant_id, listing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- PART 2: EVENT_RSVPS COLUMNS (run each ALTER separately)
-- ============================================================================
-- Run these one at a time. "Duplicate column name" error means it exists - OK!

ALTER TABLE event_rsvps ADD COLUMN is_federated TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE event_rsvps ADD COLUMN source_tenant_id INT DEFAULT NULL;

ALTER TABLE event_rsvps ADD COLUMN tenant_id INT DEFAULT NULL;

ALTER TABLE event_rsvps ADD INDEX idx_federated_events (is_federated, source_tenant_id);


-- ============================================================================
-- PART 3: GROUP_MEMBERS COLUMNS (run each ALTER separately)
-- ============================================================================
-- Run these one at a time. "Duplicate column name" error means it exists - OK!

ALTER TABLE group_members ADD COLUMN is_federated TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE group_members ADD COLUMN source_tenant_id INT DEFAULT NULL;

ALTER TABLE group_members ADD INDEX idx_federated_groups (is_federated, source_tenant_id);


-- ============================================================================
-- PART 4: FEDERATION_USER_SETTINGS COLUMN
-- ============================================================================
-- "Duplicate column name" error means it exists - OK!

ALTER TABLE federation_user_settings ADD COLUMN email_notifications TINYINT(1) NOT NULL DEFAULT 1;


-- ============================================================================
-- VERIFICATION QUERIES (run after migration to confirm)
-- ============================================================================
-- SHOW CREATE TABLE federation_transactions;
-- DESCRIBE event_rsvps;
-- DESCRIBE group_members;
-- DESCRIBE federation_user_settings;
-- ============================================================================
