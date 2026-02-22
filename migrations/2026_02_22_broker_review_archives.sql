-- ============================================================
-- BROKER REVIEW ARCHIVES TABLE
-- Immutable compliance snapshots of broker moderation decisions
-- Date: 2026-02-22
-- ============================================================

CREATE TABLE IF NOT EXISTS broker_review_archives (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,

    -- Link to the original broker copy that triggered this review
    broker_copy_id INT NOT NULL,

    -- Conversation participants (denormalized for independent search)
    sender_id INT NOT NULL,
    sender_name VARCHAR(200) NOT NULL,
    receiver_id INT NOT NULL,
    receiver_name VARCHAR(200) NOT NULL,

    -- Listing context (if any)
    related_listing_id INT NULL,
    listing_title VARCHAR(255) NULL,

    -- The copied message that triggered review
    copy_reason ENUM('first_contact','high_risk_listing','new_member','flagged_user','manual_monitoring','random_sample') NOT NULL,
    target_message_body TEXT NOT NULL,
    target_message_sent_at TIMESTAMP NOT NULL,

    -- Full conversation snapshot at time of approval (immutable JSON)
    conversation_snapshot JSON NOT NULL COMMENT 'Array of {id, sender_id, sender_name, body, created_at} — frozen at approval time',

    -- Broker decision
    decision ENUM('approved', 'flagged') NOT NULL,
    decision_notes TEXT NULL,
    decided_by INT NOT NULL,
    decided_by_name VARCHAR(200) NOT NULL,
    decided_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Flag info (populated if decision = flagged)
    flag_reason VARCHAR(255) NULL,
    flag_severity ENUM('info', 'warning', 'concern', 'urgent') NULL,

    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes for searching
    INDEX idx_tenant_decision (tenant_id, decision),
    INDEX idx_tenant_date (tenant_id, decided_at),
    INDEX idx_sender (tenant_id, sender_id),
    INDEX idx_receiver (tenant_id, receiver_id),
    INDEX idx_listing (tenant_id, related_listing_id),
    INDEX idx_broker_copy (broker_copy_id),
    INDEX idx_decided_by (decided_by),

    -- Foreign keys
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (decided_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add archived status to broker_message_copies to track which have been archived
ALTER TABLE broker_message_copies
    ADD COLUMN IF NOT EXISTS archived_at TIMESTAMP NULL COMMENT 'When this copy was archived via approve/flag decision',
    ADD COLUMN IF NOT EXISTS archive_id INT NULL COMMENT 'Link to broker_review_archives.id';

CREATE INDEX IF NOT EXISTS idx_bmc_archived ON broker_message_copies(tenant_id, archived_at);
