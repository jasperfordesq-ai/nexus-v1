<?php
/**
 * ╔═══════════════════════════════════════════════════════════════════════════╗
 * ║  NEXUS GROUPS SHOW PAGE - HOLOGRAPHIC GLASSMORPHISM 2025                  ║
 * ║  Premium Mobile-First Design with High-End Visual Effects                 ║
 * ║  Path: views/modern/groups/show.php                                       ║
 * ╚═══════════════════════════════════════════════════════════════════════════╝
 */

$pageTitle = htmlspecialchars($group['name']);
$pageSubtitle = !empty($group['description']) ? htmlspecialchars(mb_strimwidth($group['description'], 0, 200, '...')) : 'Community Hub';
$hideHero = true; // Custom hero implementation below

Nexus\Core\SEO::setTitle($group['name']);
Nexus\Core\SEO::setDescription($group['description']);

require dirname(__DIR__, 2) . '/layouts/modern/header.php';

// Helper for avatars
if (!function_exists('get_phoenix_avatar')) {
    function get_phoenix_avatar($url) {
        return $url ?: '/assets/images/default-avatar.svg';
    }
}

$currentUserId = $_SESSION['user_id'] ?? 0;

// Check if current user has pending membership status
$isPending = false;
if ($currentUserId && !$isMember) {
    $membershipStatus = \Nexus\Models\Group::getMembershipStatus($group['id'], $currentUserId);
    $isPending = ($membershipStatus === 'pending');
}
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<!-- CSS moved to /assets/css/groups-show.css -->

<!-- ═══════════════════════════════════════════════════════════════════════════
     PAGE STRUCTURE
     ═══════════════════════════════════════════════════════════════════════════ -->

<!-- Holographic Background -->
<div class="holo-bg"></div>

