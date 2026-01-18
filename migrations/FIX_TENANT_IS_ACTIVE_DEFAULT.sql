-- ============================================================
-- FIX TENANT IS_ACTIVE DEFAULT VALUE
-- Migration to fix the is_active column default and activate existing tenants
-- ============================================================

-- Problem: is_active column was defaulting to 0 (inactive)
-- This caused all tenants to appear as "Inactive" even though they should be active

-- ============================================================
-- 1. CHANGE COLUMN DEFAULT FROM 0 TO 1
-- ============================================================

ALTER TABLE tenants MODIFY COLUMN is_active TINYINT(1) DEFAULT 1;

-- ============================================================
-- 2. ACTIVATE ALL EXISTING TENANTS
-- ============================================================

UPDATE tenants SET is_active = 1 WHERE is_active = 0;

-- ============================================================
-- VERIFICATION
-- ============================================================

SELECT 'Tenant is_active fix applied!' AS status;
SELECT id, name, slug, is_active FROM tenants;
