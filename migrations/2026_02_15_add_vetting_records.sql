-- Vetting Records Table — DBS/Garda vetting tracking for TOL2 compliance
-- Created: 2026-02-15

CREATE TABLE IF NOT EXISTS vetting_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    vetting_type ENUM('dbs_basic', 'dbs_standard', 'dbs_enhanced', 'garda_vetting', 'access_ni', 'pvg_scotland', 'international', 'other') NOT NULL DEFAULT 'dbs_basic',
    status ENUM('pending', 'submitted', 'verified', 'expired', 'rejected', 'revoked') NOT NULL DEFAULT 'pending',
    reference_number VARCHAR(100) DEFAULT NULL COMMENT 'DBS certificate number or Garda ref',
    issue_date DATE DEFAULT NULL,
    expiry_date DATE DEFAULT NULL,
    verified_by INT UNSIGNED DEFAULT NULL COMMENT 'Admin/broker who verified',
    verified_at DATETIME DEFAULT NULL,
    document_url VARCHAR(500) DEFAULT NULL COMMENT 'Optional uploaded document path',
    notes TEXT DEFAULT NULL COMMENT 'Internal broker notes',
    works_with_children TINYINT(1) NOT NULL DEFAULT 0,
    works_with_vulnerable_adults TINYINT(1) NOT NULL DEFAULT 0,
    requires_enhanced_check TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_vetting_tenant (tenant_id),
    INDEX idx_vetting_user (user_id),
    INDEX idx_vetting_status (status),
    INDEX idx_vetting_expiry (expiry_date),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add vetting_status column to users table for quick lookup
ALTER TABLE users ADD COLUMN IF NOT EXISTS vetting_status ENUM('none', 'pending', 'verified', 'expired') NOT NULL DEFAULT 'none';
ALTER TABLE users ADD COLUMN IF NOT EXISTS vetting_expires_at DATE DEFAULT NULL;
