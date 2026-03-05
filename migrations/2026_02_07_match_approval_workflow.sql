-- Migration: Match Approval Workflow
-- Created: 2026-02-07
-- Description: Adds broker approval workflow for matches
-- Design Decision: ALL matches require approval, users notified on rejection

-- Match Approvals Table
-- Follows pattern from group_approval_requests
CREATE TABLE IF NOT EXISTS match_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,

    -- The match details
    user_id INT NOT NULL,                -- User who would receive this match
    listing_id INT NOT NULL,             -- The matched listing
    listing_owner_id INT NOT NULL,       -- Owner of the listing (other party)
    match_score DECIMAL(5,2) NOT NULL,   -- Score at time of generation (0-100)
    match_type VARCHAR(50) DEFAULT 'one_way',  -- one_way, potential, mutual, cold_start
    match_reasons JSON,                  -- Reasons array from matching engine
    distance_km DECIMAL(8,2),            -- Distance between parties

    -- Approval workflow fields
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT NULL,                -- Admin/broker who reviewed
    reviewed_at TIMESTAMP NULL,
    review_notes TEXT NULL,              -- Notes from reviewer (shown to user on rejection)

    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign keys
    CONSTRAINT fk_match_approvals_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_match_approvals_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_match_approvals_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    CONSTRAINT fk_match_approvals_owner FOREIGN KEY (listing_owner_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_match_approvals_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,

    -- Indexes for common queries
    INDEX idx_tenant_status (tenant_id, status),
    INDEX idx_pending (tenant_id, status, submitted_at),
    INDEX idx_user (user_id),
    INDEX idx_listing (listing_id),
    INDEX idx_reviewer (reviewed_by),

    -- Prevent duplicate pending approvals for same user/listing pair
    UNIQUE KEY unique_pending_match (tenant_id, user_id, listing_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add theme preference column for React frontend (Feature 2)
ALTER TABLE users
ADD COLUMN IF NOT EXISTS preferred_theme ENUM('light', 'dark', 'system') DEFAULT 'dark'
AFTER preferred_layout;

-- Create index for theme lookups
CREATE INDEX IF NOT EXISTS idx_users_theme ON users(preferred_theme);
