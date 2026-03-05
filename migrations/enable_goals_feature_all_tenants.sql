-- Enable goals feature for all tenants
-- 2026-03-01

UPDATE tenants
SET features = JSON_SET(
    COALESCE(features, '{}'),
    '$.goals',
    true
)
WHERE JSON_EXTRACT(features, '$.goals') IS NULL
   OR JSON_EXTRACT(features, '$.goals') = false;
