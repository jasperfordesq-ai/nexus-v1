-- ============================================================================
-- GROUP TYPES SYSTEM - Database Migration
-- ============================================================================
-- This migration adds a group types/categories system to organize groups
-- Date: 2026-01-08
-- ============================================================================

-- ============================================================================
-- 1. CREATE GROUP_TYPES TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS group_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(100) DEFAULT 'fa-layer-group',
    color VARCHAR(20) DEFAULT '#6366f1',
    image_url VARCHAR(500),
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_type_slug (tenant_id, slug),
    INDEX idx_tenant (tenant_id),
    INDEX idx_active (is_active),
    INDEX idx_sort (sort_order),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. ADD TYPE_ID COLUMN TO GROUPS TABLE
-- ============================================================================
-- Using stored procedure for safe column addition
DROP PROCEDURE IF EXISTS add_group_type_column;
DELIMITER //
CREATE PROCEDURE add_group_type_column()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = 'groups' AND COLUMN_NAME = 'type_id'
    ) THEN
        ALTER TABLE `groups`
        ADD COLUMN type_id INT DEFAULT NULL AFTER parent_id,
        ADD INDEX idx_type (type_id),
        ADD FOREIGN KEY (type_id) REFERENCES group_types(id) ON DELETE SET NULL;
    END IF;
END //
DELIMITER ;
CALL add_group_type_column();
DROP PROCEDURE IF EXISTS add_group_type_column;

-- ============================================================================
-- 3. INSERT DEFAULT GROUP TYPES (Examples)
-- ============================================================================
-- These are example types - customize based on your platform's needs
INSERT INTO group_types (tenant_id, name, slug, description, icon, color, sort_order)
SELECT
    t.id as tenant_id,
    'Community',
    'community',
    'Local community groups and neighborhood organizations',
    'fa-users',
    '#22c55e',
    10
FROM tenants t
WHERE NOT EXISTS (
    SELECT 1 FROM group_types WHERE tenant_id = t.id AND slug = 'community'
);

INSERT INTO group_types (tenant_id, name, slug, description, icon, color, sort_order)
SELECT
    t.id as tenant_id,
    'Volunteering',
    'volunteering',
    'Volunteer organizations and charitable groups',
    'fa-hands-helping',
    '#3b82f6',
    20
FROM tenants t
WHERE NOT EXISTS (
    SELECT 1 FROM group_types WHERE tenant_id = t.id AND slug = 'volunteering'
);

INSERT INTO group_types (tenant_id, name, slug, description, icon, color, sort_order)
SELECT
    t.id as tenant_id,
    'Sports & Recreation',
    'sports-recreation',
    'Sports clubs, fitness groups, and recreational activities',
    'fa-futbol',
    '#f59e0b',
    30
FROM tenants t
WHERE NOT EXISTS (
    SELECT 1 FROM group_types WHERE tenant_id = t.id AND slug = 'sports-recreation'
);

INSERT INTO group_types (tenant_id, name, slug, description, icon, color, sort_order)
SELECT
    t.id as tenant_id,
    'Arts & Culture',
    'arts-culture',
    'Arts, music, theater, and cultural organizations',
    'fa-palette',
    '#ec4899',
    40
FROM tenants t
WHERE NOT EXISTS (
    SELECT 1 FROM group_types WHERE tenant_id = t.id AND slug = 'arts-culture'
);

INSERT INTO group_types (tenant_id, name, slug, description, icon, color, sort_order)
SELECT
    t.id as tenant_id,
    'Education & Learning',
    'education-learning',
    'Educational groups, study circles, and learning communities',
    'fa-graduation-cap',
    '#8b5cf6',
    50
FROM tenants t
WHERE NOT EXISTS (
    SELECT 1 FROM group_types WHERE tenant_id = t.id AND slug = 'education-learning'
);

INSERT INTO group_types (tenant_id, name, slug, description, icon, color, sort_order)
SELECT
    t.id as tenant_id,
    'Environment',
    'environment',
    'Environmental and sustainability groups',
    'fa-leaf',
    '#10b981',
    60
FROM tenants t
WHERE NOT EXISTS (
    SELECT 1 FROM group_types WHERE tenant_id = t.id AND slug = 'environment'
);

INSERT INTO group_types (tenant_id, name, slug, description, icon, color, sort_order)
SELECT
    t.id as tenant_id,
    'Hobbies & Interests',
    'hobbies-interests',
    'Hobby groups and interest-based communities',
    'fa-star',
    '#06b6d4',
    70
FROM tenants t
WHERE NOT EXISTS (
    SELECT 1 FROM group_types WHERE tenant_id = t.id AND slug = 'hobbies-interests'
);

INSERT INTO group_types (tenant_id, name, slug, description, icon, color, sort_order)
SELECT
    t.id as tenant_id,
    'Business & Professional',
    'business-professional',
    'Business networks and professional organizations',
    'fa-briefcase',
    '#f97316',
    80
FROM tenants t
WHERE NOT EXISTS (
    SELECT 1 FROM group_types WHERE tenant_id = t.id AND slug = 'business-professional'
);

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================
-- Summary:
-- ✓ Created group_types table
-- ✓ Added type_id column to groups table
-- ✓ Created indexes and foreign keys
-- ✓ Inserted default group types for all tenants
--
-- Next Steps:
-- 1. Create GroupType model (src/Models/GroupType.php)
-- 2. Update Group model to support types
-- 3. Create admin interface for managing types
-- 4. Update group creation/edit forms to include type selection
-- ============================================================================
