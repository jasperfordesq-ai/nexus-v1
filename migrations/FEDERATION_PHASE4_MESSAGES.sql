-- ============================================================================
-- FEDERATION PHASE 4: FEDERATED MESSAGING TABLE
-- Run this migration to enable cross-tenant messaging between federated users
-- Date: January 2026
-- ============================================================================

-- Federated Messages Table
-- Stores messages between users in different tenants
CREATE TABLE IF NOT EXISTS federation_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Sender information
    sender_tenant_id INT NOT NULL,
    sender_user_id INT NOT NULL,

    -- Receiver information
    receiver_tenant_id INT NOT NULL,
    receiver_user_id INT NOT NULL,

    -- Message content
    subject VARCHAR(255) DEFAULT '',
    body TEXT NOT NULL,

    -- Message direction (from perspective of this record's owner)
    -- 'outbound' = sent message (in sender's view)
    -- 'inbound' = received message (in receiver's view)
    direction ENUM('outbound', 'inbound') NOT NULL DEFAULT 'outbound',

    -- Message status
    status ENUM('pending', 'delivered', 'unread', 'read', 'failed') NOT NULL DEFAULT 'pending',

    -- Reference to original message (for inbound copies)
    reference_message_id INT DEFAULT NULL,

    -- Timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME DEFAULT NULL,

    -- Indexes for common queries
    INDEX idx_sender (sender_tenant_id, sender_user_id),
    INDEX idx_receiver (receiver_tenant_id, receiver_user_id),
    INDEX idx_direction (direction),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_thread (sender_tenant_id, sender_user_id, receiver_tenant_id, receiver_user_id),

    -- Foreign keys (soft - may reference different tenant databases)
    -- We don't enforce strict FK here as users may be in different tenant DBs
    INDEX idx_ref_message (reference_message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add comments for documentation
ALTER TABLE federation_messages COMMENT = 'Cross-tenant messages between federated timebank members';

-- ============================================================================
-- VERIFICATION (Run separately after CREATE TABLE)
-- ============================================================================

-- Check table exists:
-- SHOW TABLES LIKE 'federation_messages';

-- View table structure:
-- DESCRIBE federation_messages;
