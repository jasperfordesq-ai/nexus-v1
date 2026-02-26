-- Fix user_interests table: add tenant_id for multi-tenant isolation
-- Also adds FK constraints to prevent orphaned data

-- Step 1: Add tenant_id column (nullable initially so existing rows don't fail)
ALTER TABLE user_interests
    ADD COLUMN IF NOT EXISTS tenant_id INT NULL AFTER user_id;

-- Step 2: Backfill tenant_id from the users table
UPDATE user_interests ui
    INNER JOIN users u ON u.id = ui.user_id
    SET ui.tenant_id = u.tenant_id
    WHERE ui.tenant_id IS NULL;

-- Step 3: Make tenant_id NOT NULL now that all rows have a value
ALTER TABLE user_interests
    MODIFY COLUMN tenant_id INT NOT NULL;

-- Step 4: Add index for tenant-scoped queries
ALTER TABLE user_interests
    ADD INDEX IF NOT EXISTS idx_tenant_id (tenant_id);

-- Step 5: Update the unique constraint to include tenant_id
--         (a user in tenant A and user B could theoretically share same user_id across tenants,
--          so the unique key must include tenant_id)
ALTER TABLE user_interests DROP INDEX IF EXISTS uk_user_category_type;
ALTER TABLE user_interests
    ADD UNIQUE KEY IF NOT EXISTS uk_tenant_user_category_type (tenant_id, user_id, category_id, interest_type);

-- Step 6: FK constraint — user must exist
-- Note: MariaDB 10.11 does not support ADD CONSTRAINT IF NOT EXISTS;
--       these are safe to run once — they will error if already present (idempotent via re-run awareness).
ALTER TABLE user_interests
    ADD CONSTRAINT fk_ui_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

-- Step 7: FK constraint — category must exist
ALTER TABLE user_interests
    ADD CONSTRAINT fk_ui_category
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE;
