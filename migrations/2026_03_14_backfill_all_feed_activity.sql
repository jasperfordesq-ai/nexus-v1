-- Backfill ALL missing content into feed_activity table
-- Safe to run multiple times (uses INSERT IGNORE with unique key on tenant_id, source_type, source_id)
-- Created: 2026-03-14

-- 1. Listings (active only)
INSERT IGNORE INTO feed_activity (tenant_id, user_id, source_type, source_id, title, content, image_url, metadata, is_visible, created_at)
SELECT
    l.tenant_id,
    l.user_id,
    'listing',
    l.id,
    l.title,
    l.description,
    l.image_url,
    NULL,
    1,
    l.created_at
FROM listings l
WHERE l.status = 'active';

-- 2. Feed posts (public only)
INSERT IGNORE INTO feed_activity (tenant_id, user_id, source_type, source_id, group_id, title, content, image_url, metadata, is_visible, created_at)
SELECT
    fp.tenant_id,
    fp.user_id,
    'post',
    fp.id,
    fp.group_id,
    NULL,
    fp.content,
    fp.image_url,
    NULL,
    1,
    fp.created_at
FROM feed_posts fp
WHERE fp.visibility = 'public';

-- 3. Events
INSERT IGNORE INTO feed_activity (tenant_id, user_id, source_type, source_id, group_id, title, content, image_url, metadata, is_visible, created_at)
SELECT
    e.tenant_id,
    e.user_id,
    'event',
    e.id,
    e.group_id,
    e.title,
    e.description,
    e.cover_image,
    JSON_OBJECT('start_date', e.start_time, 'location', e.location),
    1,
    e.created_at
FROM events e
WHERE e.status IN ('active', 'completed');

-- 4. Polls
INSERT IGNORE INTO feed_activity (tenant_id, user_id, source_type, source_id, title, content, metadata, is_visible, created_at)
SELECT
    p.tenant_id,
    p.user_id,
    'poll',
    p.id,
    p.question,
    p.question,
    NULL,
    1,
    p.created_at
FROM polls p;

-- 5. Goals
INSERT IGNORE INTO feed_activity (tenant_id, user_id, source_type, source_id, title, content, metadata, is_visible, created_at)
SELECT
    g.tenant_id,
    g.user_id,
    'goal',
    g.id,
    g.title,
    g.description,
    NULL,
    1,
    g.created_at
FROM goals g;

-- 6. Reviews (approved or pending only)
INSERT IGNORE INTO feed_activity (tenant_id, user_id, source_type, source_id, title, content, metadata, is_visible, created_at)
SELECT
    r.tenant_id,
    r.reviewer_id,
    'review',
    r.id,
    NULL,
    r.comment,
    JSON_OBJECT('rating', r.rating, 'receiver_id', r.receiver_id),
    1,
    r.created_at
FROM reviews r
WHERE r.status IN ('approved', 'pending');

-- 7. Job vacancies
INSERT IGNORE INTO feed_activity (tenant_id, user_id, source_type, source_id, title, content, metadata, is_visible, created_at)
SELECT
    jv.tenant_id,
    jv.user_id,
    'job',
    jv.id,
    jv.title,
    jv.description,
    JSON_OBJECT('job_type', jv.type, 'commitment', jv.commitment),
    1,
    jv.created_at
FROM job_vacancies jv;

-- 8. Ideation challenges
INSERT IGNORE INTO feed_activity (tenant_id, user_id, source_type, source_id, title, content, image_url, metadata, is_visible, created_at)
SELECT
    ic.tenant_id,
    ic.user_id,
    'challenge',
    ic.id,
    ic.title,
    ic.description,
    ic.cover_image,
    JSON_OBJECT('submission_deadline', ic.submission_deadline),
    1,
    ic.created_at
FROM ideation_challenges ic;
