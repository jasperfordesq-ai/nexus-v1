-- Move user sue.babey@yahoo.co.uk (id=286) from hour-timebank (tenant 2) to crewkerne-timebank (tenant 6)
-- Includes all user data: listings, notifications, badges, consents, legal acceptances, activity log

-- Pre-check: verify user exists on tenant 2
SELECT id, name, email, tenant_id, balance FROM users WHERE id = 286 AND tenant_id = 2;

-- Wrap in transaction for atomicity
START TRANSACTION;

-- 1. Move the user record
UPDATE users SET tenant_id = 6 WHERE id = 286 AND tenant_id = 2;

-- 2. Move listing(s)
UPDATE listings SET tenant_id = 6 WHERE user_id = 286 AND tenant_id = 2;

-- 3. Move user_consents (has tenant_id)
UPDATE user_consents SET tenant_id = 6 WHERE user_id = 286 AND tenant_id = 2;

-- 4. Tables without tenant_id (tied to user_id only, no change needed):
--    notifications, user_badges, user_legal_acceptances, activity_log

-- 5. Invalidate sessions so user must re-login on new tenant
DELETE FROM sessions WHERE user_id = 286;

-- 6. Clear revoked tokens
DELETE FROM revoked_tokens WHERE user_id = 286;

COMMIT;

-- Post-check: verify user is now on tenant 6
SELECT id, name, email, tenant_id, balance FROM users WHERE id = 286;
SELECT id, tenant_id, title FROM listings WHERE user_id = 286;
