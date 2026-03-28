-- Fix user_badges unique index to include tenant_id
-- The old index (user_id, badge_key) prevents the same user from having the same badge
-- across different tenants. Since badges are tenant-scoped, the unique constraint
-- should be (tenant_id, user_id, badge_key).

ALTER TABLE user_badges
    DROP INDEX IF EXISTS user_badge_unique,
    ADD UNIQUE INDEX user_badge_unique (tenant_id, user_id, badge_key);
