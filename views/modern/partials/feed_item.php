<?php
// Modern Feed Item Partial
// Expects: $item (array), $isLoggedIn (bool), $userId (int), $timeElapsed (callable)

// 1. Helper for time elapsed
if (!isset($timeElapsed) || !is_callable($timeElapsed)) {
    $timeElapsed = function ($datetime) {
        $diff = (new DateTime)->diff(new DateTime($datetime));
        if ($diff->y) return $diff->y . 'y';
        if ($diff->m) return $diff->m . 'm';
        if ($diff->d >= 7) return floor($diff->d / 7) . 'w';
        if ($diff->d) return $diff->d . 'd';
        if ($diff->h) return $diff->h . 'h';
        if ($diff->i) return $diff->i . 'm';
        return 'Just now';
    };
}

// 2. Variable normalization
$type = $item['type'] ?? 'post';
if (!isset($item['type']) && isset($item['content'])) $type = 'post';

$postId = $item['id'];
$authorUserId = $item['user_id'] ?? null;
$authorName = $item['author_name'] ?? '';
// Handle empty/null author names
if (empty(trim($authorName)) || $authorName === 'Unknown') {
    $authorName = 'Anonymous';
}
$authorAvatar = $item['author_avatar'] ?? '/assets/img/defaults/default_avatar.webp';
// Handle empty avatars
if (empty($authorAvatar)) {
    $authorAvatar = '/assets/img/defaults/default_avatar.webp';
}
$basePath = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '';
$authorProfileLink = $authorUserId ? $basePath . '/profile/' . $authorUserId : '#';
$createdAt = $item['created_at'];
$isLiked = !empty($item['is_liked']);
$likesCount = (int)($item['likes_count'] ?? 0);
// Like button styling - matches listings/show.php pill design
$likeButtonBg = $isLiked ? 'linear-gradient(135deg, #ec4899, #f43f5e)' : 'rgba(100,116,139,0.1)';
$likeButtonColor = $isLiked ? '#fff' : 'var(--text-main, #374151)';
$commentsCount = $item['comments_count'] ?? 0;

// 2b. Determine social interaction target
// If this is a shared post (has parent_id), social interactions should target the ORIGINAL content
// This ensures comments/likes are unified between the feed view and the detail page
$socialTargetType = $type;
$socialTargetId = $postId;
if (!empty($item['parent_id']) && $item['parent_id'] > 0 && !empty($item['parent_type'])) {
    $socialTargetType = $item['parent_type'];
    $socialTargetId = (int)$item['parent_id'];
}

// 3. Activity Verb
$verb = '';
$object = '';
if ($type === 'listing') {
    $verb = 'posted a';
    $object = strtoupper($item['extra_2'] ?? 'LISTING');
} elseif ($type === 'event') {
    $verb = 'created an event';
} elseif ($type === 'goal') {
    $verb = 'set a goal';
} elseif ($type === 'poll') {
    $verb = 'created a poll';
} elseif ($type === 'resource') {
    $verb = 'uploaded a file';
} elseif ($type === 'volunteering') {
    $verb = 'needs volunteers';
} elseif ($type === 'review') {
    $verb = 'left a review';
}

$eventStart = ($type === 'event') ? ($item['extra_1'] ?? null) : null;
$location   = $item['location'] ?? null;
$authorLocation = $item['author_location'] ?? null;
$postImage  = ($type === 'post') ? ($item['image_url'] ?? $item['extra_3'] ?? null) : null;
$postVideo  = ($type === 'post') ? ($item['video_url'] ?? $item['extra_4'] ?? null) : null;
// Listing images - stored in extra_3 or image_url
$listingImage = ($type === 'listing') ? ($item['extra_3'] ?? $item['image_url'] ?? null) : null;
$bodyContent = $item['content'] ?? $item['body'] ?? '';
$recommendationBadges = $item['recommendation_badges'] ?? [];

// Group context - for posts made in groups
$groupId = $item['group_id'] ?? null;
$groupName = $item['group_name'] ?? null;
$groupImage = $item['group_image'] ?? null;
$groupLocation = $item['group_location'] ?? null;
?>

<?php
// Map item type to filter type for client-side filtering
$feedTypeMap = [
    'post' => 'posts',
    'listing' => 'listings',
    'event' => 'events',
    'goal' => 'goals',
    'poll' => 'polls',
    'volunteering' => 'volunteering',
    'resource' => 'resources',
    'group' => 'groups',
    'review' => 'reviews'
];
$feedType = $feedTypeMap[$type] ?? 'posts';