<!-- Main Page Container -->
<div class="holo-page">

    <!-- Modern Hero Section -->
    <div class="modern-hero <?= !empty($group['cover_image_url']) ? 'modern-hero--with-cover' : '' ?>">
        <?php if (!empty($group['cover_image_url'])): ?>
            <div class="modern-hero__cover">
                <?= webp_image($group['cover_image_url'], htmlspecialchars($group['name']), '') ?>
            </div>
        <?php endif; ?>
        <div class="modern-hero__gradient htb-hero-gradient-hub"></div>
        <div class="modern-hero__content">
            <div class="modern-hero__badge">
                <i class="fa-solid fa-users"></i>
                <span>Community Hub</span>
            </div>
            <h1 class="modern-hero__title"><?= htmlspecialchars($group['name']) ?></h1>
            <div class="modern-hero__meta">
                <span><i class="fa-solid fa-user-group"></i> <?= count($members) ?> Members</span>
                <?php if (!empty($group['location'])): ?>
                    <span><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($group['location']) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($group['description'])): ?>
                <p class="modern-hero__description"><?= htmlspecialchars(mb_strimwidth($group['description'], 0, 200, '...')) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Content Card -->
    <div class="holo-container">
        <main class="holo-main-card">

            <!-- Group Actions Bar -->
            <div class="group-actions-bar">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($isMember): ?>
                        <span class="member-badge">
                            <i class="fa-solid fa-circle-check"></i> Member
                        </span>
                        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/leave" method="POST" class="ajax-form" data-reload="true" style="display: inline;">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                            <button type="submit" class="btn-secondary">Leave Hub</button>
                        </form>
                    <?php elseif ($isPending): ?>
                        <span class="pending-badge">
                            <i class="fa-solid fa-clock"></i> Pending Approval
                        </span>
                    <?php else: ?>
                        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/join" method="POST" class="ajax-form" data-reload="true" style="display: inline;">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                            <button type="submit" class="btn-primary">
                                <i class="fa-solid fa-plus"></i> Join Hub
                            </button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login" class="btn-primary">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i> Login to Join
                    </a>
                <?php endif; ?>
            </div>

            <!-- Tab Navigation -->
            <nav role="navigation" aria-label="Main navigation" class="holo-tabs">
                <div class="holo-tabs-scroll">
                    <?php if ($hasSubHubs): ?>
                        <button onclick="switchTab('sub-hubs')" class="holo-tab-btn <?= $activeTab == 'sub-hubs' ? 'active' : '' ?>" id="btn-sub-hubs">
                            <i class="fa-solid fa-layer-group"></i>
                            <span>Sub-Hubs</span>
                        </button>
                    <?php endif; ?>

                    <button onclick="switchTab('feed')" class="holo-tab-btn <?= $activeTab == 'feed' ? 'active' : '' ?>" id="btn-feed">
                        <i class="fa-solid fa-rss"></i>
                        <span>Feed</span>
                    </button>

                    <button onclick="switchTab('members')" class="holo-tab-btn <?= (!$hasSubHubs && $activeTab == 'members' && $activeTab != 'feed') || $activeTab == 'members' ? 'active' : '' ?>" id="btn-members">
                        <i class="fa-solid fa-users"></i>
                        <span>Members</span>
                    </button>

                    <button onclick="switchTab('discussions')" class="holo-tab-btn <?= $activeTab == 'discussions' ? 'active' : '' ?>" id="btn-discussions">
                        <i class="fa-solid fa-comments"></i>
                        <span>Discussions</span>
                    </button>

                    <button onclick="switchTab('events')" class="holo-tab-btn <?= $activeTab == 'events' ? 'active' : '' ?>" id="btn-events">
                        <i class="fa-solid fa-calendar"></i>
                        <span>Events</span>
                    </button>

                    <?php if ($isMember || $isOrganizer): ?>
                        <button onclick="switchTab('reviews')" class="holo-tab-btn <?= $activeTab == 'reviews' ? 'active' : '' ?>" id="btn-reviews">
                            <i class="fa-solid fa-star"></i>
                            <span>Reviews</span>
                        </button>
                    <?php endif; ?>

                    <?php if ($isOrganizer): ?>
                        <button onclick="switchTab('settings')" class="holo-tab-btn <?= $activeTab == 'settings' ? 'active' : '' ?>" id="btn-settings">
                            <i class="fa-solid fa-gear"></i>
                            <span>Settings</span>
                        </button>
                    <?php endif; ?>
                </div>
            </nav>

            <!-- Tab Content -->
            <div class="holo-tab-content">

                <!-- ═══════════════════════════════════════════════════════════════
                     SUB-HUBS TAB
                     ═══════════════════════════════════════════════════════════════ -->
                <?php if ($hasSubHubs): ?>
                <div id="tab-sub-hubs" class="holo-tab-pane <?= $activeTab == 'sub-hubs' ? 'active' : '' ?>">
                    <div class="holo-section-header">
                        <div>
                            <h2 class="holo-section-title"><i class="fa-solid fa-layer-group"></i> Sub-Hubs</h2>
                            <p class="holo-section-subtitle">Specialized groups within this hub</p>
                        </div>
                    </div>

                    <div class="holo-discussions-list">
                        <?php foreach ($subGroups as $sub): ?>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $sub['id'] ?>" class="holo-discussion-item">
                                <div class="holo-discussion-avatar-placeholder">
                                    <i class="fa-solid fa-layer-group"></i>
                                </div>
                                <div class="holo-discussion-content">
                                    <h3 class="holo-discussion-title"><?= htmlspecialchars($sub['name']) ?></h3>
                                    <div class="holo-discussion-meta">
                                        <span><?= htmlspecialchars(substr($sub['description'], 0, 60)) ?>...</span>
                                    </div>
                                </div>
                                <i class="fa-solid fa-chevron-right holo-discussion-arrow"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ═══════════════════════════════════════════════════════════════
                     FEED TAB (Group Social Feed)
                     ═══════════════════════════════════════════════════════════════ -->
                <div id="tab-feed" class="holo-tab-pane <?= $activeTab == 'feed' ? 'active' : '' ?>">
                    <div class="holo-section-header">
                        <div>
                            <h2 class="holo-section-title"><i class="fa-solid fa-rss"></i> Group Feed</h2>
                            <p class="holo-section-subtitle">Updates and posts from hub members</p>
                        </div>
                    </div>

                    <!-- Post Composer (Facebook-style "What's on your mind") -->
                    <?php if ($isMember): ?>
                    <div class="group-compose-box">
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?group=<?= $group['id'] ?>" class="group-compose-link">
                            <div class="group-compose-prompt">
                                <div class="group-compose-avatar-ring">
                                    <?= webp_avatar($_SESSION['user_avatar'] ?? null, $_SESSION['user_name'] ?? 'User', 40) ?>
                                </div>
                                <div class="group-compose-input">
                                    What's on your mind, <?= htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? 'User')[0]) ?>?
                                </div>
                            </div>
                        </a>
                        <div class="group-compose-actions">
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?type=post&group=<?= $group['id'] ?>" class="group-compose-btn">
                                <i class="fa-solid fa-pen" style="color: #db2777;"></i>
                                <span>Post</span>
                            </a>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?type=listing&group=<?= $group['id'] ?>" class="group-compose-btn">
                                <i class="fa-solid fa-hand-holding-heart" style="color: #10b981;"></i>
                                <span>Listing</span>
                            </a>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?type=event&group=<?= $group['id'] ?>" class="group-compose-btn">
                                <i class="fa-solid fa-calendar-plus" style="color: #6366f1;"></i>
                                <span>Event</span>
                            </a>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="group-compose-box guest-prompt">
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login?redirect=<?= urlencode('/groups/' . $group['id']) ?>" class="group-compose-link">
                            <div class="group-compose-prompt">
                                <div class="group-compose-avatar-ring guest">
                                    <div class="guest-avatar-icon">
                                        <i class="fa-solid fa-user-plus"></i>
                                    </div>
                                </div>
                                <div class="group-compose-input">
                                    Sign in to join this hub and share with the community...
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php endif; ?>

                    <!-- Feed Posts Container -->
                    <div id="groupFeedPosts" class="group-feed-posts">
                        <div class="feed-loading">
                            <i class="fa-solid fa-spinner fa-spin"></i>
                            <span>Loading posts...</span>
                        </div>
                    </div>

                    <!-- Load More Button -->
                    <div id="groupFeedLoadMore" class="feed-load-more" style="display: none;">
                        <button type="button" onclick="loadGroupFeed(<?= $group['id'] ?>, true)">
                            <i class="fa-solid fa-arrow-down"></i>
                            Load More
                        </button>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════════════════════════
                     MEMBERS TAB
                     ═══════════════════════════════════════════════════════════════ -->
                <div id="tab-members" class="holo-tab-pane <?= $activeTab == 'members' || (!$hasSubHubs && $activeTab == '') ? 'active' : '' ?>">
                    <div class="holo-section-header">
                        <div>
                            <h2 class="holo-section-title"><i class="fa-solid fa-users"></i> Members</h2>
                            <p class="holo-section-subtitle"><?= count($members) ?> people in this hub</p>
                        </div>
                    </div>

                    <?php if (!empty($members)): ?>
                        <div class="holo-members-grid">
                            <?php foreach ($members as $mem):
                                $isOrg = ($mem['id'] == $group['owner_id']);
                                $isMe = ($mem['id'] == $currentUserId);
                                $memberRating = \Nexus\Models\Review::getAverageForUser($mem['id']);
                            ?>
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/profile/<?= $mem['id'] ?>" class="holo-member-card">
                                    <?= webp_avatar($mem['avatar_url'] ?? null, $mem['name'], 48) ?>
                                    <div class="holo-member-info">
                                        <div class="holo-member-name"><?= htmlspecialchars($mem['name']) ?></div>
                                        <?php if ($isOrg): ?>
                                            <div class="holo-member-role">Organizer</div>
                                        <?php endif; ?>
                                        <?php if ($memberRating && $memberRating['total_count'] > 0): ?>
                                            <div class="holo-member-rating">
                                                <span class="stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="<?= $i <= round($memberRating['avg_rating']) ? 'fas' : 'far' ?> fa-star"></i>
                                                    <?php endfor; ?>
                                                </span>
                                                <span>(<?= $memberRating['total_count'] ?>)</span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($isMember && !$isMe): ?>
                                            <button type="button" class="holo-member-btn"
                                                    onclick="event.preventDefault(); event.stopPropagation(); openReviewModal(<?= $mem['id'] ?>, '<?= htmlspecialchars(addslashes($mem['name'])) ?>', '<?= get_phoenix_avatar($mem['avatar_url']) ?>')">
                                                <i class="fa-solid fa-star"></i> Review
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="holo-empty-state">
                            <div class="holo-empty-icon"><i class="fa-solid fa-users"></i></div>
                            <h3 class="holo-empty-title">No members yet</h3>
                            <p class="holo-empty-text">Be the first to join this hub!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ═══════════════════════════════════════════════════════════════
                     DISCUSSIONS TAB
                     ═══════════════════════════════════════════════════════════════ -->
                <div id="tab-discussions" class="holo-tab-pane <?= $activeTab == 'discussions' ? 'active' : '' ?>">
                    <?php if (isset($activeDiscussion)): ?>
                        <!-- Active Chat View -->
                        <div class="holo-chat-container">
                            <div class="holo-chat-header">
                                <div class="holo-chat-header-info">
                                    <h3><?= htmlspecialchars($activeDiscussion['title']) ?></h3>
                                    <p>Started by <?= htmlspecialchars($activeDiscussion['author_name']) ?></p>
                                </div>
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>?tab=discussions" class="holo-chat-close">
                                    <i class="fa-solid fa-xmark"></i>
                                    <span class="hide-mobile">Close</span>
                                </a>
                            </div>

                            <div class="holo-chat-stream" id="chatStream">
                                <?php foreach ($activePosts as $post):
                                    $isMe = ($post['user_id'] == $currentUserId);
                                    $msgClass = $isMe ? 'me' : 'other';
                                ?>
                                    <div class="holo-chat-message <?= $msgClass ?>">
                                        <?php if (!$isMe): ?>
                                            <?= webp_avatar($post['author_avatar'] ?? null, $post['author_name'], 32) ?>
                                        <?php endif; ?>
                                        <div class="holo-chat-bubble-wrap">
                                            <?php if (!$isMe): ?>
                                                <div class="holo-chat-author"><?= htmlspecialchars($post['author_name']) ?></div>
                                            <?php endif; ?>
                                            <div class="holo-chat-bubble">
                                                <?= nl2br(htmlspecialchars($post['content'])) ?>
                                                <div class="holo-chat-time"><?= date('g:i A', strtotime($post['created_at'])) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="holo-chat-reply-dock">
                                <?php if (isset($_SESSION['user_id']) && $isMember): ?>
                                    <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>/discussions/<?= $activeDiscussion['id'] ?>/reply" method="POST" class="holo-chat-reply-form">
                                        <?= \Nexus\Core\Csrf::input() ?>
                                        <textarea name="content" class="holo-chat-input" rows="1" placeholder="Type a message..." required
                                                  oninput="this.style.height = ''; this.style.height = Math.min(this.scrollHeight, 120) + 'px'"></textarea>
                                        <button type="submit" class="holo-chat-send">
                                            <i class="fa-solid fa-paper-plane"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div style="text-align: center; color: var(--htb-text-muted); padding: 8px;">
                                        <i class="fa-solid fa-lock"></i> Join hub to reply
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <script>
                            setTimeout(() => {
                                const el = document.getElementById('chatStream');
                                if (el) el.scrollTop = el.scrollHeight;
                            }, 100);
                        </script>

                    <?php else: ?>
                        <!-- Discussion List -->
                        <div class="holo-section-header">
                            <div>
                                <h2 class="holo-section-title"><i class="fa-solid fa-comments"></i> Discussions</h2>
                                <p class="holo-section-subtitle">Join the conversation</p>
                            </div>
                            <?php if ($isMember): ?>
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>/discussions/create" class="holo-section-action">
                                    <i class="fa-solid fa-plus"></i>
                                    <span>Start Topic</span>
                                </a>
                            <?php endif; ?>
                        </div>

                        <?php
                        $discussions = \Nexus\Models\GroupDiscussion::getForGroup($group['id']);
                        ?>

                        <?php if (empty($discussions)): ?>
                            <div class="holo-empty-state">
                                <div class="holo-empty-icon"><i class="fa-regular fa-comments"></i></div>
                                <h3 class="holo-empty-title">It's quiet in here...</h3>
                                <p class="holo-empty-text">Be the first to start a discussion!</p>
                                <?php if ($isMember): ?>
                                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>/discussions/create" class="holo-section-action">
                                        <i class="fa-solid fa-plus"></i> Start Topic
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="holo-discussions-list">
                                <?php foreach ($discussions as $disc): ?>
                                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>/discussions/<?= $disc['id'] ?>" class="holo-discussion-item">
                                        <?= webp_avatar($disc['author_avatar'] ?? null, $disc['author_name'], 40) ?>
                                        <div class="holo-discussion-content">
                                            <h3 class="holo-discussion-title"><?= htmlspecialchars($disc['title']) ?></h3>
                                            <div class="holo-discussion-meta">
                                                <span><i class="fa-regular fa-user"></i> <?= htmlspecialchars($disc['author_name']) ?></span>
                                                <span><i class="fa-regular fa-comment"></i> <?= $disc['reply_count'] ?></span>
                                                <span><i class="fa-regular fa-clock"></i> <?= date('M j', strtotime($disc['last_reply_at'] ?? $disc['created_at'])) ?></span>
                                            </div>
                                        </div>
                                        <i class="fa-solid fa-chevron-right holo-discussion-arrow"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- ═══════════════════════════════════════════════════════════════
                     EVENTS TAB
                     ═══════════════════════════════════════════════════════════════ -->
                <div id="tab-events" class="holo-tab-pane <?= $activeTab == 'events' ? 'active' : '' ?>">
                    <div class="holo-section-header">
                        <div>
                            <h2 class="holo-section-title"><i class="fa-solid fa-calendar"></i> Events</h2>
                            <p class="holo-section-subtitle">Upcoming hub activities</p>
                        </div>
                        <?php if ($isMember): ?>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/create?group_id=<?= $group['id'] ?>" class="holo-section-action">
                                <i class="fa-solid fa-plus"></i>
                                <span>Create Event</span>
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php
                    $groupEvents = \Nexus\Models\Event::getForGroup($group['id']);
                    ?>

                    <?php if (empty($groupEvents)): ?>
                        <div class="holo-empty-state">
                            <div class="holo-empty-icon"><i class="fa-regular fa-calendar"></i></div>
                            <h3 class="holo-empty-title">No upcoming events</h3>
                            <p class="holo-empty-text">Check back later or create an event!</p>
                        </div>
                    <?php else: ?>
                        <div class="holo-events-list">
                            <?php foreach ($groupEvents as $ev): ?>
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/<?= $ev['id'] ?>" class="holo-event-card">
                                    <div class="holo-event-date">
                                        <span class="holo-event-month"><?= date('M', strtotime($ev['start_time'])) ?></span>
                                        <span class="holo-event-day"><?= date('d', strtotime($ev['start_time'])) ?></span>
                                    </div>
                                    <div class="holo-event-content">
                                        <h3 class="holo-event-title"><?= htmlspecialchars($ev['title']) ?></h3>
                                        <div class="holo-event-location">
                                            <i class="fa-solid fa-location-dot"></i>
                                            <?= htmlspecialchars($ev['location']) ?>
                                        </div>
                                        <div class="holo-event-organizer">By <?= htmlspecialchars($ev['organizer_name']) ?></div>
                                    </div>
                                    <div class="holo-event-action hide-mobile">
                                        <span class="holo-event-btn">View</span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ═══════════════════════════════════════════════════════════════
                     REVIEWS TAB
                     ═══════════════════════════════════════════════════════════════ -->
                <?php if ($isMember || $isOrganizer): ?>
                <div id="tab-reviews" class="holo-tab-pane <?= $activeTab == 'reviews' ? 'active' : '' ?>">
                    <div class="holo-section-header">
                        <div>
                            <h2 class="holo-section-title"><i class="fa-solid fa-star"></i> Member Reviews</h2>
                            <p class="holo-section-subtitle">Rate members based on your interactions</p>
                        </div>
                    </div>

                    <?php if (isset($_GET['submitted'])): ?>
                        <div style="background: #dcfce7; border: 1px solid #86efac; color: #166534; padding: 14px 18px; border-radius: var(--holo-radius-xs); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                            <i class="fa-solid fa-check-circle"></i>
                            <span>Your review has been submitted!</span>
                        </div>
                    <?php endif; ?>

                    <div id="reviews-list">
                        <div style="text-align: center; padding: 40px; color: var(--htb-text-muted);">
                            <i class="fa-solid fa-spinner fa-spin" style="font-size: 1.5rem; margin-bottom: 12px; display: block;"></i>
                            <span>Loading reviews...</span>
                        </div>
                    </div>

                    <script>
                    (function() {
                        fetch('<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>/reviews')
                            .then(r => r.json())
                            .then(data => {
                                if (!data.success) return;

                                if (data.reviews.length === 0) {
                                    document.getElementById('reviews-list').innerHTML = `
                                        <div class="holo-empty-state">
                                            <div class="holo-empty-icon"><i class="fa-solid fa-star"></i></div>
                                            <h3 class="holo-empty-title">No reviews yet</h3>
                                            <p class="holo-empty-text">Be the first to review a member from the Members tab!</p>
                                        </div>
                                    `;
                                } else {
                                    let listHtml = '<div class="holo-discussions-list">';
                                    data.reviews.forEach(review => {
                                        const date = new Date(review.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
                                        const stars = Array(5).fill(0).map((_, i) =>
                                            `<i class="${i < review.rating ? 'fas' : 'far'} fa-star" style="color: #fbbf24; font-size: 0.7rem;"></i>`
                                        ).join('');

                                        listHtml += `
                                            <div class="holo-discussion-item" style="cursor: default;">
                                                <img src="${review.reviewer_avatar || '/assets/images/default-avatar.svg'}" class="holo-discussion-avatar" alt="">
                                                <div class="holo-discussion-content">
                                                    <div class="holo-discussion-title" style="font-size: 0.9rem;">
                                                        <strong>${escapeHtml(review.reviewer_name)}</strong> reviewed <strong>${escapeHtml(review.receiver_name)}</strong>
                                                    </div>
                                                    <div style="display: flex; gap: 2px; margin: 6px 0;">${stars}</div>
                                                    ${review.comment ? `<p style="margin: 8px 0 0 0; font-size: 0.85rem; color: var(--htb-text-main); line-height: 1.5;">${escapeHtml(review.comment)}</p>` : ''}
                                                    <div class="holo-discussion-meta" style="margin-top: 8px;">
                                                        <span><i class="fa-regular fa-clock"></i> ${date}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        `;
                                    });
                                    listHtml += '</div>';
                                    document.getElementById('reviews-list').innerHTML = listHtml;
                                }
                            })
                            .catch(() => {
                                document.getElementById('reviews-list').innerHTML = `
                                    <div class="holo-empty-state">
                                        <div class="holo-empty-icon"><i class="fa-solid fa-exclamation-triangle"></i></div>
                                        <h3 class="holo-empty-title">Error loading reviews</h3>
                                        <p class="holo-empty-text">Please try refreshing the page.</p>
                                    </div>
                                `;
                            });

                        function escapeHtml(text) {
                            const div = document.createElement('div');
                            div.textContent = text;
                            return div.innerHTML;
                        }
                    })();
                    </script>
                </div>
                <?php endif; ?>

                <!-- ═══════════════════════════════════════════════════════════════
                     SETTINGS TAB (Organizer Only)
                     ═══════════════════════════════════════════════════════════════ -->
                <?php if ($isOrganizer): ?>
                <div id="tab-settings" class="holo-tab-pane <?= $activeTab == 'settings' ? 'active' : '' ?>">
                    <div class="holo-section-header">
                        <div>
                            <h2 class="holo-section-title"><i class="fa-solid fa-gear"></i> Hub Settings</h2>
                            <p class="holo-section-subtitle">Manage your hub and members</p>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin-bottom: 24px;">
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/edit-group/<?= $group['id'] ?>?tab=edit"
                           class="holo-action-card"
                           style="display: flex; align-items: center; gap: 12px; padding: 16px; background: var(--holo-card-bg, white); border: 1px solid var(--holo-border-color, rgba(0,0,0,0.06)); border-radius: var(--holo-radius-sm); text-decoration: none; color: inherit; transition: var(--holo-transition);">
                            <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #3b82f6, #6366f1); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white;">
                                <i class="fa-solid fa-pen"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600; color: var(--htb-text-main);">Edit Hub</div>
                                <div style="font-size: 0.8rem; color: var(--htb-text-muted);">Update info & cover</div>
                            </div>
                        </a>

                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/edit-group/<?= $group['id'] ?>?tab=invite"
                           class="holo-action-card"
                           style="display: flex; align-items: center; gap: 12px; padding: 16px; background: var(--holo-card-bg, white); border: 1px solid var(--holo-border-color, rgba(0,0,0,0.06)); border-radius: var(--holo-radius-sm); text-decoration: none; color: inherit; transition: var(--holo-transition);">
                            <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #10b981, #14b8a6); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white;">
                                <i class="fa-solid fa-user-plus"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600; color: var(--htb-text-main);">Invite Members</div>
                                <div style="font-size: 0.8rem; color: var(--htb-text-muted);">Grow your hub</div>
                            </div>
                        </a>
                    </div>

                    <!-- Pending Requests Section -->
                    <?php if (!empty($pendingMembers)): ?>
                    <div style="margin-bottom: 32px; padding: 20px; background: linear-gradient(135deg, #fef3c7, #fde68a); border: 2px solid #f59e0b; border-radius: 16px;">
                        <h3 style="font-size: 1rem; font-weight: 700; margin: 0 0 16px 0; color: #92400e; display: flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-user-clock"></i>
                            Pending Requests (<?= count($pendingMembers) ?>)
                        </h3>
                        <p style="font-size: 0.85rem; color: #a16207; margin-bottom: 16px;">
                            These members have requested to join your hub. Approve or deny their requests.
                        </p>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <?php foreach ($pendingMembers as $pending): ?>
                                <div style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                                    <?= webp_avatar($pending['avatar_url'] ?? null, $pending['name'], 44) ?>
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: #1f2937;"><?= htmlspecialchars($pending['name']) ?></div>
                                        <div style="font-size: 0.8rem; color: #6b7280;">Waiting for approval</div>
                                    </div>
                                    <div style="display: flex; gap: 8px;">
                                        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/manage-member" method="POST" style="margin: 0;">
                                            <?= \Nexus\Core\Csrf::input() ?>
                                            <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                                            <input type="hidden" name="user_id" value="<?= $pending['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" style="padding: 8px 16px; background: linear-gradient(135deg, #10b981, #059669); border: none; border-radius: 8px; color: white; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                                                <i class="fa-solid fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/manage-member" method="POST" style="margin: 0;" onsubmit="return confirm('Deny this request?');">
                                            <?= \Nexus\Core\Csrf::input() ?>
                                            <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                                            <input type="hidden" name="user_id" value="<?= $pending['id'] ?>">
                                            <input type="hidden" name="action" value="deny">
                                            <button type="submit" style="padding: 8px 16px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #dc2626; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                                                <i class="fa-solid fa-times"></i> Deny
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Invited Members Section -->
                    <?php if (!empty($invitedMembers)): ?>
                    <div style="margin-bottom: 32px; padding: 20px; background: linear-gradient(135deg, #ede9fe, #ddd6fe); border: 2px solid #a78bfa; border-radius: 16px;">
                        <h3 style="font-size: 1rem; font-weight: 700; margin: 0 0 16px 0; color: #5b21b6; display: flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-envelope"></i>
                            Invited (<?= count($invitedMembers) ?>)
                        </h3>
                        <p style="font-size: 0.85rem; color: #6d28d9; margin-bottom: 16px;">
                            These members have been invited but haven't accepted yet.
                        </p>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <?php foreach ($invitedMembers as $invited): ?>
                                <div style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                                    <?= webp_avatar($invited['avatar_url'] ?? null, $invited['name'], 44) ?>
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: #1f2937;"><?= htmlspecialchars($invited['name']) ?></div>
                                        <div style="font-size: 0.8rem; color: #6b7280;">Invitation sent</div>
                                    </div>
                                    <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/manage-member" method="POST" style="margin: 0;" onsubmit="return confirm('Cancel this invitation?');">
                                        <?= \Nexus\Core\Csrf::input() ?>
                                        <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                                        <input type="hidden" name="user_id" value="<?= $invited['id'] ?>">
                                        <input type="hidden" name="action" value="kick">
                                        <button type="submit" style="padding: 8px 16px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #dc2626; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                                            <i class="fa-solid fa-times"></i> Cancel
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Member Management -->
                    <h3 style="font-size: 1rem; font-weight: 700; margin: 24px 0 16px 0; color: var(--htb-text-main);">
                        <i class="fa-solid fa-users-gear" style="color: var(--holo-primary); margin-right: 8px;"></i>
                        Manage Members
                    </h3>

                    <div class="holo-discussions-list">
                        <?php foreach ($members as $mem):
                            $isOwner = ($mem['id'] == $group['owner_id']);
                            if ($isOwner) continue;
                            $isOrg = ($mem['role'] === 'admin');
                        ?>
                            <div class="holo-discussion-item" style="cursor: default;">
                                <?= webp_avatar($mem['avatar_url'] ?? null, $mem['name'], 40) ?>
                                <div class="holo-discussion-content">
                                    <h3 class="holo-discussion-title"><?= htmlspecialchars($mem['name']) ?></h3>
                                    <div class="holo-discussion-meta">
                                        <?php if ($isOrg): ?>
                                            <span style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; padding: 2px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 600;">
                                                <i class="fa-solid fa-star"></i> Organiser
                                            </span>
                                        <?php else: ?>
                                            <span>Member</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <?php if ($isOrg): ?>
                                        <!-- Demote to Member -->
                                        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/manage-member" method="POST" style="margin: 0;" onsubmit="return confirm('Demote this organiser to regular member?');">
                                            <?= \Nexus\Core\Csrf::input() ?>
                                            <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                                            <input type="hidden" name="user_id" value="<?= $mem['id'] ?>">
                                            <input type="hidden" name="action" value="demote">
                                            <button type="submit" style="padding: 8px 12px; background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; color: #92400e; font-size: 0.8rem; font-weight: 600; cursor: pointer;">
                                                <i class="fa-solid fa-arrow-down"></i> Demote
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <!-- Promote to Organiser -->
                                        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/manage-member" method="POST" style="margin: 0;" onsubmit="return confirm('Promote this member to organiser?');">
                                            <?= \Nexus\Core\Csrf::input() ?>
                                            <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                                            <input type="hidden" name="user_id" value="<?= $mem['id'] ?>">
                                            <input type="hidden" name="action" value="promote">
                                            <button type="submit" style="padding: 8px 12px; background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; color: #166534; font-size: 0.8rem; font-weight: 600; cursor: pointer;">
                                                <i class="fa-solid fa-arrow-up"></i> Promote
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <!-- Remove -->
                                    <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/manage-member" method="POST" style="margin: 0;" onsubmit="return confirm('Remove this user from the hub?');">
                                        <?= \Nexus\Core\Csrf::input() ?>
                                        <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                                        <input type="hidden" name="user_id" value="<?= $mem['id'] ?>">
                                        <input type="hidden" name="action" value="kick">
                                        <button type="submit" style="padding: 8px 12px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #dc2626; font-size: 0.8rem; font-weight: 600; cursor: pointer;">
                                            <i class="fa-solid fa-times"></i> Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- .holo-tab-content -->
        </main>
    </div><!-- .holo-container -->

    <!-- ═══════════════════════════════════════════════════════════════════════
         MOBILE ACTION BAR
         ═══════════════════════════════════════════════════════════════════════ -->
    <div class="holo-action-bar">
        <?php if (isset($_SESSION['user_id'])): ?>
            <?php if ($isMember): ?>
                <div class="holo-action-info">
                    <div class="holo-action-member-badge">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>Member</span>
                    </div>
                    <div class="holo-action-subtitle"><?= count($members) ?> members</div>
                </div>
                <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/leave" method="POST" class="ajax-form" data-reload="true" style="margin: 0;">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                    <button type="submit" class="holo-action-btn secondary">Leave</button>
                </form>
            <?php elseif ($isPending): ?>
                <div class="holo-action-info">
                    <div class="holo-action-member-badge" style="background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e;">
                        <i class="fa-solid fa-clock"></i>
                        <span>Pending</span>
                    </div>
                    <div class="holo-action-subtitle">Waiting for organiser approval</div>
                </div>
                <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/leave" method="POST" class="ajax-form" data-reload="true" style="margin: 0;" onsubmit="return confirm('Cancel your join request?');">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                    <button type="submit" class="holo-action-btn secondary" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca;">
                        <i class="fa-solid fa-times"></i> Cancel Request
                    </button>
                </form>
            <?php else: ?>
                <div class="holo-action-info">
                    <h4 class="holo-action-title">Join <?= htmlspecialchars($group['name']) ?></h4>
                    <div class="holo-action-subtitle"><?= count($members) ?> members<?= $group['visibility'] === 'private' ? ' · Private hub' : '' ?></div>
                </div>
                <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/join" method="POST" class="ajax-form" data-reload="true" style="margin: 0;">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                    <button type="submit" class="holo-action-btn primary">
                        <i class="fa-solid fa-plus"></i> <?= $group['visibility'] === 'private' ? 'Request to Join' : 'Join' ?>
                    </button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <div class="holo-action-info">
                <h4 class="holo-action-title">Join this Hub</h4>
                <div class="holo-action-subtitle">Login to become a member</div>
            </div>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login" class="holo-action-btn primary">
                <i class="fa-solid fa-arrow-right-to-bracket"></i> Login
            </a>
        <?php endif; ?>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         FLOATING ACTION BUTTON
         ═══════════════════════════════════════════════════════════════════════ -->
    <?php if ($isMember): ?>
    <div class="holo-fab">
        <button class="holo-fab-main" onclick="toggleFab()" aria-label="Quick Actions">
            <i class="fa-solid fa-plus"></i>
        </button>
        <div class="holo-fab-menu" id="fabMenu">
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>/discussions/create" class="holo-fab-item">
                <i class="fa-solid fa-comments icon-discuss"></i>
                <span>Start Discussion</span>
            </a>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/create?group_id=<?= $group['id'] ?>" class="holo-fab-item">
                <i class="fa-solid fa-calendar-plus icon-event"></i>
                <span>Create Event</span>
            </a>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/members" class="holo-fab-item">
                <i class="fa-solid fa-user-plus icon-invite"></i>
                <span>Invite Members</span>
            </a>
        </div>
    </div>
    <?php endif; ?>

</div><!-- .holo-page -->

<!-- ═══════════════════════════════════════════════════════════════════════════
     REVIEW MODAL
     ═══════════════════════════════════════════════════════════════════════════ -->
<?php if ($isMember): ?>
<div id="reviewModal" class="holo-modal-overlay">
    <div class="holo-modal">
        <div class="holo-modal-header">
            <h3 class="holo-modal-title">Leave a Review</h3>
            <button onclick="closeReviewModal()" class="holo-modal-close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="holo-modal-body">
            <div style="text-align: center; margin-bottom: 20px;">
                <img id="reviewMemberAvatar" src="" style="width: 64px; height: 64px; border-radius: 50%; margin-bottom: 10px;" loading="lazy">
                <div style="font-weight: 700; font-size: 1rem;" id="reviewMemberName"></div>
            </div>

            <form id="reviewForm" action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>/reviews" method="POST">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="receiver_id" id="reviewReceiverId" value="">

                <div class="holo-form-group" style="text-align: center;">
                    <label class="holo-form-label">Your Rating</label>
                    <div class="holo-star-rating" id="starRating">
                        <i class="far fa-star" data-rating="1"></i>
                        <i class="far fa-star" data-rating="2"></i>
                        <i class="far fa-star" data-rating="3"></i>
                        <i class="far fa-star" data-rating="4"></i>
                        <i class="far fa-star" data-rating="5"></i>
                    </div>
                    <input type="hidden" name="rating" id="ratingInput" value="" required>
                    <div id="ratingLabel" style="color: var(--htb-text-muted); font-size: 0.9rem; margin-top: 8px;"></div>
                </div>

                <div class="holo-form-group">
                    <label class="holo-form-label">Comment (optional)</label>
                    <textarea name="comment" class="holo-form-textarea" placeholder="Share your experience..."></textarea>
                </div>

                <button type="submit" id="submitBtn" class="holo-form-submit" disabled>
                    <i class="fa-solid fa-paper-plane" style="margin-right: 8px;"></i> Submit Review
                </button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════════════════════
     JAVASCRIPT
     ═══════════════════════════════════════════════════════════════════════════ -->
<script>
// Tab Switching
function switchTab(tabId) {
    // Hide all panes
    document.querySelectorAll('.holo-tab-pane').forEach(el => el.classList.remove('active'));
    // Deactivate all buttons
    document.querySelectorAll('.holo-tab-btn').forEach(el => el.classList.remove('active'));

    // Show target pane
    const target = document.getElementById('tab-' + tabId);
    if (target) {
        target.classList.add('active');
    }

    // Activate target button
    const btn = document.getElementById('btn-' + tabId);
    if (btn) btn.classList.add('active');

    // Scroll tab into view on mobile
    if (btn && window.innerWidth <= 768) {
        btn.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
    }
}

// FAB Toggle
function toggleFab() {
    const btn = document.querySelector('.holo-fab-main');
    const menu = document.getElementById('fabMenu');
    btn.classList.toggle('active');
    menu.classList.toggle('show');
}

// Close FAB when clicking outside
document.addEventListener('click', function(e) {
    const fab = document.querySelector('.holo-fab');
    if (fab && !fab.contains(e.target)) {
        document.querySelector('.holo-fab-main')?.classList.remove('active');
        document.getElementById('fabMenu')?.classList.remove('show');
    }
});

<?php if ($isMember): ?>
// Review Modal Functions
function openReviewModal(memberId, memberName, memberAvatar) {
    document.getElementById('reviewReceiverId').value = memberId;
    document.getElementById('reviewMemberName').textContent = memberName;
    document.getElementById('reviewMemberAvatar').src = memberAvatar || '/assets/images/default-avatar.svg';
    document.getElementById('reviewModal').classList.add('active');
    document.body.style.overflow = 'hidden';

    // Reset form
    document.getElementById('ratingInput').value = '';
    document.getElementById('ratingLabel').textContent = '';
    document.getElementById('submitBtn').disabled = true;
    document.querySelectorAll('#starRating i').forEach(s => {
        s.classList.remove('fas', 'active');
        s.classList.add('far');
    });
}

function closeReviewModal() {
    document.getElementById('reviewModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Star Rating Interaction
(function() {
    const stars = document.querySelectorAll('#starRating i');
    const input = document.getElementById('ratingInput');
    const label = document.getElementById('ratingLabel');
    const btn = document.getElementById('submitBtn');
    const labels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];

    function setRating(rating) {
        input.value = rating;
        btn.disabled = false;
        label.textContent = labels[rating];

        stars.forEach((s, i) => {
            if (i < rating) {
                s.classList.remove('far');
                s.classList.add('fas', 'active');
            } else {
                s.classList.remove('fas', 'active');
                s.classList.add('far');
            }
        });
    }

    stars.forEach(star => {
        star.addEventListener('click', function(e) {
            e.preventDefault();
            setRating(parseInt(this.dataset.rating));
        });

        star.addEventListener('mouseenter', function() {
            const rating = parseInt(this.dataset.rating);
            stars.forEach((s, i) => {
                if (i < rating) s.style.color = '#fbbf24';
            });
        });

        star.addEventListener('mouseleave', function() {
            const currentRating = parseInt(input.value) || 0;
            stars.forEach((s, i) => {
                if (!s.classList.contains('active')) s.style.color = '';
            });
        });
    });
})();

// Close modal on overlay click
document.getElementById('reviewModal').addEventListener('click', function(e) {
    if (e.target === this) closeReviewModal();
});

// Close on escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('reviewModal').classList.contains('active')) {
        closeReviewModal();
    }
});
<?php endif; ?>

