-- Tenant Setup Hardening
-- Adds unique constraints to prevent duplicate slugs/domains on tenants table
-- and ensures idempotent seeding for attributes and categories.

-- Ensure tenants.slug is unique (prevents race condition in createTenant)
-- Using IF NOT EXISTS pattern for idempotent migration
SET @exists_slug = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tenants'
    AND INDEX_NAME = 'uq_tenants_slug'
);

SET @sql_slug = IF(@exists_slug = 0,
    'ALTER TABLE tenants ADD UNIQUE INDEX uq_tenants_slug (slug)',
    'SELECT 1'
);
PREPARE stmt FROM @sql_slug;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ensure tenants.domain is unique (when not null)
SET @exists_domain = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tenants'
    AND INDEX_NAME = 'uq_tenants_domain'
);

SET @sql_domain = IF(@exists_domain = 0,
    'ALTER TABLE tenants ADD UNIQUE INDEX uq_tenants_domain (domain)',
    'SELECT 1'
);
PREPARE stmt FROM @sql_domain;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ensure attributes has unique (tenant_id, name, target_type) for idempotent seeding
SET @exists_attr = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'attributes'
    AND INDEX_NAME = 'uq_attributes_tenant_name_type'
);

SET @sql_attr = IF(@exists_attr = 0,
    'ALTER TABLE attributes ADD UNIQUE INDEX uq_attributes_tenant_name_type (tenant_id, name, target_type)',
    'SELECT 1'
);
PREPARE stmt FROM @sql_attr;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
