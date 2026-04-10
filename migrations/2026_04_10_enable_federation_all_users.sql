-- Enable federation for ALL users with all options ON
-- Users can individually disable options via Federation Settings afterwards
-- Run date: 2026-04-10

-- Step 1: Update existing rows — turn everything on
UPDATE federation_user_settings
SET federation_optin = 1,
    profile_visible_federated = 1,
    messaging_enabled_federated = 1,
    transactions_enabled_federated = 1,
    appear_in_federated_search = 1,
    show_skills_federated = 1,
    show_location_federated = 1,
    show_reviews_federated = 1,
    email_notifications = 1,
    opted_in_at = COALESCE(opted_in_at, NOW()),
    updated_at = NOW();

-- Step 2: Insert rows for users who don't have federation settings yet
INSERT INTO federation_user_settings
    (user_id, federation_optin, profile_visible_federated, messaging_enabled_federated,
     transactions_enabled_federated, appear_in_federated_search, show_skills_federated,
     show_location_federated, show_reviews_federated, email_notifications,
     service_reach, opted_in_at, created_at)
SELECT u.id, 1, 1, 1, 1, 1, 1, 1, 1, 1, 'local_only', NOW(), NOW()
FROM users u
LEFT JOIN federation_user_settings fus ON fus.user_id = u.id
WHERE fus.user_id IS NULL;
