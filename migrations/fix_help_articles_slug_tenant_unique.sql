-- Fix multi-tenant data isolation bug on help_articles.slug
--
-- The original schema declared `slug` as globally UNIQUE, which prevents two
-- tenants from having an article with the same slug (e.g. "getting-started").
-- The unique constraint should be scoped to (tenant_id, slug) instead.
--
-- Surfaced by Sentry NEXUS-PHP-API-28 (2026-05-08): a partner-demo seeder
-- failed inserting `understanding-time-credits` for tenant 5 because tenant 1
-- already owned that slug.

ALTER TABLE `help_articles` DROP INDEX `slug`;
ALTER TABLE `help_articles` ADD UNIQUE KEY `uniq_tenant_slug` (`tenant_id`, `slug`);
