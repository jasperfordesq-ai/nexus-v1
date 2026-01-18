-- Migration: Fix NULL Member Names
-- This SQL script identifies and reports users with NULL or empty names
-- Run this first to see affected users, then uncomment the UPDATE statements to fix them

-- ==============================================================================
-- STEP 1: IDENTIFY AFFECTED USERS
-- ==============================================================================

SELECT
    id,
    tenant_id,
    first_name,
    last_name,
    email,
    organization_name,
    profile_type,
    created_at,
    is_approved,
    CASE
        WHEN first_name IS NULL OR first_name = '' THEN 'Missing first_name'
        WHEN last_name IS NULL OR last_name = '' THEN 'Missing last_name'
        ELSE 'Unknown issue'
    END as issue
FROM users
WHERE
    (first_name IS NULL OR first_name = '' OR TRIM(first_name) = '')
    OR
    (last_name IS NULL OR last_name = '' OR TRIM(last_name) = '')
ORDER BY created_at DESC;

-- ==============================================================================
-- STEP 2: FIX EMPTY/NULL NAMES (Uncomment to run)
-- ==============================================================================

-- Strategy 1: Set default values for NULL first names
-- UPDATE users
-- SET first_name = 'Member'
-- WHERE first_name IS NULL OR first_name = '' OR TRIM(first_name) = '';

-- Strategy 2: Set default values for NULL last names
-- UPDATE users
-- SET last_name = CONCAT('User-', SUBSTRING(id, 1, 4))
-- WHERE last_name IS NULL OR last_name = '' OR TRIM(last_name) = '';

-- ==============================================================================
-- STEP 3: VERIFY THE FIX (Run after uncommenting and executing STEP 2)
-- ==============================================================================

-- This should return 0 rows if all names are fixed
-- SELECT
--     id,
--     first_name,
--     last_name,
--     email,
--     CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as display_name
-- FROM users
-- WHERE
--     (first_name IS NULL OR first_name = '' OR TRIM(first_name) = '')
--     OR
--     (last_name IS NULL OR last_name = '' OR TRIM(last_name) = '');

-- ==============================================================================
-- STEP 4: TEST THE NAME DISPLAY (Optional)
-- ==============================================================================

-- Test how names will appear on the /members page
-- SELECT
--     id,
--     email,
--     first_name,
--     last_name,
--     organization_name,
--     profile_type,
--     CASE
--         WHEN profile_type = 'organisation' AND organization_name IS NOT NULL AND organization_name != ''
--         THEN organization_name
--         ELSE CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))
--     END as display_name
-- FROM users
-- WHERE tenant_id = 1  -- Change to your tenant_id
-- ORDER BY created_at DESC
-- LIMIT 20;