// For listings, map 'offer' -> 'offers', 'request' -> 'requests' to match filter
$listingType = '';
if ($type === 'listing') {
    $rawType = strtolower($item['extra_2'] ?? 'offer');
    $listingType = ($rawType === 'request') ? 'requests' : 'offers';
}
?>
<div class="fb-card" data-feed-type="<?= $feedType ?>" data-listing-type="<?= $listingType ?>">
    <?php if ($groupId && $groupName): ?>
    <!-- Group Context Banner -->
    <a href="<?= $basePath ?>/groups/<?= (int)$groupId ?>" class="feed-group-context">
        <div class="feed-group-context-avatar">
            <?php if ($groupImage): ?>
            <?= webp_image($groupImage, htmlspecialchars($groupName), '', ['loading' => 'lazy']) ?>
            <?php else: ?>
            <span><?= strtoupper(substr($groupName, 0, 1)) ?></span>
            <?php endif; ?>
        </div>
        <div class="feed-group-context-info">
            <div class="feed-group-context-name">
                <i class="fa-solid fa-users"></i>
                <?= htmlspecialchars($groupName) ?>
            </div>
            <?php if ($groupLocation): ?>
            <div class="feed-group-context-location">
                <i class="fa-solid fa-location-dot"></i>
                <?= htmlspecialchars($groupLocation) ?>
            </div>
            <?php endif; ?>
        </div>
        <i class="fa-solid fa-chevron-right feed-group-context-arrow"></i>
    </a>
    <?php endif; ?>

    <div class="feed-item-header">
        <div class="feed-item-author">
            <a href="<?= $authorProfileLink ?>" class="feed-item-avatar-link">
                <?= webp_avatar($authorAvatar, $authorName, 40) ?>
            </a>
            <div>
                <div class="feed-item-author-name">
                    <a href="<?= $authorProfileLink ?>" class="feed-item-author-link"><?= htmlspecialchars($authorName) ?></a>
                    <?php if ($groupId && $groupName): ?>
                        <span class="feed-item-verb">posted in</span>
                        <a href="<?= $basePath ?>/groups/<?= (int)$groupId ?>" class="feed-item-group-link"><?= htmlspecialchars($groupName) ?></a>
                    <?php elseif ($verb): ?>
                        <span class="feed-item-verb"><?= $verb ?></span>
                        <?php if ($object): ?>
                            <span class="feed-item-object"><?= htmlspecialchars($object) ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="feed-item-meta">
                    <?= $timeElapsed($createdAt) ?>
                    <?php
                    // Show group location if from group, otherwise author's location
                    $displayLocation = $groupLocation ?: $authorLocation ?: $location;
                    if ($displayLocation): ?>
                        · <i class="fa-solid fa-location-dot" style="font-size:11px; margin-right:2px;"></i> <?= htmlspecialchars($displayLocation) ?>
                    <?php endif; ?>
                    · <i class="fa-solid fa-globe" style="font-size:11px;" title="Public"></i>
                </div>
                <?php if (!empty($recommendationBadges)): ?>
                <div class="feed-recommendation-badges">
                    <?php foreach ($recommendationBadges as $badge): ?>
                    <span class="feed-badge" style="--badge-color: <?= htmlspecialchars($badge['color']) ?>;" title="<?= htmlspecialchars($badge['description'] ?? '') ?>">
                        <i class="fa-solid <?= htmlspecialchars($badge['icon']) ?>"></i>
                        <?= htmlspecialchars($badge['label']) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <!-- 3-Dot Menu (Facebook-style) -->
        <div class="feed-item-menu-container">
            <button type="button" class="feed-item-menu-btn" onclick="toggleFeedItemMenu(this)" aria-label="More options">
                <i class="fa-solid fa-ellipsis"></i>
            </button>
            <div class="feed-item-menu-dropdown">
                <?php if ($isLoggedIn ?? false): ?>
                    <?php if ($authorUserId != ($_SESSION['user_id'] ?? 0)): ?>
                        <button type="button" onclick="hidePost(<?= $postId ?>); closeFeedMenus();" class="feed-menu-item">
                            <i class="fa-solid fa-eye-slash"></i>
                            <div>
                                <span class="feed-menu-label">Hide post</span>
                                <span class="feed-menu-hint">See fewer posts like this</span>
                            </div>
                        </button>
                        <button type="button" onclick="muteUser(<?= $authorUserId ?>); closeFeedMenus();" class="feed-menu-item">
                            <i class="fa-solid fa-volume-xmark"></i>
                            <div>
                                <span class="feed-menu-label">Mute <?= htmlspecialchars(explode(' ', $authorName)[0]) ?></span>
                                <span class="feed-menu-hint">Stop seeing their posts</span>
                            </div>
                        </button>
                        <button type="button" onclick="reportPost(<?= $postId ?>); closeFeedMenus();" class="feed-menu-item feed-menu-item-danger">
                            <i class="fa-solid fa-flag"></i>
                            <div>
                                <span class="feed-menu-label">Report post</span>
                                <span class="feed-menu-hint">I'm concerned about this post</span>
                            </div>
                        </button>
                    <?php endif; ?>
                    <?php if ($authorUserId == ($_SESSION['user_id'] ?? 0) || ($_SESSION['user_role'] ?? '') === 'admin'): ?>
                        <button type="button" onclick="deletePost('<?= $type ?>', <?= $postId ?>); closeFeedMenus();" class="feed-menu-item feed-menu-item-danger">
                            <i class="fa-solid fa-trash"></i>
                            <div>
                                <span class="feed-menu-label">Delete post</span>
                                <span class="feed-menu-hint">Remove this permanently</span>
                            </div>
                        </button>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="<?= $basePath ?>/login" class="feed-menu-item">
                        <i class="fa-solid fa-right-to-bracket"></i>
                        <div>
                            <span class="feed-menu-label">Log in</span>
                            <span class="feed-menu-hint">Sign in to interact</span>
                        </div>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="feed-item-body">
        <?php if (!empty($item['title'])): ?>
            <?php
            // Make title clickable based on content type
            $titleLink = null;
            switch ($type) {
                case 'listing':
                    $titleLink = $basePath . '/listings/' . $postId;
                    break;
                case 'event':
                    $titleLink = $basePath . '/events/' . $postId;
                    break;
                case 'goal':
                    $titleLink = $basePath . '/goals/' . $postId;
                    break;
                case 'poll':
                    $titleLink = $basePath . '/polls/' . $postId;
                    break;
                case 'volunteering':
                    $titleLink = $basePath . '/volunteering/' . $postId;
                    break;
                case 'resource':
                    $titleLink = $basePath . '/resources/' . $postId;
                    break;
            }
            ?>
            <?php if ($titleLink): ?>
                <a href="<?= $titleLink ?>" class="feed-item-title-link" style="text-decoration: none; color: inherit; display: block;">
                    <div class="feed-item-title"><?= htmlspecialchars($item['title']) ?></div>
                </a>
            <?php else: ?>
                <div class="feed-item-title"><?= htmlspecialchars($item['title']) ?></div>
            <?php endif; ?>
        <?php endif; ?>

        <?php
        $content = htmlspecialchars($bodyContent);

        // 4. SHARED/RECURSIVE RENDERING (FIXED v2 - Simplified)
        if (!empty($item['parent_id']) && $item['parent_id'] > 0) {
            $parentId = (int) $item['parent_id'];
            $parentType = $item['parent_type'] ?? 'post';
            $sharedData = null;
            $shareError = null;

            // DEBUG: Output values to HTML comment
            echo "<!-- DEBUG SHARED: parent_id={$parentId} parent_type={$parentType} -->";

            try {
                // Determine DB Class - check both possible class names
                $dbForShare = null;
                if (class_exists('\Nexus\Core\Database')) {
                    $dbForShare = '\Nexus\Core\Database';
                } elseif (class_exists('\Nexus\Core\DatabaseWrapper')) {
                    $dbForShare = '\Nexus\Core\DatabaseWrapper';
                }

                if ($dbForShare) {

                    // ========== POSTS ==========
                    if ($parentType === 'post') {
                        $stmt = $dbForShare::query(
                            "SELECT p.*, u.name as author_name, u.avatar_url as author_avatar,
                                    g.id as group_id, g.name as group_name, g.image_url as group_image,
                                    g.location as group_location
                             FROM feed_posts p
                             LEFT JOIN users u ON p.user_id = u.id
                             LEFT JOIN `groups` g ON p.group_id = g.id
                             WHERE p.id = ?",
                            [$parentId]
                        );
                        $p = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($p) {
                            // Build label based on whether it's a group post
                            $postLabel = 'Shared Post';
                            if (!empty($p['group_id']) && !empty($p['group_name'])) {
                                $postLabel = 'Group Post';
                            }

                            $sharedData = [
                                'author' => $p['author_name'] ?? 'Unknown',
                                'avatar' => $p['author_avatar'] ?? '/assets/img/defaults/default_avatar.webp',
                                'time' => $timeElapsed($p['created_at']),
                                'content' => $p['content'],
                                'image' => $p['image_url'] ?? null,
                                'title' => null,
                                'label' => $postLabel,
                                'group_id' => $p['group_id'] ?? null,
                                'group_name' => $p['group_name'] ?? null,
                                'group_image' => $p['group_image'] ?? null,
                                'group_location' => $p['group_location'] ?? null
                            ];
                        }
                    }

                    // ========== LISTINGS ==========
                    elseif ($parentType === 'listing') {
                        $stmt = $dbForShare::query(
                            "SELECT l.*, u.name as author_name, u.avatar_url as author_avatar 
                             FROM listings l 
                             LEFT JOIN users u ON l.user_id = u.id 
                             WHERE l.id = ?",
                            [$parentId]
                        );
                        $l = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($l) {
                            $sharedData = [
                                'author' => $l['author_name'] ?? 'Unknown',
                                'avatar' => $l['author_avatar'] ?? '/assets/img/defaults/default_avatar.webp',
                                'time' => $timeElapsed($l['created_at']),
                                'content' => $l['description'],
                                'image' => $l['image_url'] ?? null,
                                'title' => ($l['title'] ?? 'Listing') . ' (' . ucfirst($l['type'] ?? 'offer') . ')',
                                'label' => 'Shared Listing'
                            ];
                        }
                    }

                    // ========== EVENTS ==========
                    elseif ($parentType === 'event') {
                        $stmt = $dbForShare::query(
                            "SELECT e.*, u.name as author_name, u.avatar_url as author_avatar 
                             FROM events e 
                             LEFT JOIN users u ON e.user_id = u.id 
                             WHERE e.id = ?",
                            [$parentId]
                        );
                        $e = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($e) {
                            $sharedData = [
                                'author' => $e['author_name'] ?? 'Unknown',
                                'avatar' => $e['author_avatar'] ?? '/assets/img/defaults/default_avatar.webp',
                                'time' => $timeElapsed($e['created_at']),
                                'content' => $e['description'],
                                'image' => $e['cover_image'] ?? null,
                                'title' => ($e['title'] ?? 'Event') . ' (Event)',
                                'label' => 'Shared Event'
                            ];
                        }
                    }

                    // ========== GOALS ==========
                    elseif ($parentType === 'goal') {
                        $stmt = $dbForShare::query(
                            "SELECT g.*, u.name as author_name, u.avatar_url as author_avatar 
                             FROM goals g 
                             LEFT JOIN users u ON g.user_id = u.id 
                             WHERE g.id = ?",
                            [$parentId]
                        );
                        $g = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($g) {
                            $sharedData = [
                                'author' => $g['author_name'] ?? 'Unknown',
                                'avatar' => $g['author_avatar'] ?? '/assets/img/defaults/default_avatar.webp',
                                'time' => $timeElapsed($g['created_at']),
                                'content' => $g['description'],
                                'image' => null,
                                'title' => ($g['title'] ?? 'Goal') . ' (Goal)',
                                'label' => 'Shared Goal'
                            ];
                        }
                    }

                    // ========== POLLS ==========
                    elseif ($parentType === 'poll') {
                        $stmt = $dbForShare::query(
                            "SELECT p.*, u.name as author_name, u.avatar_url as author_avatar
                             FROM polls p
                             LEFT JOIN users u ON p.user_id = u.id
                             WHERE p.id = ?",
                            [$parentId]
                        );
                        $p = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($p) {
                            $sharedData = [
                                'author' => $p['author_name'] ?? 'Unknown',
                                'avatar' => $p['author_avatar'] ?? '/assets/img/defaults/default_avatar.webp',
                                'time' => $timeElapsed($p['created_at']),
                                'content' => $p['description'] ?? '',
                                'image' => null,
                                'title' => ($p['question'] ?? 'Poll') . ' (Poll)',
                                'label' => 'Shared Poll'
                            ];
                        }
                    }

                    // ========== VOLUNTEERING ==========
                    elseif ($parentType === 'volunteering') {
                        $stmt = $dbForShare::query(
                            "SELECT v.*, u.name as author_name, u.avatar_url as author_avatar
                             FROM vol_opportunities v
                             LEFT JOIN users u ON v.created_by = u.id
                             WHERE v.id = ?",
                            [$parentId]
                        );
                        $v = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($v) {
                            $sharedData = [
                                'author' => $v['author_name'] ?? 'Unknown',
                                'avatar' => $v['author_avatar'] ?? '/assets/img/defaults/default_avatar.webp',
                                'time' => $timeElapsed($v['created_at']),
                                'content' => $v['description'],
                                'image' => null,
                                'title' => ($v['title'] ?? 'Volunteering') . ' • ' . ($v['credits_offered'] ?? 0) . ' Credits',
                                'label' => 'Shared Volunteering Opportunity'
                            ];
                        }
                    }
                }
            } catch (Exception $e) {
                $shareError = $e->getMessage();
            }

            // DEBUG: Confirm what we got
            echo "<!-- DEBUG RESULT: sharedData=" . ($sharedData ? 'FOUND' : 'NULL') . " shareError=" . ($shareError ?? 'none') . " -->";

            // Display User's Caption (if any)
            $content = preg_replace_callback('/(https?:\/\/[^\s]+)/', function($m) {
                $url = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
                return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener" style="color:#4f46e5; text-decoration:underline;">' . $m[1] . '</a>';
            }, $content);
            if (!empty(trim($content))) {
                echo nl2br($content);
            }

            // Render Shared Card
            if ($sharedData) {
                $parentContent = htmlspecialchars($sharedData['content'] ?? '');
                $parentContent = preg_replace_callback('/(https?:\/\/[^\s]+)/', function($m) {
                    $url = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
                    return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener" style="color:#4f46e5; text-decoration:underline;">' . $m[1] . '</a>';
                }, $parentContent);

                // Check if this is a group post
                $hasGroup = !empty($sharedData['group_id']) && !empty($sharedData['group_name']);
                $groupLink = $hasGroup ? ($basePath . '/groups/' . $sharedData['group_id']) : '';

                echo '
                <div class="feed-shared-card">';

                // Group context banner (if from a group)
                if ($hasGroup) {
                    $groupImg = $sharedData['group_image'] ?? '/assets/img/defaults/default_group.png';
                    $groupLoc = $sharedData['group_location'] ?? '';
                    echo '
                    <a href="' . $groupLink . '" class="feed-shared-group-banner" style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: linear-gradient(135deg, rgba(99,102,241,0.08), rgba(168,85,247,0.08)); border-bottom: 1px solid var(--feed-border, #e5e7eb); text-decoration: none; transition: background 0.2s;">
                        <img src="' . htmlspecialchars($groupImg) . '" style="width: 32px; height: 32px; border-radius: 8px; object-fit: cover; border: 1px solid rgba(99,102,241,0.2);" alt="" loading="lazy">
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-weight: 600; font-size: 13px; color: var(--feed-text-primary, #111); display: flex; align-items: center; gap: 6px;">
                                <i class="fa-solid fa-users" style="color: #6366f1; font-size: 11px;"></i>
                                ' . htmlspecialchars($sharedData['group_name']) . '
                            </div>';
                    if ($groupLoc) {
                        echo '
                            <div style="font-size: 11px; color: var(--feed-text-muted, #6b7280); margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <i class="fa-solid fa-location-dot" style="margin-right: 3px;"></i>' . htmlspecialchars($groupLoc) . '
                            </div>';
                    }
                    echo '
                        </div>
                        <i class="fa-solid fa-chevron-right" style="color: var(--feed-text-muted, #9ca3af); font-size: 12px;"></i>
                    </a>';
                }

                echo '
                    <div class="feed-shared-header">
                        <img src="' . htmlspecialchars($sharedData['avatar']) . '" class="feed-shared-avatar" loading="lazy">
                        <div style="flex-grow:1;">
                            <div class="feed-shared-author">' . htmlspecialchars($sharedData['author']) . ' <span class="feed-shared-label">• ' . $sharedData['label'] . '</span></div>
                            <div class="feed-shared-time">' . $sharedData['time'] . '</div>
                        </div>
                    </div>';

                if (!empty($sharedData['title'])) {
                    echo '<div class="feed-shared-title">' . htmlspecialchars($sharedData['title']) . '</div>';
                }

                if (!empty($parentContent)) {
                    echo '<div class="feed-shared-content">' . nl2br($parentContent) . '</div>';
                }

                if (!empty($sharedData['image'])) {
                    echo '<div style="border-top: 1px solid var(--feed-border);"><img src="' . htmlspecialchars($sharedData['image']) . '" style="width: 100%; display: block;" loading="lazy"></div>';
                }
                echo '</div>';
            } else {
                // Error Fallback - show debug info
                echo '
                <div style="margin-top: 12px; padding: 12px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #991b1b; font-size: 13px;">
                    <i class="fa-solid fa-triangle-exclamation" style="margin-right: 6px;"></i> 
                    <strong>Shared content not found</strong><br>
                    <small style="color:#6b7280;">Type: ' . htmlspecialchars($parentType) . ' | ID: ' . $parentId . ($shareError ? ' | Error: ' . htmlspecialchars($shareError) : '') . '</small>
                </div>';
            }
        } else {
            // Standard rendering (no parent - original post)
            // Skip body content for reviews since we show it in the review card
            if ($type !== 'review') {
                $content = preg_replace_callback('/(https?:\/\/[^\s]+)/', function($m) {
                    $url = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
                    return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener" style="color:#4f46e5; text-decoration:underline;">' . $m[1] . '</a>';
                }, $content);
                echo nl2br($content);
            }
        }

        // Logic for Viewing Original Listing/Event
        if ($type === 'listing') {
            $link = \Nexus\Core\TenantContext::getBasePath() . '/listings/' . $postId;
            echo '<div style="margin-top:10px;"><a href="' . $link . '" style="color:#4f46e5; font-weight:600;">View Original Listing &rarr;</a></div>';
        }
        ?>

        <!-- RICH MEDIA: Post Image -->
        <?php if ($type === 'post' && $postImage): ?>
            <div style="overflow: hidden; border-radius: 12px; margin-top: 12px; border: 1px solid #e5e7eb; aspect-ratio: 16/9; background: #f3f4f6;">
                <?= webp_image($postImage, 'Post image', '', ['style' => 'width: 100%; height: 100%; object-fit: cover; display: block;']) ?>
            </div>
        <?php endif; ?>

        <!-- RICH MEDIA: Post Video -->
        <?php if ($type === 'post' && $postVideo): ?>
            <div style="overflow: hidden; border-radius: 12px; margin-top: 12px; border: 1px solid var(--border-color, #e5e7eb); background: #000;">
                <video
                    controls
                    preload="metadata"
                    playsinline
                    style="width: 100%; max-height: 500px; display: block;"
                    poster=""
                >
                    <source src="<?= htmlspecialchars($postVideo) ?>" type="video/mp4">
                    <source src="<?= htmlspecialchars($postVideo) ?>" type="video/webm">
                    <source src="<?= htmlspecialchars($postVideo) ?>" type="video/ogg">
                    Your browser does not support the video tag.
                </video>
            </div>
        <?php endif; ?>

        <?php if ($eventStart): ?>
            <div class="feed-event-date-box">
                <div class="feed-event-calendar">
                    <div class="feed-event-month"><?= date('M', strtotime($eventStart)) ?></div>
                    <div class="feed-event-day"><?= date('d', strtotime($eventStart)) ?></div>
                </div>
                <div>
                    <div style="font-weight: 600; color: var(--feed-text-primary);"><?= date('l, F j @ g:i A', strtotime($eventStart)) ?></div>
                    <div style="font-size: 13px; color: var(--feed-text-secondary);"><?= htmlspecialchars($location ?? 'Online') ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($type === 'listing'): ?>
        <?php
        $lType = $item['extra_2'] ?? 'offer';
        $listingClass = 'feed-listing-default';
        $icon = 'fa-newspaper';
        if ($lType === 'offer') {
            $listingClass = 'feed-listing-offer';
            $icon = 'fa-hand-holding-heart';
        } elseif ($lType === 'request') {
            $listingClass = 'feed-listing-request';
            $icon = 'fa-hand-holding-hand';
        }
        ?>
        <?php if ($listingImage): ?>
            <!-- Listing Image -->
            <div class="feed-listing-image-container" style="position: relative; overflow: hidden; border-radius: 12px; margin: 0 0 12px 0; border: 1px solid var(--feed-border, #e5e7eb); aspect-ratio: 16/9; background: var(--feed-bg-secondary, #f3f4f6);">
                <a href="<?= $basePath ?>/listings/<?= $postId ?>" style="display: block; width: 100%; height: 100%;">
                    <?= webp_image($listingImage, htmlspecialchars($item['title'] ?? 'Listing image'), 'loaded', ['style' => 'width: 100%; height: 100%; object-fit: cover; display: block;', isset($isFirstFeedItem) && $isFirstFeedItem ? 'fetchpriority' : 'loading' => isset($isFirstFeedItem) && $isFirstFeedItem ? 'high' : 'lazy']) ?>
                </a>
                <!-- Type badge overlay -->
                <div style="position: absolute; top: 12px; left: 12px; background: <?= $lType === 'offer' ? 'linear-gradient(135deg, #10b981, #059669)' : 'linear-gradient(135deg, #f59e0b, #d97706)' ?>; color: white; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">
                    <i class="fa-solid <?= $icon ?>" style="margin-right: 4px;"></i>
                    <?= ucfirst($lType) ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Listing Banner (no image) -->
            <div class="feed-listing-banner <?= $listingClass ?>">
                <div style="text-align: center; z-index: 1;">
                    <i class="fa-solid <?= $icon ?> feed-listing-icon"></i>
                    <div class="feed-listing-category"><?= htmlspecialchars($item['extra_1'] ?? '') ?></div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($type === 'goal'): ?>
        <div class="feed-section-box" style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; color: var(--feed-text-muted); margin-bottom: 2px;">Target</div>
                <div style="font-size: 14px; font-weight: 600; color: var(--feed-text-primary);"><?= !empty($item['extra_2']) ? date('M j, Y', strtotime($item['extra_2'])) : 'No Deadline' ?></div>
            </div>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/goals/<?= $postId ?>" class="fds-btn-secondary" style="text-decoration: none; font-size:13px;">View Goal</a>
        </div>
    <?php endif; ?>

    <?php if ($type === 'poll'): ?>
        <div class="feed-section-box">
            <div style="font-size: 13px; color: var(--feed-text-secondary); margin-bottom: 10px;"><?= $item['extra_2'] ?? 0 ?> votes · <?= ucfirst($item['extra_1'] ?? 'Poll') ?></div>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/polls/<?= $postId ?>" class="fds-btn-primary" style="text-decoration: none; width: 100%; display: block; text-align: center;">Vote Now</a>
        </div>
    <?php endif; ?>

    <?php if ($type === 'resource'): ?>
        <div class="feed-section-box" style="display: flex; align-items: center; gap: 16px;">
            <div style="width: 48px; height: 48px; background: var(--feed-bg-card); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: var(--feed-text-muted); border: 1px solid var(--feed-border);">
                <i class="fa-regular fa-file"></i>
            </div>
            <div style="flex-grow: 1;">
                <div style="font-weight: 600; color: var(--feed-text-primary);"><?= htmlspecialchars($item['title'] ?? 'Resource') ?></div>
                <div style="font-size: 13px; color: var(--feed-text-secondary);"><?= strtoupper($item['extra_1'] ?? 'FILE') ?> · <?= $item['extra_3'] ?? 0 ?> downloads</div>
            </div>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/download.php?id=<?= $postId ?>" class="fds-btn-primary" style="text-decoration: none;"><i class="fa-solid fa-download"></i></a>
        </div>
    <?php endif; ?>

    <?php if ($type === 'volunteering'): ?>
        <div class="feed-section-box feed-volunteer-box">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div style="font-size: 13px; color: #1e40af; font-weight:600; margin-bottom: 6px; text-transform:uppercase; letter-spacing:0.5px;">Volunteer Opportunity</div>
                <div style="background:var(--feed-bg-card); color:#3b82f6; padding:2px 8px; border-radius:12px; font-weight:700; font-size:0.8rem; border:1px solid #dbeafe;"><?= $item['extra_2'] ?? 0 ?> Credits</div>
            </div>

            <div class="feed-item-title" style="margin-bottom: 5px;"><?= htmlspecialchars($item['title'] ?? 'Opp') ?></div>
            <div style="font-size: 0.9rem; color: var(--feed-text-secondary); margin-bottom: 10px; display:flex; gap:6px; align-items:center;">
                <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($item['extra_1'] ?? 'Remote') ?>
            </div>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/volunteering/<?= $postId ?>" class="fds-btn-secondary" style="text-decoration: none; display:block; text-align:center; background:var(--feed-bg-card);">I'm Interested</a>
        </div>
    <?php endif; ?>

    <?php if ($type === 'event'): ?>
        <div class="feed-section-box feed-event-box">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div style="font-size: 13px; color: #be185d; font-weight:600; margin-bottom: 6px; text-transform:uppercase; letter-spacing:0.5px;">Upcoming Event</div>
                <div style="background:var(--feed-bg-card); color:#be185d; padding:2px 8px; border-radius:12px; font-weight:700; font-size:0.8rem; border:1px solid #fbcfe8;">
                    <?= !empty($item['extra_2']) ? date('M j', strtotime($item['extra_2'])) : 'TBD' ?>
                </div>
            </div>

            <div class="feed-item-title" style="margin-bottom: 5px;"><?= htmlspecialchars($item['title'] ?? 'Event') ?></div>
            <div style="font-size: 0.9rem; color: var(--feed-text-secondary); margin-bottom: 10px; display:flex; gap:6px; align-items:center;">
                <i class="fas fa-map-pin" style="color:#9d174d;"></i> <?= htmlspecialchars($item['extra_1'] ?? 'TBD') ?>
            </div>

            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/events/<?= $postId ?>" class="fds-btn-secondary" style="text-decoration: none; display:block; text-align:center; background:var(--feed-bg-card);">RSVP Now</a>
        </div>
    <?php endif; ?>

    <?php if ($type === 'review'): ?>
        <?php
        $reviewRating = (int)($item['extra_1'] ?? 5);
        $receiverId = $item['extra_2'] ?? null;
        $receiverName = $item['extra_3'] ?? 'Member';
        $receiverAvatar = $item['extra_4'] ?? '/assets/img/defaults/default_avatar.webp';
        if (empty($receiverAvatar)) $receiverAvatar = '/assets/img/defaults/default_avatar.webp';
        ?>
        <div class="feed-section-box feed-review-box" style="background: linear-gradient(135deg, rgba(251,191,36,0.08), rgba(245,158,11,0.05)); border: 1px solid rgba(251,191,36,0.2); border-radius: 12px; padding: 16px;">
            <!-- Review Header with Receiver Info -->
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                <a href="<?= $basePath ?>/profile/<?= (int)$receiverId ?>" style="flex-shrink: 0;">
                    <img src="<?= htmlspecialchars($receiverAvatar) ?>" loading="lazy" alt="<?= htmlspecialchars($receiverName) ?>" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(251,191,36,0.3);">
                </a>
                <div style="flex: 1; min-width: 0;">
                    <div style="font-size: 13px; color: var(--feed-text-muted); margin-bottom: 2px;">Review for</div>
                    <a href="<?= $basePath ?>/profile/<?= (int)$receiverId ?>" style="font-weight: 700; font-size: 16px; color: var(--feed-text-primary); text-decoration: none;"><?= htmlspecialchars($receiverName) ?></a>
                </div>
                <!-- Star Rating -->
                <div style="display: flex; align-items: center; gap: 2px; background: linear-gradient(135deg, #fbbf24, #f59e0b); padding: 6px 12px; border-radius: 20px;">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="fa-solid fa-star" style="font-size: 12px; color: <?= $i <= $reviewRating ? '#fff' : 'rgba(255,255,255,0.3)' ?>;"></i>
                    <?php endfor; ?>
                    <span style="margin-left: 4px; font-weight: 700; color: #fff; font-size: 13px;"><?= $reviewRating ?>/5</span>
                </div>
            </div>

            <!-- Review Comment (already shown in body, but can emphasize here) -->
            <?php if (!empty($item['body'])): ?>
            <div style="background: var(--feed-bg-card, #fff); border-radius: 10px; padding: 12px 14px; margin-top: 8px; border-left: 3px solid #fbbf24;">
                <i class="fa-solid fa-quote-left" style="color: #fbbf24; font-size: 14px; margin-right: 6px;"></i>
                <span style="font-style: italic; color: var(--feed-text-secondary);"><?= htmlspecialchars(mb_substr($item['body'], 0, 200)) ?><?= mb_strlen($item['body']) > 200 ? '...' : '' ?></span>
            </div>
            <?php endif; ?>

            <!-- Action Button -->
            <div style="margin-top: 12px; text-align: center;">
                <a href="<?= $basePath ?>/profile/<?= (int)$receiverId ?>" class="fds-btn-secondary" style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px; background: var(--feed-bg-card); border: 1px solid rgba(251,191,36,0.3);">
                    <i class="fa-solid fa-user"></i> View Profile
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Reactions Summary Row (Facebook-style) -->
    <?php if ($likesCount > 0 || $commentsCount > 0): ?>
    <div class="feed-reactions-row">
        <?php if ($likesCount > 0): ?>
        <div class="feed-reactions-left likes-count-clickable" onclick="event.stopPropagation(); showLikers('<?= $socialTargetType ?>', <?= $socialTargetId ?>)" title="See who liked this">
            <span class="feed-reaction-icons">
                <span class="feed-reaction-icon feed-reaction-heart"><i class="fa-solid fa-heart"></i></span>
            </span>
            <span class="feed-reactions-text">
                <?php if ($isLiked && $likesCount === 1): ?>
                    You
                <?php elseif ($isLiked && $likesCount > 1): ?>
                    You and <?= $likesCount - 1 ?> <?= $likesCount === 2 ? 'other' : 'others' ?>
                <?php else: ?>
                    <?= $likesCount ?> <?= $likesCount === 1 ? 'person' : 'people' ?>
                <?php endif; ?>
            </span>
        </div>
        <?php endif; ?>
        <div class="feed-reactions-right">
            <?php if ($commentsCount > 0): ?>
                <span class="feed-stat-link" onclick="toggleCommentSection('<?= $socialTargetType ?>', <?= $socialTargetId ?>)"><?= $commentsCount ?> <?= $commentsCount === 1 ? 'comment' : 'comments' ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Action Buttons (Facebook-style full-width) -->
    <div class="feed-action-bar" role="group" aria-label="Post actions">
        <button type="button" onclick="event.preventDefault(); toggleLike(this, '<?= $socialTargetType ?>', <?= $socialTargetId ?>); return false;" class="feed-action-btn <?= $isLiked ? 'liked' : '' ?>" aria-label="<?= $isLiked ? 'Unlike' : 'Like' ?>" aria-pressed="<?= $isLiked ? 'true' : 'false' ?>">
            <i class="<?= $isLiked ? 'fa-solid' : 'fa-regular' ?> fa-heart"></i>
            <span class="like-label">Like</span>
        </button>
        <button type="button" onclick="event.preventDefault(); toggleCommentSection('<?= $socialTargetType ?>', <?= $socialTargetId ?>); return false;" class="feed-action-btn" aria-label="Comment" aria-expanded="false">
            <i class="fa-regular fa-comment"></i>
            <span>Comment</span>
        </button>
        <?php if ($type === 'listing' && $authorUserId && $authorUserId != ($userId ?? 0)): ?>
            <!-- Message button for listings (contact the seller/requester) -->
            <a href="<?= $basePath ?>/messages/thread/<?= $authorUserId ?>" class="feed-action-btn" aria-label="Message" style="text-decoration: none;">
                <i class="fa-regular fa-envelope"></i>
                <span>Message</span>
            </a>
        <?php else: ?>
            <button type="button" onclick="event.preventDefault(); repostToFeed('<?= $socialTargetType ?>', <?= $socialTargetId ?>, '<?= addslashes($authorName) ?>'); return false;" class="feed-action-btn" aria-label="Share">
                <i class="fa-solid fa-share"></i>
                <span>Share</span>
            </button>
        <?php endif; ?>
    </div>

    <div id="comments-section-<?= $socialTargetType ?>-<?= $socialTargetId ?>" class="feed-comments-section" style="display:none;">
        <?php if ($isLoggedIn ?? false): ?>
            <div style="display: flex; gap: 8px; margin-bottom: 12px;">
                <img src="<?= $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp' ?>" loading="lazy" class="feed-shared-avatar" style="flex-shrink: 0;">
                <div style="flex-grow: 1; position: relative;">
                    <input type="text" class="feed-comment-input" placeholder="Write a comment..." onkeydown="if(event.key === 'Enter') submitComment(this, '<?= $socialTargetType ?>', <?= $socialTargetId ?>)">
                    <i class="fa-regular fa-paper-plane" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: var(--feed-text-muted); cursor: pointer;" onclick="submitComment(this.previousElementSibling, '<?= $socialTargetType ?>', <?= $socialTargetId ?>)"></i>
                </div>
            </div>
        <?php endif; ?>
        <div class="comments-list"></div>
    </div>
</div>