-- ============================================================
-- BROKER CONTROL FEATURES MIGRATION
-- Version: 1.0
-- Date: 2026-02-08
-- Description: Creates tables for broker controls including
--              risk tagging, exchange workflow, and message visibility
-- ============================================================

-- ============================================================
-- 1. LISTING RISK TAGS TABLE
-- Broker-assigned risk assessments for listings
-- ============================================================
CREATE TABLE IF NOT EXISTS listing_risk_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    listing_id INT NOT NULL,

    -- Risk Assessment
    risk_level ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'low',
    risk_category VARCHAR(100) NULL COMMENT 'e.g., safeguarding, insurance, mobility, heights',
    risk_notes TEXT NULL COMMENT 'Internal broker notes',
    member_visible_notes TEXT NULL COMMENT 'Notes visible to listing owner',

    -- Approval Requirements
    requires_approval TINYINT(1) DEFAULT 0 COMMENT 'Requires broker pre-approval before match',
    insurance_required TINYINT(1) DEFAULT 0,
    dbs_required TINYINT(1) DEFAULT 0 COMMENT 'DBS/background check required',

    -- Audit
    tagged_by INT NULL COMMENT 'Broker user ID who tagged this',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_tenant_listing (tenant_id, listing_id),
    INDEX idx_risk_level (tenant_id, risk_level),
    INDEX idx_requires_approval (tenant_id, requires_approval),
    UNIQUE KEY unique_listing_tag (listing_id),

    -- Foreign Keys
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    FOREIGN KEY (tagged_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 2. EXCHANGE REQUESTS TABLE
-- Structured exchange workflow with dual-party confirmation
-- ============================================================
CREATE TABLE IF NOT EXISTS exchange_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    listing_id INT NOT NULL,

    -- Parties
    requester_id INT NOT NULL COMMENT 'User requesting the exchange',
    provider_id INT NOT NULL COMMENT 'Listing owner providing service',

    -- Exchange Details
    proposed_hours DECIMAL(5,2) NOT NULL,
    proposed_date DATE NULL,
    proposed_time TIME NULL,
    proposed_location VARCHAR(255) NULL,
    requester_notes TEXT NULL,

    -- Workflow Status
    status ENUM(
        'pending_provider',      -- Awaiting provider acceptance
        'pending_broker',        -- Awaiting broker approval (if required)
        'accepted',              -- Provider accepted, ready to schedule
        'scheduled',             -- Date/time confirmed
        'in_progress',           -- Exchange is happening
        'pending_confirmation',  -- Complete, awaiting dual confirmation
        'completed',             -- Both confirmed, credits transferred
        'disputed',              -- Dispute raised
        'cancelled',             -- Cancelled by either party
        'expired'                -- No response within time limit
    ) DEFAULT 'pending_provider',

    -- Broker Fields
    broker_id INT NULL COMMENT 'Assigned broker for this exchange',
    broker_notes TEXT NULL,
    broker_approved_at TIMESTAMP NULL,
    broker_conditions TEXT NULL COMMENT 'Conditions set by broker',

    -- Dual-Party Confirmation
    requester_confirmed_at TIMESTAMP NULL,
    requester_confirmed_hours DECIMAL(5,2) NULL,
    requester_feedback TEXT NULL,
    requester_rating TINYINT NULL COMMENT '1-5 rating',

    provider_confirmed_at TIMESTAMP NULL,
    provider_confirmed_hours DECIMAL(5,2) NULL,
    provider_feedback TEXT NULL,
    provider_rating TINYINT NULL COMMENT '1-5 rating',

    -- Final Resolution
    final_hours DECIMAL(5,2) NULL COMMENT 'Agreed hours after confirmation',
    transaction_id INT NULL COMMENT 'Link to wallet transaction',
    completed_at TIMESTAMP NULL,

    -- Risk Assessment
    risk_tag_id INT NULL COMMENT 'Link to listing risk tag if exists',
    risk_acknowledged_at TIMESTAMP NULL COMMENT 'When requester acknowledged risk',

    -- Cancellation/Decline
    cancelled_by INT NULL,
    cancelled_at TIMESTAMP NULL,
    cancellation_reason TEXT NULL,
    decline_reason TEXT NULL,

    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL COMMENT 'Request expires if not actioned',

    -- Indexes
    INDEX idx_tenant_status (tenant_id, status),
    INDEX idx_requester (tenant_id, requester_id),
    INDEX idx_provider (tenant_id, provider_id),
    INDEX idx_broker (tenant_id, broker_id),
    INDEX idx_listing (listing_id),
    INDEX idx_pending_provider (tenant_id, status, created_at),
    INDEX idx_pending_broker (tenant_id, status, broker_id),

    -- Foreign Keys
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (broker_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (risk_tag_id) REFERENCES listing_risk_tags(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 3. EXCHANGE HISTORY TABLE
-- Audit trail for all exchange state changes
-- ============================================================
CREATE TABLE IF NOT EXISTS exchange_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exchange_id INT NOT NULL,

    -- Action Details
    action VARCHAR(100) NOT NULL COMMENT 'e.g., created, accepted, broker_approved, confirmed',
    actor_id INT NULL COMMENT 'User who performed action',
    actor_role ENUM('requester', 'provider', 'broker', 'system') NOT NULL,

    -- Status Change
    old_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NULL,

    -- Additional Data
    notes TEXT NULL,
    metadata JSON NULL COMMENT 'Additional action-specific data',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_exchange (exchange_id),
    INDEX idx_actor (actor_id),
    INDEX idx_action (action),

    -- Foreign Keys
    FOREIGN KEY (exchange_id) REFERENCES exchange_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 4. BROKER MESSAGE COPIES TABLE
-- Message visibility for compliance monitoring
-- ============================================================
CREATE TABLE IF NOT EXISTS broker_message_copies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    original_message_id INT NOT NULL,

    -- Conversation Identification
    conversation_key VARCHAR(100) NOT NULL COMMENT 'Hash of sorted sender+receiver IDs',

    -- Original Message Details (denormalized for independent access)
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message_body TEXT NULL,
    sent_at TIMESTAMP NOT NULL,

    -- Copy Reason
    copy_reason ENUM(
        'first_contact',        -- First message between two users
        'high_risk_listing',    -- Message about high-risk listing
        'new_member',           -- Sender or receiver is new member
        'flagged_user',         -- User has been flagged previously
        'manual_monitoring',    -- User under manual monitoring
        'random_sample'         -- Random compliance sample
    ) NOT NULL,

    -- Related Context
    related_listing_id INT NULL,
    related_exchange_id INT NULL,

    -- Broker Review
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    flagged TINYINT(1) DEFAULT 0,
    flag_reason VARCHAR(255) NULL,
    flag_severity ENUM('info', 'warning', 'concern', 'urgent') NULL,

    -- Action Taken
    action_taken VARCHAR(100) NULL COMMENT 'e.g., no_action, warning_sent, monitoring_added',
    action_notes TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_tenant_unreviewed (tenant_id, reviewed_at),
    INDEX idx_conversation (conversation_key),
    INDEX idx_flagged (tenant_id, flagged),
    INDEX idx_copy_reason (tenant_id, copy_reason),
    INDEX idx_sender (sender_id),
    INDEX idx_receiver (receiver_id),

    -- Foreign Keys
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (related_listing_id) REFERENCES listings(id) ON DELETE SET NULL,
    FOREIGN KEY (related_exchange_id) REFERENCES exchange_requests(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 5. USER MESSAGING RESTRICTIONS TABLE
-- Per-user messaging controls and monitoring flags
-- ============================================================
CREATE TABLE IF NOT EXISTS user_messaging_restrictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,

    -- Restrictions
    messaging_disabled TINYINT(1) DEFAULT 0 COMMENT 'Messaging disabled for this user',
    requires_broker_approval TINYINT(1) DEFAULT 0 COMMENT 'All outgoing messages need approval',
    under_monitoring TINYINT(1) DEFAULT 0 COMMENT 'Messages are copied to broker',

    -- Monitoring Details
    monitoring_reason TEXT NULL,
    monitoring_started_at TIMESTAMP NULL,
    monitoring_expires_at TIMESTAMP NULL,

    -- Audit
    set_by INT NULL COMMENT 'Admin who set the restriction',
    restricted_at TIMESTAMP NULL,
    restriction_reason TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    UNIQUE KEY unique_tenant_user (tenant_id, user_id),
    INDEX idx_monitoring (tenant_id, under_monitoring),
    INDEX idx_messaging_disabled (tenant_id, messaging_disabled),

    -- Foreign Keys
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (set_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 6. ADD COLUMNS TO EXISTING TABLES
-- ============================================================

-- Add direct_messaging_disabled to listings (per-listing override)
ALTER TABLE listings
    ADD COLUMN IF NOT EXISTS direct_messaging_disabled TINYINT(1) DEFAULT 0
    COMMENT 'Disable direct contact for this listing, require exchange request';

-- Add exchange_workflow_required to listings
ALTER TABLE listings
    ADD COLUMN IF NOT EXISTS exchange_workflow_required TINYINT(1) DEFAULT 0
    COMMENT 'This listing requires formal exchange workflow';

-- Add index for listings with messaging disabled
CREATE INDEX IF NOT EXISTS idx_listings_messaging_disabled
    ON listings(tenant_id, direct_messaging_disabled);


-- ============================================================
-- 7. FIRST-CONTACT TRACKING
-- Track first messages between user pairs for monitoring
-- ============================================================
CREATE TABLE IF NOT EXISTS user_first_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user1_id INT NOT NULL COMMENT 'Lower user ID',
    user2_id INT NOT NULL COMMENT 'Higher user ID',
    first_message_id INT NOT NULL,
    first_contact_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_user_pair (tenant_id, user1_id, user2_id),
    INDEX idx_user1 (user1_id),
    INDEX idx_user2 (user2_id),

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