// ============================================
// GOLD STANDARD - Native App Features
// ============================================

// Offline Indicator
(function initOfflineIndicator() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;

    function handleOffline() {
        banner.classList.add('visible');
        if (navigator.vibrate) navigator.vibrate(100);
    }

    function handleOnline() {
        banner.classList.remove('visible');
    }

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    if (!navigator.onLine) {
        handleOffline();
    }
})();

// Form Submission Offline Protection
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!navigator.onLine) {
            e.preventDefault();
            alert('You are offline. Please connect to the internet to submit.');
            return;
        }
    });
});

// Button Press States
document.querySelectorAll('.holo-tab-btn, .holo-section-action, .holo-member-btn, button').forEach(btn => {
    btn.addEventListener('pointerdown', function() {
        this.style.transform = 'scale(0.96)';
    });
    btn.addEventListener('pointerup', function() {
        this.style.transform = '';
    });
    btn.addEventListener('pointerleave', function() {
        this.style.transform = '';
    });
});

// Dynamic Theme Color
(function initDynamicThemeColor() {
    const metaTheme = document.querySelector('meta[name="theme-color"]');
    if (!metaTheme) {
        const meta = document.createElement('meta');
        meta.name = 'theme-color';
        meta.content = '#db2777';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#db2777');
        }
    }

    const observer = new MutationObserver(updateThemeColor);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });

    updateThemeColor();
})();

