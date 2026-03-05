-- Migration: Add layout_banner feature flag to tenant_settings
-- Date: 2026-01-22
-- Purpose: Allow tenants to toggle the layout switch banner on/off

-- Insert default setting for all existing tenants (enabled by default)
-- Uses ON DUPLICATE KEY UPDATE to be idempotent (safe to run multiple times)

INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, setting_type, description, created_at, updated_at)
SELECT
    t.id,
    'feature.layout_banner',
    '1',
    'boolean',
    'Display the layout switch banner at the top of pages',
    NOW(),
    NOW()
FROM tenants t
WHERE t.is_active = 1
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Verify the setting was added
SELECT tenant_id, setting_key, setting_value, description
FROM tenant_settings
WHERE setting_key = 'feature.layout_banner';
