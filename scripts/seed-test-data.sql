-- ============================================================
-- Project NEXUS - Seed Test Data for Admin Panel Testing
-- Target: tenant_id=2
-- Safe to re-run (uses INSERT IGNORE / slug/title checks)
-- ============================================================

SET @tenant_id = 2;
SET @admin_id = 650;
SET @alice = 283;
SET @bob = 284;
SET @carol = 285;
SET @dave = 286;
SET @emma = 287;

-- ──────────────────────────────────────────────
-- 1. BLOG POSTS (5 posts: 3 published, 2 draft)
-- ──────────────────────────────────────────────

INSERT IGNORE INTO posts (tenant_id, author_id, title, slug, excerpt, content, status, category_id, created_at)
SELECT @tenant_id, @admin_id,
  'Seed: How Timebanking Strengthened Our Community',
  'seed-timebanking-strengthened-community',
  'Discover how members are using time credits to build stronger neighbourhood bonds.',
  '<p>Timebanking has transformed how our community members interact. From garden help to tech tutoring, the exchange of skills has created deeper connections between neighbours who might otherwise never have met.</p><p>In this post, we explore three success stories from our members.</p>',
  'published',
  (SELECT id FROM categories WHERE tenant_id = @tenant_id AND type = 'blog' LIMIT 1),
  NOW() - INTERVAL 10 DAY
FROM dual WHERE NOT EXISTS (SELECT 1 FROM posts WHERE tenant_id = @tenant_id AND slug = 'seed-timebanking-strengthened-community');

INSERT IGNORE INTO posts (tenant_id, author_id, title, slug, excerpt, content, status, category_id, created_at)
SELECT @tenant_id, @alice,
  'Seed: Monthly Roundup - January 2026',
  'seed-monthly-roundup-january-2026',
  'A look back at our busiest month yet with 50+ exchanges completed.',
  '<p>January was a record-breaking month for our timebank. Over 50 exchanges were completed, 12 new members joined, and we launched our new events calendar.</p>',
  'published',
  (SELECT id FROM categories WHERE tenant_id = @tenant_id AND type = 'blog' LIMIT 1),
  NOW() - INTERVAL 5 DAY
FROM dual WHERE NOT EXISTS (SELECT 1 FROM posts WHERE tenant_id = @tenant_id AND slug = 'seed-monthly-roundup-january-2026');

INSERT IGNORE INTO posts (tenant_id, author_id, title, slug, excerpt, content, status, category_id, created_at)
SELECT @tenant_id, @carol,
  'Seed: Tips for New Timebank Members',
  'seed-tips-new-timebank-members',
  'Getting started with timebanking? Here are our top tips.',
  '<p>Welcome to the timebank! Here are five tips to help you get the most out of your membership:</p><ol><li>Complete your profile</li><li>Browse listings regularly</li><li>Start with a small exchange</li><li>Leave reviews for your exchanges</li><li>Attend community events</li></ol>',
  'published',
  (SELECT id FROM categories WHERE tenant_id = @tenant_id AND type = 'blog' LIMIT 1),
  NOW() - INTERVAL 3 DAY
FROM dual WHERE NOT EXISTS (SELECT 1 FROM posts WHERE tenant_id = @tenant_id AND slug = 'seed-tips-new-timebank-members');

INSERT IGNORE INTO posts (tenant_id, author_id, title, slug, excerpt, content, status, category_id, created_at)
SELECT @tenant_id, @admin_id,
  'Seed: Upcoming Spring Events [DRAFT]',
  'seed-upcoming-spring-events-draft',
  'Preview of spring community events - draft for review.',
  '<p>Draft content for spring events announcement. Needs dates confirmed.</p>',
  'draft',
  (SELECT id FROM categories WHERE tenant_id = @tenant_id AND type = 'blog' LIMIT 1),
  NOW() - INTERVAL 1 DAY
