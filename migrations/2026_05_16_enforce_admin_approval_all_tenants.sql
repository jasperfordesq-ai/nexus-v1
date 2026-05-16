-- Copyright © 2024–2026 Jasper Ford
-- SPDX-License-Identifier: AGPL-3.0-or-later
--
-- Enforce admin_approval=true for every existing tenant.
--
-- Why: TenantSettingsService::requiresAdminApproval() now defaults to TRUE
-- (fail-closed), but writing an explicit row per tenant makes the policy
-- auditable in the database and survives a future default-flip.
--
-- This only writes the bare `admin_approval` key the reader uses. It does
-- NOT touch `is_approved` on any existing user row — already-active
-- members keep their access. The gate applies only to NEW registrations
-- and any user currently in `status='pending'`.

INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, setting_type)
SELECT id, 'admin_approval', 'true', 'boolean'
FROM tenants
ON DUPLICATE KEY UPDATE
    setting_value = 'true',
    setting_type  = 'boolean';
