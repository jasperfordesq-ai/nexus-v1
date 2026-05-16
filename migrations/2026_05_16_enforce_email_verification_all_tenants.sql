-- Copyright © 2024–2026 Jasper Ford
-- SPDX-License-Identifier: AGPL-3.0-or-later
--
-- Enforce email_verification=true for every existing tenant.
--
-- Why: Email verification is now a platform-wide security requirement.
-- Only God (platform super-admin) may disable it per-tenant. This migration
-- writes an explicit row per tenant so the policy is auditable and survives
-- a future default-flip.
--
-- Also enforces the flag in tenant_registration_policies for tenants that
-- already have a policy row — both storage paths stay in sync.
--
-- Already-verified users are unaffected; only new registrations and any
-- user in status='pending_email' will be held at the email gate.

-- 1. Upsert the general.email_verification key in tenant_settings
INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, setting_type)
SELECT id, 'email_verification', 'true', 'boolean'
FROM tenants
ON DUPLICATE KEY UPDATE
    setting_value = 'true',
    setting_type  = 'boolean';

-- 2. Sync require_email_verify on any existing policy rows
UPDATE tenant_registration_policies
SET require_email_verify = 1
WHERE require_email_verify = 0 OR require_email_verify IS NULL;
