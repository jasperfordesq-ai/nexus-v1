<?php
/**
 * Home Page Sidebar Partial - CivicOne Version
 * Meta/Instagram Style Intelligent Sidebar
 *
 * Expected variables from parent:
 * - $isLoggedIn (bool)
 * - $userId (int|null)
 * - $tenantId (int)
 *
 * @package Nexus\Views\CivicOne\Partials
 */

// Get base path for links
$basePath = \Nexus\Core\TenantContext::getBasePath();
$DB = '\Nexus\Core\Database';

/**
 * INTELLIGENT SIDEBAR DATA QUERIES
 * Robust queries with individual try/catch for each section
 */
$sidebarData = [
    'stats' => null,
    'trending' => [],
    'members' => [],
    'events' => [],
    'recommended' => [],
    'community' => null,
    'groups' => [],
    'friends' => []
];

// 1. YOUR PERSONAL STATS
if ($isLoggedIn && $userId) {
    try {
        $sidebarData['stats'] = $DB::query(
            "SELECT
                (SELECT COUNT(*) FROM listings WHERE user_id = ?) as total_listings,
                (SELECT COUNT(*) FROM listings WHERE user_id = ? AND type = 'offer') as offers,
                (SELECT COUNT(*) FROM listings WHERE user_id = ? AND type = 'request') as requests,
                (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE sender_id = ?) as hours_given,
                (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE receiver_id = ?) as hours_received",
            [$userId, $userId, $userId, $userId, $userId]
        )->fetch(\PDO::FETCH_ASSOC);
    } catch (\Exception $e) { /* skip */ }
}

// 2. COMMUNITY STATS
try {
    $memberCount = $DB::query("SELECT COUNT(*) FROM users WHERE tenant_id = ?", [$tenantId])->fetchColumn() ?: 0;
    $listingCount = $DB::query("SELECT COUNT(*) FROM listings WHERE tenant_id = ?", [$tenantId])->fetchColumn() ?: 0;
    $eventsCount = 0;
    $groupsCount = 0;
    try { $eventsCount = $DB::query("SELECT COUNT(*) FROM events WHERE tenant_id = ?", [$tenantId])->fetchColumn() ?: 0; } catch (\Exception $e) {}
    try { $groupsCount = $DB::query("SELECT COUNT(*) FROM `groups` WHERE tenant_id = ?", [$tenantId])->fetchColumn() ?: 0; } catch (\Exception $e) {}
    $sidebarData['community'] = ['members' => $memberCount, 'listings' => $listingCount, 'events' => $eventsCount, 'groups_count' => $groupsCount];
} catch (\Exception $e) { /* skip */ }

// 3. TRENDING CATEGORIES - Only categories with active listings, ordered by popularity
try {
    $sidebarData['trending'] = $DB::query(
        "SELECT c.id, c.name, c.slug, c.color, COUNT(l.id) as listing_count
         FROM categories c
         INNER JOIN listings l ON l.category_id = c.id AND l.tenant_id = ? AND l.status = 'active'
         WHERE c.tenant_id = ? AND c.type = 'listing'
         GROUP BY c.id
         HAVING listing_count > 0
         ORDER BY listing_count DESC, c.name ASC
         LIMIT 8",
        [$tenantId, $tenantId]
    )->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) { /* skip */ }