FROM dual WHERE NOT EXISTS (SELECT 1 FROM posts WHERE tenant_id = @tenant_id AND slug = 'seed-upcoming-spring-events-draft');

INSERT IGNORE INTO posts (tenant_id, author_id, title, slug, excerpt, content, status, category_id, created_at)
SELECT @tenant_id, @admin_id,
  'Seed: Old Announcement - Platform Migration',
  'seed-old-announcement-platform-migration',
  'We have successfully migrated to the new platform.',
  '<p>This post announced our platform migration which is now complete.</p>',
  'draft',
  (SELECT id FROM categories WHERE tenant_id = @tenant_id AND type = 'blog' LIMIT 1),
  NOW() - INTERVAL 30 DAY
FROM dual WHERE NOT EXISTS (SELECT 1 FROM posts WHERE tenant_id = @tenant_id AND slug = 'seed-old-announcement-platform-migration');

-- ──────────────────────────────────────────────
-- 2. LISTINGS (5 listings: 3 active, 1 pending, 1 expired)
-- ──────────────────────────────────────────────

INSERT INTO listings (tenant_id, user_id, category_id, title, description, type, status, location, price, created_at)
SELECT @tenant_id, @alice, 56,
  'Seed: Dog Walking & Pet Sitting',
  'Happy to walk dogs or look after pets while you are away. Experienced with all breeds. Available weekdays.',
  'offer', 'active', 'Crewkerne, Somerset', 1.00, NOW() - INTERVAL 15 DAY
FROM dual WHERE NOT EXISTS (SELECT 1 FROM listings WHERE tenant_id = @tenant_id AND title = 'Seed: Dog Walking & Pet Sitting');

INSERT INTO listings (tenant_id, user_id, category_id, title, description, type, status, location, price, created_at)
SELECT @tenant_id, @bob, 60,
  'Seed: Help Moving House',
  'Need help moving furniture and boxes to a new house. Two trips expected. Would appreciate strong helpers!',
  'request', 'active', 'Yeovil, Somerset', 3.00, NOW() - INTERVAL 12 DAY
FROM dual WHERE NOT EXISTS (SELECT 1 FROM listings WHERE tenant_id = @tenant_id AND title = 'Seed: Help Moving House');

INSERT INTO listings (tenant_id, user_id, category_id, title, description, type, status, location, price, created_at)
SELECT @tenant_id, @emma, 101,
  'Seed: Guitar Lessons for Beginners',
  'Offering beginner guitar lessons. I can teach acoustic or electric. All ages welcome.',
  'offer', 'active', 'Crewkerne, Somerset', 1.50, NOW() - INTERVAL 8 DAY
FROM dual WHERE NOT EXISTS (SELECT 1 FROM listings WHERE tenant_id = @tenant_id AND title = 'Seed: Guitar Lessons for Beginners');

INSERT INTO listings (tenant_id, user_id, category_id, title, description, type, status, location, price, created_at)
SELECT @tenant_id, @carol, 92,
  'Seed: Sewing & Alterations [PENDING]',
  'Can do basic sewing repairs and clothing alterations. Pending approval.',
  'offer', 'pending', 'Crewkerne, Somerset', 1.00, NOW() - INTERVAL 2 DAY
FROM dual WHERE NOT EXISTS (SELECT 1 FROM listings WHERE tenant_id = @tenant_id AND title = 'Seed: Sewing & Alterations [PENDING]');

INSERT INTO listings (tenant_id, user_id, category_id, title, description, type, status, location, price, created_at)
SELECT @tenant_id, @dave, 97,
  'Seed: Christmas Decorating Help [EXPIRED]',
  'Needed help putting up Christmas decorations. This listing has expired.',
  'request', 'expired', 'Chard, Somerset', 2.00, NOW() - INTERVAL 60 DAY
FROM dual WHERE NOT EXISTS (SELECT 1 FROM listings WHERE tenant_id = @tenant_id AND title = 'Seed: Christmas Decorating Help [EXPIRED]');

