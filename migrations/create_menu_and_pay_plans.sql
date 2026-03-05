-- Menu Management & Pay Plans Database Schema
-- Migration for Project NEXUS TimeBank
-- Run this migration to set up tenant-aware, pay layout-aware menu system

-- ============================================================================
-- PAY PLANS TABLES
-- ============================================================================

-- Payment plans (subscription tiers)
CREATE TABLE IF NOT EXISTS pay_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    tier_level INT NOT NULL DEFAULT 0,
    features JSON DEFAULT NULL COMMENT 'Feature flags available to this plan',
    allowed_layouts JSON DEFAULT NULL COMMENT 'Array of layout names allowed for this plan',
    max_menus INT DEFAULT 5 COMMENT 'Maximum number of custom menus allowed',
    max_menu_items INT DEFAULT 50 COMMENT 'Maximum menu items per menu',
    price_monthly DECIMAL(10,2) DEFAULT 0.00,
    price_yearly DECIMAL(10,2) DEFAULT 0.00,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pay_plans_tier (tier_level),
    INDEX idx_pay_plans_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tenant plan assignments (which plan each tenant is on)
CREATE TABLE IF NOT EXISTS tenant_plan_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    pay_plan_id INT NOT NULL,
    status ENUM('active', 'expired', 'cancelled', 'trial') DEFAULT 'active',
    starts_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL COMMENT 'NULL means unlimited',
    trial_ends_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_plan_tenant (tenant_id),
    INDEX idx_tenant_plan_status (status),
    INDEX idx_tenant_plan_expires (expires_at),
    FOREIGN KEY (pay_plan_id) REFERENCES pay_plans(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MENU TABLES
-- ============================================================================

-- Menus (containers for menu items)
CREATE TABLE IF NOT EXISTS menus (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL,
    description TEXT,
    location VARCHAR(50) NOT NULL COMMENT 'header-main, header-secondary, footer, sidebar, mobile',
    layout VARCHAR(50) DEFAULT NULL COMMENT 'modern, civicone, skeleton, or NULL for all layouts',
    min_plan_tier INT DEFAULT 0 COMMENT 'Minimum plan tier required to see this menu',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_menu_unique (tenant_id, slug),
    INDEX idx_menu_tenant (tenant_id),
    INDEX idx_menu_location (location),
    INDEX idx_menu_layout (layout),
    INDEX idx_menu_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Menu items (individual links, dropdowns, etc.)
CREATE TABLE IF NOT EXISTS menu_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    menu_id INT NOT NULL,
    parent_id INT DEFAULT NULL COMMENT 'For nested/dropdown menus',
    type ENUM('link', 'dropdown', 'page', 'route', 'external', 'divider') DEFAULT 'link',
    label VARCHAR(255) NOT NULL,
    url VARCHAR(500) DEFAULT NULL,
    route_name VARCHAR(100) DEFAULT NULL COMMENT 'Internal route name for route type',
    page_id INT DEFAULT NULL COMMENT 'CMS page ID for page type',
    icon VARCHAR(100) DEFAULT NULL COMMENT 'Icon class or name',
    css_class VARCHAR(255) DEFAULT NULL,
    target VARCHAR(20) DEFAULT '_self' COMMENT '_self, _blank, etc.',
    sort_order INT DEFAULT 0,
    visibility_rules JSON DEFAULT NULL COMMENT 'Auth, role, feature conditions',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_menu_items_menu (menu_id),
    INDEX idx_menu_items_parent (parent_id),
    INDEX idx_menu_items_sort (sort_order),
    INDEX idx_menu_items_active (is_active),
    INDEX idx_menu_items_page (page_id),
    FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES menu_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MENU CACHE TABLE
-- ============================================================================

-- Menu cache for performance
CREATE TABLE IF NOT EXISTS menu_cache (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    layout VARCHAR(50) DEFAULT NULL,
    location VARCHAR(50) NOT NULL,
    user_role VARCHAR(50) DEFAULT 'guest' COMMENT 'guest, user, admin, super_admin',
    cache_key VARCHAR(255) NOT NULL,
    cached_data LONGTEXT NOT NULL COMMENT 'Serialized menu structure',
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_menu_cache_key (cache_key),
    INDEX idx_menu_cache_tenant (tenant_id, location, layout),
    INDEX idx_menu_cache_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DEFAULT DATA SEEDS
-- ============================================================================

-- Insert default pay plans
INSERT INTO pay_plans (name, slug, description, tier_level, features, allowed_layouts, max_menus, max_menu_items, price_monthly, price_yearly) VALUES
('Free', 'free', 'Basic features for small communities', 0,
 JSON_OBJECT('listings', true, 'groups', false, 'wallet', false, 'custom_menus', false, 'page_builder', false),
 JSON_ARRAY('modern'),
 1, 10, 0.00, 0.00),

('Basic', 'basic', 'Essential features for growing communities', 1,
 JSON_OBJECT('listings', true, 'groups', true, 'wallet', true, 'custom_menus', true, 'page_builder', false, 'analytics', false),
 JSON_ARRAY('modern', 'civicone'),
 3, 30, 29.00, 290.00),

('Professional', 'professional', 'Advanced features for established organizations', 2,
 JSON_OBJECT('listings', true, 'groups', true, 'wallet', true, 'custom_menus', true, 'page_builder', true, 'analytics', true, 'ai_features', false, 'white_label', false),
 JSON_ARRAY('modern', 'civicone', 'skeleton'),
 10, 100, 79.00, 790.00),

('Enterprise', 'enterprise', 'Full platform access with premium support', 3,
 JSON_OBJECT('listings', true, 'groups', true, 'wallet', true, 'custom_menus', true, 'page_builder', true, 'analytics', true, 'ai_features', true, 'white_label', true, 'priority_support', true, 'custom_domain', true),
 JSON_ARRAY('modern', 'civicone', 'skeleton'),
 999, 999, 199.00, 1990.00);

-- Assign master tenant to Enterprise plan (tenant_id = 1)
INSERT INTO tenant_plan_assignments (tenant_id, pay_plan_id, status, starts_at, expires_at) VALUES
(1, 4, 'active', NOW(), NULL);

-- Create default menu for master tenant
INSERT INTO menus (tenant_id, name, slug, description, location, layout, min_plan_tier, is_active) VALUES
(1, 'Main Navigation', 'main-nav', 'Primary navigation menu', 'header-main', NULL, 0, 1);

-- Insert sample menu items for master tenant
SET @menu_id = LAST_INSERT_ID();

INSERT INTO menu_items (menu_id, type, label, url, sort_order, visibility_rules, is_active) VALUES
(@menu_id, 'link', 'Home', '/', 10, NULL, 1),
(@menu_id, 'link', 'Explore', '/listings', 20, JSON_OBJECT('requires_auth', false), 1),
(@menu_id, 'dropdown', 'Community', NULL, 30, NULL, 1);

SET @community_dropdown_id = LAST_INSERT_ID();

INSERT INTO menu_items (menu_id, parent_id, type, label, url, sort_order, visibility_rules, is_active) VALUES
(@menu_id, @community_dropdown_id, 'link', 'Groups', '/groups', 10, JSON_OBJECT('requires_feature', 'groups'), 1),
(@menu_id, @community_dropdown_id, 'link', 'Members', '/members', 20, JSON_OBJECT('requires_auth', true), 1),
(@menu_id, @community_dropdown_id, 'link', 'Events', '/events', 30, NULL, 1);

INSERT INTO menu_items (menu_id, type, label, url, sort_order, visibility_rules, is_active) VALUES
(@menu_id, 'link', 'About', '/about', 40, NULL, 1),
(@menu_id, 'link', 'Dashboard', '/dashboard', 50, JSON_OBJECT('requires_auth', true, 'min_role', 'user'), 1);

-- Create footer menu for master tenant
INSERT INTO menus (tenant_id, name, slug, description, location, layout, min_plan_tier, is_active) VALUES
(1, 'Footer Navigation', 'footer-nav', 'Footer links', 'footer', NULL, 0, 1);

SET @footer_menu_id = LAST_INSERT_ID();

INSERT INTO menu_items (menu_id, type, label, url, sort_order, is_active) VALUES
(@footer_menu_id, 'link', 'Privacy Policy', '/privacy', 10, 1),
(@footer_menu_id, 'link', 'Terms of Service', '/terms', 20, 1),
(@footer_menu_id, 'link', 'Contact', '/contact', 30, 1),
(@footer_menu_id, 'link', 'Help', '/help', 40, 1);

-- ============================================================================
-- NOTES
-- ============================================================================

/*
USAGE NOTES:

1. Pay Plans:
   - tier_level: 0=Free, 1=Basic, 2=Professional, 3=Enterprise
   - features: JSON object with feature flags
   - allowed_layouts: JSON array of layout names

2. Menu Visibility Rules (JSON):
   {
     "requires_auth": true/false,
     "min_role": "user|admin|super_admin",
     "requires_feature": "feature_name",
     "exclude_roles": ["role1", "role2"],
     "custom_condition": "php_function_name"
   }

3. Menu Locations:
   - header-main: Primary navigation
   - header-secondary: Secondary/utility navigation
   - footer: Footer links
   - sidebar: Sidebar navigation
   - mobile: Mobile-specific menu

4. Menu Item Types:
   - link: Simple link with URL
   - dropdown: Parent item with children
   - page: Links to CMS page (uses page_id)
   - route: Uses internal route name
   - external: External URL (opens in new tab)
   - divider: Visual separator (no link)

5. Caching:
   - Cache keys are generated based on: tenant_id + layout + location + user_role
   - Expires_at should be set to 1 hour by default
   - Clear cache when menus/items are updated
*/
