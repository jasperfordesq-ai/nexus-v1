-- =============================================
-- PAGE MENU SETTINGS MIGRATION
-- Adds menu visibility and location options
-- Run after PAGE_BUILDER_UPGRADE.sql
-- =============================================

-- Add menu settings columns to pages table
ALTER TABLE pages
ADD COLUMN IF NOT EXISTS `show_in_menu` TINYINT(1) DEFAULT 0 AFTER `sort_order`,
ADD COLUMN IF NOT EXISTS `menu_location` VARCHAR(20) DEFAULT 'about' AFTER `show_in_menu`;

-- Index for menu queries
CREATE INDEX IF NOT EXISTS idx_pages_menu ON pages(tenant_id, is_published, show_in_menu, menu_location);

-- Update existing published pages to show in About menu by default (optional)
-- Uncomment if you want existing pages to appear in menus
-- UPDATE pages SET show_in_menu = 1, menu_location = 'about' WHERE is_published = 1;
