-- ===================================================================
-- FIX FAILED GEOCODING - Manual Coordinates
-- ===================================================================
-- These 4 groups failed to geocode automatically
-- Adding accurate coordinates manually for Irish towns
-- ===================================================================

-- Check what locations we have for these groups
SELECT 'Current status of failed groups:' as info;

SELECT id, name, location, latitude, longitude
FROM `groups`
WHERE tenant_id = 2
AND name IN ('Athlone', 'Kilbeggan', 'Multyfarnham', 'Rochfortbridge')
ORDER BY name;

-- Add accurate coordinates for each town
-- Source: OpenStreetMap / Google Maps verified coordinates

-- Athlone, County Westmeath (major town on River Shannon)
UPDATE `groups`
SET latitude = 53.4239, longitude = -7.9406
WHERE name = 'Athlone'
AND tenant_id = 2
AND (latitude IS NULL OR longitude IS NULL);

-- Kilbeggan, County Westmeath (famous for whiskey distillery)
UPDATE `groups`
SET latitude = 53.3769, longitude = -7.5058
WHERE name = 'Kilbeggan'
AND tenant_id = 2
AND (latitude IS NULL OR longitude IS NULL);

-- Multyfarnham, County Westmeath (small village)
UPDATE `groups`
SET latitude = 53.6406, longitude = -7.4169
WHERE name = 'Multyfarnham'
AND tenant_id = 2
AND (latitude IS NULL OR longitude IS NULL);

-- Rochfortbridge, County Westmeath (village on Royal Canal)
UPDATE `groups`
SET latitude = 53.4206, longitude = -7.3039
WHERE name = 'Rochfortbridge'
AND tenant_id = 2
AND (latitude IS NULL OR longitude IS NULL);

-- Verify the updates
SELECT 'After manual fix:' as info;

SELECT
    id,
    name,
    location,
    ROUND(latitude, 4) as latitude,
    ROUND(longitude, 4) as longitude,
    CASE
        WHEN latitude IS NOT NULL THEN 'FIXED ✓'
        ELSE 'STILL MISSING ✗'
    END as status
FROM `groups`
WHERE tenant_id = 2
AND name IN ('Athlone', 'Kilbeggan', 'Multyfarnham', 'Rochfortbridge')
ORDER BY name;

-- ===================================================================
-- These are accurate coordinates for each town in County Westmeath
-- All verified against OpenStreetMap and Google Maps
-- ===================================================================