-- ──────────────────────────────────────────────
-- 3. EXCHANGE REQUESTS (3 with different statuses)
--    Uses first 3 active listings from tenant 2
-- ──────────────────────────────────────────────

-- pending_broker exchange
INSERT INTO exchange_requests (tenant_id, listing_id, requester_id, provider_id, proposed_hours, status, broker_id, broker_notes, created_at)
SELECT @tenant_id,
  l.id, @bob, l.user_id, 2.00, 'pending_broker', @admin_id,
  'Awaiting review - first exchange for this member.',
  NOW() - INTERVAL 2 DAY
FROM (SELECT id, user_id FROM listings WHERE tenant_id = @tenant_id AND status = 'active' ORDER BY id LIMIT 1) l
WHERE NOT EXISTS (
  SELECT 1 FROM exchange_requests WHERE tenant_id = @tenant_id AND requester_id = @bob AND status = 'pending_broker'
  AND listing_id = (SELECT id FROM listings WHERE tenant_id = @tenant_id AND status = 'active' ORDER BY id LIMIT 1)
);

-- accepted exchange
INSERT INTO exchange_requests (tenant_id, listing_id, requester_id, provider_id, proposed_hours, status, broker_id, broker_notes, created_at)
SELECT @tenant_id,
  l.id, @carol, l.user_id, 1.50, 'accepted', @admin_id,
  'Approved - both members have good history.',
  NOW() - INTERVAL 5 DAY
FROM (SELECT id, user_id FROM listings WHERE tenant_id = @tenant_id AND status = 'active' ORDER BY id LIMIT 1 OFFSET 1) l
WHERE NOT EXISTS (
  SELECT 1 FROM exchange_requests WHERE tenant_id = @tenant_id AND requester_id = @carol AND status = 'accepted'
  AND listing_id = (SELECT id FROM listings WHERE tenant_id = @tenant_id AND status = 'active' ORDER BY id LIMIT 1 OFFSET 1)
);

-- cancelled exchange
INSERT INTO exchange_requests (tenant_id, listing_id, requester_id, provider_id, proposed_hours, status, broker_id, broker_notes, created_at)
SELECT @tenant_id,
  l.id, @dave, l.user_id, 3.00, 'cancelled', @admin_id,
  'Cancelled by requester - scheduling conflict.',
  NOW() - INTERVAL 7 DAY
FROM (SELECT id, user_id FROM listings WHERE tenant_id = @tenant_id AND status = 'active' ORDER BY id LIMIT 1 OFFSET 2) l
WHERE NOT EXISTS (
  SELECT 1 FROM exchange_requests WHERE tenant_id = @tenant_id AND requester_id = @dave AND status = 'cancelled'
  AND listing_id = (SELECT id FROM listings WHERE tenant_id = @tenant_id AND status = 'active' ORDER BY id LIMIT 1 OFFSET 2)
);

-- ──────────────────────────────────────────────
-- 4. BROKER MESSAGE COPIES (10 rows)
-- ──────────────────────────────────────────────

INSERT INTO broker_message_copies (tenant_id, original_message_id, conversation_key, sender_id, receiver_id, message_body, sent_at, copy_reason)
SELECT @tenant_id, 9000, 'seed_conv_283_284', @alice, @bob,
  'Hi Bob, I can help with your garden this Saturday.', NOW() - INTERVAL 10 DAY, 'first_contact'
FROM dual WHERE NOT EXISTS (SELECT 1 FROM broker_message_copies WHERE tenant_id = @tenant_id AND conversation_key = 'seed_conv_283_284' AND sender_id = @alice);

INSERT INTO broker_message_copies (tenant_id, original_message_id, conversation_key, sender_id, receiver_id, message_body, sent_at, copy_reason)
SELECT @tenant_id, 9001, 'seed_conv_283_284', @bob, @alice,
  'That would be great Alice! What time works?', NOW() - INTERVAL 9 DAY, 'first_contact'
