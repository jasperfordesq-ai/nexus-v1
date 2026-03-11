-- Backfill feed_activity with active listings that are missing
-- This is a one-time fix for listings created via OnboardingService, legacy
-- ListingController, or Listing::create() which bypassed FeedActivityService.
-- Uses INSERT IGNORE to skip listings already in feed_activity (idempotent).

INSERT IGNORE INTO feed_activity
    (tenant_id, user_id, source_type, source_id, title, content, image_url, metadata, is_visible, created_at)
SELECT
    l.tenant_id,
    l.user_id,
    'listing' AS source_type,
    l.id AS source_id,
    l.title,
    l.description AS content,
    l.image_url,
    JSON_OBJECT('location', COALESCE(l.location, '')) AS metadata,
    1 AS is_visible,
    l.created_at
FROM listings l
LEFT JOIN feed_activity fa
    ON fa.tenant_id = l.tenant_id
    AND fa.source_type = 'listing'
    AND fa.source_id = l.id
WHERE l.status = 'active'
    AND fa.id IS NULL;