// ═══════════════════════════════════════════════════════════════════════════
// GROUP FEED FUNCTIONALITY
// ═══════════════════════════════════════════════════════════════════════════

let groupFeedOffset = 0;
let groupFeedLoading = false;
let groupFeedHasMore = true;
const GROUP_ID = <?= $group['id'] ?>;
const CURRENT_USER_ID = <?= $currentUserId ?>;
const BASE_PATH = '<?= Nexus\Core\TenantContext::getBasePath() ?>';
console.log('BASE_PATH:', BASE_PATH);

// Time elapsed helper
function timeElapsed(datetime) {
    const now = new Date();
    const then = new Date(datetime);
    const diff = Math.floor((now - then) / 1000);

    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd';
    if (diff < 2592000) return Math.floor(diff / 604800) + 'w';
    return Math.floor(diff / 2592000) + 'mo';
}

// Load group feed
async function loadGroupFeed(groupId, loadMore = false) {
    if (groupFeedLoading || (!loadMore && groupFeedOffset > 0)) return;

    groupFeedLoading = true;
    const container = document.getElementById('groupFeedPosts');
    const loadMoreBtn = document.getElementById('groupFeedLoadMore');

    if (!loadMore) {
        container.innerHTML = '<div class="feed-loading"><i class="fa-solid fa-spinner fa-spin"></i><span>Loading posts...</span></div>';
        groupFeedOffset = 0;
    }

    try {
        const apiUrl = BASE_PATH + '/api/social/feed';
        console.log('Fetching feed from:', apiUrl);
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache'
            },
            cache: 'no-store',
            body: JSON.stringify({
                group_id: groupId,
                offset: groupFeedOffset,
                limit: 10,
                filter: 'posts'
            })
        });

        const data = await response.json();

        if (!loadMore) {
            container.innerHTML = '';
        }

        if (data.success && data.items && data.items.length > 0) {
            data.items.forEach(post => {
                container.appendChild(createPostElement(post));
            });

            groupFeedOffset += data.items.length;
            groupFeedHasMore = data.items.length >= 10;
            loadMoreBtn.style.display = groupFeedHasMore ? 'block' : 'none';
        } else if (!loadMore) {
            container.innerHTML = `
                <div class="feed-empty">
                    <i class="fa-regular fa-comment-dots"></i>
                    <h3>No posts yet</h3>
                    <p>Be the first to share something with the hub!</p>
                </div>
            `;
            loadMoreBtn.style.display = 'none';
        }
    } catch (error) {
        console.error('Error loading feed:', error);
        if (!loadMore) {
            container.innerHTML = `
                <div class="feed-empty">
                    <i class="fa-solid fa-exclamation-triangle"></i>
                    <h3>Failed to load feed</h3>
                    <p>Please try again later</p>
                </div>
            `;
        }
    }

    groupFeedLoading = false;
}

