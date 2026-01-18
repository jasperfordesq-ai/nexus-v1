-- ============================================================
-- FEDERATION DIRECTORY - FIX DISCOVERABLE FLAG
-- ============================================================
-- Run this to make all whitelisted tenants visible in the directory
-- ============================================================

-- Enable discoverability for all whitelisted tenants
UPDATE tenants t
INNER JOIN federation_tenant_whitelist fw ON t.id = fw.tenant_id
SET t.federation_discoverable = 1
WHERE t.federation_discoverable = 0 OR t.federation_discoverable IS NULL;

-- Verify the update
SELECT t.id, t.name, t.federation_discoverable
FROM tenants t
INNER JOIN federation_tenant_whitelist fw ON t.id = fw.tenant_id;

-- ============================================================
-- DONE!
-- ============================================================
