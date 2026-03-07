-- 1. Enable federation for all existing users who have a settings row but opted out
UPDATE federation_user_settings SET
    federation_optin = 1,
    profile_visible_federated = 1,
    messaging_enabled_federated = 1,
    transactions_enabled_federated = 1,
    appear_in_federated_search = 1,
    show_skills_federated = 1,
    updated_at = NOW()
WHERE federation_optin = 0;

-- 2. Create federation settings rows for all active users who don't have one yet
--    (so INNER JOIN queries in federation search/listings still find them)
INSERT INTO federation_user_settings (
    user_id, federation_optin, profile_visible_federated,
    messaging_enabled_federated, transactions_enabled_federated,
    appear_in_federated_search, show_skills_federated,
    show_location_federated, service_reach, travel_radius_km,
    opted_in_at, created_at
)
SELECT
    u.id, 1, 1, 1, 1, 1, 1, 0, 'local_only', NULL, NOW(), NOW()
FROM users u
LEFT JOIN federation_user_settings fus ON u.id = fus.user_id
WHERE fus.user_id IS NULL
AND u.status = 'active';
