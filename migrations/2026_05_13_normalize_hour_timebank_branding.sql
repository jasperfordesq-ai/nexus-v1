-- ============================================================================
-- NORMALIZE hour-timebank TENANT BRANDING
-- ============================================================================
-- Date: 2026-05-13
-- Purpose: Make the public-facing brand consistent.
--
-- ChatGPT's 2026-05-13 audit found the homepage showing "TI Timebank Ireland"
-- (avatar initials TI), the page title showing "hOUR Timebank Ireland",
-- the blog avatar showing "HT" (hOUR Timebank initials), and the About page
-- using "Timebank Ireland". Same tenant, three different brand strings.
--
-- The convention going forward:
--   - Public brand:  "Timebank Ireland"  (tenants.name, meta_title suffix,
--                                         and TenantLogo initials → "TI")
--   - Legal entity:  "hOUR Timebank CLG" (footer credit only)
--   - Software:      "Project NEXUS"     (unchanged, footer attribution)
--
-- Historical references to "hOUR Timebank" in the SROI study and impact
-- pages are factually correct (the 2023 study was commissioned by the
-- hOUR Timebank programme) and are deliberately not changed.
-- ============================================================================

-- ── 1. Canonical tenant name ────────────────────────────────────────────
UPDATE tenants
SET name = 'Timebank Ireland'
WHERE slug = 'hour-timebank';

-- ── 2. SEO meta title (browser tab, search results) ─────────────────────
-- Trimmed to under 60 chars; suffix only — PageMeta builds "{page} | {suffix}".
UPDATE tenants
SET meta_title = 'Timebank Ireland'
WHERE slug = 'hour-timebank';

-- ── 3. tenant_settings SEO overrides ────────────────────────────────────
-- The PageMeta component prefers tenant_settings.seo_title_suffix when set,
-- so an old "hOUR Timebank Ireland" value here is the most likely source
-- of the page-title regression ChatGPT spotted.
INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, setting_type, updated_at)
SELECT t.id, 'seo_title_suffix', 'Timebank Ireland', 'string', NOW()
FROM tenants t WHERE t.slug = 'hour-timebank'
ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value),
    updated_at = NOW();

-- ── 4. Footer text — legal entity credit + AGPL software credit ─────────
-- footer_text is read by Footer.tsx and replaces the default "© {year} {name}".
UPDATE tenants
SET config = JSON_SET(
    COALESCE(config, JSON_OBJECT()),
    '$.footer_text',
    CONCAT('© ', YEAR(NOW()), ' Timebank Ireland · operated by hOUR Timebank CLG · RCN 20162023 · CRO 608327')
)
WHERE slug = 'hour-timebank';

-- ── 5. Sanity check ─────────────────────────────────────────────────────
-- After running, verify with:
--   SELECT slug, name, meta_title, JSON_EXTRACT(config, '$.footer_text') FROM tenants WHERE slug = 'hour-timebank';
--   SELECT setting_key, setting_value FROM tenant_settings
--     WHERE tenant_id = (SELECT id FROM tenants WHERE slug = 'hour-timebank')
--     AND setting_key IN ('seo_title_suffix', 'seo_meta_description');
