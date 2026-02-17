-- ============================================================================
-- TENANT SEO & GEOLOCATION FIELDS MIGRATION
-- ============================================================================
-- Purpose: Add SEO metadata and geolocation fields to tenants table
-- This enables:
--   1. Custom meta titles/descriptions per tenant (stops Google using feed text)
--   2. Custom homepage H1 and hero text per tenant
--   3. Geolocation for local SEO (Schema.org LocalBusiness markup)
--   4. Country/region targeting for search engines
--
-- Run this in phpMyAdmin or MySQL CLI
-- Date: 2026-01-14
-- ============================================================================

-- ============================================================================
-- 1. SEO METADATA FIELDS
-- ============================================================================
-- These override the defaults when set, allowing per-tenant customization

-- Meta Title: Custom browser tab title (max 60 chars recommended)
ALTER TABLE tenants ADD COLUMN IF NOT EXISTS meta_title VARCHAR(70) DEFAULT NULL
    COMMENT 'Custom SEO title for search results (max 60 chars)';

-- Meta Description: Custom search snippet (max 160 chars recommended)
ALTER TABLE tenants ADD COLUMN IF NOT EXISTS meta_description VARCHAR(180) DEFAULT NULL
    COMMENT 'Custom meta description for search results (max 160 chars)';

-- H1 Headline: Main heading on homepage
ALTER TABLE tenants ADD COLUMN IF NOT EXISTS h1_headline VARCHAR(100) DEFAULT NULL
    COMMENT 'Main H1 heading for homepage hero section';

-- Hero Intro: Introductory paragraph below H1
ALTER TABLE tenants ADD COLUMN IF NOT EXISTS hero_intro TEXT DEFAULT NULL
    COMMENT 'Hero section intro text (2-3 sentences)';

-- OG Image: Custom social share image URL
ALTER TABLE tenants ADD COLUMN IF NOT EXISTS og_image_url VARCHAR(500) DEFAULT NULL
    COMMENT 'Open Graph image URL for social sharing';

-- Robots Directive: Control indexing per tenant
ALTER TABLE tenants ADD COLUMN IF NOT EXISTS robots_directive VARCHAR(50) DEFAULT 'index, follow'
    COMMENT 'Robots meta directive (index/noindex, follow/nofollow)';

-- ============================================================================
-- 2. GEOLOCATION FIELDS (Google Maps Integration)
-- ============================================================================
-- These power local SEO and Schema.org Organization/LocalBusiness markup

-- Latitude: Decimal degrees (e.g., 53.3498 for Dublin)
ALTER TABLE tenants ADD COLUMN IF NOT EXISTS latitude DECIMAL(10, 8) DEFAULT NULL
    COMMENT 'Headquarters latitude for local SEO';

-- Longitude: Decimal degrees (e.g., -6.2603 for Dublin)
ALTER TABLE tenants ADD COLUMN IF NOT EXISTS longitude DECIMAL(11, 8) DEFAULT NULL
    COMMENT 'Headquarters longitude for local SEO';

-- Location Name: Human-readable location string
ALTER TABLE tenants ADD COLUMN IF NOT EXISTS location_name VARCHAR(255) DEFAULT NULL
    COMMENT 'Human-readable location (e.g., Dublin, Ireland)';

-- Country Code: ISO 3166-1 alpha-2 (e.g., IE, GB, US)
ALTER TABLE tenants ADD COLUMN IF NOT EXISTS country_code CHAR(2) DEFAULT NULL
    COMMENT 'ISO country code for geo-targeting (e.g., IE, GB, US)';

-- Service Area: Geographic scope of the tenant
ALTER TABLE tenants ADD COLUMN IF NOT EXISTS service_area ENUM('local', 'regional', 'national', 'international') DEFAULT 'national'
    COMMENT 'Geographic scope: local (city), regional (county), national, international';

-- ============================================================================
-- 3. ADDITIONAL SEO CONTROLS
-- ============================================================================

-- Robots Directive: Control indexing per tenant
ALTER TABLE tenants ADD COLUMN IF NOT EXISTS robots_directive VARCHAR(50) DEFAULT 'index, follow'
    COMMENT 'Robots meta directive (index/noindex, follow/nofollow)';

-- ============================================================================
-- 4. SEED DATA FOR EXISTING TENANTS (Optional - Customize as needed)
-- ============================================================================
-- Uncomment and modify these to pre-populate your tenants

-- Project NEXUS (Master Tenant - typically ID 1)
-- UPDATE tenants SET
--     meta_title = 'Project NEXUS - White-Label Community Platform',
--     meta_description = 'Launch your own timebank or skill-sharing network. Project NEXUS provides a turnkey, white-label platform for organizations building community engagement solutions.',
--     h1_headline = 'Build Thriving Communities with NEXUS',
--     hero_intro = 'NEXUS is the all-in-one platform for launching and managing timebanks, mutual aid networks, and community skill exchanges. White-label ready, fully customizable, and designed for scale.',
--     service_area = 'international'
-- WHERE id = 1;

-- Timebank Ireland (typically ID 2)
-- UPDATE tenants SET
--     meta_title = 'hOUR Timebank Ireland - Share Skills, Build Community',
--     meta_description = 'Join Ireland''s nationwide skill-sharing network. Exchange one hour of your time for one hour of help. Connect with neighbors, build community, and make every hour count.',
--     h1_headline = 'Ireland''s Modern Meitheal',
--     hero_intro = 'Share skills, support neighbors, and strengthen communities nationwide. In our network, every hour is equal—and everyone has something valuable to give. Join thousands of Irish members exchanging time, not money.',
--     country_code = 'IE',
--     location_name = 'Dublin, Ireland',
--     latitude = 53.349805,
--     longitude = -6.260310,
--     service_area = 'national'
-- WHERE id = 2 OR slug = 'hour-timebank';

-- ============================================================================
-- 5. VERIFICATION QUERY
-- ============================================================================
-- Run this to verify the columns were added successfully:
-- DESCRIBE tenants;

-- Or check specific columns:
-- SELECT id, name, meta_title, meta_description, country_code, service_area FROM tenants;
