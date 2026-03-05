-- Migration: Backfill missing tenant defaults for all existing tenants
-- Date: 2026-03-05
-- Context: Tenant creation previously only seeded categories and attributes.
--          This migration adds missing tenant_settings defaults for existing tenants.
--
-- Idempotent: Uses INSERT IGNORE so re-running is safe.

-- Seed default tenant_settings for every active tenant that is missing them
-- These are the same defaults from TenantSettingsService::seedDefaults()

INSERT IGNORE INTO tenant_settings (tenant_id, setting_key, setting_value, setting_type)
SELECT t.id, 'general.registration_mode', 'open', 'string'
FROM tenants t
WHERE t.is_active = 1
  AND NOT EXISTS (
    SELECT 1 FROM tenant_settings ts
    WHERE ts.tenant_id = t.id AND ts.setting_key = 'general.registration_mode'
  );

INSERT IGNORE INTO tenant_settings (tenant_id, setting_key, setting_value, setting_type)
SELECT t.id, 'general.email_verification', 'true', 'boolean'
FROM tenants t
WHERE t.is_active = 1
  AND NOT EXISTS (
    SELECT 1 FROM tenant_settings ts
    WHERE ts.tenant_id = t.id AND ts.setting_key = 'general.email_verification'
  );

INSERT IGNORE INTO tenant_settings (tenant_id, setting_key, setting_value, setting_type)
SELECT t.id, 'general.admin_approval', 'true', 'boolean'
FROM tenants t
WHERE t.is_active = 1
  AND NOT EXISTS (
    SELECT 1 FROM tenant_settings ts
    WHERE ts.tenant_id = t.id AND ts.setting_key = 'general.admin_approval'
  );

INSERT IGNORE INTO tenant_settings (tenant_id, setting_key, setting_value, setting_type)
SELECT t.id, 'general.maintenance_mode', 'false', 'boolean'
FROM tenants t
WHERE t.is_active = 1
  AND NOT EXISTS (
    SELECT 1 FROM tenant_settings ts
    WHERE ts.tenant_id = t.id AND ts.setting_key = 'general.maintenance_mode'
  );

-- Backfill default features JSON for tenants that have NULL features
-- This ensures TenantContext::hasFeature() works consistently
UPDATE tenants
SET features = '{"events":true,"groups":true,"gamification":false,"goals":false,"blog":true,"resources":false,"volunteering":false,"exchange_workflow":false,"organisations":false,"federation":false,"connections":true,"reviews":true,"polls":false,"job_vacancies":false,"ideation_challenges":false,"direct_messaging":true,"group_exchanges":false,"search":true,"ai_chat":false}'
WHERE features IS NULL AND is_active = 1;
