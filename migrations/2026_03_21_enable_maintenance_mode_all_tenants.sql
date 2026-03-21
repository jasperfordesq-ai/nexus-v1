-- Migration: Enable maintenance mode for ALL tenants
-- Date: 2026-03-21
-- Purpose: Put all tenants into maintenance mode
-- Reverse: UPDATE tenant_settings SET setting_value = 'false' WHERE setting_key = 'general.maintenance_mode';

UPDATE tenant_settings
SET setting_value = 'true'
WHERE setting_key = 'general.maintenance_mode';

-- Also insert for any tenants that don't have the setting yet
INSERT IGNORE INTO tenant_settings (tenant_id, setting_key, setting_value, setting_type)
SELECT t.id, 'general.maintenance_mode', 'true', 'boolean'
FROM tenants t
WHERE t.is_active = 1
  AND NOT EXISTS (
    SELECT 1 FROM tenant_settings ts
    WHERE ts.tenant_id = t.id AND ts.setting_key = 'general.maintenance_mode'
  );
