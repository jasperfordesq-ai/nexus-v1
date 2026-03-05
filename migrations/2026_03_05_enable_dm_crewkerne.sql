-- Enable direct_messaging for crewkerne-timebank tenant
-- The features column is JSON; we need to set direct_messaging = true

-- First, check current state
SELECT id, slug, name, features FROM tenants WHERE slug = 'crewkerne-timebank';

-- Update: merge direct_messaging into the existing features JSON
UPDATE tenants
SET features = JSON_SET(COALESCE(features, '{}'), '$.direct_messaging', true)
WHERE slug = 'crewkerne-timebank';

-- Clear Redis cache for this tenant so bootstrap picks up the change immediately
-- (Run manually: php -r "require 'vendor/autoload.php'; \Nexus\Services\RedisCache::delete('tenant_bootstrap', <TENANT_ID>);")

-- Verify
SELECT id, slug, name, features FROM tenants WHERE slug = 'crewkerne-timebank';
