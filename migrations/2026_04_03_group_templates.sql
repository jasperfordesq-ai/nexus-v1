-- Migration: Group templates for quick creation
-- Date: 2026-04-03
-- Idempotent: uses IF NOT EXISTS

CREATE TABLE IF NOT EXISTS group_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    icon VARCHAR(50) NULL,
    default_visibility ENUM('public', 'private', 'secret') NOT NULL DEFAULT 'public',
    default_type_id INT NULL,
    default_tags JSON NULL COMMENT 'Array of tag IDs to auto-assign',
    features JSON NULL COMMENT 'Feature toggles to apply',
    welcome_message TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_group_templates_tenant (tenant_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Group custom fields
CREATE TABLE IF NOT EXISTS group_custom_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    field_key VARCHAR(100) NOT NULL,
    field_type ENUM('text', 'number', 'date', 'select', 'multi_select', 'boolean', 'url') NOT NULL DEFAULT 'text',
    options JSON NULL COMMENT 'Options for select/multi_select types',
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    is_searchable TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_gcf_key (tenant_id, field_key),
    INDEX idx_gcf_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS group_custom_field_values (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    field_id INT NOT NULL,
    field_value TEXT NULL,
    UNIQUE KEY uk_gcfv (group_id, field_id),
    INDEX idx_gcfv_field (field_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
