-- Migration: Enable all languages for all tenants
-- Date: 2026-02-25
-- Description: Sets supported_languages to all 5 platform languages for every tenant.
-- Previously only EN+GA were configured; this adds DE, FR, IT.

UPDATE tenants
SET configuration = JSON_SET(
    COALESCE(configuration, '{}'),
    '$.supported_languages',
    JSON_ARRAY('en', 'ga', 'de', 'fr', 'it')
);
