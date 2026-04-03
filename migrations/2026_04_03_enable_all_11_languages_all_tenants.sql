-- Migration: Enable all 11 languages for all tenants (add Dutch, Polish, Japanese, Arabic)
-- Date: 2026-04-03
-- Description: Updates supported_languages to include NL, PL, JA, AR for every tenant.
-- Previously only EN, GA, DE, FR, IT, PT, ES were configured.

UPDATE tenants
SET configuration = JSON_SET(
    COALESCE(configuration, '{}'),
    '$.supported_languages',
    JSON_ARRAY('en', 'ga', 'de', 'fr', 'it', 'pt', 'es', 'nl', 'pl', 'ja', 'ar')
);