// Create post element
function createPostElement(post) {
    const div = document.createElement('div');
    div.className = 'group-feed-post';
    div.id = 'post-' + post.id;

    const isLiked = post.is_liked ? 'liked' : '';
    const likeIcon = post.is_liked ? 'fa-solid' : 'fa-regular';
    const likesCount = post.likes_count || 0;
    const commentsCount = post.comments_count || 0;
    const authorAvatar = post.author_avatar || '/assets/img/defaults/default_avatar.webp';
    const authorName = post.author_name || 'Anonymous';

    let imageHtml = '';
    if (post.image_url) {
        imageHtml = `<img src="${escapeHtml(post.image_url)}" class="feed-post-image" loading="lazy">`;
    }

    let deleteBtn = '';
    if (post.user_id == CURRENT_USER_ID) {
        deleteBtn = `<button class="feed-action-btn" onclick="deleteGroupPost(${post.id})" title="Delete"><i class="fa-solid fa-trash"></i></button>`;
    }

    div.innerHTML = `
        <div class="feed-post-header">
            <a href="${BASE_PATH}/profile/${post.user_id}">
                <img src="${escapeHtml(authorAvatar)}" class="feed-post-avatar" alt="${escapeHtml(authorName)}" loading="lazy">
            </a>
            <div class="feed-post-author">
                <a href="${BASE_PATH}/profile/${post.user_id}" class="feed-post-author-name">${escapeHtml(authorName)}</a>
                <div class="feed-post-meta">${timeElapsed(post.created_at)}</div>
            </div>
        </div>
        <div class="feed-post-content">${escapeHtml(post.content || '').replace(/\n/g, '<br>')}</div>
        ${imageHtml}
        <div class="feed-post-actions">
            <button class="feed-action-btn ${isLiked}" onclick="toggleGroupLike(this, ${post.id})">
                <i class="${likeIcon} fa-heart"></i>
                <span>${likesCount > 0 ? likesCount + ' ' : ''}Like${likesCount !== 1 ? 's' : ''}</span>
            </button>
            <button class="feed-action-btn" onclick="toggleGroupComments(${post.id})">
                <i class="fa-regular fa-comment"></i>
                <span>${commentsCount > 0 ? commentsCount + ' ' : ''}Comment${commentsCount !== 1 ? 's' : ''}</span>
            </button>
            <button class="feed-action-btn" onclick="shareGroupPost(${post.id}, '${escapeHtml(authorName)}')">
                <i class="fa-solid fa-share"></i>
                <span>Share</span>
            </button>
            ${deleteBtn}
        </div>
        <div id="comments-${post.id}" class="feed-post-comments" style="display:none;">
            <div class="feed-comment-input-row">
                <input type="text" class="feed-comment-input" placeholder="Write a comment..." onkeydown="if(event.key==='Enter')submitGroupComment(this, ${post.id})">
                <button class="feed-action-btn" onclick="submitGroupComment(this.previousElementSibling, ${post.id})">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </div>
            <div class="comments-list"></div>
        </div>
    `;

    return div;
}

