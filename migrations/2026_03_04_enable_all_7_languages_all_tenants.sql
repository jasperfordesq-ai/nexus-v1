-- Migration: Enable all 7 languages for all tenants (add Portuguese + Spanish)
-- Date: 2026-03-04
-- Description: Updates supported_languages to include PT and ES for every tenant.
-- Previously only EN, GA, DE, FR, IT were configured.

UPDATE tenants
SET configuration = JSON_SET(
    COALESCE(configuration, '{}'),
    '$.supported_languages',
    JSON_ARRAY('en', 'ga', 'de', 'fr', 'it', 'pt', 'es')
);
