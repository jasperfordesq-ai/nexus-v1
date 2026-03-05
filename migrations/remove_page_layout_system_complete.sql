-- ============================================================================
-- COMPLETE REMOVAL OF NEXUS SOCIAL PAGE LAYOUT SYSTEM
-- ============================================================================
-- This migration removes ALL traces of the page layout system including:
-- - Layout builder infrastructure
-- - A/B testing system
-- - Export/import functionality
-- - User preferences and history
-- - All related tables and columns
-- ============================================================================

-- Drop all layout-related tables in correct dependency order

-- A/B Testing System
DROP TABLE IF EXISTS layout_recommendations;
DROP TABLE IF EXISTS layout_ab_metrics;
DROP TABLE IF EXISTS layout_ab_assignments;
DROP TABLE IF EXISTS layout_ab_tests;

-- Export/Import System
DROP TABLE IF EXISTS layout_imports;
DROP TABLE IF EXISTS layout_exports;

-- Layout Builder System
DROP TABLE IF EXISTS layout_versions;
DROP TABLE IF EXISTS layout_builder_sessions;
DROP TABLE IF EXISTS layout_builder_templates;
DROP TABLE IF EXISTS custom_layouts;

-- User Preferences
DROP TABLE IF EXISTS user_layout_history;

-- Remove layout preference column from users table
ALTER TABLE users DROP COLUMN IF EXISTS preferred_layout;

-- ============================================================================
-- CLEANUP COMPLETE
-- All page layout system tables and columns have been removed
-- ============================================================================
