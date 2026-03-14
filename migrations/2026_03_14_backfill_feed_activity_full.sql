-- Full feed_activity backfill — re-syncs all 9 content types.
-- Uses INSERT IGNORE for idempotency — safe to re-run at any time.

-- 1. Listings
INSERT IGNORE INTO feed_activity (tenant_id, user_id, source_type, source_id, group_id, title, content, image_url, metadata, is_visible, created_at)
SELECT l.tenant_id, l.user_id, 'listing', l.id, NULL, l.title, l.description, l.image_url,
    JSON_OBJECT('location', COALESCE(l.location, ''), 'listing_type', COALESCE(l.type, 'offer')),
    CASE WHEN l.status = 'active' THEN 1 ELSE 0 END, l.created_at
FROM listings l WHERE l.tenant_id IS NOT NULL AND l.user_id IS NOT NULL;

-- 2. Posts
INSERT IGNORE INTO feed_activity (tenant_id, user_id, source_type, source_id, group_id, title, content, image_url, metadata, is_visible, created_at)
SELECT p.tenant_id, p.user_id, 'post', p.id, p.group_id, NULL, p.content, p.image_url, NULL,
    CASE WHEN p.visibility = 'public' THEN 1 ELSE 0 END, p.created_at
FROM feed_posts p WHERE p.tenant_id IS NOT NULL AND p.user_id IS NOT NULL;

-- 3. Events
INSERT IGNORE INTO feed_activity (tenant_id, user_id, source_type, source_id, group_id, title, content, image_url, metadata, is_visible, created_at)
SELECT e.tenant_id, e.user_id, 'event', e.id, NULL, e.title, e.description, e.cover_image,
    JSON_OBJECT('start_date', COALESCE(e.start_time, ''), 'location', COALESCE(e.location, '')),
    CASE WHEN e.status = 'cancelled' THEN 0 ELSE 1 END, e.created_at
FROM events e WHERE e.tenant_id IS NOT NULL AND e.user_id IS NOT NULL;

-- 4. Polls
INSERT IGNORE INTO feed_activity (tenant_id, user_id, source_type, source_id, group_id, title, content, image_url, metadata, is_visible, created_at)
SELECT po.tenant_id, po.user_id, 'poll', po.id, NULL, po.question, po.question, NULL, NULL,
    CASE WHEN po.is_active = 1 THEN 1 ELSE 0 END, po.created_at
FROM polls po WHERE po.tenant_id IS NOT NULL AND po.user_id IS NOT NULL;

-- 5. Goals
INSERT IGNORE INTO feed_activity (tenant_id, user_id, source_type, source_id, group_id, title, content, image_url, metadata, is_visible, created_at)
SELECT g.tenant_id, g.user_id, 'goal', g.id, NULL, g.title, g.description, NULL, NULL, 1, g.created_at
FROM goals g WHERE g.tenant_id IS NOT NULL AND g.user_id IS NOT NULL;

-- 6. Reviews
INSERT IGNORE INTO feed_activity (tenant_id, user_id, source_type, source_id, group_id, title, content, image_url, metadata, is_visible, created_at)
SELECT r.reviewer_tenant_id, r.reviewer_id, 'review', r.id, NULL, NULL, r.comment, NULL,
    JSON_OBJECT('rating', r.rating, 'receiver_id', r.receiver_id),
    CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END, r.created_at
FROM reviews r WHERE r.reviewer_tenant_id IS NOT NULL AND r.reviewer_id IS NOT NULL;

-- 7. Job Vacancies
INSERT IGNORE INTO feed_activity (tenant_id, user_id, source_type, source_id, group_id, title, content, image_url, metadata, is_visible, created_at)
SELECT j.tenant_id, j.user_id, 'job', j.id, NULL, j.title, j.description, NULL,
    JSON_OBJECT('location', COALESCE(j.location, ''), 'job_type', COALESCE(j.type, ''), 'commitment', COALESCE(j.commitment, '')),
    CASE WHEN j.status = 'open' THEN 1 ELSE 0 END, j.created_at
FROM job_vacancies j WHERE j.tenant_id IS NOT NULL AND j.user_id IS NOT NULL;

-- 8. Ideation Challenges
INSERT IGNORE INTO feed_activity (tenant_id, user_id, source_type, source_id, group_id, title, content, image_url, metadata, is_visible, created_at)
SELECT ic.tenant_id, ic.user_id, 'challenge', ic.id, NULL, ic.title, ic.description, ic.cover_image,
    JSON_OBJECT('submission_deadline', COALESCE(ic.submission_deadline, ''), 'ideas_count', COALESCE(ic.ideas_count, 0)),
    CASE WHEN ic.status = 'open' THEN 1 ELSE 0 END, ic.created_at
FROM ideation_challenges ic WHERE ic.tenant_id IS NOT NULL AND ic.user_id IS NOT NULL;

-- 9. Volunteer Opportunities
INSERT IGNORE INTO feed_activity (tenant_id, user_id, source_type, source_id, group_id, title, content, image_url, metadata, is_visible, created_at)
SELECT vo.tenant_id, COALESCE(vo.created_by, org.user_id, 1), 'volunteer', vo.id, NULL,
    vo.title, vo.description, NULL,
    JSON_OBJECT('location', COALESCE(vo.location, ''), 'credits_offered', COALESCE(vo.credits_offered, 0), 'organization', COALESCE(org.name, '')),
    CASE WHEN vo.status = 'open' AND vo.is_active = 1 THEN 1 ELSE 0 END, vo.created_at
FROM vol_opportunities vo LEFT JOIN vol_organizations org ON vo.organization_id = org.id
WHERE vo.tenant_id IS NOT NULL;
