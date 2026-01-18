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
        $_SESSION['user_avatar'] = $userRow['avatar_url'] ?? '/assets/img/defaults/default_avatar.webp';
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
// PUBLIC SECTOR DEMO: Mock Data (for demo purposes only)
// ---------------------------------------------------------
$slug = \Nexus\Core\TenantContext::get()['slug'] ?? '';
if ($slug === 'public-sector-demo') {
    // MOCK DATA FOR DEMO LAYOUT PARITY
    if (empty($featuredGroups)) {
        $featuredGroups = [
            ['id' => 1, 'name' => 'Elder Care Support', 'description' => 'Connecting volunteers with isolated seniors for companionship and errands.', 'image_url' => '/assets/img/placeholders/community.webp', 'member_count' => 128],
            ['id' => 2, 'name' => 'Youth Mentorship', 'description' => 'After-school support and skill sharing for local youth.', 'image_url' => '/assets/img/placeholders/abstract.webp', 'member_count' => 85],
            ['id' => 3, 'name' => 'Green Space Initiative', 'description' => 'Community gardening and urban re-wilding projects.', 'image_url' => '/assets/img/placeholders/geometric.webp', 'member_count' => 240]
        ];
    }
    if (empty($listings)) {
        $listings = [
            ['title' => 'Graphic Design for Non-Profit', 'type' => 'offer', 'author_name' => 'Sarah Jenkins', 'author_location' => 'Cork', 'description' => 'I can help design flyers and social media assets for your cause.', 'created_at_human' => '2 hours ago'],
            ['title' => 'Community Transport Driver', 'type' => 'request', 'author_name' => 'Age Action', 'author_location' => 'Dublin', 'description' => 'Looking for drivers to help seniors get to appointments.', 'created_at_human' => '5 hours ago'],
            ['title' => 'IT Support Workshop', 'type' => 'offer', 'author_name' => 'Tech4Good', 'author_location' => 'Galway', 'description' => 'Hosting a free digital literacy workshop next Tuesday.', 'created_at_human' => '1 day ago']
        ];
    }
    if (empty($hubs)) {
        $hubs = $featuredGroups; // Reuse for demo
    }
    if (empty($members)) {
        $members = [
            ['id' => 1, 'name' => 'Dr. Emily Chen', 'role' => 'organisation', 'location' => 'HSE Coordinator', 'avatar_url' => '/assets/img/defaults/default_avatar.webp'],
            ['id' => 2, 'name' => 'Liam O\'Connor', 'role' => 'member', 'location' => 'Volunteer', 'avatar_url' => '/assets/img/defaults/default_avatar.webp'],
            ['id' => 3, 'name' => 'Civic Trust', 'role' => 'organisation', 'location' => 'Partner', 'avatar_url' => '/assets/img/defaults/default_avatar.webp']
        ];
    }
}

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
                $dbClass::query("DELETE FROM $table WHERE id = ?", [$targetId]);
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

        // --- H. DEMO MOCK DATA (If Empty) ---
        // Only show if feed is empty OR explicitly in Public Sector Demo
        if (empty($feedItems) || (\Nexus\Core\TenantContext::get()['slug'] ?? '') === 'public-sector-demo') {
            // Mock Poll
            $feedItems[] = [
                'type' => 'poll',
                'id' => 999,
                'user_id' => 1,
                'author_name' => 'Community Admin',
                'author_avatar' => '/assets/img/defaults/default_avatar.webp',
                'title' => 'Where should we plant the new community garden?',
                'body' => 'We have three locations proposed by the council.',
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'likes_count' => 5,
                'extra_1' => 'poll',
                'extra_2' => 45
            ];
            // Mock Goal
            $feedItems[] = [
                'type' => 'goal',
                'id' => 888,
                'user_id' => 2,
                'author_name' => 'Green Team',
                'author_avatar' => '/assets/img/defaults/default_avatar.webp',
                'title' => 'Plant 500 Trees by December',
                'body' => 'We are 60% of the way there! Join us this Saturday.',
                'created_at' => date('Y-m-d H:i:s', strtotime('-5 hours')),
                'likes_count' => 12,
                'extra_2' => date('Y-12-31')
            ];
            // Mock Volunteering
            $feedItems[] = [
                'type' => 'volunteering',
                'id' => 777,
                'user_id' => 3,
                'author_name' => 'Elder Care Alliance',
                'author_avatar' => '/assets/img/defaults/default_avatar.webp',
                'title' => 'Drivers Needed for Weekend Meals',
                'body' => 'Earn 2 Time Credits per hour. Must have own vehicle.',
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'likes_count' => 8,
                'extra_1' => 'Community Center',
                'extra_2' => 2
            ];
        }

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
// Add home feed CSS - standard load (minified version)
$cssVersion = '2.5.0';
$additionalCSS = '
<link rel="stylesheet" href="/assets/css/nexus-home.min.css?v=' . $cssVersion . '">';
require __DIR__ . '/../layouts/modern/header.php';
?>


<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<!-- Pull to Refresh removed -->