// Escape HTML helper
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Submit new group post
async function submitGroupPost(groupId) {
    const content = document.getElementById('groupPostContent').value.trim();
    const imageInput = document.getElementById('groupPostImage');
    const submitBtn = document.querySelector('.composer-submit-btn');

    if (!content && !imageInput.files.length) {
        alert('Please enter some content or add an image');
        return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Posting...';

    try {
        // Use FormData to send both content and image together
        const formData = new FormData();
        formData.append('content', content);
        formData.append('group_id', groupId);
        formData.append('visibility', 'public');

        // Add image if present
        if (imageInput.files.length > 0) {
            formData.append('image', imageInput.files[0]);
        }

        // Create the post with image in one request
        const response = await fetch(BASE_PATH + '/api/social/create-post', {
            method: 'POST',
            body: formData  // Don't set Content-Type header - browser sets it with boundary
        });

        const data = await response.json();

        if (data.success) {
            // Clear form
            document.getElementById('groupPostContent').value = '';
            clearGroupPostImage();

            // Reload feed to show new post
            groupFeedOffset = 0;
            loadGroupFeed(groupId);
        } else {
            alert(data.error || 'Failed to create post');
        }
    } catch (error) {
        console.error('Error creating post:', error);
        alert('Failed to create post. Please try again.');
    }

    submitBtn.disabled = false;
    submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> <span>Post</span>';
}

// Image preview
document.getElementById('groupPostImage')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('groupPostImagePreview');
            preview.querySelector('img').src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
});

