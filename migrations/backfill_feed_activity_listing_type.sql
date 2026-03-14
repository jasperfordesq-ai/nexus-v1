-- Backfill listing_type into feed_activity.metadata for existing listing records.
-- This enables subtype filtering (offer/request) on the feed API.
-- Safe to run multiple times: only updates rows that don't already have listing_type in metadata.

UPDATE feed_activity fa
JOIN listings l ON fa.source_id = l.id AND fa.tenant_id = l.tenant_id
SET fa.metadata = JSON_SET(COALESCE(fa.metadata, '{}'), '$.listing_type', l.type)
WHERE fa.source_type = 'listing'
  AND (fa.metadata IS NULL OR JSON_EXTRACT(fa.metadata, '$.listing_type') IS NULL);
