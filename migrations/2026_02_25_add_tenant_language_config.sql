-- Migration: Add supported_languages to tenant configuration
-- Date: 2026-02-25
-- Seeds supported_languages on all tenants that don't have it set yet.
-- Idempotent: only updates tenants where supported_languages is not already configured.
--
-- Rules:
--   - agoris → English only (EN)
--   - All other tenants → English + Irish (EN, GA)

-- Agoris: English only
UPDATE tenants
SET configuration = JSON_SET(
    COALESCE(configuration, '{}'),
    '$.supported_languages',
    JSON_ARRAY('en')
)
WHERE slug = 'agoris'
  AND JSON_EXTRACT(configuration, '$.supported_languages') IS NULL;

-- All other tenants: English + Irish
UPDATE tenants
SET configuration = JSON_SET(
    COALESCE(configuration, '{}'),
    '$.supported_languages',
    JSON_ARRAY('en', 'ga')
)
WHERE slug != 'agoris'
  AND JSON_EXTRACT(configuration, '$.supported_languages') IS NULL;