<!-- Main content wrapper (main tag opened in header.php) -->
<div class="htb-container htb-container-full" style="position: relative; z-index: 10;">

    <div class="home-two-column-grid">
        <!-- Main Feed Column -->
        <div class="home-feed-column">
            <div class="fds-feed-container">

        <!-- Smart Welcome Section with Dynamic Buttons -->
        <div class="nexus-welcome-hero">
            <div class="nexus-welcome-content">
                <?php if ($isLoggedIn): ?>
                    <h1 class="nexus-welcome-title">Welcome back, <?= htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? 'User')[0]) ?>!</h1>
                    <p class="nexus-welcome-subtitle">What would you like to do today?</p>
                <?php else: ?>
                    <h1 class="nexus-welcome-title"><?= htmlspecialchars($hero_title ?? 'Community Exchange') ?></h1>
                    <p class="nexus-welcome-subtitle"><?= htmlspecialchars($hero_subtitle ?? 'Share skills, build community, and exchange time.') ?></p>
                <?php endif; ?>
            </div>

        </div>

            <!-- Universal Feed Filter Component -->
            <?php include __DIR__ . '/partials/universal-feed-filter.php'; ?>

        <!-- Simple Create Post Prompt (Facebook-style) - Opens full compose page -->
        <div class="fds-create-post">
            <div style="padding: 14px 16px;">
                <?php if ($isLoggedIn): ?>
                    <!-- Logged In: Simple prompt that opens /compose -->
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/compose" class="compose-prompt-link">
                        <div class="compose-prompt">
                            <div class="composer-avatar-ring">
                                <?= webp_avatar($_SESSION['user_avatar'] ?? null, $_SESSION['user_name'] ?? 'User', 40) ?>
                            </div>
                            <div class="compose-prompt-input">
                                What's on your mind, <?= htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? 'User')[0]) ?>?
                            </div>
                        </div>
                    </a>

                    <!-- Quick action buttons -->
                    <div class="compose-quick-actions">
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/compose?type=post" class="compose-quick-btn">
                            <i class="fa-solid fa-pen" style="color: #6366f1;"></i>
                            <span>Post</span>
                        </a>
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/compose?type=listing" class="compose-quick-btn">
                            <i class="fa-solid fa-hand-holding-heart" style="color: #10b981;"></i>
                            <span>Listing</span>
                        </a>
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/compose?type=event" class="compose-quick-btn">
                            <i class="fa-solid fa-calendar-plus" style="color: #ec4899;"></i>
                            <span>Event</span>
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Logged Out: Join CTA -->
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/register" class="compose-prompt-link">
                        <div class="compose-prompt">
                            <div class="composer-avatar-ring guest">
                                <div style="width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #6366f1, #a855f7); display: flex; align-items: center; justify-content: center;">
                                    <i class="fa-solid fa-user" style="color: white; font-size: 18px;"></i>
                                </div>
                            </div>
                            <div class="compose-prompt-input">
                                What's on your mind? Join to share...
                            </div>
                        </div>
                    </a>

                    <!-- Auth buttons -->
                    <div class="compose-quick-actions">
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/login" class="compose-quick-btn">
                            <i class="fa-solid fa-right-to-bracket" style="color: #3b82f6;"></i>
                            <span>Log In</span>
                        </a>
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/register" class="compose-quick-btn highlight">
                            <i class="fa-solid fa-user-plus" style="color: #fff;"></i>
                            <span>Sign Up</span>
                        </a>
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/listings" class="compose-quick-btn">
                            <i class="fa-solid fa-compass" style="color: #f59e0b;"></i>
                            <span>Browse</span>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Skeleton Loaders (shown on initial load) -->
        <div class="skeleton-container" id="skeletonLoader" aria-label="Loading feed">
            <?php for ($i = 0; $i < 3; $i++): ?>
            <div class="skeleton-card">
                <div style="display: flex; gap: 12px; margin-bottom: 12px;">
                    <div class="skeleton-avatar"></div>
                    <div style="flex: 1;">
                        <div class="skeleton-line skeleton-line-medium" style="margin-bottom: 8px;"></div>
                        <div class="skeleton-line skeleton-line-short"></div>
                    </div>
                </div>
                <div class="skeleton-line skeleton-line-full" style="margin-bottom: 8px;"></div>
                <div class="skeleton-line skeleton-line-medium" style="margin-bottom: 8px;"></div>
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
            <div id="feedSentinel" class="infinite-scroll-loader" style="display: none;">
                <i class="fa-solid fa-spinner"></i>
                <span>Loading more...</span>
            </div>

            <!-- End of Feed Message -->
            <div id="feedEndMessage" class="feed-end-message" style="display: none;">
                <i class="fa-regular fa-check-circle"></i>
                You're all caught up!
            </div>
        <?php else: ?>
            <?php if (!empty($errorMsg)): ?>
            <div class="fb-card" style="text-align: center; padding: 48px 20px; border-left: 4px solid #ef4444;">
                <div style="font-size: 48px; margin-bottom: 16px;">⚠️</div>
                <h3 class="feed-item-title" style="font-size: 18px; color: #ef4444;">Feed Error</h3>
                <p style="color: var(--feed-text-secondary);"><?= htmlspecialchars($errorMsg) ?></p>
            </div>
            <?php else: ?>
            <div class="fb-card" style="text-align: center; padding: 48px 20px;">
                <div style="font-size: 48px; margin-bottom: 16px;">👋</div>
                <h3 class="feed-item-title" style="font-size: 18px;">Welcome to the Feed</h3>
                <p style="color: var(--feed-text-secondary);">Be the first to post something!</p>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        </div>

            </div> <!-- /.fds-feed-container -->
        </div> <!-- /.home-feed-column -->

        <!-- Sidebar Column -->
        <aside class="home-sidebar-column">
            <?php
            /**
             * INTELLIGENT SIDEBAR - Meta/Instagram Style
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
            $basePath = \Nexus\Core\TenantContext::getBasePath();
            $DB = '\Nexus\Core\Database';

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
            <div class="sidebar-card" style="overflow:visible;">
                <div style="background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 50%,#ec4899 100%);padding:20px;text-align:center;border-radius:20px 20px 0 0;margin:-1px -1px 0 -1px;">
                    <?= webp_avatar($_SESSION['user_avatar'] ?? null, $_SESSION['user_name'] ?? 'Member', 70) ?>
                    <h4 style="color:white;margin:10px 0 2px;font-size:16px;"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Member') ?></h4>
                    <p style="color:rgba(255,255,255,0.8);font-size:12px;margin:0;">@<?= htmlspecialchars($_SESSION['username'] ?? 'member') ?></p>
                </div>
                <div class="sidebar-card-body" style="padding-top:16px;">
                    <div style="display:flex;justify-content:space-around;text-align:center;margin-bottom:12px;">
                        <a href="<?= $basePath ?>/profile" style="text-decoration:none;flex:1;">
                            <span style="display:block;font-size:22px;font-weight:800;background:linear-gradient(135deg,#6366f1,#ec4899);-webkit-background-clip:text;-webkit-text-fill-color:transparent;"><?= (int)($sidebarData['stats']['total_listings'] ?? 0) ?></span>
                            <span class="sidebar-text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:0.5px;">Listings</span>
                        </a>
                        <div class="sidebar-divider" style="width:1px;"></div>
                        <a href="<?= $basePath ?>/wallet" style="text-decoration:none;flex:1;">
                            <span style="display:block;font-size:22px;font-weight:800;color:#10b981;"><?= number_format((float)($sidebarData['stats']['hours_given'] ?? 0), 1) ?></span>
                            <span class="sidebar-text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:0.5px;">Given</span>
                        </a>
                        <div class="sidebar-divider" style="width:1px;"></div>
                        <a href="<?= $basePath ?>/wallet" style="text-decoration:none;flex:1;">
                            <span style="display:block;font-size:22px;font-weight:800;color:#f59e0b;"><?= number_format((float)($sidebarData['stats']['hours_received'] ?? 0), 1) ?></span>
                            <span class="sidebar-text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:0.5px;">Received</span>
                        </a>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                        <a href="<?= $basePath ?>/listings?user=me&type=offer" style="text-decoration:none;display:flex;align-items:center;gap:8px;padding:10px 12px;background:linear-gradient(135deg,rgba(16,185,129,0.1),rgba(5,150,105,0.05));border-radius:10px;">
                            <i class="fa-solid fa-hand-holding-heart" style="color:#10b981;"></i>
                            <span class="sidebar-text-dark" style="font-size:13px;"><strong><?= (int)($sidebarData['stats']['offers'] ?? 0) ?></strong> Offers</span>
                        </a>
                        <a href="<?= $basePath ?>/listings?user=me&type=request" style="text-decoration:none;display:flex;align-items:center;gap:8px;padding:10px 12px;background:linear-gradient(135deg,rgba(249,115,22,0.1),rgba(234,88,12,0.05));border-radius:10px;">
                            <i class="fa-solid fa-hand" style="color:#f97316;"></i>
                            <span class="sidebar-text-dark" style="font-size:13px;"><strong><?= (int)($sidebarData['stats']['requests'] ?? 0) ?></strong> Requests</span>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ============================================
                 FRIENDS - Your Connections (Logged In)
                 ============================================ -->
            <?php if ($isLoggedIn && !empty($sidebarData['friends'])): ?>
            <div class="sidebar-card">
                <div class="sidebar-card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h3 style="margin:0;"><i class="fa-solid fa-user-group"></i> Friends</h3>
                    <a href="<?= $basePath ?>/connections" style="font-size:12px;color:#6366f1;text-decoration:none;">See All</a>
                </div>
                <div class="sidebar-card-body">
                    <?php foreach ($sidebarData['friends'] as $friend):
                        $friendName = $friend['profile_type'] === 'organization'
                            ? ($friend['organization_name'] ?: 'Organization')
                            : (trim(($friend['first_name'] ?? '') . ' ' . ($friend['last_name'] ?? '')) ?: 'Member');
                        $isOnline = !empty($friend['last_active_at']) && strtotime($friend['last_active_at']) > strtotime('-5 minutes');
                        $isRecent = !empty($friend['last_active_at']) && strtotime($friend['last_active_at']) > strtotime('-24 hours');
                    ?>
                        <a href="<?= $basePath ?>/profile/<?= $friend['id'] ?>" class="sidebar-hover-item" style="display:flex;align-items:center;gap:12px;padding:10px;margin:-4px -8px;border-radius:12px;text-decoration:none;transition:background 0.2s;">
                            <div style="position:relative;">
                                <?= webp_avatar($friend['avatar_url'] ?: null, $friendName, 44) ?>
                                <?php if ($isOnline): ?>
                                    <span class="sidebar-online-dot" style="position:absolute;bottom:2px;right:2px;width:12px;height:12px;background:#10b981;border-radius:50%;" title="Online now"></span>
                                <?php elseif ($isRecent): ?>
                                    <span class="sidebar-online-dot" style="position:absolute;bottom:2px;right:2px;width:12px;height:12px;background:#f59e0b;border-radius:50%;" title="Active today"></span>
                                <?php endif; ?>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <span class="sidebar-text-dark" style="display:block;font-weight:600;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($friendName) ?></span>
                                <span class="sidebar-text-muted" style="font-size:12px;"><?= htmlspecialchars($friend['location'] ?: 'Community Member') ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ============================================
                 2. COMMUNITY PULSE - Always Show
                 ============================================ -->
            <?php if ($sidebarData['community']): ?>
            <div class="sidebar-card">
                <div class="sidebar-card-header">
                    <h3><i class="fa-solid fa-heart-pulse"></i> Community Pulse</h3>
                </div>
                <div class="sidebar-card-body">
                    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;">
                        <a href="<?= $basePath ?>/members" style="text-decoration:none;text-align:center;padding:12px 8px;background:linear-gradient(135deg,rgba(99,102,241,0.1),rgba(139,92,246,0.05));border-radius:12px;transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                            <i class="fa-solid fa-users" style="font-size:18px;color:#6366f1;display:block;margin-bottom:6px;"></i>
                            <span style="font-size:20px;font-weight:800;color:#6366f1;display:block;"><?= number_format((int)($sidebarData['community']['members'] ?? 0)) ?></span>
                            <span class="sidebar-text-muted" style="font-size:10px;text-transform:uppercase;letter-spacing:0.5px;">Members</span>
                        </a>
                        <a href="<?= $basePath ?>/listings" style="text-decoration:none;text-align:center;padding:12px 8px;background:linear-gradient(135deg,rgba(16,185,129,0.1),rgba(5,150,105,0.05));border-radius:12px;transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                            <i class="fa-solid fa-hand-holding-heart" style="font-size:18px;color:#10b981;display:block;margin-bottom:6px;"></i>
                            <span style="font-size:20px;font-weight:800;color:#10b981;display:block;"><?= number_format((int)($sidebarData['community']['listings'] ?? 0)) ?></span>
                            <span class="sidebar-text-muted" style="font-size:10px;text-transform:uppercase;letter-spacing:0.5px;">Listings</span>
                        </a>
                        <a href="<?= $basePath ?>/events" style="text-decoration:none;text-align:center;padding:12px 8px;background:linear-gradient(135deg,rgba(236,72,153,0.1),rgba(219,39,119,0.05));border-radius:12px;transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                            <i class="fa-solid fa-calendar" style="font-size:18px;color:#ec4899;display:block;margin-bottom:6px;"></i>
                            <span style="font-size:20px;font-weight:800;color:#ec4899;display:block;"><?= number_format((int)($sidebarData['community']['events'] ?? 0)) ?></span>
                            <span class="sidebar-text-muted" style="font-size:10px;text-transform:uppercase;letter-spacing:0.5px;">Events</span>
                        </a>
                        <a href="<?= $basePath ?>/groups" style="text-decoration:none;text-align:center;padding:12px 8px;background:linear-gradient(135deg,rgba(245,158,11,0.1),rgba(217,119,6,0.05));border-radius:12px;transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                            <i class="fa-solid fa-users-rectangle" style="font-size:18px;color:#f59e0b;display:block;margin-bottom:6px;"></i>
                            <span style="font-size:20px;font-weight:800;color:#f59e0b;display:block;"><?= number_format((int)($sidebarData['community']['groups_count'] ?? 0)) ?></span>
                            <span class="sidebar-text-muted" style="font-size:10px;text-transform:uppercase;letter-spacing:0.5px;">Groups</span>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ============================================
                 3. SUGGESTED FOR YOU (Logged In)
                 ============================================ -->
            <?php if ($isLoggedIn && !empty($sidebarData['recommended'])): ?>
            <div class="sidebar-card">
                <div class="sidebar-card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h3 style="margin:0;"><i class="fa-solid fa-sparkles"></i> Suggested For You</h3>
                    <a href="<?= $basePath ?>/listings" style="font-size:12px;color:#6366f1;text-decoration:none;">See All</a>
                </div>
                <div class="sidebar-card-body">
                    <?php foreach ($sidebarData['recommended'] as $listing): ?>
                        <a href="<?= $basePath ?>/listings/<?= $listing['id'] ?>" class="sidebar-hover-item" style="display:flex;align-items:center;gap:12px;padding:10px;margin:-4px -8px;border-radius:12px;text-decoration:none;transition:background 0.2s;">
                            <div style="width:44px;height:44px;border-radius:12px;background:<?= $listing['type'] === 'offer' ? 'linear-gradient(135deg,#10b981,#059669)' : 'linear-gradient(135deg,#f97316,#ea580c)' ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fa-solid <?= $listing['type'] === 'offer' ? 'fa-hand-holding-heart' : 'fa-hand' ?>" style="color:white;font-size:16px;"></i>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <span class="sidebar-text-dark" style="display:block;font-weight:600;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($listing['title']) ?></span>
                                <span class="sidebar-text-muted" style="font-size:12px;">by <?= htmlspecialchars($listing['owner_name'] ?? 'Member') ?></span>
                            </div>
                            <span style="padding:4px 10px;border-radius:20px;font-size:10px;font-weight:600;text-transform:uppercase;background:<?= $listing['type'] === 'offer' ? 'rgba(16,185,129,0.1)' : 'rgba(249,115,22,0.1)' ?>;color:<?= $listing['type'] === 'offer' ? '#10b981' : '#f97316' ?>;"><?= $listing['type'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ============================================
                 4. BROWSE CATEGORIES - Smart: Only with listings
                 ============================================ -->
            <?php if (!empty($sidebarData['trending'])): ?>
            <div class="sidebar-card">
                <div class="sidebar-card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h3 style="margin:0;"><i class="fa-solid fa-fire"></i> Top Categories</h3>
                    <a href="<?= $basePath ?>/listings" style="font-size:12px;color:#6366f1;text-decoration:none;">All Listings</a>
                </div>
                <div class="sidebar-card-body">
                    <div style="display:flex;flex-wrap:wrap;gap:8px;">
                        <?php foreach ($sidebarData['trending'] as $index => $cat):
                            $colors = ['#6366f1', '#ec4899', '#10b981', '#f59e0b', '#8b5cf6', '#06b6d4', '#ef4444', '#84cc16'];
                            $color = $cat['color'] ? '#' . ltrim($cat['color'], '#') : $colors[$index % count($colors)];
                            // Ensure color is a valid hex
                            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                                $color = $colors[$index % count($colors)];
                            }
                        ?>
                            <a href="<?= $basePath ?>/listings?cat=<?= (int)$cat['id'] ?>"
                               style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:<?= $color ?>15;border-radius:20px;text-decoration:none;transition:all 0.2s;border:1px solid <?= $color ?>30;"
                               onmouseover="this.style.background='<?= $color ?>25';this.style.transform='translateY(-1px)'"
                               onmouseout="this.style.background='<?= $color ?>15';this.style.transform='translateY(0)'">
                                <span style="font-size:13px;font-weight:500;color:<?= $color ?>;"><?= htmlspecialchars($cat['name']) ?></span>
                                <span style="font-size:11px;font-weight:600;color:<?= $color ?>;opacity:0.7;">(<?= (int)$cat['listing_count'] ?>)</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ============================================
                 5. PEOPLE YOU MAY KNOW - Facebook Style
                 ============================================ -->
            <?php if (!empty($sidebarData['members'])): ?>
            <div class="sidebar-card">
                <div class="sidebar-card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h3 style="margin:0;"><i class="fa-solid fa-user-plus"></i> People You May Know</h3>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <?php if (!empty($sidebarData['communityRankActive'])): ?>
                        <span style="display:inline-flex;align-items:center;gap:4px;background:linear-gradient(135deg,rgba(16,185,129,0.15),rgba(6,182,212,0.15));border:1px solid rgba(16,185,129,0.3);border-radius:12px;padding:2px 8px;font-size:10px;color:#10b981;" title="Members ranked by CommunityRank algorithm">
                            <i class="fa-solid fa-diagram-project" style="font-size:9px;"></i> CR
                        </span>
                        <?php endif; ?>
                        <a href="<?= $basePath ?>/members" style="font-size:12px;color:#6366f1;text-decoration:none;">See All</a>
                    </div>
                </div>
                <div class="sidebar-card-body">
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
                        <div class="sidebar-border-light" style="display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid;">
                            <div style="position:relative;">
                                <?= webp_avatar($member['avatar_url'] ?: null, $memberName, 48) ?>
                                <?php if ($isOnline): ?>
                                    <span class="sidebar-online-dot" style="position:absolute;bottom:2px;right:2px;width:12px;height:12px;background:#10b981;border:2px solid var(--bg-card, white);border-radius:50%;" title="Online now"></span>
                                <?php elseif ($isRecentlyActive): ?>
                                    <span class="sidebar-online-dot" style="position:absolute;bottom:2px;right:2px;width:12px;height:12px;background:#f59e0b;border:2px solid var(--bg-card, white);border-radius:50%;" title="Active today"></span>
                                <?php endif; ?>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <a href="<?= $basePath ?>/profile/<?= $member['id'] ?>" class="sidebar-text-dark" style="display:block;font-weight:600;font-size:14px;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($memberName) ?></a>
                                <span class="sidebar-text-muted" style="font-size:12px;"><?= htmlspecialchars($member['location'] ?: 'Community Member') ?></span>
                            </div>
                            <a href="<?= $basePath ?>/profile/<?= $member['id'] ?>" style="padding:6px 14px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;transition:opacity 0.2s;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">View</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ============================================
                 6. UPCOMING EVENTS
                 ============================================ -->
            <?php if (!empty($sidebarData['events'])): ?>
            <div class="sidebar-card">
                <div class="sidebar-card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h3 style="margin:0;"><i class="fa-solid fa-calendar-days"></i> Upcoming Events</h3>
                    <a href="<?= $basePath ?>/events" style="font-size:12px;color:#6366f1;text-decoration:none;">See All</a>
                </div>
                <div class="sidebar-card-body">
                    <?php foreach ($sidebarData['events'] as $event):
                        $eventDate = new DateTime($event['start_time']);
                    ?>
                        <a href="<?= $basePath ?>/events/<?= $event['id'] ?>" class="sidebar-hover-item" style="display:flex;gap:12px;padding:10px;margin:-4px -8px;border-radius:12px;text-decoration:none;transition:background 0.2s;">
                            <div style="width:50px;padding:8px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:12px;text-align:center;color:white;flex-shrink:0;">
                                <span style="display:block;font-size:10px;font-weight:600;text-transform:uppercase;opacity:0.9;"><?= $eventDate->format('M') ?></span>
                                <span style="display:block;font-size:22px;font-weight:800;line-height:1;"><?= $eventDate->format('j') ?></span>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <span class="sidebar-text-dark" style="display:block;font-weight:600;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($event['title']) ?></span>
                                <span class="sidebar-text-muted" style="display:flex;align-items:center;gap:4px;font-size:12px;margin-top:4px;">
                                    <i class="fa-regular fa-clock"></i> <?= $eventDate->format('g:i A') ?>
                                </span>
                                <?php if (!empty($event['location'])): ?>
                                <span class="sidebar-text-muted" style="display:flex;align-items:center;gap:4px;font-size:12px;">
                                    <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars(mb_strimwidth($event['location'], 0, 25, '...')) ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ============================================
                 7. POPULAR GROUPS
                 ============================================ -->
            <?php if (!empty($sidebarData['groups'])): ?>
            <div class="sidebar-card">
                <div class="sidebar-card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h3 style="margin:0;"><i class="fa-solid fa-users-rectangle"></i> Popular Groups</h3>
                    <a href="<?= $basePath ?>/groups" style="font-size:12px;color:#6366f1;text-decoration:none;">See All</a>
                </div>
                <div class="sidebar-card-body">
                    <?php foreach ($sidebarData['groups'] as $group): ?>
                        <a href="<?= $basePath ?>/groups/<?= $group['id'] ?>" class="sidebar-hover-item" style="display:flex;align-items:center;gap:12px;padding:10px;margin:-4px -8px;border-radius:12px;text-decoration:none;transition:background 0.2s;">
                            <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#f59e0b,#d97706);display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;">
                                <?php if (!empty($group['cover_image'])): ?>
                                    <img src="<?= htmlspecialchars($group['cover_image']) ?>" loading="lazy" style="width:100%;height:100%;object-fit:cover;">
                                <?php else: ?>
                                    <i class="fa-solid fa-users" style="color:white;font-size:18px;"></i>
                                <?php endif; ?>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <span class="sidebar-text-dark" style="display:block;font-weight:600;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($group['name']) ?></span>
                                <span class="sidebar-text-muted" style="font-size:12px;"><?= htmlspecialchars(mb_strimwidth($group['description'] ?? 'Community Group', 0, 30, '...')) ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ============================================
                 8. QUICK ACTIONS (Always Show)
                 ============================================ -->
            <div class="sidebar-card">
                <div class="sidebar-card-header">
                    <h3><i class="fa-solid fa-rocket"></i> Quick Actions</h3>
                </div>
                <div class="sidebar-card-body">
                    <?php if ($isLoggedIn): ?>
                    <a href="<?= $basePath ?>/compose?type=listing" style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:14px;text-decoration:none;margin-bottom:10px;transition:transform 0.2s,box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 20px rgba(99,102,241,0.3)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='none'">
                        <i class="fa-solid fa-plus-circle" style="color:white;font-size:22px;"></i>
                        <div>
                            <span style="display:block;font-weight:700;color:white;font-size:15px;">Create New Listing</span>
                            <span style="font-size:12px;color:rgba(255,255,255,0.8);">Share your skills with the community</span>
                        </div>
                    </a>
                    <?php endif; ?>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                        <a href="<?= $basePath ?>/compose?type=event" style="text-decoration:none;display:flex;flex-direction:column;align-items:center;gap:6px;padding:14px 8px;background:linear-gradient(135deg,rgba(236,72,153,0.1),rgba(219,39,119,0.05));border-radius:12px;transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                            <i class="fa-solid fa-calendar-plus" style="font-size:20px;color:#ec4899;"></i>
                            <span class="sidebar-text-dark" style="font-size:12px;font-weight:600;">Host Event</span>
                        </a>
                        <a href="<?= $basePath ?>/compose?type=poll" style="text-decoration:none;display:flex;flex-direction:column;align-items:center;gap:6px;padding:14px 8px;background:linear-gradient(135deg,rgba(99,102,241,0.1),rgba(139,92,246,0.05));border-radius:12px;transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                            <i class="fa-solid fa-square-poll-vertical" style="font-size:20px;color:#6366f1;"></i>
                            <span class="sidebar-text-dark" style="font-size:12px;font-weight:600;">Create Poll</span>
                        </a>
                        <a href="<?= $basePath ?>/compose?type=goal" style="text-decoration:none;display:flex;flex-direction:column;align-items:center;gap:6px;padding:14px 8px;background:linear-gradient(135deg,rgba(245,158,11,0.1),rgba(217,119,6,0.05));border-radius:12px;transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                            <i class="fa-solid fa-bullseye" style="font-size:20px;color:#f59e0b;"></i>
                            <span class="sidebar-text-dark" style="font-size:12px;font-weight:600;">Set Goal</span>
                        </a>
                        <a href="<?= $basePath ?>/groups" style="text-decoration:none;display:flex;flex-direction:column;align-items:center;gap:6px;padding:14px 8px;background:linear-gradient(135deg,rgba(16,185,129,0.1),rgba(5,150,105,0.05));border-radius:12px;transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                            <i class="fa-solid fa-users-rectangle" style="font-size:20px;color:#10b981;"></i>
                            <span class="sidebar-text-dark" style="font-size:12px;font-weight:600;">Groups</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- ============================================
                 FOOTER - App Download / Social
                 ============================================ -->
            <div style="text-align:center;padding:16px;color:#94a3b8;font-size:11px;">
                <p style="margin:0 0 8px;">© <?= date('Y') ?> Community Timebank</p>
                <div style="display:flex;justify-content:center;gap:12px;">
                    <a href="#" style="color:#94a3b8;"><i class="fa-brands fa-facebook" style="font-size:16px;"></i></a>
                    <a href="#" style="color:#94a3b8;"><i class="fa-brands fa-twitter" style="font-size:16px;"></i></a>
                    <a href="#" style="color:#94a3b8;"><i class="fa-brands fa-instagram" style="font-size:16px;"></i></a>
                </div>
            </div>
        </aside>
    </div> <!-- /.home-two-column-grid -->

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

<script>
    const IS_LOGGED_IN = <?= json_encode($isLoggedIn) ?>;
    const BASE_URL = "<?= \Nexus\Core\TenantContext::getBasePath() ?>";

    // ============================================
    // FACEBOOK-STYLE FEED MENU FUNCTIONS
    // ============================================

    // Toggle 3-dot menu dropdown
    function toggleFeedItemMenu(btn) {
        event.stopPropagation();
        const dropdown = btn.nextElementSibling;
        if (!dropdown) return;

        const isOpen = dropdown.classList.contains('show');
        closeFeedMenus();

        if (!isOpen) {
            dropdown.classList.add('show');
            document.addEventListener('click', closeFeedMenusOnOutsideClick);
        }
    }

    function closeFeedMenus() {
        document.querySelectorAll('.feed-item-menu-dropdown.show').forEach(d => {
            d.classList.remove('show');
        });
        document.removeEventListener('click', closeFeedMenusOnOutsideClick);
    }

    function closeFeedMenusOnOutsideClick(e) {
        if (!e.target.closest('.feed-item-menu-container')) {
            closeFeedMenus();
        }
    }

    // Hide post function
    function hidePost(postId) {
        if (!IS_LOGGED_IN) {
            window.location.href = BASE_URL + '/login';
            return;
        }

        fetch(BASE_URL + '/api/feed/hide', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ post_id: postId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Fade out the post
                const card = event.target.closest('.fb-card');
                if (card) {
                    card.style.transition = 'opacity 0.3s, transform 0.3s';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.95)';
                    setTimeout(() => card.remove(), 300);
                }
                showFeedToast('Post hidden. You won\'t see this anymore.');
            } else {
                showFeedToast(data.error || 'Could not hide post', 'error');
            }
        })
        .catch(() => showFeedToast('Could not hide post', 'error'));
    }

    // Mute user function
    function muteUser(userId) {
        if (!IS_LOGGED_IN) {
            window.location.href = BASE_URL + '/login';
            return;
        }

        fetch(BASE_URL + '/api/feed/mute', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ user_id: userId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showFeedToast('User muted. You\'ll see fewer of their posts.');
            } else {
                showFeedToast(data.error || 'Could not mute user', 'error');
            }
        })
        .catch(() => showFeedToast('Could not mute user', 'error'));
    }

    // Report post function
    function reportPost(postId) {
        if (!IS_LOGGED_IN) {
            window.location.href = BASE_URL + '/login';
            return;
        }

        if (confirm('Are you sure you want to report this post?')) {
            fetch(BASE_URL + '/api/feed/report', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ post_id: postId, target_type: 'post' })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showFeedToast('Thanks for letting us know. We\'ll review this post.');
                } else {
                    showFeedToast(data.error || 'Could not submit report', 'error');
                }
            })
            .catch(() => showFeedToast('Could not submit report', 'error'));
        }
    }

    // Simple toast notification
    function showFeedToast(message, type = 'success') {
        // Use NexusMobile toast if available
        if (window.NexusMobile && NexusMobile.showToast) {
            NexusMobile.showToast(message, type);
            return;
        }

        // Fallback toast
        const existing = document.querySelector('.feed-toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = 'feed-toast';
        toast.style.cssText = `
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%);
            background: ${type === 'error' ? '#ef4444' : '#1e293b'};
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            z-index: 10000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            animation: toastSlide 0.3s ease;
        `;
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Ensure SocialInteractions is configured (belt and suspenders)
    window.SocialInteractions = window.SocialInteractions || {};
    window.SocialInteractions.isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;
    console.log('Home.php SocialInteractions config:', window.SocialInteractions);

    // ============================================
    // SKELETON LOADER - Show shimmer then reveal content
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        const skeleton = document.getElementById('skeletonLoader');
        const feed = document.getElementById('feedContainer');

        console.log('[SKELETON LOADER] Elements found:', { skeleton: !!skeleton, feed: !!feed });

        if (!skeleton || !feed) {
            console.error('[SKELETON LOADER] Missing elements!');
            return;
        }

        // Brief shimmer effect then reveal real content
        setTimeout(function() {
            console.log('[SKELETON LOADER] Hiding skeleton and showing feed');
            skeleton.classList.add('hidden');
            feed.classList.add('loaded');
        }, 400); // Brief delay for perceived performance
    });

    // ============================================
    // Pull-to-refresh feature has been permanently removed

    // Feed Filter Function
    function filterFeed(filterType) {
        console.log('[FilterFeed] Filtering by:', filterType);

        // Update button states
        document.querySelectorAll('.feed-filter-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`.feed-filter-btn[data-filter="${filterType}"]`)?.classList.add('active');

        // Get all feed items - use fb-card which is the main feed card class
        const feedItems = document.querySelectorAll('.fb-card[data-feed-type]');
        let visibleCount = 0;

        feedItems.forEach(item => {
            if (filterType === 'all') {
                item.style.display = '';
                visibleCount++;
                return;
            }

            // Get item type from data attribute
            const itemType = item.dataset.feedType;

            if (itemType === filterType) {
                item.style.display = '';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });

        console.log('[FilterFeed] Visible items:', visibleCount, 'of', feedItems.length);

        // Haptic feedback
        if (navigator.vibrate) navigator.vibrate(10);
    }

    // ============================================
    // UNIVERSAL FEED FILTER INTEGRATION
    // Connect FeedFilter component to server-side ranked queries
    // ============================================
    if (typeof FeedFilter !== 'undefined') {
        // Track previous state to detect changes that require reload
        let previousAlgo = FeedFilter.getActiveFilter().algorithmMode;
        let previousLocation = FeedFilter.getActiveFilter().locationMode;
        let previousRadius = FeedFilter.getActiveFilter().radius;

        FeedFilter.onFilterChange(function(state) {
            // Check if algo, location mode, or radius changed - these require server reload
            const needsReload = (
                state.algorithmMode !== previousAlgo ||
                state.locationMode !== previousLocation ||
                (state.locationMode === 'nearby' && state.radius !== previousRadius)
            );

            if (needsReload) {
                // These changes affect server-side queries, need full page reload
                // URL is already updated by FeedFilter component
                console.log('[FeedFilter] Server-side change detected, reloading...');
                window.location.reload();
                return;
            }

            // Update tracking
            previousAlgo = state.algorithmMode;
            previousLocation = state.locationMode;
            previousRadius = state.radius;

            // Map new filter names to legacy filterFeed names
            const filterMap = {
                'all': 'all',
                'listings': 'listings',
                'events': 'events',
                'goals': 'goals',
                'polls': 'polls',
                'volunteering': 'volunteering',
                'groups': 'groups',
                'resources': 'resources'
            };

            const legacyFilter = filterMap[state.filter] || 'all';

            // Call the existing filterFeed function for client-side filtering
            filterFeed(legacyFilter);

            // Handle sub-filters (offers/requests for listings)
            if (state.filter === 'listings' && state.subFilter) {
                const feedItems = document.querySelectorAll('[data-feed-type="listings"], .feed-listing');
                feedItems.forEach(item => {
                    if (state.subFilter === 'all') {
                        item.style.display = '';
                    } else {
                        const listingType = item.dataset.listingType ||
                            (item.querySelector('.listing-type-offer') ? 'offers' :
                             item.querySelector('.listing-type-request') ? 'requests' : null);

                        if (listingType === state.subFilter) {
                            item.style.display = '';
                        } else if (listingType) {
                            item.style.display = 'none';
                        }
                    }
                });
            }
        });
    }

    // ============================================
    // SOCIAL INTERACTION FUNCTIONS
    // Now provided by shared library: /assets/js/social-interactions.js
    // Functions available globally: toggleLike, toggleCommentSection,
    // fetchComments, submitComment, repostToFeed, deletePost, showToast,
    // toggleReaction, showReplyForm, submitReply, editComment, deleteComment
    // ============================================

    // Focus Composer (page-specific) - scrolls to and focuses the textarea
    function focusComposer() {
        const composer = document.getElementById('composer-input');
        if (composer) {
            composer.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => composer.focus(), 300);
        }
    }

    // Image preview (page-specific)
    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('image-preview-img').src = e.target.result;
                document.getElementById('image-preview-area').style.display = 'block';

                // Hide video preview if showing (can only have image OR video)
                const videoArea = document.getElementById('video-preview-area');
                const videoPlayer = document.getElementById('video-preview-player');
                const videoInput = document.getElementById('post-video-input');
                if (videoArea) {
                    videoArea.style.display = 'none';
                    if (videoPlayer && videoPlayer.src) {
                        URL.revokeObjectURL(videoPlayer.src);
                        videoPlayer.src = '';
                    }
                    if (videoInput) videoInput.value = '';
                }
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function removeImage() {
        document.getElementById('post-image-input').value = '';
        document.getElementById('image-preview-area').style.display = 'none';
    }

    // ============================================
    // VIDEO PREVIEW & UPLOAD
    // ============================================
    function previewVideo(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const maxSize = 100 * 1024 * 1024; // 100MB limit

            if (file.size > maxSize) {
                showToast('Video must be less than 100MB');
                input.value = '';
                return;
            }

            const videoPlayer = document.getElementById('video-preview-player');
            const videoArea = document.getElementById('video-preview-area');

            // Create object URL for preview
            const videoURL = URL.createObjectURL(file);
            videoPlayer.src = videoURL;
            videoArea.style.display = 'block';

            // Hide image preview if showing
            document.getElementById('image-preview-area').style.display = 'none';
            document.getElementById('post-image-input').value = '';
        }
    }

    function removeVideo() {
        const videoPlayer = document.getElementById('video-preview-player');
        const videoArea = document.getElementById('video-preview-area');

        // Revoke the object URL to free memory
        if (videoPlayer.src) {
            URL.revokeObjectURL(videoPlayer.src);
        }

        videoPlayer.src = '';
        document.getElementById('post-video-input').value = '';
        videoArea.style.display = 'none';
    }

    // ============================================
    // EMOJI PICKER
    // ============================================
    const emojiData = {
        smileys: ['😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '🙃', '😉', '😊', '😇', '🥰', '😍', '🤩', '😘', '😗', '😚', '😙', '🥲', '😋', '😛', '😜', '🤪', '😝', '🤑', '🤗', '🤭', '🤫', '🤔', '🤐', '🤨', '😐', '😑', '😶', '😏', '😒', '🙄', '😬', '🤥', '😌', '😔', '😪', '🤤', '😴', '😷', '🤒', '🤕', '🤢', '🤮', '🤧', '🥵', '🥶', '🥴', '😵', '🤯', '🤠', '🥳', '🥸', '😎', '🤓', '🧐'],
        people: ['👋', '🤚', '🖐️', '✋', '🖖', '👌', '🤌', '🤏', '✌️', '🤞', '🤟', '🤘', '🤙', '👈', '👉', '👆', '🖕', '👇', '👍', '👎', '✊', '👊', '🤛', '🤜', '👏', '🙌', '👐', '🤲', '🤝', '🙏', '💪', '🦾', '🦿', '🦵', '🦶', '👂', '🦻', '👃', '🧠', '🫀', '🫁', '🦷', '🦴', '👀', '👁️', '👅', '👄', '👶', '🧒', '👦', '👧', '🧑', '👱', '👨', '🧔', '👩', '🧓', '👴', '👵'],
        nature: ['🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼', '🐨', '🐯', '🦁', '🐮', '🐷', '🐽', '🐸', '🐵', '🙈', '🙉', '🙊', '🐒', '🐔', '🐧', '🐦', '🐤', '🐣', '🐥', '🦆', '🦅', '🦉', '🦇', '🐺', '🐗', '🐴', '🦄', '🐝', '🐛', '🦋', '🐌', '🐞', '🐜', '🦟', '🦗', '🌸', '💐', '🌷', '🌹', '🥀', '🌺', '🌻', '🌼', '🌱', '🌲', '🌳', '🌴', '🌵', '🌾', '🌿', '☘️', '🍀', '🍁', '🍂', '🍃'],
        food: ['🍕', '🍔', '🍟', '🌭', '🍿', '🧂', '🥓', '🥚', '🍳', '🧇', '🥞', '🧈', '🍞', '🥐', '🥖', '🥨', '🧀', '🥗', '🥙', '🥪', '🌮', '🌯', '🫔', '🥫', '🍝', '🍜', '🍲', '🍛', '🍣', '🍱', '🥟', '🦪', '🍤', '🍙', '🍚', '🍘', '🍥', '🥠', '🥮', '🍢', '🍡', '🍧', '🍨', '🍦', '🥧', '🧁', '🍰', '🎂', '🍮', '🍭', '🍬', '🍫', '🍩', '🍪', '🌰', '🥜', '🍯', '🥛', '🍼', '☕', '🍵', '🧃', '🥤', '🧋'],
        activities: ['⚽', '🏀', '🏈', '⚾', '🥎', '🎾', '🏐', '🏉', '🥏', '🎱', '🪀', '🏓', '🏸', '🏒', '🏑', '🥍', '🏏', '🪃', '🥅', '⛳', '🪁', '🏹', '🎣', '🤿', '🥊', '🥋', '🎽', '🛹', '🛼', '🛷', '⛸️', '🥌', '🎿', '⛷️', '🏂', '🪂', '🏋️', '🤼', '🤸', '🤺', '⛹️', '🤾', '🏌️', '🏇', '🧘', '🏄', '🏊', '🤽', '🚣', '🧗', '🚵', '🚴', '🏆', '🥇', '🥈', '🥉', '🏅', '🎖️', '🏵️', '🎗️', '🎫', '🎟️', '🎪', '🎭'],
        travel: ['🚗', '🚕', '🚙', '🚌', '🚎', '🏎️', '🚓', '🚑', '🚒', '🚐', '🛻', '🚚', '🚛', '🚜', '🦯', '🦽', '🦼', '🛴', '🚲', '🛵', '🏍️', '🛺', '🚨', '🚔', '🚍', '🚘', '🚖', '🚡', '🚠', '🚟', '🚃', '🚋', '🚞', '🚝', '🚄', '🚅', '🚈', '🚂', '🚆', '🚇', '🚊', '🚉', '✈️', '🛫', '🛬', '🛩️', '💺', '🛰️', '🚀', '🛸', '🚁', '🛶', '⛵', '🚤', '🛥️', '🛳️', '⛴️', '🚢', '⚓', '🪝', '⛽', '🚧', '🚦', '🚥'],
        objects: ['💡', '🔦', '🏮', '🪔', '📱', '📲', '💻', '🖥️', '🖨️', '⌨️', '🖱️', '🖲️', '💽', '💾', '💿', '📀', '📼', '📷', '📸', '📹', '🎥', '📽️', '🎞️', '📞', '☎️', '📟', '📠', '📺', '📻', '🎙️', '🎚️', '🎛️', '🧭', '⏱️', '⏲️', '⏰', '🕰️', '⌛', '⏳', '📡', '🔋', '🔌', '💰', '🪙', '💴', '💵', '💶', '💷', '💸', '💳', '🧾', '💎', '⚖️', '🪜', '🧰', '🪛', '🔧', '🔨', '⚒️', '🛠️', '⛏️', '🪚', '🔩', '⚙️'],
        symbols: ['❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔', '❣️', '💕', '💞', '💓', '💗', '💖', '💘', '💝', '💟', '☮️', '✝️', '☪️', '🕉️', '☸️', '✡️', '🔯', '🕎', '☯️', '☦️', '🛐', '⛎', '♈', '♉', '♊', '♋', '♌', '♍', '♎', '♏', '♐', '♑', '♒', '♓', '🆔', '⚛️', '🉑', '☢️', '☣️', '📴', '📳', '🈶', '🈚', '🈸', '🈺', '🈷️', '✴️', '🆚', '💮', '🉐', '㊙️', '㊗️', '🈴', '🈵', '🈹']
    };

    let currentEmojiCategory = 'smileys';

    function toggleEmojiPicker() {
        const picker = document.getElementById('emoji-picker-container');
        const isVisible = picker.style.display !== 'none';

        if (isVisible) {
            picker.style.display = 'none';
        } else {
            picker.style.display = 'block';
            renderEmojis(currentEmojiCategory);
        }
    }

    function renderEmojis(category) {
        const grid = document.getElementById('emoji-grid');
        const emojis = emojiData[category] || [];

        grid.innerHTML = emojis.map(emoji =>
            `<button type="button" class="emoji-item" onclick="insertEmoji('${emoji}')">${emoji}</button>`
        ).join('');

        // Update active tab
        document.querySelectorAll('.emoji-tab').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.category === category);
        });

        currentEmojiCategory = category;
    }

    function insertEmoji(emoji) {
        const textarea = document.getElementById('composer-input');
        if (!textarea) return;

        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;

        textarea.value = text.substring(0, start) + emoji + text.substring(end);
        textarea.selectionStart = textarea.selectionEnd = start + emoji.length;
        textarea.focus();

        // Close the picker after inserting emoji
        const picker = document.getElementById('emoji-picker-container');
        if (picker) {
            picker.style.display = 'none';
        }
    }

    // Initialize emoji tab clicks
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.emoji-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                renderEmojis(this.dataset.category);
            });
        });

        // Close emoji picker when clicking outside
        document.addEventListener('click', function(e) {
            const picker = document.getElementById('emoji-picker-container');
            const btn = document.getElementById('emoji-picker-btn');
            if (picker && !picker.contains(e.target) && e.target !== btn && !btn.contains(e.target)) {
                picker.style.display = 'none';
            }
        });
    });

    // ============================================
    // POST TYPE SWITCHING (Multi-Module Composer)
    // ============================================
    const postTypeConfig = {
        post: {
            submitText: 'Post',
            icon: 'fa-paper-plane',
            action: ''  // Submit to same page
        },
        listing: {
            submitText: 'Create Listing',
            icon: 'fa-hand-holding-heart',
            action: ''  // Submit to same page, handled by multi-module backend
        },
        event: {
            submitText: 'Create Event',
            icon: 'fa-calendar-plus',
            action: ''
        },
        goal: {
            submitText: 'Create Goal',
            icon: 'fa-bullseye',
            action: ''
        },
        poll: {
            submitText: 'Create Poll',
            icon: 'fa-chart-bar',
            action: ''
        }
    };

    function switchPostType(type) {
        const config = postTypeConfig[type];
        if (!config) return;

        // Update active tab
        document.querySelectorAll('.composer-type-tab').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.type === type);
        });

        // Update hidden input
        const typeInput = document.getElementById('post-type-input');
        if (typeInput) typeInput.value = type;

        // Update submit button
        const submitText = document.getElementById('submit-btn-text');
        const submitIcon = document.getElementById('submit-btn-icon');
        if (submitText) submitText.textContent = config.submitText;
        if (submitIcon) submitIcon.className = 'fa-solid ' + config.icon;

        // Show/hide fields
        document.querySelectorAll('.composer-fields-inline').forEach(field => {
            field.style.display = 'none';
        });
        const fieldsEl = document.getElementById('fields-' + type);
        if (fieldsEl) fieldsEl.style.display = 'block';

        // Update form action (all handled by same page now)
        const form = document.getElementById('composer-form');
        if (form) {
            form.action = config.action || '';
        }

        // Update required fields
        updateRequiredFields(type);
    }

    function updateRequiredFields(type) {
        // Remove required from all hidden fields
        document.querySelectorAll('.composer-fields-inline').forEach(container => {
            if (container.style.display === 'none') {
                container.querySelectorAll('input, textarea, select').forEach(el => {
                    el.required = false;
                });
            }
        });

        // Add required to visible fields based on type
        const fieldsEl = document.getElementById('fields-' + type);
        if (!fieldsEl) return;

        switch(type) {
            case 'post':
                setRequired(fieldsEl, 'textarea[name="content"]', true);
                break;
            case 'listing':
                setRequired(fieldsEl, 'input[name="listing_title"]', true);
                setRequired(fieldsEl, 'select[name="listing_category_id"]', true);
                setRequired(fieldsEl, 'textarea[name="listing_description"]', true);
                break;
            case 'event':
                setRequired(fieldsEl, 'input[name="event_title"]', true);
                setRequired(fieldsEl, 'input[name="event_start_date"]', true);
                setRequired(fieldsEl, 'input[name="event_start_time"]', true);
                setRequired(fieldsEl, 'textarea[name="event_description"]', true);
                break;
            case 'goal':
                setRequired(fieldsEl, 'input[name="goal_title"]', true);
                break;
            case 'poll':
                setRequired(fieldsEl, 'input[name="poll_question"]', true);
                // First two poll options are required
                const pollOptions = fieldsEl.querySelectorAll('input[name="poll_options[]"]');
                if (pollOptions[0]) pollOptions[0].required = true;
                if (pollOptions[1]) pollOptions[1].required = true;
                break;
        }
    }

    function setRequired(container, selector, required) {
        const el = container.querySelector(selector);
        if (el) el.required = required;
    }

    // Poll option management
    let pollOptionCount = 2;

    function addPollOption() {
        if (pollOptionCount >= 10) {
            showToast('Maximum 10 options allowed');
            return;
        }
        pollOptionCount++;

        const container = document.getElementById('poll-options-container');
        const optionDiv = document.createElement('div');
        optionDiv.className = 'composer-poll-option';
        optionDiv.innerHTML = `
            <input type="text" name="poll_options[]" class="composer-input-inline" placeholder="Option ${pollOptionCount}" style="padding-right: 40px;">
            <button type="button" class="remove-option-btn" onclick="removePollOption(this)">
                <i class="fa-solid fa-times"></i>
            </button>
        `;
        container.appendChild(optionDiv);
    }

    function removePollOption(btn) {
        if (pollOptionCount <= 2) {
            showToast('Minimum 2 options required');
            return;
        }
        btn.closest('.composer-poll-option').remove();
        pollOptionCount--;

        // Re-number placeholders
        const options = document.querySelectorAll('#poll-options-container input[name="poll_options[]"]');
        options.forEach((input, idx) => {
            input.placeholder = `Option ${idx + 1}`;
        });
    }

    // Listing attribute filtering based on category
    function filterListingAttributes() {
        const categorySelect = document.querySelector('select[name="listing_category_id"]');
        const typeInputs = document.querySelectorAll('input[name="listing_type"]');
        if (!categorySelect) return;

        const selectedCat = categorySelect.value;
        const selectedType = Array.from(typeInputs).find(i => i.checked)?.value || 'offer';

        document.querySelectorAll('.composer-attribute-item').forEach(item => {
            const itemCat = item.getAttribute('data-category-id');
            const itemType = item.getAttribute('data-target-type');

            const catMatch = itemCat === 'global' || itemCat == selectedCat || !selectedCat;
            const typeMatch = itemType === 'any' || itemType === selectedType;

            item.style.display = (catMatch && typeMatch) ? 'flex' : 'none';

            // Uncheck hidden items
            if (item.style.display === 'none') {
                const checkbox = item.querySelector('input');
                if (checkbox) checkbox.checked = false;
            }
        });
    }

    // Initialize on load
    document.addEventListener('DOMContentLoaded', function() {
        updateRequiredFields('post');

        // Set up attribute filtering
        const catSelect = document.querySelector('select[name="listing_category_id"]');
        const typeInputs = document.querySelectorAll('input[name="listing_type"]');

        if (catSelect) {
            catSelect.addEventListener('change', filterListingAttributes);
        }
        typeInputs.forEach(input => {
            input.addEventListener('change', filterListingAttributes);
        });

        // Initial filter
        filterListingAttributes();
    });

    // ============================================
    // OFFLINE INDICATOR - Connection Status
    // ============================================
    (function initOfflineIndicator() {
        const banner = document.getElementById('offlineBanner');
        if (!banner) return;

        let wasOffline = false;

        function handleOffline() {
            wasOffline = true;
            banner.classList.add('visible');
            if (navigator.vibrate) navigator.vibrate(100);
        }

        function handleOnline() {
            banner.classList.remove('visible');
            if (wasOffline) {
                showToast('Connection restored');
                wasOffline = false;
            }
        }

        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);

        // Initial check
        if (!navigator.onLine) {
            handleOffline();
        }
    })();

    // ============================================
    // BUTTON PRESS STATES - Native Touch Feel
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        // Add press states to all action buttons
        document.querySelectorAll('.fb-action-btn, .nexus-smart-btn, .fds-btn-primary, .fds-btn-secondary').forEach(btn => {
            btn.addEventListener('pointerdown', function(e) {
                this.classList.add('pressing');
            });

            btn.addEventListener('pointerup', function(e) {
                this.classList.remove('pressing');
            });

            btn.addEventListener('pointerleave', function(e) {
                this.classList.remove('pressing');
            });

            btn.addEventListener('pointercancel', function(e) {
                this.classList.remove('pressing');
            });
        });
    });

    // ============================================
    // INFINITE SCROLL PAGINATION
    // ============================================
    (function initInfiniteScroll() {
        const sentinel = document.getElementById('feedSentinel');
        const endMessage = document.getElementById('feedEndMessage');
        const feedContainer = document.getElementById('feedContainer');

        if (!sentinel || !feedContainer) return;

        let currentPage = 1;
        let isLoading = false;
        let hasMore = true;
        const ITEMS_PER_PAGE = 15;

        // Calculate if we should enable infinite scroll (if we have enough items)
        const initialItems = feedContainer.querySelectorAll('.fb-card').length;
        if (initialItems < ITEMS_PER_PAGE) {
            // Not enough items for pagination, show end message
            endMessage.style.display = 'block';
            return;
        }

        // Show sentinel
        sentinel.style.display = 'flex';

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !isLoading && hasMore) {
                    loadMoreFeed();
                }
            });
        }, { rootMargin: '200px' });

        observer.observe(sentinel);

        async function loadMoreFeed() {
            isLoading = true;
            sentinel.style.display = 'flex';
            currentPage++;

            try {
                const formData = new FormData();
                formData.append('action', 'load_more_feed');
                formData.append('page', currentPage);

                // Pass filter parameters for EdgeRank & location filtering
                if (typeof FeedFilter !== 'undefined') {
                    const filterState = FeedFilter.getActiveFilter();
                    formData.append('algo', filterState.algorithmMode || 'ranked');
                    formData.append('location', filterState.locationMode || 'global');
                    formData.append('radius', filterState.radius || 500);
                } else {
                    // Fallback: read from URL params
                    const urlParams = new URLSearchParams(window.location.search);
                    formData.append('algo', urlParams.get('algo') || 'ranked');
                    formData.append('location', urlParams.get('location') || 'global');
                    formData.append('radius', urlParams.get('radius') || '500');
                }

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.html && data.html.trim()) {
                    // Insert new items before sentinel
                    sentinel.insertAdjacentHTML('beforebegin', data.html);
                } else {
                    hasMore = false;
                }

                if (!hasMore || (data.items && data.items.length < ITEMS_PER_PAGE)) {
                    hasMore = false;
                    sentinel.style.display = 'none';
                    endMessage.style.display = 'block';
                    observer.disconnect();
                }
            } catch (err) {
                console.error('Feed load error:', err);
                hasMore = false;
                sentinel.style.display = 'none';
                endMessage.innerHTML = '<i class="fa-solid fa-exclamation-circle"></i> Failed to load more';
                endMessage.style.display = 'block';
            } finally {
                isLoading = false;
            }
        }
    })();

    // ============================================
    // DYNAMIC THEME COLOR FOR STATUS BAR
    // ============================================
    (function initDynamicThemeColor() {
        const themeColorMeta = document.querySelector('meta[name="theme-color"]');
        if (!themeColorMeta) return;

        function updateThemeColor() {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            themeColorMeta.setAttribute('content', isDark ? '#0f172a' : '#ffffff');
        }

        // Watch for theme changes
        const observer = new MutationObserver(updateThemeColor);
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-theme']
        });

        // Initial check
        updateThemeColor();
    })();
</script>