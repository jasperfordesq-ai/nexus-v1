-- Migration: Group invites system (email + link invitations)
-- Date: 2026-04-03
-- Idempotent: uses IF NOT EXISTS

CREATE TABLE IF NOT EXISTS group_invites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    group_id INT NOT NULL,
    invited_by INT NOT NULL,
    invite_type ENUM('email', 'link') NOT NULL DEFAULT 'email',
    email VARCHAR(255) NULL,
    token VARCHAR(80) NOT NULL,
    message TEXT NULL,
    status ENUM('pending', 'accepted', 'expired', 'revoked') NOT NULL DEFAULT 'pending',
    accepted_by INT NULL,
    accepted_at DATETIME NULL,
    expires_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_group_invites_token (token),
    INDEX idx_group_invites_group (group_id, status),
    INDEX idx_group_invites_email (email, status),
    INDEX idx_group_invites_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
