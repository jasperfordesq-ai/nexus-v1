-- ============================================
-- GDPR Compliance Database Schema
-- Project NEXUS Enterprise
-- ============================================

-- GDPR Data Subject Requests
CREATE TABLE IF NOT EXISTS gdpr_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    tenant_id INT NOT NULL,
    request_type ENUM('access', 'erasure', 'rectification', 'restriction', 'portability', 'objection') NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'rejected', 'cancelled') DEFAULT 'pending',
    priority ENUM('normal', 'high', 'urgent') DEFAULT 'normal',
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    acknowledged_at DATETIME NULL,
    processed_at DATETIME NULL,
    processed_by INT NULL,
    rejection_reason TEXT NULL,
    export_file_path VARCHAR(500) NULL,
    export_expires_at DATETIME NULL,
    verification_token VARCHAR(255) NULL,
    verified_at DATETIME NULL,
    notes TEXT NULL,
    metadata JSON NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_request_type (request_type),
    INDEX idx_requested_at (requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Consent Records
CREATE TABLE IF NOT EXISTS user_consents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    tenant_id INT NOT NULL,
    consent_type VARCHAR(100) NOT NULL,
    consent_given BOOLEAN DEFAULT FALSE,
    consent_text TEXT NOT NULL,
    consent_version VARCHAR(20) NOT NULL,
    consent_hash VARCHAR(64) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    source VARCHAR(50) DEFAULT 'web',
    given_at DATETIME NULL,
    withdrawn_at DATETIME NULL,
    expires_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_consent (user_id, consent_type),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_consent_type (consent_type),
    INDEX idx_given_at (given_at),
    UNIQUE KEY unique_user_consent_version (user_id, consent_type, consent_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Consent Types Reference
CREATE TABLE IF NOT EXISTS consent_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    slug VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    category VARCHAR(50) DEFAULT 'general',
    is_required BOOLEAN DEFAULT FALSE,
    current_version VARCHAR(20) NOT NULL DEFAULT '1.0',
    current_text TEXT NOT NULL,
    legal_basis ENUM('consent', 'contract', 'legal_obligation', 'vital_interests', 'public_task', 'legitimate_interests') DEFAULT 'consent',
    retention_days INT DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default consent types
INSERT INTO consent_types (slug, name, description, category, is_required, current_version, current_text, legal_basis) VALUES
('terms_of_service', 'Terms of Service', 'Agreement to platform terms and conditions', 'legal', TRUE, '1.0', 'I agree to the Terms of Service and understand my rights and obligations as a platform user.', 'contract'),
('privacy_policy', 'Privacy Policy', 'Acknowledgment of data processing practices', 'legal', TRUE, '1.0', 'I have read and understand the Privacy Policy and how my personal data will be processed.', 'contract'),
('marketing_email', 'Marketing Emails', 'Receive promotional emails and newsletters', 'marketing', FALSE, '1.0', 'I agree to receive marketing emails, newsletters, and promotional content. I can unsubscribe at any time.', 'consent'),
('marketing_sms', 'SMS Notifications', 'Receive SMS marketing messages', 'marketing', FALSE, '1.0', 'I agree to receive SMS marketing messages and promotions. Standard messaging rates may apply.', 'consent'),
('analytics', 'Analytics Tracking', 'Allow anonymous usage analytics', 'analytics', FALSE, '1.0', 'I agree to anonymous analytics tracking to help improve the platform experience.', 'legitimate_interests'),
('third_party_sharing', 'Third Party Sharing', 'Share data with partner organizations', 'data_sharing', FALSE, '1.0', 'I agree to share my data with trusted partner organizations for enhanced services.', 'consent'),
('location_tracking', 'Location Services', 'Use location for local features', 'functional', FALSE, '1.0', 'I agree to share my location to enable local listings, nearby members, and location-based features.', 'consent'),
('personalization', 'Personalization', 'Use data for personalized experience', 'functional', FALSE, '1.0', 'I agree to personalized recommendations based on my activity and preferences.', 'legitimate_interests')
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Data Processing Activity Log (Article 30)
CREATE TABLE IF NOT EXISTS data_processing_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    processing_activity VARCHAR(200) NOT NULL,
    purpose VARCHAR(500) NOT NULL,
    legal_basis ENUM('consent', 'contract', 'legal_obligation', 'vital_interests', 'public_task', 'legitimate_interests') NOT NULL,
    data_categories JSON NOT NULL,
    data_subjects JSON NOT NULL,
    recipients JSON NULL,
    third_country_transfers JSON NULL,
    retention_period VARCHAR(100) NOT NULL,
    security_measures TEXT NULL,
    dpia_required BOOLEAN DEFAULT FALSE,
    dpia_conducted BOOLEAN DEFAULT FALSE,
    dpia_date DATE NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_processing_activity (processing_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GDPR Audit Log
CREATE TABLE IF NOT EXISTS gdpr_audit_log (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    admin_id INT NULL,
    tenant_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NULL,
    entity_id INT NULL,
    old_value JSON NULL,
    new_value JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    request_id VARCHAR(100) NULL,
    additional_data JSON NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_admin_id (admin_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data Breach Log
CREATE TABLE IF NOT EXISTS data_breach_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    breach_id VARCHAR(50) NOT NULL UNIQUE,
    breach_type VARCHAR(100) NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    description TEXT NOT NULL,
    data_categories_affected JSON NOT NULL,
    number_of_records_affected INT NULL,
    number_of_users_affected INT NULL,
    detected_at DATETIME NOT NULL,
    occurred_at DATETIME NULL,
    contained_at DATETIME NULL,
    resolved_at DATETIME NULL,
    reported_to_authority BOOLEAN DEFAULT FALSE,
    reported_to_authority_at DATETIME NULL,
    authority_reference VARCHAR(200) NULL,
    authority_response TEXT NULL,
    users_notified BOOLEAN DEFAULT FALSE,
    users_notified_at DATETIME NULL,
    notification_method VARCHAR(100) NULL,
    remediation_actions TEXT NULL,
    root_cause TEXT NULL,
    lessons_learned TEXT NULL,
    prevention_measures TEXT NULL,
    created_by INT NOT NULL,
    updated_by INT NULL,
    status ENUM('detected', 'investigating', 'contained', 'resolved', 'closed') DEFAULT 'detected',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_detected_at (detected_at),
    INDEX idx_severity (severity),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data Retention Policies
CREATE TABLE IF NOT EXISTS data_retention_policies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    data_category VARCHAR(100) NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    retention_days INT NOT NULL,
    deletion_method ENUM('hard_delete', 'soft_delete', 'anonymize') DEFAULT 'soft_delete',
    legal_basis TEXT NULL,
    exception_criteria TEXT NULL,
    last_cleanup_at DATETIME NULL,
    records_deleted_last INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_id (tenant_id),
    UNIQUE KEY unique_tenant_category (tenant_id, data_category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cookie Consent Log
CREATE TABLE IF NOT EXISTS cookie_consents (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(255) NOT NULL,
    user_id INT NULL,
    tenant_id INT NOT NULL,
    essential BOOLEAN DEFAULT TRUE,
    analytics BOOLEAN DEFAULT FALSE,
    marketing BOOLEAN DEFAULT FALSE,
    functional BOOLEAN DEFAULT FALSE,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    consent_string TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_session_id (session_id),
    INDEX idx_user_id (user_id),
    INDEX idx_tenant_id (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add anonymized_at column to users table if not exists
-- ALTER TABLE users ADD COLUMN IF NOT EXISTS anonymized_at DATETIME NULL;
-- ALTER TABLE users ADD COLUMN IF NOT EXISTS gdpr_export_requested_at DATETIME NULL;
-- ALTER TABLE users ADD COLUMN IF NOT EXISTS gdpr_deletion_requested_at DATETIME NULL;