function clearGroupPostImage() {
    const input = document.getElementById('groupPostImage');
    const preview = document.getElementById('groupPostImagePreview');
    if (input) input.value = '';
    if (preview) {
        preview.style.display = 'none';
        preview.querySelector('img').src = '';
    }
}

// Debounce tracking for group likes
const groupLikeDebounce = {};

// Toggle like
async function toggleGroupLike(btn, postId) {
    // Debounce: prevent rapid clicks (500ms cooldown)
    if (groupLikeDebounce[postId]) {
        return;
    }
    groupLikeDebounce[postId] = true;
    setTimeout(() => { delete groupLikeDebounce[postId]; }, 500);

    try {
        const response = await fetch(BASE_PATH + '/api/social/like', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                target_type: 'post',
                target_id: postId
            })
        });

        const data = await response.json();

        // API returns {status: 'liked'/'unliked', likes_count: N}
        if (data.status === 'liked' || data.status === 'unliked') {
            const isLiked = data.status === 'liked';
            const icon = btn.querySelector('i');
            const span = btn.querySelector('span');
            const count = data.likes_count || 0;

            btn.classList.toggle('liked', isLiked);
            icon.className = (isLiked ? 'fa-solid' : 'fa-regular') + ' fa-heart';
            span.textContent = (count > 0 ? count + ' ' : '') + 'Like' + (count !== 1 ? 's' : '');
        }
    } catch (error) {
        console.error('Error toggling like:', error);
    }
}

// Check if mobile device
function isMobileDevice() {
    return window.innerWidth <= 768 || ('ontouchstart' in window);
}

// Toggle comments section
async function toggleGroupComments(postId) {
    // On mobile, use the mobile comment sheet instead
    if (isMobileDevice() && typeof openMobileCommentSheet === 'function') {
        openMobileCommentSheet('post', postId, '');
        return;
    }

    // Desktop: toggle inline comments
    const section = document.getElementById('comments-' + postId);
    if (!section) return;

    const isVisible = section.style.display !== 'none';
    section.style.display = isVisible ? 'none' : 'block';

    if (!isVisible) {
        // Load comments
        await loadGroupComments(postId);
    }
}

// Available reactions
const REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '🎉'];

// Load comments with nested replies
async function loadGroupComments(postId) {
    const section = document.getElementById('comments-' + postId);
    const list = section.querySelector('.comments-list');
    list.innerHTML = '<div class="gf-no-comments"><i class="fa-solid fa-spinner fa-spin"></i><br>Loading...</div>';

    try {
        const response = await fetch(BASE_PATH + '/api/social/comments', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'fetch_comments',
                target_type: 'post',
                target_id: postId
            })
        });

        const data = await response.json();

        if ((data.success || data.status === 'success') && data.comments) {
            if (data.comments.length === 0) {
                list.innerHTML = '<div class="gf-no-comments"><i class="fa-regular fa-comment-dots"></i><br>No comments yet. Be the first!</div>';
                return;
            }
            list.innerHTML = data.comments.map(comment => renderComment(comment, postId)).join('');
        }
    } catch (error) {
        console.error('Error loading comments:', error);
        list.innerHTML = '<div class="gf-no-comments" style="color:#ef4444;"><i class="fa-solid fa-exclamation-triangle"></i><br>Failed to load comments</div>';
    }
}

// Render a single comment with nested replies
function renderComment(comment, postId, isReply = false) {
    const avatar = escapeHtml(comment.author_avatar || comment.avatar_url || '/assets/img/defaults/default_avatar.webp');
    const name = escapeHtml(comment.author_name || comment.user_name || 'User');
    const content = formatMentions(escapeHtml(comment.content || ''));
    const time = timeElapsed(comment.created_at);
    const commentId = comment.id;
    const isOwner = comment.is_owner || (comment.user_id == CURRENT_USER_ID);

    // Build reactions HTML
    let reactionsHtml = '';
    if (comment.reactions && Object.keys(comment.reactions).length > 0) {
        const userReactions = comment.user_reactions || [];
        reactionsHtml = '<div class="gf-reactions">' +
            Object.entries(comment.reactions).map(([emoji, count]) => {
                const isActive = userReactions.includes(emoji) ? 'active' : '';
                return `<span class="gf-reaction ${isActive}" onclick="gfToggleReaction(${commentId}, '${emoji}', ${postId})">${emoji} ${count}</span>`;
            }).join('') +
        '</div>';
    }

    // Build replies HTML
    let repliesHtml = '';
    if (comment.replies && comment.replies.length > 0) {
        repliesHtml = '<div class="gf-replies">' +
            comment.replies.map(reply => renderComment(reply, postId, true)).join('') +
        '</div>';
    }

    return `
        <div class="gf-comment-wrapper" data-comment-id="${commentId}">
            <div class="gf-comment">
                <img src="${avatar}" class="gf-comment-avatar" alt="${name}" loading="lazy">
                <div class="gf-comment-body">
                    <div class="gf-comment-header">
                        <span class="gf-comment-author">${name}</span>
                        <span class="gf-comment-time">${time}</span>
                        ${comment.is_edited ? '<span class="gf-comment-time">(edited)</span>' : ''}
                    </div>
                    <div class="gf-comment-content">${content}</div>
                    ${reactionsHtml}
                    <div class="gf-comment-actions">
                        <button type="button" class="gf-comment-action" onclick="gfShowReactionPicker(this, ${commentId}, ${postId})">
                            <i class="fa-regular fa-face-smile"></i> React
                        </button>
                        ${!isReply ? `<button type="button" class="gf-comment-action" onclick="gfShowReplyForm(${commentId}, ${postId})">
                            <i class="fa-solid fa-reply"></i> Reply
                        </button>` : ''}
                        ${isOwner ? `<button type="button" class="gf-comment-action" onclick="gfDeleteComment(${commentId}, ${postId})">
                            <i class="fa-solid fa-trash"></i>
                        </button>` : ''}
                    </div>
                    <div class="gf-reaction-picker" id="reaction-picker-${commentId}">
                        ${REACTIONS.map(r => `<span onclick="gfToggleReaction(${commentId}, '${r}', ${postId})">${r}</span>`).join('')}
                    </div>
                </div>
            </div>
            <div class="gf-reply-form" id="reply-form-${commentId}" style="display:none;">
                <input type="text" placeholder="Write a reply..." onkeypress="if(event.key==='Enter'){event.preventDefault();gfSubmitReply(this, ${postId}, ${commentId});}">
                <button type="button" onclick="gfSubmitReply(this.previousElementSibling, ${postId}, ${commentId})">Reply</button>
            </div>
            ${repliesHtml}
        </div>
    `;
}

