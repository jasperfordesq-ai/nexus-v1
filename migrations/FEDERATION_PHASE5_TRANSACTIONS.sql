-- ============================================================================
-- FEDERATION PHASE 5: FEDERATED TRANSACTIONS TABLE
-- Cross-tenant hour exchanges between federated timebank members
-- Date: January 2026
-- ============================================================================
-- SAFE TO RUN: Uses IF NOT EXISTS, won't error if table exists
-- ============================================================================

-- Federated Transactions Table
CREATE TABLE IF NOT EXISTS federation_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Sender information
    sender_tenant_id INT NOT NULL,
    sender_user_id INT NOT NULL,

    -- Receiver information
    receiver_tenant_id INT NOT NULL,
    receiver_user_id INT NOT NULL,

    -- Transaction details
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,

    -- Status tracking
    status ENUM('pending', 'completed', 'cancelled', 'disputed') NOT NULL DEFAULT 'pending',

    -- Optional listing reference
    listing_id INT DEFAULT NULL,
    listing_tenant_id INT DEFAULT NULL,

    -- Timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,
    cancelled_at DATETIME DEFAULT NULL,

    -- Cancellation info
    cancelled_by INT DEFAULT NULL,
    cancellation_reason VARCHAR(500) DEFAULT NULL,

    -- Indexes
    INDEX idx_sender (sender_tenant_id, sender_user_id),
    INDEX idx_receiver (receiver_tenant_id, receiver_user_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_listing (listing_tenant_id, listing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- VERIFICATION: Run this after to confirm
-- ============================================================================
-- SHOW CREATE TABLE federation_transactions;
-- ============================================================================