FROM dual WHERE NOT EXISTS (SELECT 1 FROM broker_message_copies WHERE tenant_id = @tenant_id AND conversation_key = 'seed_conv_283_284' AND sender_id = @bob);

INSERT INTO broker_message_copies (tenant_id, original_message_id, conversation_key, sender_id, receiver_id, message_body, sent_at, copy_reason)
SELECT @tenant_id, 9002, 'seed_conv_285_286', @carol, @dave,
  'Dave, I saw your listing for home repairs. Can you fix a leaky tap?', NOW() - INTERVAL 8 DAY, 'high_risk_listing'
FROM dual WHERE NOT EXISTS (SELECT 1 FROM broker_message_copies WHERE tenant_id = @tenant_id AND conversation_key = 'seed_conv_285_286' AND sender_id = @carol);

INSERT INTO broker_message_copies (tenant_id, original_message_id, conversation_key, sender_id, receiver_id, message_body, sent_at, copy_reason)
SELECT @tenant_id, 9003, 'seed_conv_285_286', @dave, @carol,
  'Yes Carol, I can take a look. Is Tuesday afternoon OK?', NOW() - INTERVAL 7 DAY, 'high_risk_listing'
FROM dual WHERE NOT EXISTS (SELECT 1 FROM broker_message_copies WHERE tenant_id = @tenant_id AND conversation_key = 'seed_conv_285_286' AND sender_id = @dave);

INSERT INTO broker_message_copies (tenant_id, original_message_id, conversation_key, sender_id, receiver_id, message_body, sent_at, copy_reason)
SELECT @tenant_id, 9004, 'seed_conv_287_283', @emma, @alice,
  'Alice, would you be interested in swapping cooking lessons for tech help?', NOW() - INTERVAL 6 DAY, 'new_member'
FROM dual WHERE NOT EXISTS (SELECT 1 FROM broker_message_copies WHERE tenant_id = @tenant_id AND conversation_key = 'seed_conv_287_283' AND sender_id = @emma);

INSERT INTO broker_message_copies (tenant_id, original_message_id, conversation_key, sender_id, receiver_id, message_body, sent_at, copy_reason)
SELECT @tenant_id, 9005, 'seed_conv_287_283', @alice, @emma,
  'That sounds perfect Emma! I have been wanting to learn more about computers.', NOW() - INTERVAL 5 DAY, 'new_member'
FROM dual WHERE NOT EXISTS (SELECT 1 FROM broker_message_copies WHERE tenant_id = @tenant_id AND conversation_key = 'seed_conv_287_283' AND sender_id = @alice AND receiver_id = @emma);

INSERT INTO broker_message_copies (tenant_id, original_message_id, conversation_key, sender_id, receiver_id, message_body, sent_at, copy_reason)
SELECT @tenant_id, 9006, 'seed_conv_284_287', @bob, @emma,
  'Emma, can you tutor my son in maths? He is doing his GCSE.', NOW() - INTERVAL 4 DAY, 'monitoring'
FROM dual WHERE NOT EXISTS (SELECT 1 FROM broker_message_copies WHERE tenant_id = @tenant_id AND conversation_key = 'seed_conv_284_287' AND sender_id = @bob);

INSERT INTO broker_message_copies (tenant_id, original_message_id, conversation_key, sender_id, receiver_id, message_body, sent_at, copy_reason)
SELECT @tenant_id, 9007, 'seed_conv_284_287', @emma, @bob,
  'Of course Bob. What level is he at currently?', NOW() - INTERVAL 3 DAY, 'monitoring'
FROM dual WHERE NOT EXISTS (SELECT 1 FROM broker_message_copies WHERE tenant_id = @tenant_id AND conversation_key = 'seed_conv_284_287' AND sender_id = @emma);

INSERT INTO broker_message_copies (tenant_id, original_message_id, conversation_key, sender_id, receiver_id, message_body, sent_at, copy_reason)
SELECT @tenant_id, 9008, 'seed_conv_285_283', @carol, @alice,
  'Alice, I flagged a concern about a new member messaging patterns.', NOW() - INTERVAL 2 DAY, 'flagged_user'