// Format @mentions in content
function formatMentions(text) {
    return text.replace(/@(\w+)/g, '<span class="mention">@$1</span>');
}

// Show/hide reaction picker (prefixed to avoid conflict with global SocialInteractions)
function gfShowReactionPicker(btn, commentId, postId) {
    // Close all other pickers first
    document.querySelectorAll('.gf-reaction-picker.show').forEach(p => p.classList.remove('show'));
    const picker = document.getElementById('reaction-picker-' + commentId);
    if (picker) {
        picker.classList.toggle('show');
        // Close when clicking outside
        setTimeout(() => {
            document.addEventListener('click', function closePickerHandler(e) {
                if (!picker.contains(e.target) && !btn.contains(e.target)) {
                    picker.classList.remove('show');
                    document.removeEventListener('click', closePickerHandler);
                }
            });
        }, 10);
    }
}

// Toggle emoji reaction on comment (prefixed to avoid conflict with global SocialInteractions)
async function gfToggleReaction(commentId, emoji, postId) {
    try {
        const response = await fetch(BASE_PATH + '/api/social/reaction', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                target_type: 'comment',
                target_id: commentId,
                emoji: emoji
            })
        });
        const data = await response.json();
        if (data.success) {
            // Close picker and reload comments to show updated reactions
            document.querySelectorAll('.gf-reaction-picker.show').forEach(p => p.classList.remove('show'));
            await loadGroupComments(postId);
        }
    } catch (error) {
        console.error('Error toggling reaction:', error);
    }
}

// Show reply form (prefixed to avoid conflict with global SocialInteractions)
function gfShowReplyForm(commentId, postId) {
    console.log('gfShowReplyForm called:', commentId, postId);
    // Hide all other reply forms
    document.querySelectorAll('.gf-reply-form').forEach(f => f.style.display = 'none');
    const form = document.getElementById('reply-form-' + commentId);
    console.log('Found form:', form);
    if (form) {
        form.style.display = 'flex';
        const input = form.querySelector('input');
        if (input) input.focus();
    } else {
        console.error('Reply form not found for comment:', commentId);
    }
}

// Submit reply to a comment (prefixed to avoid conflict with global SocialInteractions)
async function gfSubmitReply(input, postId, parentId) {
    console.log('gfSubmitReply called:', { postId, parentId, input: input?.value });
    const content = input.value.trim();
    if (!content) {
        console.log('Empty content, returning');
        return;
    }

    input.disabled = true;

    try {
        console.log('Sending reply request to:', BASE_PATH + '/api/social/reply');
        const response = await fetch(BASE_PATH + '/api/social/reply', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                target_type: 'post',
                target_id: postId,
                parent_id: parentId,
                content: content
            })
        });

        const data = await response.json();
        console.log('Reply response:', data);

        if (data.success || data.status === 'success') {
            input.value = '';
            input.parentElement.style.display = 'none';
            await loadGroupComments(postId);
            updateCommentCount(postId, 1);
        } else {
            console.error('Reply failed:', data.error);
            alert(data.error || 'Failed to post reply');
        }
    } catch (error) {
        console.error('Error submitting reply:', error);
        alert('Network error while posting reply');
    }

    input.disabled = false;
}

// Delete comment (prefixed to avoid conflict with global SocialInteractions)
async function gfDeleteComment(commentId, postId) {
    if (!confirm('Delete this comment?')) return;

    try {
        const response = await fetch(BASE_PATH + '/api/social/delete-comment', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                comment_id: commentId
            })
        });

        const data = await response.json();

        if (data.success) {
            await loadGroupComments(postId);
            updateCommentCount(postId, -1);
        } else {
            alert(data.error || 'Failed to delete comment');
        }
    } catch (error) {
        console.error('Error deleting comment:', error);
    }
}

// Update comment count display
function updateCommentCount(postId, delta) {
    const post = document.getElementById('post-' + postId);
    if (post) {
        const commentBtn = post.querySelector('.feed-post-actions button:nth-child(2) span');
        if (commentBtn) {
            const currentText = commentBtn.textContent;
            const match = currentText.match(/(\d+)/);
            const count = Math.max(0, (match ? parseInt(match[1]) : 0) + delta);
            commentBtn.textContent = (count > 0 ? count + ' ' : '') + 'Comment' + (count !== 1 ? 's' : '');
        }
    }
}

// Submit comment (main comment, not reply)
async function submitGroupComment(input, postId) {
    const content = input.value.trim();
    if (!content) return;

    input.disabled = true;

    try {
        const response = await fetch(BASE_PATH + '/api/social/comments', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'submit_comment',
                target_type: 'post',
                target_id: postId,
                content: content
            })
        });

        const data = await response.json();

        if (data.success || data.status === 'success') {
            input.value = '';
            await loadGroupComments(postId);
            updateCommentCount(postId, 1);
        } else {
            alert(data.error || 'Failed to post comment');
        }
    } catch (error) {
        console.error('Error submitting comment:', error);
    }

    input.disabled = false;
}

// Delete post
async function deleteGroupPost(postId) {
    if (!confirm('Are you sure you want to delete this post?')) return;

    try {
        const response = await fetch(BASE_PATH + '/api/social/delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                target_type: 'post',
                target_id: postId
            })
        });

        const data = await response.json();

        if (data.success) {
            const post = document.getElementById('post-' + postId);
            if (post) {
                post.remove();
            }
        } else {
            alert(data.error || 'Failed to delete post');
        }
    } catch (error) {
        console.error('Error deleting post:', error);
    }
}

// Share post
function shareGroupPost(postId, authorName) {
    const caption = prompt(`Share this post by ${authorName}?\n\nAdd a comment (optional):`);
    if (caption === null) return;

    fetch(BASE_PATH + '/api/social/share', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            target_type: 'post',
            target_id: postId,
            comment: caption
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Post shared to your feed!');
        } else {
            alert(data.error || 'Failed to share post');
        }
    })
    .catch(err => {
        console.error('Error sharing:', err);
        alert('Failed to share post');
    });
}

// Load feed when Feed tab is activated
const originalSwitchTab = switchTab;
switchTab = function(tabId) {
    originalSwitchTab(tabId);

    if (tabId === 'feed' && groupFeedOffset === 0) {
        loadGroupFeed(GROUP_ID);
    }
};

// Auto-load feed if tab is already active on page load
document.addEventListener('DOMContentLoaded', function() {
    const feedTab = document.getElementById('tab-feed');
    if (feedTab && feedTab.classList.contains('active')) {
        loadGroupFeed(GROUP_ID);
    }
});
</script>

<?php
// Mobile Bottom Sheets - Now included centrally in footer.php
?>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
