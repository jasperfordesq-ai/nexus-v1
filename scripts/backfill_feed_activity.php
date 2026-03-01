<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Backfill script: Populate feed_activity from all existing content tables.
 *
 * Uses INSERT IGNORE for idempotency — safe to re-run at any time.
 * Run after the feed_activity migration: php scripts/backfill_feed_activity.php
 */

require_once __DIR__ . '/../httpdocs/bootstrap.php';

use Nexus\Core\Database;

$db = Database::getConnection();

// Verify table exists
try {
    $db->query("SELECT 1 FROM feed_activity LIMIT 1");
} catch (\Exception $e) {
    echo "ERROR: feed_activity table does not exist. Run migrations first.\n";
    exit(1);
}

$totalInserted = 0;

// Helper: run an INSERT IGNORE ... SELECT and report count
function backfillType(PDO $db, string $label, string $sql): int
{
    echo "Backfilling {$label}... ";
    try {
        $count = $db->exec($sql);
        echo "{$count} rows inserted.\n";
        return (int)$count;
    } catch (\Exception $e) {
        echo "SKIPPED ({$e->getMessage()})\n";
        return 0;
    }
}

// 1. Posts (feed_posts)
$totalInserted += backfillType($db, 'posts', "
    INSERT IGNORE INTO feed_activity (tenant_id, user_id, source_type, source_id, group_id, title, content, image_url, metadata, is_visible, created_at)
    SELECT
        p.tenant_id,
        p.user_id,
        'post',
        p.id,
        CASE WHEN EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_NAME = 'feed_posts' AND COLUMN_NAME = 'group_id' AND TABLE_SCHEMA = DATABASE())
             THEN p.group_id ELSE NULL END,
        NULL,
        p.content,
        p.image_url,
        NULL,
        CASE WHEN p.visibility = 'public' THEN 1 ELSE 0 END,
        p.created_at
    FROM feed_posts p
");

// 2. Listings
$totalInserted += backfillType($db, 'listings', "
    INSERT IGNORE INTO feed_activity (tenant_id, user_id, source_type, source_id, group_id, title, content, image_url, metadata, is_visible, created_at)
    SELECT
        l.tenant_id,
        l.user_id,
        'listing',
        l.id,
        NULL,
        l.title,
        l.description,
        l.image_url,
        NULL,
        CASE WHEN l.status = 'active' THEN 1 ELSE 0 END,
        l.created_at
    FROM listings l
");

// 3. Events
$totalInserted += backfillType($db, 'events', "
    INSERT IGNORE INTO feed_activity (tenant_id, user_id, source_type, source_id, group_id, title, content, image_url, metadata, is_visible, created_at)
    SELECT
        e.tenant_id,
        e.user_id,
        'event',
        e.id,
        NULL,
        e.title,
        e.description,
        e.cover_image,
        JSON_OBJECT('start_date', e.start_time, 'location', e.location),
        CASE WHEN e.status = 'cancelled' THEN 0 ELSE 1 END,
        e.created_at
    FROM events e
");

// 4. Polls
$totalInserted += backfillType($db, 'polls', "
    INSERT IGNORE INTO feed_activity (tenant_id, user_id, source_type, source_id, group_id, title, content, image_url, metadata, is_visible, created_at)
    SELECT
        po.tenant_id,
        po.user_id,
        'poll',
        po.id,
        NULL,
        po.question,
        po.question,
        NULL,
        NULL,
        CASE WHEN po.is_active = 1 THEN 1 ELSE 0 END,
        po.created_at
    FROM polls po
");

// 5. Goals
$totalInserted += backfillType($db, 'goals', "
    INSERT IGNORE INTO feed_activity (tenant_id, user_id, source_type, source_id, group_id, title, content, image_url, metadata, is_visible, created_at)
    SELECT
        g.tenant_id,
        g.user_id,
        'goal',
        g.id,
        NULL,
        g.title,
        g.description,
        NULL,
        NULL,
        1,
        g.created_at
    FROM goals g
");

// 6. Reviews
$totalInserted += backfillType($db, 'reviews', "
    INSERT IGNORE INTO feed_activity (tenant_id, user_id, source_type, source_id, group_id, title, content, image_url, metadata, is_visible, created_at)
    SELECT
        r.reviewer_tenant_id,
        r.reviewer_id,
        'review',
        r.id,
        NULL,
        NULL,
        r.comment,
        NULL,
        JSON_OBJECT('rating', r.rating, 'receiver_id', r.receiver_id),
        CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END,
        r.created_at
    FROM reviews r
");

// 7. Job Vacancies
$totalInserted += backfillType($db, 'jobs', "
    INSERT IGNORE INTO feed_activity (tenant_id, user_id, source_type, source_id, group_id, title, content, image_url, metadata, is_visible, created_at)
    SELECT
        j.tenant_id,
        j.user_id,
        'job',
        j.id,
        NULL,
        j.title,
        j.description,
        NULL,
        JSON_OBJECT('location', j.location, 'job_type', j.type, 'commitment', j.commitment),
        CASE WHEN j.status = 'open' THEN 1 ELSE 0 END,
        j.created_at
    FROM job_vacancies j
");

// 8. Ideation Challenges
$totalInserted += backfillType($db, 'challenges', "
    INSERT IGNORE INTO feed_activity (tenant_id, user_id, source_type, source_id, group_id, title, content, image_url, metadata, is_visible, created_at)
    SELECT
        ic.tenant_id,
        ic.user_id,
        'challenge',
        ic.id,
        NULL,
        ic.title,
        ic.description,
        ic.cover_image,
        JSON_OBJECT('submission_deadline', ic.submission_deadline, 'ideas_count', ic.ideas_count),
        CASE WHEN ic.status = 'open' THEN 1 ELSE 0 END,
        ic.created_at
    FROM ideation_challenges ic
");

// 9. Volunteer Opportunities
$totalInserted += backfillType($db, 'volunteer opportunities', "
    INSERT IGNORE INTO feed_activity (tenant_id, user_id, source_type, source_id, group_id, title, content, image_url, metadata, is_visible, created_at)
    SELECT
        vo.tenant_id,
        COALESCE(vo.created_by, org.user_id),
        'volunteer',
        vo.id,
        NULL,
        vo.title,
        vo.description,
        NULL,
        JSON_OBJECT('location', vo.location, 'credits_offered', vo.credits_offered, 'organization', org.name),
        CASE WHEN vo.status = 'open' AND vo.is_active = 1 THEN 1 ELSE 0 END,
        vo.created_at
    FROM vol_opportunities vo
    LEFT JOIN vol_organizations org ON vo.organization_id = org.id
");

echo "\nBackfill complete. Total rows inserted: {$totalInserted}\n";