// 4. ACTIVE MEMBERS (Use CommunityRank if enabled)
$sidebarData['communityRankActive'] = false; // Default
try {
    $communityRankEnabled = class_exists('\Nexus\Services\MemberRankingService')
        && \Nexus\Services\MemberRankingService::isEnabled();

    if ($communityRankEnabled) {
        $sidebarData['communityRankActive'] = true; // Set early so badge shows even if query fails
        // Use CommunityRank algorithm for intelligent member suggestions
        $rankQuery = \Nexus\Services\MemberRankingService::buildRankedQuery($userId ?: null, ['limit' => 5]);
        $sidebarData['members'] = $DB::query($rankQuery['sql'], $rankQuery['params'])->fetchAll(\PDO::FETCH_ASSOC);
    } else {
        // Fall back to last login ordering - try with last_active_at first
        try {
            $sidebarData['members'] = $DB::query(
                "SELECT id, first_name, last_name, organization_name, profile_type, avatar_url, location, last_login_at, last_active_at
                 FROM users WHERE tenant_id = ? AND id != ? ORDER BY last_active_at DESC, created_at DESC LIMIT 5",
                [$tenantId, $userId ?: 0]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            // last_active_at column doesn't exist yet - fall back to basic query
            $sidebarData['members'] = $DB::query(
                "SELECT id, first_name, last_name, organization_name, profile_type, avatar_url, location, NULL as last_login_at, NULL as last_active_at
                 FROM users WHERE tenant_id = ? AND id != ? ORDER BY created_at DESC LIMIT 5",
                [$tenantId, $userId ?: 0]
            )->fetchAll(\PDO::FETCH_ASSOC);
        }
        $sidebarData['communityRankActive'] = false;
    }
} catch (\Exception $e) {
    $sidebarData['communityRankActive'] = false;
    error_log("CommunityRank sidebar error: " . $e->getMessage());
    // Fall back to simple query without online status columns
    try {
        $sidebarData['members'] = $DB::query(
            "SELECT id, first_name, last_name, organization_name, profile_type, avatar_url, location, NULL as last_login_at, NULL as last_active_at
             FROM users WHERE tenant_id = ? AND id != ? ORDER BY created_at DESC LIMIT 5",
            [$tenantId, $userId ?: 0]
        )->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\Exception $e2) { /* skip */ }
}

// 5. UPCOMING EVENTS
try {
    $sidebarData['events'] = $DB::query(
        "SELECT id, title, start_time, location FROM events WHERE tenant_id = ? AND start_time >= NOW() ORDER BY start_time LIMIT 3",
        [$tenantId]
    )->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) { /* skip */ }

// 6. RECOMMENDED LISTINGS
if ($isLoggedIn) {
    try {
        $sidebarData['recommended'] = $DB::query(
            "SELECT l.id, l.title, l.type, u.first_name as owner_name
             FROM listings l JOIN users u ON l.user_id = u.id
             WHERE l.tenant_id = ? AND l.user_id != ? ORDER BY l.created_at DESC LIMIT 3",
            [$tenantId, $userId]
        )->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\Exception $e) { /* skip */ }
}

// 7. POPULAR GROUPS (using member count from group_members table)
try {
    $sidebarData['groups'] = $DB::query(
        "SELECT g.id, g.name, g.description, g.image_url as cover_image, COUNT(gm.id) as member_count
         FROM `groups` g
         LEFT JOIN group_members gm ON g.id = gm.group_id
         WHERE g.tenant_id = ?
         GROUP BY g.id
         ORDER BY member_count DESC, g.created_at DESC
         LIMIT 3",
        [$tenantId]
    )->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) { /* skip */ }

// 8. FRIENDS (using Connection model)
if ($isLoggedIn && $userId) {
    try {
        $sidebarData['friends'] = $DB::query(
            "SELECT u.id, u.first_name, u.last_name, u.organization_name, u.profile_type, u.avatar_url, u.location, u.last_active_at
             FROM connections c
             JOIN users u ON (CASE WHEN c.requester_id = ? THEN c.receiver_id ELSE c.requester_id END) = u.id
             WHERE (c.requester_id = ? OR c.receiver_id = ?) AND c.status = 'accepted'
             ORDER BY u.last_active_at DESC
             LIMIT 5",
            [$userId, $userId, $userId]
        )->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\Exception $e) { /* skip - connections table may not exist */ }
}
?>

<!-- ============================================
     1. YOUR ACTIVITY (Logged In) - Instagram Style Profile Card
     ============================================ -->
<?php if ($isLoggedIn && $sidebarData['stats']): ?>
<section class="sidebar-card sidebar-profile-card" aria-labelledby="sidebar-profile-heading">
    <h2 id="sidebar-profile-heading" class="visually-hidden">Your Profile</h2>
    <div class="sidebar-profile-header">
        <?= webp_avatar($_SESSION['user_avatar'] ?? null, $_SESSION['user_name'] ?? 'Member', 70) ?>
        <h3><?= htmlspecialchars($_SESSION['user_name'] ?? 'Member') ?></h3>
        <p>@<?= htmlspecialchars($_SESSION['username'] ?? 'member') ?></p>
    </div>
    <div class="sidebar-card-body sidebar-profile-body">
        <div class="sidebar-stats-row">
            <a href="<?= $basePath ?>/profile" class="sidebar-stat-link">
                <span class="sidebar-stat-value gradient"><?= (int)($sidebarData['stats']['total_listings'] ?? 0) ?></span>
                <span class="sidebar-text-muted sidebar-stat-label">Listings</span>
            </a>
            <div class="sidebar-divider" aria-hidden="true"></div>
            <a href="<?= $basePath ?>/wallet" class="sidebar-stat-link">
                <span class="sidebar-stat-value green"><?= number_format((float)($sidebarData['stats']['hours_given'] ?? 0), 1) ?></span>
                <span class="sidebar-text-muted sidebar-stat-label">Given</span>
            </a>
            <div class="sidebar-divider" aria-hidden="true"></div>
            <a href="<?= $basePath ?>/wallet" class="sidebar-stat-link">
                <span class="sidebar-stat-value orange"><?= number_format((float)($sidebarData['stats']['hours_received'] ?? 0), 1) ?></span>
                <span class="sidebar-text-muted sidebar-stat-label">Received</span>
            </a>
        </div>
        <div class="sidebar-mini-grid">
            <a href="<?= $basePath ?>/listings?user=me&type=offer" class="sidebar-mini-stat offers">
                <i class="fa-solid fa-hand-holding-heart green" aria-hidden="true"></i>
                <span class="sidebar-text-dark"><strong><?= (int)($sidebarData['stats']['offers'] ?? 0) ?></strong> Offers</span>
            </a>
            <a href="<?= $basePath ?>/listings?user=me&type=request" class="sidebar-mini-stat requests">
                <i class="fa-solid fa-hand orange" aria-hidden="true"></i>
                <span class="sidebar-text-dark"><strong><?= (int)($sidebarData['stats']['requests'] ?? 0) ?></strong> Requests</span>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================================
     FRIENDS - Your Connections (Logged In)
     ============================================ -->
<?php if ($isLoggedIn && !empty($sidebarData['friends'])): ?>
<section class="sidebar-card" aria-labelledby="sidebar-friends-heading">
    <div class="sidebar-card-header sidebar-header-flex">
        <h3 id="sidebar-friends-heading"><i class="fa-solid fa-user-group" aria-hidden="true"></i> Friends</h3>
        <a href="<?= $basePath ?>/connections" class="sidebar-see-all">See All</a>
    </div>
    <div class="sidebar-card-body">
        <ul class="sidebar-member-list" role="list">
        <?php foreach ($sidebarData['friends'] as $friend):
            $friendName = $friend['profile_type'] === 'organization'
                ? ($friend['organization_name'] ?: 'Organization')
                : (trim(($friend['first_name'] ?? '') . ' ' . ($friend['last_name'] ?? '')) ?: 'Member');
            $isOnline = !empty($friend['last_active_at']) && strtotime($friend['last_active_at']) > strtotime('-5 minutes');
            $isRecent = !empty($friend['last_active_at']) && strtotime($friend['last_active_at']) > strtotime('-24 hours');
        ?>
            <li>
                <a href="<?= $basePath ?>/profile/<?= $friend['id'] ?>" class="sidebar-member-item">
                    <div class="sidebar-avatar-wrapper">
                        <?= webp_avatar($friend['avatar_url'] ?: null, $friendName, 44) ?>
                        <?php if ($isOnline): ?>
                            <span class="sidebar-online-dot online" title="Online now" aria-label="Online now"></span>
                        <?php elseif ($isRecent): ?>
                            <span class="sidebar-online-dot recent" title="Active today" aria-label="Active today"></span>
                        <?php endif; ?>
                    </div>
                    <div class="sidebar-member-info">
                        <span class="sidebar-member-name"><?= htmlspecialchars($friendName) ?></span>
                        <span class="sidebar-member-location"><?= htmlspecialchars($friend['location'] ?: 'Community Member') ?></span>
                    </div>
                </a>
            </li>
        <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<!-- ============================================
     2. COMMUNITY PULSE - Always Show
     ============================================ -->
<?php if ($sidebarData['community']): ?>
<section class="sidebar-card" aria-labelledby="sidebar-pulse-heading">
    <div class="sidebar-card-header">
        <h3 id="sidebar-pulse-heading"><i class="fa-solid fa-heart-pulse" aria-hidden="true"></i> Community Pulse</h3>
    </div>
    <div class="sidebar-card-body">
        <div class="sidebar-pulse-grid" role="list">
            <a href="<?= $basePath ?>/members" class="sidebar-pulse-item members" role="listitem">
                <i class="fa-solid fa-users indigo" aria-hidden="true"></i>
                <span class="sidebar-pulse-value indigo"><?= number_format((int)($sidebarData['community']['members'] ?? 0)) ?></span>
                <span class="sidebar-pulse-label">Members</span>
            </a>
            <a href="<?= $basePath ?>/listings" class="sidebar-pulse-item listings" role="listitem">
                <i class="fa-solid fa-hand-holding-heart green" aria-hidden="true"></i>
                <span class="sidebar-pulse-value green"><?= number_format((int)($sidebarData['community']['listings'] ?? 0)) ?></span>
                <span class="sidebar-pulse-label">Listings</span>
            </a>
            <a href="<?= $basePath ?>/events" class="sidebar-pulse-item events" role="listitem">
                <i class="fa-solid fa-calendar pink" aria-hidden="true"></i>
                <span class="sidebar-pulse-value pink"><?= number_format((int)($sidebarData['community']['events'] ?? 0)) ?></span>
                <span class="sidebar-pulse-label">Events</span>
            </a>
            <a href="<?= $basePath ?>/groups" class="sidebar-pulse-item groups" role="listitem">
                <i class="fa-solid fa-users-rectangle amber" aria-hidden="true"></i>
                <span class="sidebar-pulse-value amber"><?= number_format((int)($sidebarData['community']['groups_count'] ?? 0)) ?></span>
                <span class="sidebar-pulse-label">Groups</span>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================================
     3. SUGGESTED FOR YOU (Logged In)
     ============================================ -->
<?php if ($isLoggedIn && !empty($sidebarData['recommended'])): ?>
<section class="sidebar-card" aria-labelledby="sidebar-suggested-heading">
    <div class="sidebar-card-header sidebar-header-flex">
        <h3 id="sidebar-suggested-heading"><i class="fa-solid fa-sparkles" aria-hidden="true"></i> Suggested For You</h3>
        <a href="<?= $basePath ?>/listings" class="sidebar-see-all">See All</a>
    </div>
    <div class="sidebar-card-body">
        <ul role="list">
        <?php foreach ($sidebarData['recommended'] as $listing): ?>
            <li>
                <a href="<?= $basePath ?>/listings/<?= $listing['id'] ?>" class="sidebar-listing-item">
                    <div class="sidebar-listing-icon <?= $listing['type'] === 'offer' ? 'offer' : 'request' ?>">
                        <i class="fa-solid <?= $listing['type'] === 'offer' ? 'fa-hand-holding-heart' : 'fa-hand' ?>" aria-hidden="true"></i>
                    </div>
                    <div class="sidebar-listing-info">
                        <span class="sidebar-listing-title"><?= htmlspecialchars($listing['title']) ?></span>
                        <span class="sidebar-listing-author">by <?= htmlspecialchars($listing['owner_name'] ?? 'Member') ?></span>
                    </div>
                    <span class="sidebar-listing-badge <?= $listing['type'] === 'offer' ? 'offer' : 'request' ?>"><?= $listing['type'] ?></span>
                </a>
            </li>
        <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<!-- ============================================
     4. BROWSE CATEGORIES - Smart: Only with listings
     ============================================ -->
<?php if (!empty($sidebarData['trending'])): ?>
<section class="sidebar-card" aria-labelledby="sidebar-categories-heading">
    <div class="sidebar-card-header sidebar-header-flex">
        <h3 id="sidebar-categories-heading"><i class="fa-solid fa-fire" aria-hidden="true"></i> Top Categories</h3>
        <a href="<?= $basePath ?>/listings" class="sidebar-see-all">All Listings</a>
    </div>
    <div class="sidebar-card-body">
        <nav class="sidebar-tags" aria-label="Browse by category">
            <?php
            $colorClasses = ['color-indigo', 'color-pink', 'color-green', 'color-amber', 'color-violet', 'color-cyan', 'color-red', 'color-lime'];
            foreach ($sidebarData['trending'] as $index => $cat):
                $colorClass = $colorClasses[$index % count($colorClasses)];
            ?>
                <a href="<?= $basePath ?>/listings?cat=<?= (int)$cat['id'] ?>" class="sidebar-tag <?= $colorClass ?>">
                    <span class="sidebar-tag-name"><?= htmlspecialchars($cat['name']) ?></span>
                    <span class="sidebar-tag-count">(<?= (int)$cat['listing_count'] ?>)</span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</section>
<?php endif; ?>

<!-- ============================================
     5. PEOPLE YOU MAY KNOW - Facebook Style
     ============================================ -->
<?php if (!empty($sidebarData['members'])): ?>
<section class="sidebar-card" aria-labelledby="sidebar-people-heading">
    <div class="sidebar-card-header sidebar-header-flex">
        <h3 id="sidebar-people-heading"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> People You May Know</h3>
        <div class="sidebar-header-actions">
            <?php if (!empty($sidebarData['communityRankActive'])): ?>
            <span class="sidebar-cr-badge" title="Members ranked by CommunityRank algorithm">
                <i class="fa-solid fa-diagram-project" aria-hidden="true"></i> <span class="visually-hidden">CommunityRank</span>CR
            </span>
            <?php endif; ?>
            <a href="<?= $basePath ?>/members" class="sidebar-see-all">See All</a>
        </div>
    </div>
    <div class="sidebar-card-body">
        <ul role="list">
        <?php foreach ($sidebarData['members'] as $member):
            $memberName = $member['profile_type'] === 'organisation' && !empty($member['organization_name'])
                ? $member['organization_name']
                : trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
            if (empty($memberName)) $memberName = 'Community Member';

            // Real-time online status: Active within last 5 minutes
            $lastActiveAt = $member['last_active_at'] ?? null;
            $isOnline = $lastActiveAt && (strtotime($lastActiveAt) > strtotime('-5 minutes'));
            // Fallback to last_login_at for "recently active" indicator (within 24h)
            $isRecentlyActive = !$isOnline && $member['last_login_at'] && (strtotime($member['last_login_at']) > strtotime('-1 day'));
        ?>
            <li class="sidebar-member-row">
                <div class="sidebar-avatar-wrapper">
                    <?= webp_avatar($member['avatar_url'] ?: null, $memberName, 48) ?>
                    <?php if ($isOnline): ?>
                        <span class="sidebar-online-dot online" title="Online now" aria-label="Online now"></span>
                    <?php elseif ($isRecentlyActive): ?>
                        <span class="sidebar-online-dot recent" title="Active today" aria-label="Active today"></span>
                    <?php endif; ?>
                </div>
                <div class="sidebar-member-info">
                    <a href="<?= $basePath ?>/profile/<?= $member['id'] ?>" class="sidebar-member-name"><?= htmlspecialchars($memberName) ?></a>
                    <span class="sidebar-member-location"><?= htmlspecialchars($member['location'] ?: 'Community Member') ?></span>
                </div>
                <a href="<?= $basePath ?>/profile/<?= $member['id'] ?>" class="sidebar-btn-view">View</a>
            </li>
        <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<!-- ============================================
     6. UPCOMING EVENTS
     ============================================ -->
<?php if (!empty($sidebarData['events'])): ?>
<section class="sidebar-card" aria-labelledby="sidebar-events-heading">
    <div class="sidebar-card-header sidebar-header-flex">
        <h3 id="sidebar-events-heading"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i> Upcoming Events</h3>
        <a href="<?= $basePath ?>/events" class="sidebar-see-all">See All</a>
    </div>
    <div class="sidebar-card-body">
        <ul role="list">
        <?php foreach ($sidebarData['events'] as $event):
            $eventDate = new DateTime($event['start_time']);
        ?>
            <li>
                <a href="<?= $basePath ?>/events/<?= $event['id'] ?>" class="sidebar-event-item">
                    <div class="sidebar-event-date">
                        <span class="sidebar-event-month"><?= $eventDate->format('M') ?></span>
                        <span class="sidebar-event-day"><?= $eventDate->format('j') ?></span>
                    </div>
                    <div class="sidebar-event-info">
                        <span class="sidebar-event-title"><?= htmlspecialchars($event['title']) ?></span>
                        <span class="sidebar-event-meta">
                            <i class="fa-regular fa-clock" aria-hidden="true"></i> <?= $eventDate->format('g:i A') ?>
                        </span>
                        <?php if (!empty($event['location'])): ?>
                        <span class="sidebar-event-meta">
                            <i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= htmlspecialchars(mb_strimwidth($event['location'], 0, 25, '...')) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </a>
            </li>
        <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<!-- ============================================
     7. POPULAR GROUPS
     ============================================ -->
<?php if (!empty($sidebarData['groups'])): ?>
<section class="sidebar-card" aria-labelledby="sidebar-groups-heading">
    <div class="sidebar-card-header sidebar-header-flex">
        <h3 id="sidebar-groups-heading"><i class="fa-solid fa-users-rectangle" aria-hidden="true"></i> Popular Groups</h3>
        <a href="<?= $basePath ?>/groups" class="sidebar-see-all">See All</a>
    </div>
    <div class="sidebar-card-body">
        <ul role="list">
        <?php foreach ($sidebarData['groups'] as $group): ?>
            <li>
                <a href="<?= $basePath ?>/groups/<?= $group['id'] ?>" class="sidebar-group-item">
                    <div class="sidebar-group-icon">
                        <?php if (!empty($group['cover_image'])): ?>
                            <img src="<?= htmlspecialchars($group['cover_image']) ?>" loading="lazy" alt="">
                        <?php else: ?>
                            <i class="fa-solid fa-users" aria-hidden="true"></i>
                        <?php endif; ?>
                    </div>
                    <div class="sidebar-group-info">
                        <span class="sidebar-group-name"><?= htmlspecialchars($group['name']) ?></span>
                        <span class="sidebar-group-desc"><?= htmlspecialchars(mb_strimwidth($group['description'] ?? 'Community Group', 0, 30, '...')) ?></span>
                    </div>
                </a>
            </li>
        <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<!-- ============================================
     8. QUICK ACTIONS (Always Show)
     ============================================ -->
<section class="sidebar-card" aria-labelledby="sidebar-actions-heading">
    <div class="sidebar-card-header">
        <h3 id="sidebar-actions-heading"><i class="fa-solid fa-rocket" aria-hidden="true"></i> Quick Actions</h3>
    </div>
    <div class="sidebar-card-body">
        <?php if ($isLoggedIn): ?>
        <a href="<?= $basePath ?>/compose?type=listing" class="sidebar-cta-primary">
            <i class="fa-solid fa-plus-circle" aria-hidden="true"></i>
            <div>
                <span class="sidebar-cta-primary-text">Create New Listing</span>
                <span class="sidebar-cta-primary-sub">Share your skills with the community</span>
            </div>
        </a>
        <?php endif; ?>
        <nav class="sidebar-action-grid" aria-label="Quick actions">
            <a href="<?= $basePath ?>/compose?type=event" class="sidebar-action-item pink">
                <i class="fa-solid fa-calendar-plus pink" aria-hidden="true"></i>
                <span class="sidebar-action-label">Host Event</span>
            </a>
            <a href="<?= $basePath ?>/compose?type=poll" class="sidebar-action-item indigo">
                <i class="fa-solid fa-square-poll-vertical indigo" aria-hidden="true"></i>
                <span class="sidebar-action-label">Create Poll</span>
            </a>
            <a href="<?= $basePath ?>/compose?type=goal" class="sidebar-action-item amber">
                <i class="fa-solid fa-bullseye amber" aria-hidden="true"></i>
                <span class="sidebar-action-label">Set Goal</span>
            </a>
            <a href="<?= $basePath ?>/groups" class="sidebar-action-item green">
                <i class="fa-solid fa-users-rectangle green" aria-hidden="true"></i>
                <span class="sidebar-action-label">Groups</span>
            </a>
        </nav>
    </div>
</section>
