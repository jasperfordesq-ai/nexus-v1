<?php
// =========================================================
// MODERN: HOME FEED (Ported from Nexus Social v5.3)
// =========================================================

if (session_status() === PHP_SESSION_NONE) session_start();

use Nexus\Core\TenantContext;

// 1. AUTH CHECK
$isLoggedIn = !empty($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? 0;
$tenantId = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::get()['id'] : ($_SESSION['current_tenant_id'] ?? 1);


// ---------------------------------------------------------
// 1b. FEED FILTER PARAMETERS (From UniversalFeedFilter)
// ---------------------------------------------------------
$feedAlgoMode = $_GET['algo'] ?? 'ranked';  // 'ranked' (EdgeRank) or 'recent' (chronological)
$feedLocationMode = $_GET['location'] ?? 'global';  // 'global' or 'nearby'
$feedRadius = max(10, min(500, (int)($_GET['radius'] ?? 500)));  // 10-500km, default 500 (covers Ireland)
$feedFilterType = $_GET['filter'] ?? 'all';  // Content type filter
$feedSubFilter = $_GET['subfilter'] ?? null;  // Sub-filter (e.g., offers/requests)

// Ensure user avatar is in session (for users who logged in before this was added)
if ($isLoggedIn && empty($_SESSION['user_avatar'])) {
    try {
        $userRow = \Nexus\Core\Database::query("SELECT avatar_url FROM users WHERE id = ?", [$userId])->fetch(\PDO::FETCH_ASSOC);
        $avatarUrl = !empty($userRow['avatar_url']) ? trim($userRow['avatar_url']) : '';
        $_SESSION['user_avatar'] = $avatarUrl ?: '/assets/img/defaults/default_avatar.webp';
    } catch (\Exception $e) {
        $_SESSION['user_avatar'] = '/assets/img/defaults/default_avatar.webp';
    }
}

// Import Models
use Nexus\Models\Goal;
use Nexus\Models\Poll;
use Nexus\Models\ResourceItem;
use Nexus\Models\FeedPost;

// ---------------------------------------------------------
// 2a. FETCH DATA FOR MULTI-MODULE COMPOSER
// ---------------------------------------------------------
$composerCategories = [];
$composerEventCategories = [];
$composerVolCategories = [];
$composerResourceCategories = [];
$composerOrganizations = [];
$composerGroups = [];
$composerAttributes = [];

if ($isLoggedIn) {
    try {
        // Listing categories
        if (class_exists('\Nexus\Models\Category')) {
            $composerCategories = \Nexus\Models\Category::getByType('listing') ?: [];
            $composerEventCategories = \Nexus\Models\Category::getByType('event') ?: [];
            $composerVolCategories = \Nexus\Models\Category::getByType('volunteering') ?: [];
            $composerResourceCategories = \Nexus\Models\Category::getByType('resource') ?: [];
        }

        // Listing attributes for dynamic filtering
        if (class_exists('\Nexus\Models\Attribute')) {
            $composerAttributes = \Nexus\Models\Attribute::all() ?: [];
        }

        // Organizations for volunteering (users with profile_type = 'organisation')
        $dbClassInit = class_exists('\Nexus\Core\Database') ? '\Nexus\Core\Database' : '\Nexus\Core\DatabaseWrapper';
        if (method_exists($dbClassInit, 'query')) {
            $composerOrganizations = $dbClassInit::query(
                "SELECT id, COALESCE(organization_name, name) as name FROM users WHERE profile_type = 'organisation' AND tenant_id = ? ORDER BY name LIMIT 100",
                [$tenantId]
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Groups for events
            $composerGroups = $dbClassInit::query(
                "SELECT id, name FROM groups WHERE tenant_id = ? AND is_active = 1 ORDER BY name LIMIT 100",
                [$tenantId]
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (Exception $e) {
        // Silently continue if data fetch fails
    }
}

// ---------------------------------------------------------
// 2. HELPER FUNCTION (Defined as Variable to prevent Scope Errors)
// ---------------------------------------------------------
$timeElapsed = function ($datetime) {
    try {
        if (empty($datetime)) return '';
        $diff = (new DateTime)->diff(new DateTime($datetime));
        if ($diff->y) return $diff->y . 'y';
        if ($diff->m) return $diff->m . 'm';
        if ($diff->d >= 7) return floor($diff->d / 7) . 'w';
        if ($diff->d) return $diff->d . 'd';
        if ($diff->h) return $diff->h . 'h';
        if ($diff->i) return $diff->i . 'm';
        return 'Just now';
    } catch (Exception $e) {
        return '';
    }
};

// ---------------------------------------------------------
// DYNAMIC SEO & HERO CONTENT (Loaded from Tenant Database)
// ---------------------------------------------------------
// This pulls meta_title, meta_description, h1_headline, and hero_intro
// directly from the tenant record. Configure these in Super Admin > Tenant Edit.

$heroContent = \Nexus\Core\SEO::getTenantHeroContent();
$hero_title = $heroContent['h1'];
$hero_subtitle = $heroContent['intro'];

// Get tenant name for hero type label
$tenantName = \Nexus\Core\TenantContext::get()['name'] ?? 'Community Platform';
$hero_type = "Welcome to " . $tenantName;

// SEO is now loaded automatically via SEO::loadFromTenant() when SEO::load() is called
// No need for hardcoded tenant-specific SEO here anymore!

// ---------------------------------------------------------
// PUBLIC SECTOR DEMO: Mock Data - REMOVED
// ---------------------------------------------------------
// Mock data removed - was causing confusion on new tenants.

// ---------------------------------------------------------
// 3. BACKEND ACTIONS
// ---------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // A. Handle Multi-Module Post Submission
    $postType = $_POST['post_type'] ?? 'post';
    $hasFormData = !empty($_POST['content']) || !empty($_POST['listing_title']) || !empty($_POST['goal_title'])
                || !empty($_POST['vol_title']) || !empty($_POST['event_title']) || !empty($_POST['poll_question'])
                || !empty($_FILES['image']['name']) || !empty($_FILES['video']['name']);

    if ($hasFormData && !isset($_POST['action'])) {
        if (!$isLoggedIn) {
            header("Location: /login");
            exit;
        }
        try {
            // Handle image upload for any module type
            $imageUrl = null;
            if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($_FILES['image']['tmp_name']);
                $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

                if (!in_array($mimeType, $allowedMimes)) {
                    throw new \Exception('Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.');
                }

                $imageInfo = @getimagesize($_FILES['image']['tmp_name']);
                if ($imageInfo === false) {
                    throw new \Exception('Uploaded file is not a valid image.');
                }

                if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                    throw new \Exception('Image file is too large. Maximum size is 5MB.');
                }

                $uploadDir = __DIR__ . '/../../httpdocs/uploads/posts/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
                $ext = $extensions[$mimeType] ?? 'jpg';
                $fileName = bin2hex(random_bytes(16)) . '.' . $ext;
                $targetFile = $uploadDir . $fileName;

                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                    $imageUrl = '/uploads/posts/' . $fileName;
                }
            }

            // Handle video upload for posts
            $videoUrl = null;
            if (!empty($_FILES['video']['name']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
                error_log("[Video Upload] Starting video upload: " . $_FILES['video']['name']);

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($_FILES['video']['tmp_name']);
                error_log("[Video Upload] Detected MIME type: " . $mimeType);

                $allowedVideoMimes = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska'];

                if (!in_array($mimeType, $allowedVideoMimes)) {
                    throw new \Exception('Invalid video type. Only MP4, WebM, OGG, MOV, AVI, and MKV videos are allowed. Got: ' . $mimeType);
                }

                if ($_FILES['video']['size'] > 100 * 1024 * 1024) {
                    throw new \Exception('Video file is too large. Maximum size is 100MB.');
                }

                $uploadDir = __DIR__ . '/../../httpdocs/uploads/videos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                    error_log("[Video Upload] Created directory: " . $uploadDir);
                }

                $videoExtensions = [
                    'video/mp4' => 'mp4',
                    'video/webm' => 'webm',
                    'video/ogg' => 'ogg',
                    'video/quicktime' => 'mov',
                    'video/x-msvideo' => 'avi',
                    'video/x-matroska' => 'mkv'
                ];
                $ext = $videoExtensions[$mimeType] ?? 'mp4';
                $fileName = bin2hex(random_bytes(16)) . '.' . $ext;
                $targetFile = $uploadDir . $fileName;

                if (move_uploaded_file($_FILES['video']['tmp_name'], $targetFile)) {
                    $videoUrl = '/uploads/videos/' . $fileName;
                    error_log("[Video Upload] Success! Saved to: " . $videoUrl);
                } else {
                    error_log("[Video Upload] Failed to move uploaded file to: " . $targetFile);
                }
            } elseif (!empty($_FILES['video']['name'])) {
                error_log("[Video Upload] Upload error code: " . ($_FILES['video']['error'] ?? 'unknown'));
            }

            $dbClassPost = class_exists('\Nexus\Core\Database') ? '\Nexus\Core\Database' : '\Nexus\Core\DatabaseWrapper';
            $basePath = \Nexus\Core\TenantContext::getBasePath();
            $moduleId = null;
            $feedContent = '';

            switch ($postType) {
                case 'listing':
                    // Create listing in listings table
                    $listingTitle = trim($_POST['listing_title'] ?? '');
                    $listingDesc = trim($_POST['listing_description'] ?? '');
                    $listingType = $_POST['listing_type'] ?? 'offer';
                    $listingCatId = (int)($_POST['listing_category_id'] ?? 0);

                    if ($listingTitle && $listingDesc) {
                        // Get user location
                        $user = \Nexus\Models\User::findById($userId);
                        $userCoords = \Nexus\Models\User::getCoordinates($userId);

                        if (class_exists('\Nexus\Models\Listing')) {
                            $moduleId = \Nexus\Models\Listing::create(
                                $userId, $listingTitle, $listingDesc, $listingType,
                                $listingCatId ?: null, $imageUrl,
                                $user['location'] ?? null,
                                $userCoords['latitude'] ?? null,
                                $userCoords['longitude'] ?? null
                            );

                            // Handle SDGs
                            if (!empty($_POST['sdg_goals']) && $moduleId) {
                                $sdgJson = json_encode($_POST['sdg_goals']);
                                $dbClassPost::query("UPDATE listings SET sdg_goals = ? WHERE id = ?", [$sdgJson, $moduleId]);
                            }

                            // Handle attributes
                            if (!empty($_POST['attributes']) && is_array($_POST['attributes']) && $moduleId) {
                                foreach ($_POST['attributes'] as $attrId => $val) {
                                    if ($val) {
                                        $dbClassPost::query(
                                            "INSERT INTO listing_attributes (listing_id, attribute_id, value) VALUES (?, ?, ?)",
                                            [$moduleId, (int)$attrId, '1']
                                        );
                                    }
                                }
                            }
                        }

                        $typeEmoji = $listingType === 'offer' ? '🎁' : '🙋';
                        $feedContent = "$typeEmoji New " . ucfirst($listingType) . ": $listingTitle\n\n$listingDesc";
                    }
                    break;

                case 'goal':
                    // Create goal in goals table
                    $goalTitle = trim($_POST['goal_title'] ?? '');
                    $goalDesc = trim($_POST['goal_description'] ?? '');
                    $goalDeadline = $_POST['goal_deadline'] ?? null;
                    $goalPublic = ($_POST['goal_visibility'] ?? 'public') === 'public';

                    if ($goalTitle) {
                        if (class_exists('\Nexus\Models\Goal')) {
                            $moduleId = \Nexus\Models\Goal::create($tenantId, $userId, $goalTitle, $goalDesc, $goalDeadline, $goalPublic);
                        } else {
                            $dbClassPost::query(
                                "INSERT INTO goals (user_id, tenant_id, title, description, target_date, is_public, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
                                [$userId, $tenantId, $goalTitle, $goalDesc, $goalDeadline, $goalPublic ? 1 : 0]
                            );
                            $moduleId = $dbClassPost::query("SELECT LAST_INSERT_ID() as id")->fetch()['id'];
                        }
                        $feedContent = "🎯 New Goal: $goalTitle\n\n$goalDesc";
                    }
                    break;

                case 'volunteering':
                    // Create volunteering opportunity
                    $volTitle = trim($_POST['vol_title'] ?? '');
                    $volDesc = trim($_POST['vol_description'] ?? '');
                    $volLocation = trim($_POST['vol_location'] ?? '');
                    $volCredits = (int)($_POST['vol_credits'] ?? 1);
                    $volCatId = (int)($_POST['vol_category_id'] ?? 0);
                    $volOrgId = (int)($_POST['vol_org_id'] ?? 0) ?: null;
                    $volStartDate = $_POST['vol_start_date'] ?? null;
                    $volEndDate = $_POST['vol_end_date'] ?? null;
                    $volSkills = trim($_POST['vol_skills'] ?? '');

                    if ($volTitle && $volDesc) {
                        $dbClassPost::query(
                            "INSERT INTO vol_opportunities (tenant_id, created_by, org_id, title, description, location, skills_needed, credits_offered, category_id, start_date, end_date, status, created_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', NOW())",
                            [$tenantId, $userId, $volOrgId, $volTitle, $volDesc, $volLocation, $volSkills, $volCredits, $volCatId ?: null, $volStartDate, $volEndDate]
                        );
                        $moduleId = $dbClassPost::query("SELECT LAST_INSERT_ID() as id")->fetch()['id'];
                        $feedContent = "🤝 Volunteer Opportunity: $volTitle\n\n$volDesc\n\n📍 $volLocation • ⏱️ $volCredits credits";
                    }
                    break;

                case 'event':
                    // Create event
                    $eventTitle = trim($_POST['event_title'] ?? '');
                    $eventDesc = trim($_POST['event_description'] ?? '');
                    $eventLocation = trim($_POST['event_location'] ?? '');
                    $eventCatId = (int)($_POST['event_category_id'] ?? 0);
                    $eventGroupId = (int)($_POST['event_group_id'] ?? 0) ?: null;
                    $eventStartDate = $_POST['event_start_date'] ?? date('Y-m-d');
                    $eventStartTime = $_POST['event_start_time'] ?? '09:00';
                    $eventEndDate = $_POST['event_end_date'] ?? $eventStartDate;
                    $eventEndTime = $_POST['event_end_time'] ?? '17:00';
                    $eventLat = $_POST['event_lat'] ?? null;
                    $eventLon = $_POST['event_lon'] ?? null;

                    if ($eventTitle) {
                        $startDateTime = $eventStartDate . ' ' . $eventStartTime . ':00';
                        $endDateTime = $eventEndDate . ' ' . $eventEndTime . ':00';

                        $dbClassPost::query(
                            "INSERT INTO events (tenant_id, user_id, group_id, category_id, title, description, location, latitude, longitude, start_time, end_time, created_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                            [$tenantId, $userId, $eventGroupId, $eventCatId ?: null, $eventTitle, $eventDesc, $eventLocation, $eventLat, $eventLon, $startDateTime, $endDateTime]
                        );
                        $moduleId = $dbClassPost::query("SELECT LAST_INSERT_ID() as id")->fetch()['id'];

                        // Handle SDGs for events
                        if (!empty($_POST['event_sdg_goals']) && $moduleId) {
                            $sdgJson = json_encode($_POST['event_sdg_goals']);
                            $dbClassPost::query("UPDATE events SET sdg_goals = ? WHERE id = ?", [$sdgJson, $moduleId]);
                        }

                        $formattedDate = date('M j, Y', strtotime($eventStartDate));
                        $feedContent = "📅 New Event: $eventTitle\n\n$eventDesc\n\n📍 $eventLocation • 🗓️ $formattedDate at $eventStartTime";
                    }
                    break;

                case 'poll':
                    // Create poll
                    $pollQuestion = trim($_POST['poll_question'] ?? '');
                    $pollDesc = trim($_POST['poll_description'] ?? '');
                    $pollEndDate = $_POST['poll_end_date'] ?? null;
                    $pollOptions = array_filter(array_map('trim', $_POST['poll_options'] ?? []));

                    if ($pollQuestion && count($pollOptions) >= 2) {
                        $dbClassPost::query(
                            "INSERT INTO polls (tenant_id, user_id, question, description, end_date, is_active, created_at)
                             VALUES (?, ?, ?, ?, ?, 1, NOW())",
                            [$tenantId, $userId, $pollQuestion, $pollDesc, $pollEndDate]
                        );
                        $moduleId = $dbClassPost::query("SELECT LAST_INSERT_ID() as id")->fetch()['id'];

                        // Insert poll options
                        foreach ($pollOptions as $index => $optionText) {
                            $dbClassPost::query(
                                "INSERT INTO poll_options (poll_id, option_text, sort_order) VALUES (?, ?, ?)",
                                [$moduleId, $optionText, $index]
                            );
                        }

                        $optionsList = implode(' • ', array_slice($pollOptions, 0, 3));
                        $feedContent = "📊 Poll: $pollQuestion\n\n$pollDesc\n\nOptions: $optionsList" . (count($pollOptions) > 3 ? '...' : '');
                    }
                    break;

                case 'post':
                default:
                    // Standard feed post
                    $feedContent = trim($_POST['content'] ?? '');
                    break;
            }

            // Create feed post (always, to surface module content in the feed)
            if ($feedContent || $imageUrl || $videoUrl) {
                // Ensure video_url column exists (auto-migrate)
                if ($videoUrl) {
                    try {
                        $dbClassPost::query("ALTER TABLE feed_posts ADD COLUMN video_url VARCHAR(500) NULL AFTER image_url");
                    } catch (\Throwable $e) {
                        // Column likely already exists, ignore
                    }
                }

                // Use raw SQL for reliable insert with video support
                $dbClassPost::query(
                    "INSERT INTO feed_posts (user_id, tenant_id, content, image_url, video_url, likes_count, parent_id, parent_type, created_at)
                     VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)",
                    [
                        $userId,
                        $tenantId,
                        $feedContent,
                        $imageUrl,
                        $videoUrl,
                        $moduleId,
                        $postType !== 'post' ? $postType : null,
                        date('Y-m-d H:i:s')
                    ]
                );
            }

            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } catch (\Throwable $e) {
            error_log("Multi-Module Post Error: " . $e->getMessage());
            $errorMsg = "Post Creation Failed: " . $e->getMessage();
        }
    }

    // B. AJAX Actions
    if (isset($_POST['action'])) {
        ob_clean();
        header('Content-Type: application/json');

        if (!$isLoggedIn) {
            echo json_encode(['error' => 'Login required', 'redirect' => '/login']);
            exit;
        }

        $targetType = $_POST['target_type'] ?? '';
        $targetId = (int)($_POST['target_id'] ?? 0);

        try {
            // Helper to get DB connection
            // Use raw Database (not DatabaseWrapper) to avoid tenant_id injection on likes/comments
            // The likes table already stores tenant_id, and we check by user_id + target
            $dbClass = class_exists('\Nexus\Core\Database') ? '\Nexus\Core\Database' : '\Nexus\Core\DatabaseWrapper';

            // TOGGLE LIKE
            if ($_POST['action'] === 'toggle_like') {
                        $existing = $dbClass::query("SELECT id FROM likes WHERE user_id = ? AND target_type = ? AND target_id = ?", [$userId, $targetType, $targetId])->fetch();
                if ($existing) {
                    $dbClass::query("DELETE FROM likes WHERE id = ?", [$existing['id']]);
                    if ($targetType === 'post') $dbClass::query("UPDATE feed_posts SET likes_count = GREATEST(likes_count - 1, 0) WHERE id = ?", [$targetId]);

                    // Get updated count
                    $countResult = $dbClass::query("SELECT COUNT(*) as cnt FROM likes WHERE target_type = ? AND target_id = ?", [$targetType, $targetId])->fetch();
                    echo json_encode(['status' => 'unliked', 'likes_count' => (int)($countResult['cnt'] ?? 0)]);
                } else {
                    $dbClass::query("INSERT INTO likes (user_id, target_type, target_id, tenant_id) VALUES (?, ?, ?, ?)", [$userId, $targetType, $targetId, $tenantId]);
                    if ($targetType === 'post') $dbClass::query("UPDATE feed_posts SET likes_count = likes_count + 1 WHERE id = ?", [$targetId]);

                    // Send notification (platform + email) to content owner
                    if (class_exists('\Nexus\Services\SocialNotificationService')) {
                        $contentOwnerId = \Nexus\Services\SocialNotificationService::getContentOwnerId($targetType, $targetId);
                        if ($contentOwnerId && $contentOwnerId != $userId) {
                            $contentPreview = \Nexus\Services\SocialNotificationService::getContentPreview($targetType, $targetId);
                            \Nexus\Services\SocialNotificationService::notifyLike($contentOwnerId, $userId, $targetType, $targetId, $contentPreview);
                        }
                    }

                    // Get updated count
                    $countResult = $dbClass::query("SELECT COUNT(*) as cnt FROM likes WHERE target_type = ? AND target_id = ?", [$targetType, $targetId])->fetch();
                    echo json_encode(['status' => 'liked', 'likes_count' => (int)($countResult['cnt'] ?? 0)]);
                }
            }

            // SUBMIT COMMENT (Enhanced with @mention support)
            elseif ($_POST['action'] === 'submit_comment') {
                $content = trim($_POST['content']);
                if (empty($content)) exit;

                // Use CommentService if available for @mention support
                if (class_exists('\Nexus\Services\CommentService')) {
                    $result = \Nexus\Services\CommentService::addComment($userId, $tenantId, $targetType, $targetId, $content);

                    // Send notification to content owner
                    if ($result['status'] === 'success' && class_exists('\Nexus\Services\SocialNotificationService')) {
                        $contentOwnerId = \Nexus\Services\SocialNotificationService::getContentOwnerId($targetType, $targetId);
                        if ($contentOwnerId && $contentOwnerId != $userId) {
                            \Nexus\Services\SocialNotificationService::notifyComment($contentOwnerId, $userId, $targetType, $targetId, $content);
                        }
                    }

                    echo json_encode($result);
                } else {
                    // Fallback to basic insert
                    if (method_exists($dbClass, 'insert')) {
                        $dbClass::insert('comments', [
                            'user_id' => $userId,
                            'tenant_id' => $tenantId,
                            'target_type' => $targetType,
                            'target_id' => $targetId,
                            'content' => $content,
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    } else {
                        $dbClass::query(
                            "INSERT INTO comments (user_id, tenant_id, target_type, target_id, content, created_at) VALUES (?, ?, ?, ?, ?, ?)",
                            [$userId, $tenantId, $targetType, $targetId, $content, date('Y-m-d H:i:s')]
                        );
                    }

                    // Send notification (platform + email) to content owner
                    if (class_exists('\Nexus\Services\SocialNotificationService')) {
                        $contentOwnerId = \Nexus\Services\SocialNotificationService::getContentOwnerId($targetType, $targetId);
                        if ($contentOwnerId && $contentOwnerId != $userId) {
                            \Nexus\Services\SocialNotificationService::notifyComment($contentOwnerId, $userId, $targetType, $targetId, $content);
                        }
                    }

                    echo json_encode(['status' => 'success', 'comment' => [
                        'author_name' => $_SESSION['user_name'] ?? 'Me',
                        'author_avatar' => $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp',
                        'content' => $content
                    ]]);
                }
            }

            // SHARE (REPOST) - UPDATED FOR RECURSIVE RENDERING
            elseif ($_POST['action'] === 'share_repost') {
                $parentId = (int)($_POST['parent_id'] ?? 0);
                $parentType = $_POST['parent_type'] ?? 'post';

                if ($parentId <= 0) {
                    echo json_encode(['error' => 'Invalid Post ID']);
                    exit;
                }

                $newContent = trim($_POST['content'] ?? '');

                try {
                    if (class_exists('Nexus\Models\FeedPost')) {
                        \Nexus\Models\FeedPost::create($userId, $newContent, null, null, $parentId, $parentType);
                    } else {
                        $dbClass::query(
                            "INSERT INTO feed_posts (user_id, tenant_id, content, likes_count, visibility, created_at, parent_id, parent_type) VALUES (?, ?, ?, 0, 'public', ?, ?, ?)",
                            [$userId, $tenantId, $newContent, date('Y-m-d H:i:s'), $parentId, $parentType]
                        );
                    }

                    // Send notification (platform + email) to original content owner
                    if (class_exists('\Nexus\Services\SocialNotificationService')) {
                        $contentOwnerId = \Nexus\Services\SocialNotificationService::getContentOwnerId($parentType, $parentId);
                        if ($contentOwnerId && $contentOwnerId != $userId) {
                            \Nexus\Services\SocialNotificationService::notifyShare($contentOwnerId, $userId, $parentType, $parentId);
                        }
                    }

                    echo json_encode(['status' => 'success']);
                } catch (\Throwable $e) {
                    error_log("Share Exception: " . $e->getMessage());
                    echo json_encode(['error' => $e->getMessage()]);
                }
            }

            // DELETE POST
            elseif ($_POST['action'] === 'delete_post') {
                if (($_SESSION['user_role'] ?? '') !== 'admin') exit;
                $table = ($targetType === 'post') ? 'feed_posts' : 'listings';
                $dbClass::query("DELETE FROM `$table` WHERE id = ?", [$targetId]);
                echo json_encode(['status' => 'deleted']);
            }

            // FETCH COMMENTS (Enhanced with nested replies and reactions)
            elseif ($_POST['action'] === 'fetch_comments') {
                if (class_exists('\Nexus\Services\CommentService')) {
                    $comments = \Nexus\Services\CommentService::fetchComments($targetType, $targetId, $userId);
                    echo json_encode([
                        'status' => 'success',
                        'comments' => $comments,
                        'available_reactions' => \Nexus\Services\CommentService::getAvailableReactions()
                    ]);
                } else {
                    // Fallback to basic fetch
                    $sql = "SELECT c.content, c.created_at,
                                   COALESCE(u.name, 'Unknown') as author_name,
                                   COALESCE(u.avatar_url, '/assets/img/defaults/default_avatar.webp') as author_avatar
                            FROM comments c
                            LEFT JOIN users u ON c.user_id = u.id
                            WHERE c.target_type = ? AND c.target_id = ?
                            ORDER BY c.created_at ASC";
                    $stmt = $dbClass::query($sql, [$targetType, $targetId]);
                    echo json_encode(['status' => 'success', 'comments' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                }
            }

            // DELETE COMMENT
            elseif ($_POST['action'] === 'delete_comment') {
                $commentId = (int)($_POST['comment_id'] ?? 0);
                $isSuperAdmin = !empty($_SESSION['is_super_admin']) || ($_SESSION['user_role'] ?? '') === 'admin';
                if (class_exists('\Nexus\Services\CommentService')) {
                    $result = \Nexus\Services\CommentService::deleteComment($commentId, $userId, $isSuperAdmin);
                    echo json_encode($result);
                } else {
                    echo json_encode(['error' => 'CommentService not available']);
                }
            }

            // EDIT COMMENT
            elseif ($_POST['action'] === 'edit_comment') {
                $commentId = (int)($_POST['comment_id'] ?? 0);
                $newContent = $_POST['content'] ?? '';
                if (class_exists('\Nexus\Services\CommentService')) {
                    $result = \Nexus\Services\CommentService::editComment($commentId, $userId, $newContent);
                    echo json_encode($result);
                } else {
                    echo json_encode(['error' => 'CommentService not available']);
                }
            }

            // REPLY TO COMMENT
            elseif ($_POST['action'] === 'reply_comment') {
                $parentId = (int)($_POST['parent_id'] ?? 0);
                $content = trim($_POST['content'] ?? '');
                if (class_exists('\Nexus\Services\CommentService')) {
                    $result = \Nexus\Services\CommentService::addComment($userId, $tenantId, $targetType, $targetId, $content, $parentId);

                    // Notify parent comment author
                    if ($result['status'] === 'success' && $result['is_reply'] && class_exists('\Nexus\Services\SocialNotificationService')) {
                        $pdo = \Nexus\Core\Database::getInstance();
                        $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
                        $stmt->execute([$parentId]);
                        $parentComment = $stmt->fetch(\PDO::FETCH_ASSOC);
                        if ($parentComment && $parentComment['user_id'] != $userId) {
                            \Nexus\Services\SocialNotificationService::notifyComment(
                                $parentComment['user_id'], $userId, $targetType, $targetId, "replied to your comment: " . substr($content, 0, 50)
                            );
                        }
                    }
                    echo json_encode($result);
                } else {
                    echo json_encode(['error' => 'CommentService not available']);
                }
            }

            // TOGGLE REACTION ON COMMENT
            elseif ($_POST['action'] === 'toggle_reaction') {
                $commentId = (int)($_POST['comment_id'] ?? 0);
                $emoji = $_POST['emoji'] ?? '';
                if (class_exists('\Nexus\Services\CommentService')) {
                    $result = \Nexus\Services\CommentService::toggleReaction($userId, $tenantId, $commentId, $emoji);
                    echo json_encode($result);
                } else {
                    echo json_encode(['error' => 'CommentService not available']);
                }
            }

            // SEARCH USERS FOR @MENTION
            elseif ($_POST['action'] === 'search_users') {
                $query = trim($_POST['query'] ?? '');
                if (class_exists('\Nexus\Services\CommentService') && strlen($query) >= 1) {
                    $users = \Nexus\Services\CommentService::searchUsersForMention($query, $tenantId);
                    echo json_encode(['status' => 'success', 'users' => $users]);
                } else {
                    echo json_encode(['status' => 'success', 'users' => []]);
                }
            }

            // INFINITE SCROLL - LOAD MORE FEED (EdgeRank & Location Aware)
            elseif ($_POST['action'] === 'load_more_feed') {
                $page = (int)($_POST['page'] ?? 2);
                $limit = 15;
                $offset = ($page - 1) * $limit;

                // Get filter parameters from POST (passed by JS)
                $ajaxAlgoMode = $_POST['algo'] ?? 'ranked';
                $ajaxLocationMode = $_POST['location'] ?? 'global';
                $ajaxRadius = max(10, min(500, (int)($_POST['radius'] ?? 500)));

                $moreFeedItems = [];
                $uid = $userId ?: 0;

                // Get viewer coordinates from session
                $ajaxViewerLat = !empty($_SESSION['user_latitude']) ? (float)$_SESSION['user_latitude'] : null;
                $ajaxViewerLon = !empty($_SESSION['user_longitude']) ? (float)$_SESSION['user_longitude'] : null;

                // Check if EdgeRank should be used
                $useEdgeRankAjax = class_exists('\Nexus\Services\FeedRankingService')
                                && \Nexus\Services\FeedRankingService::isEnabled()
                                && $ajaxAlgoMode === 'ranked';

                if ($useEdgeRankAjax) {
                    // Build ranked query with pagination
                    $whereConditions = ["p.visibility = 'public' OR (p.user_id = ? AND p.visibility != 'private')"];
                    $whereParams = [$uid];

                    // Add location radius filter if needed
                    if ($ajaxLocationMode === 'nearby' && $ajaxViewerLat !== null && $ajaxViewerLon !== null) {
                        $whereConditions[] = "(
                            6371 * acos(
                                cos(radians(?)) * cos(radians(u.latitude)) *
                                cos(radians(u.longitude) - radians(?)) +
                                sin(radians(?)) * sin(radians(u.latitude))
                            )
                        ) <= ?";
                        $whereParams[] = $ajaxViewerLat;
                        $whereParams[] = $ajaxViewerLon;
                        $whereParams[] = $ajaxViewerLat;
                        $whereParams[] = $ajaxRadius;
                    }

                    $rankedQuery = \Nexus\Services\FeedRankingService::buildRankedFeedQuery(
                        $uid,
                        $ajaxViewerLat,
                        $ajaxViewerLon,
                        $whereConditions,
                        $whereParams
                    );

                    $rankedQuery['sql'] .= " LIMIT $limit OFFSET $offset";
                    $rawPosts = $dbClass::query($rankedQuery['sql'], $rankedQuery['params'])->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    // Chronological query with optional location filter
                    $chronoParams = [$userId, $userId, $tenantId, $userId];
                    $locationWhereClause = "";

                    if ($ajaxLocationMode === 'nearby' && $ajaxViewerLat !== null && $ajaxViewerLon !== null) {
                        $locationWhereClause = " AND (
                            6371 * acos(
                                cos(radians(?)) * cos(radians(u.latitude)) *
                                cos(radians(u.longitude) - radians(?)) +
                                sin(radians(?)) * sin(radians(u.latitude))
                            )
                        ) <= ?";
                        $chronoParams = array_merge($chronoParams, [$ajaxViewerLat, $ajaxViewerLon, $ajaxViewerLat, $ajaxRadius]);
                    }

                    // Check if feed_posts has group_id column (for backwards compatibility)
                    $hasGroupIdColumnAjax = false;
                    try {
                        $columnCheckAjax = $dbClass::query("SHOW COLUMNS FROM feed_posts LIKE 'group_id'")->fetch();
                        $hasGroupIdColumnAjax = !empty($columnCheckAjax);
                    } catch (\Exception $e) {
                        $hasGroupIdColumnAjax = false;
                    }

                    $groupSelectColsAjax = $hasGroupIdColumnAjax ? "g.id as group_id, g.name as group_name, g.image_url as group_image, g.location as group_location," : "NULL as group_id, NULL as group_name, NULL as group_image, NULL as group_location,";
                    $groupJoinAjax = $hasGroupIdColumnAjax ? "LEFT JOIN `groups` g ON p.group_id = g.id" : "";

                    // Fetch more posts with pagination
                    $feedSql = "SELECT p.*,
                                CASE
                                    WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != '' THEN u.organization_name
                                    ELSE COALESCE(NULLIF(u.name, ''), CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')), 'User')
                                END as author_name,
                                u.avatar_url as author_avatar,
                                u.location as author_location,
                                {$groupSelectColsAjax}
                                CASE
                                    WHEN p.parent_id IS NOT NULL AND p.parent_id > 0 THEN
                                        (SELECT COUNT(*) FROM likes WHERE user_id = ? AND target_type = p.parent_type AND target_id = p.parent_id)
                                    ELSE
                                        (SELECT COUNT(*) FROM likes WHERE user_id = ? AND target_type = 'post' AND target_id = p.id)
                                END as is_liked,
                                CASE
                                    WHEN p.parent_id IS NOT NULL AND p.parent_id > 0 THEN
                                        (SELECT COUNT(*) FROM likes WHERE target_type = p.parent_type AND target_id = p.parent_id)
                                    ELSE
                                        (SELECT COUNT(*) FROM likes WHERE target_type = 'post' AND target_id = p.id)
                                END as likes_count,
                                CASE
                                    WHEN p.parent_id IS NOT NULL AND p.parent_id > 0 THEN
                                        (SELECT COUNT(*) FROM comments WHERE target_type = p.parent_type AND target_id = p.parent_id)
                                    ELSE
                                        (SELECT COUNT(*) FROM comments WHERE target_type = 'post' AND target_id = p.id)
                                END as comments_count
                                FROM feed_posts p
                                JOIN users u ON p.user_id = u.id
                                {$groupJoinAjax}
                                WHERE p.tenant_id = ?
                                AND (p.visibility = 'public' OR (p.user_id = ? AND p.visibility != 'private'))
                                {$locationWhereClause}
                                ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset";
                    $rawPosts = $dbClass::query($feedSql, $chronoParams)->fetchAll(PDO::FETCH_ASSOC);
                }

                foreach ($rawPosts as $p) {
                    $moreFeedItems[] = [
                        'type' => 'post',
                        'id' => $p['id'],
                        'user_id' => $p['user_id'],
                        'author_name' => $p['author_name'],
                        'author_avatar' => $p['author_avatar'],
                        'author_location' => $p['author_location'] ?? null,
                        'title' => null,
                        'body' => $p['content'],
                        'content' => $p['content'],
                        'created_at' => $p['created_at'],
                        'likes_count' => $p['likes_count'],
                        'comments_count' => $p['comments_count'],
                        'is_liked' => $p['is_liked'],
                        'image_url' => $p['image_url'],
                        'extra_3' => $p['image_url'],
                        'parent_id' => $p['parent_id'] ?? null,
                        'parent_type' => $p['parent_type'] ?? 'post',
                        // Group context
                        'group_id' => $p['group_id'] ?? null,
                        'group_name' => $p['group_name'] ?? null,
                        'group_image' => $p['group_image'] ?? null,
                        'group_location' => $p['group_location'] ?? null
                    ];
                }

                // Render feed items to HTML
                $html = '';
                foreach ($moreFeedItems as $item) {
                    ob_start();
                    include __DIR__ . '/partials/feed_item.php';
                    $html .= ob_get_clean();
                }

                echo json_encode([
                    'status' => 'success',
                    'items' => $moreFeedItems,
                    'html' => $html,
                    'page' => $page
                ]);
            }
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
}


// ---------------------------------------------------------
// 4. FETCH & RANK FEED (Modified EdgeRank Algorithm)
// ---------------------------------------------------------
$feedItems = [];
if (!isset($errorMsg)) $errorMsg = null;

// Determine DB class
$useRawDB = class_exists('\Nexus\Core\Database');
$dbClass = $useRawDB ? '\Nexus\Core\Database' : '\Nexus\Core\DatabaseWrapper';

if (class_exists($dbClass)) {
    try {
        $uid = $userId ?: 0;
        $fetchLimit = 15;

        // --- A. FETCH RANKED POSTS (EdgeRank Algorithm) ---
        $rawPosts = [];

        if (method_exists($dbClass, 'query')) {
            // Check if FeedRankingService is available AND enabled for EdgeRank scoring
            // User can toggle via algo=ranked (For You) or algo=recent (chronological)
            $useEdgeRank = class_exists('\Nexus\Services\FeedRankingService')
                        && \Nexus\Services\FeedRankingService::isEnabled()
                        && $feedAlgoMode === 'ranked';  // User preference from filter

            // Get viewer's coordinates from profile (for geo-ranking and location filtering)
            $viewerLat = null;
            $viewerLon = null;
            if (!empty($_SESSION['user_latitude']) && !empty($_SESSION['user_longitude'])) {
                $viewerLat = (float)$_SESSION['user_latitude'];
                $viewerLon = (float)$_SESSION['user_longitude'];
            } elseif ($uid && class_exists('\Nexus\Models\User')) {
                try {
                    $userCoords = \Nexus\Models\User::getCoordinates($uid);
                    if ($userCoords) {
                        $viewerLat = $userCoords['latitude'] ?? null;
                        $viewerLon = $userCoords['longitude'] ?? null;
                        if ($viewerLat) $_SESSION['user_latitude'] = $viewerLat;
                        if ($viewerLon) $_SESSION['user_longitude'] = $viewerLon;
                    }
                } catch (\Exception $e) {}
            }

            if ($useEdgeRank) {
                // Build the ranked query with EdgeRank algorithm
                // Factors: Engagement × Vitality × GeoDecay × Freshness × SocialGraph × Quality
                $whereConditions = ["p.visibility = 'public' OR (p.user_id = ? AND p.visibility != 'private')"];
                $whereParams = [$uid];

                // Add location radius filter if location mode is 'nearby' and user has coordinates
                if ($feedLocationMode === 'nearby' && $viewerLat !== null && $viewerLon !== null) {
                    // Haversine distance formula to filter by radius (in km)
                    $whereConditions[] = "(
                        6371 * acos(
                            cos(radians(?)) * cos(radians(u.latitude)) *
                            cos(radians(u.longitude) - radians(?)) +
                            sin(radians(?)) * sin(radians(u.latitude))
                        )
                    ) <= ?";
                    $whereParams[] = $viewerLat;
                    $whereParams[] = $viewerLon;
                    $whereParams[] = $viewerLat;
                    $whereParams[] = $feedRadius;
                }

                $rankedQuery = \Nexus\Services\FeedRankingService::buildRankedFeedQuery(
                    $uid,
                    $viewerLat,
                    $viewerLon,
                    $whereConditions,
                    $whereParams
                );

                $rankedQuery['sql'] .= " LIMIT 50";
                $rawPosts = $dbClass::query($rankedQuery['sql'], $rankedQuery['params'])->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Chronological query (Recent mode or EdgeRank disabled)
                // Still supports location-based filtering if location mode is 'nearby'
                $chronoParams = [$userId, $userId, $tenantId, $userId];
                $locationWhereClause = "";

                // Add location radius filter if location mode is 'nearby' and user has coordinates
                if ($feedLocationMode === 'nearby' && $viewerLat !== null && $viewerLon !== null) {
                    $locationWhereClause = " AND (
                        6371 * acos(
                            cos(radians(?)) * cos(radians(u.latitude)) *
                            cos(radians(u.longitude) - radians(?)) +
                            sin(radians(?)) * sin(radians(u.latitude))
                        )
                    ) <= ?";
                    $chronoParams = array_merge($chronoParams, [$viewerLat, $viewerLon, $viewerLat, $feedRadius]);
                }

                // Check if feed_posts has group_id column (for backwards compatibility with older databases)
                $hasGroupIdColumn = false;
                try {
                    $columnCheck = $dbClass::query("SHOW COLUMNS FROM feed_posts LIKE 'group_id'")->fetch();
                    $hasGroupIdColumn = !empty($columnCheck);
                } catch (\Exception $e) {
                    $hasGroupIdColumn = false;
                }

                // Build query with or without group join based on column availability
                $groupSelectCols = $hasGroupIdColumn ? "g.id as group_id, g.name as group_name, g.image_url as group_image, g.location as group_location," : "NULL as group_id, NULL as group_name, NULL as group_image, NULL as group_location,";
                $groupJoin = $hasGroupIdColumn ? "LEFT JOIN `groups` g ON p.group_id = g.id" : "";

                $feedSql = "SELECT p.*,
                            CASE
                                WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != '' THEN u.organization_name
                                ELSE COALESCE(NULLIF(u.name, ''), CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')), 'User')
                            END as author_name,
                            u.avatar_url as author_avatar,
                            u.location as author_location,
                            {$groupSelectCols}
                            CASE
                                WHEN p.parent_id IS NOT NULL AND p.parent_id > 0 THEN
                                    (SELECT COUNT(*) FROM likes WHERE user_id = ? AND target_type = p.parent_type AND target_id = p.parent_id)
                                ELSE
                                    (SELECT COUNT(*) FROM likes WHERE user_id = ? AND target_type = 'post' AND target_id = p.id)
                            END as is_liked,
                            CASE
                                WHEN p.parent_id IS NOT NULL AND p.parent_id > 0 THEN
                                    (SELECT COUNT(*) FROM likes WHERE target_type = p.parent_type AND target_id = p.parent_id)
                                ELSE
                                    (SELECT COUNT(*) FROM likes WHERE target_type = 'post' AND target_id = p.id)
                            END as likes_count,
                            CASE
                                WHEN p.parent_id IS NOT NULL AND p.parent_id > 0 THEN
                                    (SELECT COUNT(*) FROM comments WHERE target_type = p.parent_type AND target_id = p.parent_id)
                                ELSE
                                    (SELECT COUNT(*) FROM comments WHERE target_type = 'post' AND target_id = p.id)
                            END as comments_count
                            FROM feed_posts p
                            JOIN users u ON p.user_id = u.id
                            {$groupJoin}
                            WHERE p.tenant_id = ?
                            AND (p.visibility = 'public' OR (p.user_id = ? AND p.visibility != 'private'))
                            {$locationWhereClause}
                            ORDER BY p.created_at DESC LIMIT 50";
                $rawPosts = $dbClass::query($feedSql, $chronoParams)->fetchAll(PDO::FETCH_ASSOC);
            }

            // Check if we should calculate recommendation context
            $useRecommendationContext = $useEdgeRank && method_exists('\Nexus\Services\FeedRankingService', 'getRecommendationContext');
            $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

            foreach ($rawPosts as $p) {
                $feedItem = [
                    'type' => 'post',
                    'id' => $p['id'],
                    'user_id' => $p['user_id'],
                    'author_name' => $p['author_name'],
                    'author_avatar' => $p['author_avatar'],
                    'author_location' => $p['author_location'] ?? null,
                    'title' => null,
                    'body' => $p['content'],
                    'content' => $p['content'],
                    'created_at' => $p['created_at'],
                    'likes_count' => (int)$p['likes_count'],
                    'comments_count' => (int)$p['comments_count'],
                    'is_liked' => (int)$p['is_liked'] > 0,
                    'extra_3' => $p['image_url'],
                    'extra_4' => $p['video_url'] ?? null,
                    'image_url' => $p['image_url'],
                    'video_url' => $p['video_url'] ?? null,
                    'parent_id' => $p['parent_id'] ?? null,
                    'parent_type' => $p['parent_type'] ?? 'post',
                    'rank_score' => $p['rank_score'] ?? null, // EdgeRank score for debugging
                    'vitality_score' => $p['vitality_score'] ?? null,
                    'recommendation_badges' => [], // Will be populated below
                    // Group context - for posts made in groups
                    'group_id' => $p['group_id'] ?? null,
                    'group_name' => $p['group_name'] ?? null,
                    'group_image' => $p['group_image'] ?? null,
                    'group_location' => $p['group_location'] ?? null
                ];

                // Add recommendation context badges if EdgeRank is active
                if ($useRecommendationContext) {
                    $context = \Nexus\Services\FeedRankingService::getRecommendationContext($feedItem, $uid);
                    $feedItem['recommendation_badges'] = \Nexus\Services\FeedRankingService::filterBadgesForUser(
                        $context['badges'],
                        $isAdmin
                    );
                }

                $feedItems[] = $feedItem;
            }
        }

        // --- B. FETCH LISTINGS ---
        try {
            $sqlListings = "SELECT l.*,
                   CASE
                       WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != '' THEN u.organization_name
                       ELSE COALESCE(NULLIF(u.name, ''), CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')), 'Unknown')
                   END as author_name,
                   u.avatar_url as author_avatar, u.location as author_location,
                   (SELECT COUNT(*) FROM likes WHERE target_type = 'listing' AND target_id = l.id) as likes_count,
                   (SELECT COUNT(*) FROM comments WHERE target_type = 'listing' AND target_id = l.id) as comments_count,
                   (SELECT COUNT(*) FROM likes WHERE target_type = 'listing' AND target_id = l.id AND user_id = $uid) as is_liked
                   FROM listings l LEFT JOIN users u ON l.user_id = u.id
                   WHERE l.tenant_id = $tenantId AND l.status = 'active' ORDER BY l.created_at DESC LIMIT $fetchLimit";
            $rawListings = $dbClass::query($sqlListings)->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rawListings as $l) {
                $feedItems[] = [
                    'type' => 'listing',
                    'id' => $l['id'],
                    'user_id' => $l['user_id'],
                    'author_name' => $l['author_name'],
                    'author_avatar' => $l['author_avatar'],
                    'author_location' => $l['author_location'] ?? null,
                    'title' => $l['title'],
                    'body' => $l['description'],
                    'created_at' => $l['created_at'],
                    'likes_count' => $l['likes_count'],
                    'comments_count' => $l['comments_count'] ?? 0,
                    'is_liked' => (int)$l['is_liked'] > 0,
                    'location' => $l['location'] ?? null,
                    'extra_1' => $l['title'],
                    'extra_2' => $l['type'],
                    'extra_3' => $l['image_url']
                ];
            }
        } catch (Exception $e) {
        }

        // --- C. FETCH POLLS ---
        try {
            $sqlPolls = "SELECT p.*,
                         CASE
                             WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != '' THEN u.organization_name
                             ELSE COALESCE(NULLIF(u.name, ''), CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')), 'Admin')
                         END as author_name,
                         u.avatar_url as author_avatar, u.location as author_location,
                         (SELECT COUNT(*) FROM likes WHERE target_type = 'poll' AND target_id = p.id) as likes_count,
                         (SELECT COUNT(*) FROM comments WHERE target_type = 'poll' AND target_id = p.id) as comments_count,
                         (SELECT COUNT(*) FROM likes WHERE target_type = 'poll' AND target_id = p.id AND user_id = $uid) as is_liked
                         FROM polls p LEFT JOIN users u ON p.user_id = u.id
                         WHERE p.tenant_id = $tenantId AND p.is_active = 1 ORDER BY p.created_at DESC LIMIT $fetchLimit";
            $rawPolls = $dbClass::query($sqlPolls)->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rawPolls as $p) {
                $feedItems[] = [
                    'type' => 'poll',
                    'id' => $p['id'],
                    'user_id' => $p['user_id'],
                    'author_name' => $p['author_name'],
                    'author_avatar' => $p['author_avatar'],
                    'author_location' => $p['author_location'] ?? null,
                    'title' => $p['question'],
                    'body' => $p['description'],
                    'created_at' => $p['created_at'],
                    'likes_count' => (int)$p['likes_count'],
                    'comments_count' => (int)$p['comments_count'],
                    'is_liked' => (int)$p['is_liked'],
                    'extra_1' => 'poll',
                    'extra_2' => 0 // Vote count placeholder
                ];
            }
        } catch (Exception $e) {
        }


        // --- D. FETCH GOALS ---
        try {
            $sqlGoals = "SELECT g.*,
                         CASE
                             WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != '' THEN u.organization_name
                             ELSE COALESCE(NULLIF(u.name, ''), CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')), 'Unknown')
                         END as author_name,
                         u.avatar_url as author_avatar, u.location as author_location,
                         (SELECT COUNT(*) FROM likes WHERE target_type = 'goal' AND target_id = g.id) as likes_count,
                         (SELECT COUNT(*) FROM comments WHERE target_type = 'goal' AND target_id = g.id) as comments_count,
                         (SELECT COUNT(*) FROM likes WHERE target_type = 'goal' AND target_id = g.id AND user_id = $uid) as is_liked
                         FROM goals g LEFT JOIN users u ON g.user_id = u.id
                         WHERE g.tenant_id = ? ORDER BY g.created_at DESC LIMIT $fetchLimit";
            $rawGoals = $dbClass::query($sqlGoals, [$tenantId])->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rawGoals as $g) {
                $feedItems[] = [
                    'type' => 'goal',
                    'id' => $g['id'],
                    'user_id' => $g['user_id'],
                    'author_name' => $g['author_name'],
                    'author_avatar' => $g['author_avatar'],
                    'author_location' => $g['author_location'] ?? null,
                    'title' => $g['title'],
                    'body' => $g['description'],
                    'created_at' => $g['created_at'],
                    'likes_count' => (int)($g['likes_count'] ?? 0),
                    'comments_count' => (int)($g['comments_count'] ?? 0),
                    'is_liked' => (int)($g['is_liked'] ?? 0) > 0,
                    'extra_2' => $g['target_date'] ?? $g['deadline'] ?? null
                ];
            }
        } catch (Exception $e) {
        }

        // --- E. FETCH VOLUNTEERING ---
        try {
            // RELAXED QUERY: Removed "AND v.status = 'open'" to verify data existence first
            $sqlVols = "SELECT v.*,
                        CASE
                            WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != '' THEN u.organization_name
                            ELSE COALESCE(NULLIF(u.name, ''), CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')), 'Organizer')
                        END as author_name,
                        u.avatar_url as author_avatar,
                        u.location as author_location,
                        (SELECT COUNT(*) FROM likes WHERE target_type = 'volunteering' AND target_id = v.id) as likes_count,
                        (SELECT COUNT(*) FROM comments WHERE target_type = 'volunteering' AND target_id = v.id) as comments_count,
                        (SELECT COUNT(*) FROM likes WHERE target_type = 'volunteering' AND target_id = v.id AND user_id = $uid) as is_liked
                        FROM vol_opportunities v LEFT JOIN users u ON v.created_by = u.id
                        WHERE v.tenant_id = ? ORDER BY v.created_at DESC LIMIT $fetchLimit";
            $rawVols = $dbClass::query($sqlVols, [$tenantId])->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rawVols as $v) {
                $feedItems[] = [
                    'type' => 'volunteering',
                    'id' => $v['id'],
                    'user_id' => $v['created_by'],
                    'author_name' => $v['author_name'],
                    'author_avatar' => $v['author_avatar'],
                    'author_location' => $v['author_location'] ?? null,
                    'title' => $v['title'],
                    'body' => $v['description'],
                    'created_at' => $v['created_at'],
                    'likes_count' => (int)$v['likes_count'],
                    'comments_count' => (int)$v['comments_count'],
                    'is_liked' => (int)$v['is_liked'],
                    'location' => $v['location'],
                    'extra_1' => $v['location'],
                    'extra_2' => $v['credits_offered']
                ];
            }
        } catch (Exception $e) {
        }

        // --- F. FETCH EVENTS ---
        try {
            $sqlEvents = "SELECT e.*,
                          CASE
                              WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != '' THEN u.organization_name
                              ELSE COALESCE(NULLIF(u.name, ''), CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')), 'Organizer')
                          END as author_name,
                          u.avatar_url as author_avatar,
                          u.location as author_location,
                          (SELECT COUNT(*) FROM likes WHERE target_type = 'event' AND target_id = e.id) as likes_count,
                          (SELECT COUNT(*) FROM comments WHERE target_type = 'event' AND target_id = e.id) as comments_count,
                          (SELECT COUNT(*) FROM likes WHERE target_type = 'event' AND target_id = e.id AND user_id = $uid) as is_liked
                          FROM events e
                          LEFT JOIN users u ON e.user_id = u.id
                          WHERE e.tenant_id = ?
                          ORDER BY e.start_time ASC LIMIT $fetchLimit";
            $rawEvents = $dbClass::query($sqlEvents, [$tenantId])->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rawEvents as $e) {
                $feedItems[] = [
                    'type' => 'event',
                    'id' => $e['id'],
                    'user_id' => $e['user_id'],
                    'author_name' => $e['author_name'],
                    'author_avatar' => $e['author_avatar'],
                    'author_location' => $e['author_location'] ?? null,
                    'title' => $e['title'],
                    'body' => $e['description'] ?? 'No description',
                    'created_at' => $e['created_at'],
                    'likes_count' => (int)$e['likes_count'],
                    'comments_count' => (int)$e['comments_count'],
                    'is_liked' => (int)$e['is_liked'],
                    'location' => $e['location'],
                    'extra_1' => $e['location'],
                    'extra_2' => $e['start_time']
                ];
            }
        } catch (Exception $ex) {
        }

        // --- G. FETCH REVIEWS ---
        try {
            $sqlReviews = "SELECT r.*,
                          reviewer.name as reviewer_name,
                          reviewer.avatar_url as reviewer_avatar,
                          reviewer.location as reviewer_location,
                          receiver.id as receiver_id,
                          receiver.name as receiver_name,
                          receiver.avatar_url as receiver_avatar,
                          (SELECT COUNT(*) FROM likes WHERE target_type = 'review' AND target_id = r.id) as likes_count,
                          (SELECT COUNT(*) FROM comments WHERE target_type = 'review' AND target_id = r.id) as comments_count,
                          (SELECT COUNT(*) FROM likes WHERE target_type = 'review' AND target_id = r.id AND user_id = $uid) as is_liked
                          FROM reviews r
                          LEFT JOIN users reviewer ON r.reviewer_id = reviewer.id
                          LEFT JOIN users receiver ON r.receiver_id = receiver.id
                          WHERE reviewer.tenant_id = ?
                          ORDER BY r.created_at DESC LIMIT $fetchLimit";
            $rawReviews = $dbClass::query($sqlReviews, [$tenantId])->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rawReviews as $rev) {
                $feedItems[] = [
                    'type' => 'review',
                    'id' => $rev['id'],
                    'user_id' => $rev['reviewer_id'],
                    'author_name' => $rev['reviewer_name'],
                    'author_avatar' => $rev['reviewer_avatar'],
                    'author_location' => $rev['reviewer_location'] ?? null,
                    'title' => 'Left a review for ' . ($rev['receiver_name'] ?? 'a member'),
                    'body' => $rev['comment'],
                    'created_at' => $rev['created_at'],
                    'likes_count' => (int)($rev['likes_count'] ?? 0),
                    'comments_count' => (int)($rev['comments_count'] ?? 0),
                    'is_liked' => (int)($rev['is_liked'] ?? 0) > 0,
                    'extra_1' => $rev['rating'],  // Star rating (1-5)
                    'extra_2' => $rev['receiver_id'],  // Receiver user ID
                    'extra_3' => $rev['receiver_name'],  // Receiver name
                    'extra_4' => $rev['receiver_avatar']  // Receiver avatar
                ];
            }
        } catch (Exception $ex) {
            // Reviews table may not exist - silently skip
        }

        // --- H. DEMO MOCK DATA - REMOVED ---
        // Mock data removed - was causing confusion on new tenants.

        // --- G. SORT AGGREGATED FEED ---
        usort($feedItems, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        $feedItems = array_slice($feedItems, 0, 50);

        // DEBUG DIAGNOSTICS - Removed (were breaking API JSON responses)
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        error_log("[FEED ERROR] " . $e->getMessage());
        error_log("[FEED ERROR] Stack trace: " . $e->getTraceAsString());
    }
}

// 5. LOAD MODERN HEADER
// CSS now loaded via centralized page-css-loader.php (Phase 4 CSS Refactoring)
require __DIR__ . '/../layouts/modern/header.php';
?>


<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<!-- Pull to Refresh removed -->


<!-- Main content wrapper (main tag opened in header.php) -->
<div class="htb-container htb-container-full home-feed-wrapper" style="padding-top: 136px !important;">
    <div id="home-glass-wrapper">

    <div class="home-two-column-grid">
        <!-- Main Feed Column -->
        <div class="home-feed-column">
            <div class="fds-feed-container">

        <!-- Smart Welcome Hero Section (matches /listings and /volunteering) -->
        <div class="nexus-welcome-hero">
            <?php if ($isLoggedIn): ?>
                <h1 class="nexus-welcome-title">
                    <i class="fa-solid fa-house"></i> Welcome back, <?= htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? 'User')[0]) ?>!
                </h1>
                <p class="nexus-welcome-subtitle">What would you like to do today?</p>

                <div class="nexus-smart-buttons">
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/compose" class="nexus-smart-btn nexus-smart-btn-primary">
                        <i class="fa-solid fa-plus"></i>
                        <span>Create Post</span>
                    </a>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/listings" class="nexus-smart-btn nexus-smart-btn-secondary">
                        <i class="fa-solid fa-handshake"></i>
                        <span>Offers & Requests</span>
                    </a>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/events" class="nexus-smart-btn nexus-smart-btn-secondary">
                        <i class="fa-solid fa-calendar"></i>
                        <span>Events</span>
                    </a>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/messages" class="nexus-smart-btn nexus-smart-btn-outline">
                        <i class="fa-solid fa-envelope"></i>
                        <span>Messages</span>
                    </a>
                </div>
            <?php else: ?>
                <h1 class="nexus-welcome-title">
                    <i class="fa-solid fa-users"></i> <?= htmlspecialchars($hero_title ?? 'Community Exchange') ?>
                </h1>
                <p class="nexus-welcome-subtitle"><?= htmlspecialchars($hero_subtitle ?? 'Share skills, build community, and exchange time.') ?></p>

                <div class="nexus-smart-buttons">
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/register" class="nexus-smart-btn nexus-smart-btn-primary">
                        <i class="fa-solid fa-user-plus"></i>
                        <span>Join Community</span>
                    </a>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/login" class="nexus-smart-btn nexus-smart-btn-secondary">
                        <i class="fa-solid fa-right-to-bracket"></i>
                        <span>Log In</span>
                    </a>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/listings" class="nexus-smart-btn nexus-smart-btn-outline">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <span>Browse Listings</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>

            <!-- Universal Feed Filter Component -->
            <?php include __DIR__ . '/partials/universal-feed-filter.php'; ?>

        <?php include __DIR__ . '/partials/home-composer.php'; ?>

        <!-- Skeleton Loaders (shown on initial load) -->
        <div class="skeleton-container" id="skeletonLoader" aria-label="Loading feed">
            <?php for ($i = 0; $i < 3; $i++): ?>
            <div class="skeleton-card">
                <div class="skeleton-header">
                    <div class="skeleton-avatar"></div>
                    <div class="skeleton-header-content">
                        <div class="skeleton-line skeleton-line-medium skeleton-line-spaced"></div>
                        <div class="skeleton-line skeleton-line-short"></div>
                    </div>
                </div>
                <div class="skeleton-line skeleton-line-full skeleton-line-spaced"></div>
                <div class="skeleton-line skeleton-line-medium skeleton-line-spaced"></div>
                <div class="skeleton-line skeleton-line-short"></div>
                <?php if ($i === 0): ?>
                <div class="skeleton-image"></div>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>

        <!-- Real Feed Content -->
        <!-- data-nosnippet tells Google to NEVER use this content for search result descriptions -->
        <div class="feed-container-real" id="feedContainer" data-nosnippet>
        <?php
        // DEBUG: Removed (were breaking API JSON responses)

        // Admin-only EdgeRank status indicator
        $isAdminUser = ($_SESSION['user_role'] ?? '') === 'admin';
        $edgeRankActive = isset($useEdgeRank) && $useEdgeRank;
        if ($isAdminUser): ?>
        <div class="edgerank-status-indicator <?= $edgeRankActive ? 'active' : 'inactive' ?>">
            <i class="fa-solid <?= $edgeRankActive ? 'fa-bolt' : 'fa-clock' ?>"></i>
            <span><?= $edgeRankActive ? 'EdgeRank Active' : 'Chronological' ?></span>
            <?php if ($edgeRankActive && !empty($feedItems[0]['rank_score'])): ?>
            <span class="edgerank-score">Top: <?= number_format($feedItems[0]['rank_score'], 2) ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($feedItems)): ?>
            <?php foreach ($feedItems as $feedIndex => $item):
                // Mark first item for LCP optimization (no lazy loading)
                $isFirstFeedItem = ($feedIndex === 0);
                // The partial expects $item, $isLoggedIn, $userId, $timeElapsed helper
                include __DIR__ . '/partials/feed_item.php';
            endforeach; ?>

            <!-- Infinite Scroll Sentinel -->
            <div id="feedSentinel" class="infinite-scroll-loader">
                <i class="fa-solid fa-spinner"></i>
                <span>Loading more...</span>
            </div>

            <!-- End of Feed Message -->
            <div id="feedEndMessage" class="feed-end-message">
                <i class="fa-regular fa-check-circle"></i>
                You're all caught up!
            </div>
        <?php else: ?>
            <?php if (!empty($errorMsg)): ?>
            <div class="feed-empty-state error-state">
                <div class="feed-empty-icon error"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <h3 class="feed-empty-title">Something went wrong</h3>
                <p class="feed-empty-text"><?= htmlspecialchars($errorMsg) ?></p>
            </div>
            <?php else: ?>
            <div class="feed-empty-state">
                <div class="feed-empty-icon"><i class="fa-solid fa-seedling"></i></div>
                <h3 class="feed-empty-title">This community is just getting started!</h3>
                <p class="feed-empty-text">Be the first to share something with the community.</p>
                <div class="feed-empty-actions">
                    <?php if ($isLoggedIn): ?>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/compose" class="feed-empty-btn primary">
                        <i class="fa-solid fa-plus"></i> Create Post
                    </a>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/compose?type=listing" class="feed-empty-btn secondary">
                        <i class="fa-solid fa-hand-holding-heart"></i> Add Listing
                    </a>
                    <?php else: ?>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/register" class="feed-empty-btn primary">
                        <i class="fa-solid fa-user-plus"></i> Join Community
                    </a>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/login" class="feed-empty-btn secondary">
                        <i class="fa-solid fa-right-to-bracket"></i> Log In
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        </div>

            </div> <!-- /.fds-feed-container -->
        </div> <!-- /.home-feed-column -->

        <!-- Sidebar Column -->
        <aside class="home-sidebar-column">
            <?php require __DIR__ . '/partials/home-sidebar.php'; ?>
        </aside>
    </div> <!-- /.home-two-column-grid -->
    </div> <!-- /#home-glass-wrapper -->

</div> <!-- /.htb-container -->
<!-- End main content (main tag closed in footer.php) -->

<div id="nexus-toast">Action successful</div>


<?php
// Mobile Bottom Sheets - Now included centrally in footer.php
// No longer needed here to avoid duplicate elements
?>

<?php
// 6. LOAD MODERN FOOTER
require __DIR__ . '/../layouts/modern/footer.php';
?>

<!-- Home Feed Configuration -->
<script>
    window.HomeFeed = {
        isLoggedIn: <?= json_encode($isLoggedIn) ?>,
        baseUrl: "<?= \Nexus\Core\TenantContext::getBasePath() ?>"
    };
    // Configure SocialInteractions
    window.SocialInteractions = window.SocialInteractions || {};
    window.SocialInteractions.isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;
</script>
<script src="/assets/js/home-feed.js?v=<?= $cssVersion ?>"></script>