-- Backfill default pages for existing tenants that have none.
-- Idempotent: only inserts for tenants with 0 rows in pages table.
-- Skips master tenant (id=1).

INSERT INTO pages (tenant_id, title, slug, content, is_published, show_in_menu, menu_location, sort_order, created_at, updated_at)
SELECT t.id, 'About', 'about',
       '<p>Welcome to our community. This page can be customised from the admin panel.</p>',
       1, 1, 'about', 0, NOW(), NOW()
FROM tenants t
WHERE t.id > 1
  AND NOT EXISTS (SELECT 1 FROM pages p WHERE p.tenant_id = t.id AND p.slug = 'about');

INSERT INTO pages (tenant_id, title, slug, content, is_published, show_in_menu, menu_location, sort_order, created_at, updated_at)
SELECT t.id, 'Privacy Policy', 'privacy',
       '<p>Your privacy is important to us. Please update this page with your community''s privacy policy.</p>',
       1, 1, 'footer', 1, NOW(), NOW()
FROM tenants t
WHERE t.id > 1
  AND NOT EXISTS (SELECT 1 FROM pages p WHERE p.tenant_id = t.id AND p.slug = 'privacy');

INSERT INTO pages (tenant_id, title, slug, content, is_published, show_in_menu, menu_location, sort_order, created_at, updated_at)
SELECT t.id, 'Terms of Service', 'terms',
       '<p>Please update this page with your community''s terms of service.</p>',
       1, 1, 'footer', 2, NOW(), NOW()
FROM tenants t
WHERE t.id > 1
  AND NOT EXISTS (SELECT 1 FROM pages p WHERE p.tenant_id = t.id AND p.slug = 'terms');
