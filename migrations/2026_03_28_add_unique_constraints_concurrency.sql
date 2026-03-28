-- Migration: Add unique constraints to prevent duplicate rows from race conditions
-- Date: 2026-03-28
-- Purpose: Fix concurrency bugs where check-then-insert patterns can create duplicates

-- 1. likes table: prevent duplicate likes (no unique constraint existed)
-- First, clean up any existing duplicates (keep the oldest)
DELETE l1 FROM likes l1
INNER JOIN likes l2
ON l1.user_id = l2.user_id
  AND l1.target_type = l2.target_type
  AND l1.target_id = l2.target_id
  AND l1.tenant_id = l2.tenant_id
  AND l1.id > l2.id;

ALTER TABLE likes
ADD UNIQUE KEY `uk_likes_user_target` (`user_id`, `tenant_id`, `target_type`, `target_id`);

-- 2. connections table: prevent duplicate connection requests between same pair
-- First, clean up any existing duplicates (keep the oldest)
DELETE c1 FROM connections c1
INNER JOIN connections c2
ON c1.tenant_id = c2.tenant_id
  AND LEAST(c1.requester_id, c1.receiver_id) = LEAST(c2.requester_id, c2.receiver_id)
  AND GREATEST(c1.requester_id, c1.receiver_id) = GREATEST(c2.requester_id, c2.receiver_id)
  AND c1.id > c2.id;

-- Note: MySQL doesn't support LEAST/GREATEST in unique keys directly.
-- The application-level lock (on user rows) prevents duplicates.
-- We add a standard unique key on (tenant_id, requester_id, receiver_id) which
-- covers the most common case (same direction requests).
ALTER TABLE connections
ADD UNIQUE KEY `uk_connections_pair` (`tenant_id`, `requester_id`, `receiver_id`);