FROM dual WHERE NOT EXISTS (SELECT 1 FROM broker_message_copies WHERE tenant_id = @tenant_id AND conversation_key = 'seed_conv_285_283' AND sender_id = @carol);

INSERT INTO broker_message_copies (tenant_id, original_message_id, conversation_key, sender_id, receiver_id, message_body, sent_at, copy_reason)
SELECT @tenant_id, 9009, 'seed_conv_286_287', @dave, @emma,
  'Emma, are you available for the community event next week?', NOW() - INTERVAL 1 DAY, 'monitoring'
FROM dual WHERE NOT EXISTS (SELECT 1 FROM broker_message_copies WHERE tenant_id = @tenant_id AND conversation_key = 'seed_conv_286_287' AND sender_id = @dave);

-- ──────────────────────────────────────────────
-- 5. LISTING RISK TAGS (5 rows - for first 5 untagged listings)
-- ──────────────────────────────────────────────

INSERT IGNORE INTO listing_risk_tags (tenant_id, listing_id, risk_level, risk_category, risk_notes, member_visible_notes, requires_approval, insurance_required, dbs_required, tagged_by)
SELECT @tenant_id, l.id, 'low', 'general_assistance',
  'Standard service - no special requirements.', NULL, 0, 0, 0, @admin_id
FROM listings l LEFT JOIN listing_risk_tags lrt ON l.id = lrt.listing_id
WHERE l.tenant_id = @tenant_id AND lrt.id IS NULL
ORDER BY l.id LIMIT 1;

INSERT IGNORE INTO listing_risk_tags (tenant_id, listing_id, risk_level, risk_category, risk_notes, member_visible_notes, requires_approval, insurance_required, dbs_required, tagged_by)
SELECT @tenant_id, l.id, 'medium', 'home_visit',
  'Involves entering member home. Buddy system recommended.',
  'A buddy may attend the first visit for safety.', 1, 0, 0, @admin_id
FROM listings l LEFT JOIN listing_risk_tags lrt ON l.id = lrt.listing_id
WHERE l.tenant_id = @tenant_id AND lrt.id IS NULL
ORDER BY l.id LIMIT 1;

INSERT IGNORE INTO listing_risk_tags (tenant_id, listing_id, risk_level, risk_category, risk_notes, member_visible_notes, requires_approval, insurance_required, dbs_required, tagged_by)
SELECT @tenant_id, l.id, 'high', 'vulnerable_adult',
  'Service involves vulnerable adults. DBS check required.',
  'This service requires a DBS check before starting.', 1, 0, 1, @admin_id
FROM listings l LEFT JOIN listing_risk_tags lrt ON l.id = lrt.listing_id
WHERE l.tenant_id = @tenant_id AND lrt.id IS NULL
ORDER BY l.id LIMIT 1;

INSERT IGNORE INTO listing_risk_tags (tenant_id, listing_id, risk_level, risk_category, risk_notes, member_visible_notes, requires_approval, insurance_required, dbs_required, tagged_by)
SELECT @tenant_id, l.id, 'critical', 'physical_activity',
  'Physical activity with potential injury risk. Insurance documentation required.',
  'Please ensure you have appropriate insurance cover.', 1, 1, 0, @admin_id
FROM listings l LEFT JOIN listing_risk_tags lrt ON l.id = lrt.listing_id
WHERE l.tenant_id = @tenant_id AND lrt.id IS NULL
ORDER BY l.id LIMIT 1;

INSERT IGNORE INTO listing_risk_tags (tenant_id, listing_id, risk_level, risk_category, risk_notes, member_visible_notes, requires_approval, insurance_required, dbs_required, tagged_by)
SELECT @tenant_id, l.id, 'low', 'remote_service',
  'Remote/online service. Minimal risk.', NULL, 0, 0, 0, @admin_id
