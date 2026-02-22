-- Insurance Certificates Table — UK compliance for insured services
-- Created: 2026-02-22

CREATE TABLE IF NOT EXISTS insurance_certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    insurance_type ENUM('public_liability','professional_indemnity','employers_liability','product_liability','personal_accident','other') NOT NULL DEFAULT 'public_liability',
    provider_name VARCHAR(255) DEFAULT NULL COMMENT 'Insurance company name',
    policy_number VARCHAR(100) DEFAULT NULL,
    coverage_amount DECIMAL(12,2) DEFAULT NULL COMMENT 'Coverage amount in local currency',
    start_date DATE DEFAULT NULL,
    expiry_date DATE DEFAULT NULL,
    certificate_file_path VARCHAR(500) DEFAULT NULL COMMENT 'Uploaded certificate file',
    status ENUM('pending','submitted','verified','expired','rejected','revoked') NOT NULL DEFAULT 'pending',
    verified_by INT DEFAULT NULL,
    verified_at DATETIME DEFAULT NULL,
    notes TEXT DEFAULT NULL COMMENT 'Internal broker notes',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ins_tenant (tenant_id),
    INDEX idx_ins_user (user_id),
    INDEX idx_ins_status (status),
    INDEX idx_ins_expiry (expiry_date),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users ADD COLUMN IF NOT EXISTS insurance_status ENUM('none','pending','verified','expired') NOT NULL DEFAULT 'none';
ALTER TABLE users ADD COLUMN IF NOT EXISTS insurance_expires_at DATE DEFAULT NULL;
