-- Enable direct messaging for crewkerne-timebank tenant
-- Two controls gate messaging:
--   1. tenants.features JSON → $.direct_messaging (React UI feature flag)
--   2. tenants.configuration JSON → $.broker_controls.messaging.direct_messaging_enabled (PHP API check)
-- Both must be true for messaging to work end-to-end.

-- Pre-check
SELECT id, slug, name, features, configuration FROM tenants WHERE slug = 'crewkerne-timebank';

-- 1. Enable the React feature flag
UPDATE tenants
SET features = JSON_SET(COALESCE(features, '{}'), '$.direct_messaging', true)
WHERE slug = 'crewkerne-timebank';

-- 2. Enable the broker control config (PHP API)
UPDATE tenants
SET configuration = JSON_SET(
    COALESCE(configuration, '{}'),
    '$.broker_controls.messaging.direct_messaging_enabled', true
)
WHERE slug = 'crewkerne-timebank';

-- 3. Also clear any tenant_settings override that might disable it
DELETE FROM tenant_settings
WHERE tenant_id = (SELECT id FROM tenants WHERE slug = 'crewkerne-timebank')
  AND setting_key = 'broker_config'
  AND JSON_EXTRACT(setting_value, '$.direct_messaging_enabled') = false;

-- Clear Redis cache for this tenant so bootstrap picks up the change immediately
-- Run manually: docker exec nexus-php-redis redis-cli DEL "tenant_bootstrap:<TENANT_ID>"

-- Verify
SELECT id, slug, name, features, configuration FROM tenants WHERE slug = 'crewkerne-timebank';
