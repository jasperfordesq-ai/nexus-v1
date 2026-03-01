-- Migration: Groups, Matching, Federation, and Messaging module enhancements
-- Date: 2026-03-01
-- Features: GR1 (file sharing), GR2 (group events), GR3 (announcements),
--           MA1 (cross-module matching), MA2 (predictive staffing), MA3 (match digest),
--           FD1 (credit pooling), FD2 (neighborhoods), MS1 (contextual messaging),
--           MS2 (safeguarding assignments)
-- All statements are idempotent (IF NOT EXISTS / IF EXISTS)

-- ============================================================================
-- GR1: GROUP FILE SHARING
-- ============================================================================

CREATE TABLE IF NOT EXISTS group_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    tenant_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    file_size BIGINT NOT NULL DEFAULT 0,
    uploaded_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_group_files_group (group_id),
    INDEX idx_group_files_tenant (tenant_id),
    INDEX idx_group_files_uploader (uploaded_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- GR2: GROUP EVENTS (add group_id FK to events table)
-- ============================================================================

ALTER TABLE events ADD COLUMN IF NOT EXISTS group_id INT NULL DEFAULT NULL;
ALTER TABLE events ADD INDEX IF NOT EXISTS idx_events_group (group_id);

-- ============================================================================
-- GR3: GROUP ANNOUNCEMENTS
-- ============================================================================

CREATE TABLE IF NOT EXISTS group_announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    tenant_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    is_pinned TINYINT(1) NOT NULL DEFAULT 0,
    priority INT NOT NULL DEFAULT 0,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    expires_at DATETIME NULL DEFAULT NULL,
    INDEX idx_group_announcements_group (group_id),
    INDEX idx_group_announcements_tenant (tenant_id),
    INDEX idx_group_announcements_pinned (is_pinned, priority DESC),
    INDEX idx_group_announcements_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MA1: CROSS-MODULE MATCHING (uses existing match_cache and match_history)
-- No new tables needed — CrossModuleMatchingService queries across modules
-- ============================================================================

-- ============================================================================
-- MA2: PREDICTIVE STAFFING
-- ============================================================================

CREATE TABLE IF NOT EXISTS staffing_predictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    shift_id INT NULL DEFAULT NULL,
    event_id INT NULL DEFAULT NULL,
    predicted_date DATE NOT NULL,
    predicted_shortfall INT NOT NULL DEFAULT 0,
    confidence DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    risk_level ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'low',
    factors_json TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL DEFAULT NULL,
    INDEX idx_staffing_tenant (tenant_id),
    INDEX idx_staffing_date (predicted_date),
    INDEX idx_staffing_risk (risk_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MA3: MATCH DIGEST EMAIL (tracking table)
-- ============================================================================

CREATE TABLE IF NOT EXISTS match_digest_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    matches_count INT NOT NULL DEFAULT 0,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    digest_data TEXT NULL,
    INDEX idx_digest_tenant_user (tenant_id, user_id),
    INDEX idx_digest_sent (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- FD1: CROSS-COMMUNITY CREDIT POOLING
-- ============================================================================

CREATE TABLE IF NOT EXISTS federation_credit_agreements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_tenant_id INT NOT NULL,
    to_tenant_id INT NOT NULL,
    exchange_rate DECIMAL(10,4) NOT NULL DEFAULT 1.0000,
    status ENUM('pending', 'active', 'suspended', 'terminated') NOT NULL DEFAULT 'pending',
    max_monthly_credits DECIMAL(10,2) NULL DEFAULT NULL,
    approved_by_from INT NULL DEFAULT NULL,
    approved_by_to INT NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_credit_from (from_tenant_id),
    INDEX idx_credit_to (to_tenant_id),
    INDEX idx_credit_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS federation_credit_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agreement_id INT NOT NULL,
    from_tenant_id INT NOT NULL,
    to_tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    converted_amount DECIMAL(10,2) NOT NULL,
    exchange_rate DECIMAL(10,4) NOT NULL,
    description VARCHAR(500) NULL DEFAULT NULL,
    status ENUM('pending', 'completed', 'reversed') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL DEFAULT NULL,
    INDEX idx_transfer_agreement (agreement_id),
    INDEX idx_transfer_user (user_id),
    INDEX idx_transfer_from (from_tenant_id),
    INDEX idx_transfer_to (to_tenant_id),
    INDEX idx_transfer_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS federation_credit_balances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id_a INT NOT NULL,
    tenant_id_b INT NOT NULL,
    net_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    last_settlement_at DATETIME NULL DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_balance_pair (tenant_id_a, tenant_id_b)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- FD2: FEDERATION NEIGHBORHOOD GROUPS
-- ============================================================================

CREATE TABLE IF NOT EXISTS federation_neighborhoods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    region VARCHAR(255) NULL DEFAULT NULL,
    created_by INT NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_neighborhood_region (region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS federation_neighborhood_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    neighborhood_id INT NOT NULL,
    tenant_id INT NOT NULL,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_neighborhood_tenant (neighborhood_id, tenant_id),
    INDEX idx_neighborhood_member_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MS1: CONTEXTUAL MESSAGING (add context_type + context_id to messages)
-- listing_id already exists from prior migration; add generic context columns
-- ============================================================================

ALTER TABLE messages ADD COLUMN IF NOT EXISTS context_type VARCHAR(50) NULL DEFAULT NULL;
ALTER TABLE messages ADD COLUMN IF NOT EXISTS context_id INT NULL DEFAULT NULL;
ALTER TABLE messages ADD INDEX IF NOT EXISTS idx_messages_context (context_type, context_id);

-- ============================================================================
-- MS2: SAFEGUARDING / GUARDIAN ANGEL ASSIGNMENTS
-- ============================================================================

CREATE TABLE IF NOT EXISTS safeguarding_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    guardian_user_id INT NOT NULL,
    ward_user_id INT NOT NULL,
    tenant_id INT NOT NULL,
    assigned_by INT NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    consent_given_at DATETIME NULL DEFAULT NULL,
    revoked_at DATETIME NULL DEFAULT NULL,
    notes TEXT NULL,
    INDEX idx_safeguard_guardian (guardian_user_id),
    INDEX idx_safeguard_ward (ward_user_id),
    INDEX idx_safeguard_tenant (tenant_id),
    UNIQUE KEY uk_safeguard_pair (guardian_user_id, ward_user_id, tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS safeguarding_flagged_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    tenant_id INT NOT NULL,
    flagged_reason VARCHAR(255) NOT NULL DEFAULT 'keyword_match',
    matched_keyword VARCHAR(100) NULL DEFAULT NULL,
    reviewed_by INT NULL DEFAULT NULL,
    reviewed_at DATETIME NULL DEFAULT NULL,
    review_notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_flagged_tenant (tenant_id),
    INDEX idx_flagged_message (message_id),
    INDEX idx_flagged_reviewed (reviewed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
