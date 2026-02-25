-- Enable all 5 languages for every tenant
UPDATE tenants
SET configuration = JSON_SET(
    COALESCE(configuration, '{}'),
    '$.supported_languages',
    JSON_ARRAY('en', 'ga', 'de', 'fr', 'it')
);

-- Set Agoris default language to German (Swiss default)
UPDATE tenants
SET configuration = JSON_SET(
    configuration,
    '$.default_language', 'de'
)
WHERE slug = 'agoris';

-- All other tenants default to English (explicit)
UPDATE tenants
SET configuration = JSON_SET(
    configuration,
    '$.default_language', 'en'
)
WHERE slug != 'agoris';