FROM listings l LEFT JOIN listing_risk_tags lrt ON l.id = lrt.listing_id
WHERE l.tenant_id = @tenant_id AND lrt.id IS NULL
ORDER BY l.id LIMIT 1;

-- ──────────────────────────────────────────────
-- 6. USER MONITORING - table does not exist, skipped
-- ──────────────────────────────────────────────

-- ──────────────────────────────────────────────
-- 7. EVENTS (3 events with future dates)
-- ──────────────────────────────────────────────

INSERT INTO events (tenant_id, user_id, title, description, location, start_time, end_time, status, max_attendees, category_id, created_at)
SELECT @tenant_id, @admin_id,
  'Seed: Community Coffee Morning',
  'Join us for a friendly coffee morning. Meet new members, share stories, and learn about upcoming timebank opportunities. Tea, coffee, and biscuits provided.',
  'Community Hall, Crewkerne',
  DATE_ADD(CURDATE(), INTERVAL 7 DAY) + INTERVAL 10 HOUR,
  DATE_ADD(CURDATE(), INTERVAL 7 DAY) + INTERVAL 12 HOUR,
  'active', 30,
  (SELECT id FROM categories WHERE tenant_id = @tenant_id AND type = 'event' LIMIT 1),
  NOW()
FROM dual WHERE NOT EXISTS (SELECT 1 FROM events WHERE tenant_id = @tenant_id AND title = 'Seed: Community Coffee Morning');

INSERT INTO events (tenant_id, user_id, title, description, location, start_time, end_time, status, max_attendees, category_id, created_at)
SELECT @tenant_id, @alice,
  'Seed: Skills Share Workshop',
  'A workshop where members demonstrate their skills. This month: basic DIY, smartphone tips, and simple cooking. Bring something to share!',
  'Library Meeting Room, Crewkerne',
  DATE_ADD(CURDATE(), INTERVAL 14 DAY) + INTERVAL 14 HOUR,
  DATE_ADD(CURDATE(), INTERVAL 14 DAY) + INTERVAL 16.5 HOUR,
  'active', 20,
  (SELECT id FROM categories WHERE tenant_id = @tenant_id AND type = 'event' LIMIT 1),
  NOW()
FROM dual WHERE NOT EXISTS (SELECT 1 FROM events WHERE tenant_id = @tenant_id AND title = 'Seed: Skills Share Workshop');

INSERT INTO events (tenant_id, user_id, title, description, location, start_time, end_time, status, max_attendees, category_id, created_at)
SELECT @tenant_id, @carol,
  'Seed: Spring Garden Party',
  'Celebrate spring with a garden party! There will be seed swaps, gardening tips, and a plant sale. All proceeds go to the community fund.',
  'Memorial Gardens, Crewkerne',
  DATE_ADD(CURDATE(), INTERVAL 21 DAY) + INTERVAL 11 HOUR,
  DATE_ADD(CURDATE(), INTERVAL 21 DAY) + INTERVAL 15 HOUR,
  'active', 50,
  (SELECT id FROM categories WHERE tenant_id = @tenant_id AND type = 'event' LIMIT 1),
  NOW()
FROM dual WHERE NOT EXISTS (SELECT 1 FROM events WHERE tenant_id = @tenant_id AND title = 'Seed: Spring Garden Party');

-- ──────────────────────────────────────────────
-- 8. GROUPS (2 groups)
-- ──────────────────────────────────────────────

INSERT INTO `groups` (tenant_id, owner_id, name, description, visibility, is_featured, is_active, created_at)
SELECT @tenant_id, @alice,
  'Seed: Gardeners Circle',
  'A group for timebank members who enjoy gardening. Share tips, swap seeds, and organise group gardening sessions.',
  'public', 1, 1, NOW()
FROM dual WHERE NOT EXISTS (SELECT 1 FROM `groups` WHERE tenant_id = @tenant_id AND name = 'Seed: Gardeners Circle');

INSERT INTO `groups` (tenant_id, owner_id, name, description, visibility, is_featured, is_active, created_at)
SELECT @tenant_id, @dave,
  'Seed: Tech Help & Digital Skills',
  'Get help with computers, phones, and the internet. Our tech-savvy members are happy to assist with any digital questions.',
  'public', 0, 1, NOW()
FROM dual WHERE NOT EXISTS (SELECT 1 FROM `groups` WHERE tenant_id = @tenant_id AND name = 'Seed: Tech Help & Digital Skills');

-- ──────────────────────────────────────────────
-- 9. WALLET TRANSACTIONS (5 with different types)
-- ──────────────────────────────────────────────

INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, description, transaction_type, status, created_at)
SELECT @tenant_id, @alice, @bob, 2,
  'Seed: Garden help - pruning and weeding', 'exchange', 'completed', NOW() - INTERVAL 20 DAY
FROM dual WHERE NOT EXISTS (SELECT 1 FROM transactions WHERE tenant_id = @tenant_id AND description = 'Seed: Garden help - pruning and weeding');

INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, description, transaction_type, status, created_at)
SELECT @tenant_id, @carol, @dave, 1,
  'Seed: Fixed kitchen tap', 'exchange', 'completed', NOW() - INTERVAL 15 DAY
FROM dual WHERE NOT EXISTS (SELECT 1 FROM transactions WHERE tenant_id = @tenant_id AND description = 'Seed: Fixed kitchen tap');

INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, description, transaction_type, status, created_at)
SELECT @tenant_id, @emma, @alice, 3,
  'Seed: Volunteer hours at community centre', 'volunteer', 'completed', NOW() - INTERVAL 10 DAY
FROM dual WHERE NOT EXISTS (SELECT 1 FROM transactions WHERE tenant_id = @tenant_id AND description = 'Seed: Volunteer hours at community centre');

INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, description, transaction_type, status, created_at)
SELECT @tenant_id, @admin_id, @bob, 5,
  'Seed: Welcome bonus for new member onboarding', 'donation', 'completed', NOW() - INTERVAL 5 DAY
FROM dual WHERE NOT EXISTS (SELECT 1 FROM transactions WHERE tenant_id = @tenant_id AND description = 'Seed: Welcome bonus for new member onboarding');

INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, description, transaction_type, status, created_at)
SELECT @tenant_id, @dave, @emma, 2,
  'Seed: Maths tutoring session (2 hours)', 'exchange', 'pending', NOW() - INTERVAL 1 DAY
FROM dual WHERE NOT EXISTS (SELECT 1 FROM transactions WHERE tenant_id = @tenant_id AND description = 'Seed: Maths tutoring session (2 hours)');

-- ──────────────────────────────────────────────
-- 10. CATEGORIES (already have 34, no action needed)
-- ──────────────────────────────────────────────

-- Verification queries
SELECT 'posts' AS `table`, COUNT(*) AS cnt FROM posts WHERE tenant_id = @tenant_id
UNION ALL SELECT 'listings', COUNT(*) FROM listings WHERE tenant_id = @tenant_id
UNION ALL SELECT 'exchange_requests', COUNT(*) FROM exchange_requests WHERE tenant_id = @tenant_id
UNION ALL SELECT 'broker_message_copies', COUNT(*) FROM broker_message_copies WHERE tenant_id = @tenant_id
UNION ALL SELECT 'listing_risk_tags', COUNT(*) FROM listing_risk_tags WHERE tenant_id = @tenant_id
UNION ALL SELECT 'events', COUNT(*) FROM events WHERE tenant_id = @tenant_id
UNION ALL SELECT '`groups`', COUNT(*) FROM `groups` WHERE tenant_id = @tenant_id
UNION ALL SELECT 'transactions', COUNT(*) FROM transactions WHERE tenant_id = @tenant_id
UNION ALL SELECT 'categories', COUNT(*) FROM categories WHERE tenant_id = @tenant_id;
