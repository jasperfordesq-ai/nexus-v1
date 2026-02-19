<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers;

use Nexus\Models\Listing;
use Nexus\Models\Category; // Added for Category::allContext
use Nexus\Core\View;
use Nexus\Core\DatabaseWrapper; // Security Fix
use Nexus\Services\ListingRankingService; // MatchRank algorithm
use Nexus\Services\SmartMatchingEngine; // Smart Matching cache
use Nexus\Services\ListingRiskTagService; // Broker risk tags
use Nexus\Middleware\TenantModuleMiddleware;

class ListingController
{
    /**
     * Check if listings module is enabled
     */
    private function checkFeature()
    {
        TenantModuleMiddleware::require('listings');
    }

    public function index()
    {
        $this->checkFeature();
        // Handle type parameter - support both single value and array (from checkboxes)
        $type = $_GET['type'] ?? null;
        // Ensure type is always an array for consistent handling
        if ($type !== null && !is_array($type)) {
            $type = [$type];
        }

        $category = $_GET['cat'] ?? null;
        $search = $_GET['q'] ?? null;

        // Nearby search parameters
        $lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
        $lon = isset($_GET['lon']) ? (float)$_GET['lon'] : null;
        $radius = isset($_GET['radius']) ? (float)$_GET['radius'] : 25;

        // Get current viewer ID for personalized ranking
        $viewerId = $_SESSION['user_id'] ?? null;

        // API / AJAX Handling
        $isApi = (isset($_GET['format']) && $_GET['format'] === 'json') ||
            (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        // Nearby search takes priority when coordinates are provided
        if ($lat !== null && $lon !== null) {
            $listings = Listing::getNearby($lat, $lon, $radius, 50, $type, $category);
            if ($isApi) {
                header('Content-Type: application/json');
                echo json_encode([
                    'data' => $listings,
                    'search' => [
                        'type' => 'nearby',
                        'lat' => $lat,
                        'lon' => $lon,
                        'radius_km' => $radius,
                        'count' => count($listings)
                    ]
                ]);
                exit;
            }
        } elseif ($search) {
            $listings = Listing::search($search);
            // Apply MatchRank to search results
            if (ListingRankingService::isEnabled()) {
                $listings = ListingRankingService::rankListings($listings, $viewerId, ['search' => $search]);
            }
            if ($isApi) {
                header('Content-Type: application/json');
                echo json_encode(['data' => $listings]);
                exit;
            }
        } else {
            // Use MatchRank algorithm if enabled, otherwise fall back to default
            if (ListingRankingService::isEnabled()) {
                try {
                    $filters = [
                        'limit' => 100,
                        'include_own' => true  // Show all listings including user's own
                    ];
                    if ($type) $filters['type'] = $type;
                    if ($category) $filters['category_id'] = $category;

                    $rankQuery = ListingRankingService::buildRankedQuery($viewerId, $filters);
                    $listings = \Nexus\Core\Database::query($rankQuery['sql'], $rankQuery['params'])->fetchAll(\PDO::FETCH_ASSOC);
                } catch (\Exception $e) {
                    // Fall back to default sorting on error
                    error_log("MatchRank error: " . $e->getMessage());
                    $listings = Listing::all($type, $category, $search);
                }
            } else {
                $listings = Listing::all($type, $category, $search);
            }
        }
        $categories = Category::getByType('listing'); // Filter by context

        // Get categories with listing counts for smart filter buttons
        $tenantId = \Nexus\Core\TenantContext::getId();
        try {
            $categoriesWithCounts = \Nexus\Core\Database::query(
                "SELECT c.id, c.name, c.slug, c.color, COUNT(l.id) as listing_count
                 FROM categories c
                 INNER JOIN listings l ON l.category_id = c.id AND l.tenant_id = ? AND l.status = 'active'
                 WHERE c.tenant_id = ? AND c.type = 'listing'
                 GROUP BY c.id
                 HAVING listing_count > 0
                 ORDER BY listing_count DESC, c.name ASC
                 LIMIT 10",
                [$tenantId, $tenantId]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $categoriesWithCounts = [];
        }

        // Set SEO Data
        \Nexus\Core\SEO::setTitle('Offers & Requests');
        \Nexus\Core\SEO::setDescription('Browse community offers and requests. Exchange time and skills with your neighbors.');

        View::render('listings/index', [
            'listings' => $listings,
            'categories' => $categories,
            'categoriesWithCounts' => $categoriesWithCounts,
            'type' => $type,
            'category' => $category,
            'search' => $search
        ]);
    }

    public function show($id)
    {
        $this->checkFeature();
        $listing = Listing::find($id);

        if (!$listing) {
            header("HTTP/1.0 404 Not Found");
            echo "Listing not found or access denied.";
            exit;
        }

        // Handle AJAX actions for likes/comments
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            $this->handleListingAjax($listing);
            exit;
        }

        // Set SEO Data Dynamic
        \Nexus\Core\SEO::setTitle($listing['title']);
        \Nexus\Core\SEO::setDescription(substr($listing['description'], 0, 160));
        if (!empty($listing['image_url'])) {
            \Nexus\Core\SEO::setImage($listing['image_url']);
        }

        // Load Overrides
        \Nexus\Core\SEO::load('listing', $id);

        // Auto-generate description if not set
        \Nexus\Core\SEO::autoDescription($listing['description']);

        // Add JSON-LD Schema
        $author = \Nexus\Models\User::findById($listing['user_id']);
        \Nexus\Core\SEO::autoSchema('offer', $listing, $author);

        // Breadcrumbs
        \Nexus\Core\SEO::addBreadcrumbs([
            ['name' => 'Home', 'url' => '/'],
            ['name' => 'Listings', 'url' => '/listings'],
            ['name' => $listing['title'], 'url' => '/listings/' . $id]
        ]);

        // Get Attributes
        $attributes = \Nexus\Models\Attribute::getForListing($id);

        // Get Risk Tag (if any) for broker controls display
        $riskTag = null;
        try {
            $riskTag = ListingRiskTagService::getTagForListing($id);
        } catch (\Exception $e) {
            // Risk tag service may not be available - continue without it
        }

        View::render('listings/show', [
            'listing' => $listing,
            'attributes' => $attributes,
            'riskTag' => $riskTag
        ]);
    }

    public function create()
    {
        $this->checkFeature();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }
        $categories = \Nexus\Models\Category::getByType('listing');

        // Fetch ALL active attributes so we can filter them client-side
        // Note: Attribute::all() now includes target_type
        $attributes = \Nexus\Models\Attribute::all();

        // Get current user for preview
        $user = \Nexus\Models\User::findById($_SESSION['user_id']);

        // Check federation eligibility for this user
        $federationEnabled = false;
        $userFederationOptedIn = false;
        if (\Nexus\Services\FederationFeatureService::isTenantFederationEnabled()) {
            $federationEnabled = true;
            $userFedSettings = \Nexus\Core\Database::query(
                "SELECT federation_optin FROM federation_user_settings WHERE user_id = ?",
                [$_SESSION['user_id']]
            )->fetch();
            $userFederationOptedIn = $userFedSettings && $userFedSettings['federation_optin'];
        }

        \Nexus\Core\SEO::setTitle('Post an Ad');

        View::render('listings/create', [
            'categories' => $categories,
            'attributes' => $attributes,
            'user' => $user,
            'federationEnabled' => $federationEnabled,
            'userFederationOptedIn' => $userFederationOptedIn
        ]);
    }

    public function store()
    {
        $this->checkFeature();
        \Nexus\Core\Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $userCheck = \Nexus\Models\User::findById($userId);

        // Robust retry logic - handles temporary DB issues without destroying session
        if (!$userCheck) {
            $maxRetries = 3;
            for ($i = 0; $i < $maxRetries && !$userCheck; $i++) {
                usleep(200000); // 200ms delay between retries
                $userCheck = \Nexus\Models\User::findById($userId);
            }
        }

        // If still not found after retries, log and redirect but DON'T destroy session
        // This prevents random logouts on transient DB issues
        if (!$userCheck) {
            error_log("ListingController::store - User ID {$userId} not found after retries. Possible DB issue or deleted user.");
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login?error=session_check_failed');
            exit;
        }

        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $type = $_POST['type'] ?? 'offer';
        $categoryId = $_POST['category_id'] ?? null;

        // Get location from user's profile (not from form)
        $user = \Nexus\Models\User::findById($userId);
        $userCoords = \Nexus\Models\User::getCoordinates($userId);
        $location = $user['location'] ?? null;
        $latitude = $userCoords['latitude'] ?? null;
        $longitude = $userCoords['longitude'] ?? null;

        // Image Upload
        $imageUrl = null;
        if (!empty($_FILES['image']['name'])) {
            try {
                $imageUrl = \Nexus\Core\ImageUploader::upload($_FILES['image']);
            } catch (\Exception $e) {
                die("Image Upload Failed: " . $e->getMessage());
            }
        }

        if ($title && $description) {
            // Handle federated visibility (only if user has opted into federation)
            $federatedVisibility = 'none';
            if (!empty($_POST['federated_visibility']) && in_array($_POST['federated_visibility'], ['listed', 'bookable'])) {
                // Check if user has opted into federation
                if (\Nexus\Services\FederationFeatureService::isTenantFederationEnabled()) {
                    $userFedSettings = \Nexus\Core\Database::query(
                        "SELECT federation_optin FROM federation_user_settings WHERE user_id = ?",
                        [$userId]
                    )->fetch();

                    if ($userFedSettings && $userFedSettings['federation_optin']) {
                        $federatedVisibility = $_POST['federated_visibility'];
                    }
                }
            }

            // 1. Create Listing (using user's profile location)
            $listingId = Listing::create($userId, $title, $description, $type, $categoryId, $imageUrl, $location, $latitude, $longitude, $federatedVisibility);

            // 2. SDGs (If posted)
            if (!empty($_POST['sdg_goals'])) {
                $sdgs = array_keys($_POST['sdg_goals']);
                $json = json_encode($_POST['sdg_goals']);
                \Nexus\Core\Database::query("UPDATE listings SET sdg_goals = ? WHERE id = ?", [$json, $listingId]);
            }

            // 3. Attributes
            if (!empty($_POST['attributes']) && is_array($_POST['attributes'])) {
                foreach ($_POST['attributes'] as $attrId => $val) {
                    if ($val) {
                        \Nexus\Core\Database::query(
                            "INSERT INTO listing_attributes (listing_id, attribute_id, value) VALUES (?, ?, ?)",
                            [$listingId, intval($attrId), '1']
                        );
                    }
                }
            }

            // 4. Gamification Check
            try {
                \Nexus\Services\GamificationService::checkListingBadges($userId);
            } catch (\Exception $e) {
                // Ignore gamification errors
            }

            // 5. Invalidate Smart Matching Cache
            // When a new listing is created, invalidate cached matches for relevant users
            try {
                SmartMatchingEngine::invalidateCacheForCategory($categoryId);
            } catch (\Exception $e) {
                // Cache invalidation is non-critical
            }

            // Log Activity for Pulse Feed
            $action = ($type === 'offer') ? 'posted an Offer 🎁' : 'requested Help 🙋';
            \Nexus\Models\ActivityLog::log($userId, $action, $title, true, '/listings/' . $listingId);

            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/listings');
            exit;
        }

        echo "Error: Missing required fields.";
    }

    public function edit($id)
    {
        $this->checkFeature();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $listing = Listing::find($id);
        $currentUser = \Nexus\Models\User::findById($_SESSION['user_id']);
        $isAdmin = ($currentUser['role'] === 'admin' || $currentUser['is_super_admin'] == 1);

        if (!$listing || ($listing['user_id'] != $_SESSION['user_id'] && !$isAdmin)) {
            header("HTTP/1.0 403 Forbidden");
            echo "Access Denied";
            exit;
        }

        $categories = \Nexus\Models\Category::getByType('listing');
        $attributes = \Nexus\Models\Attribute::all();
        $listingAttributes = \Nexus\Models\Attribute::getForListing($id);

        $selectedAttrs = [];
        foreach ($listingAttributes as $la) {
            $selectedAttrs[$la['id']] = $la['value'];
        }

        // Check federation eligibility for this user
        $federationEnabled = false;
        $userFederationOptedIn = false;
        if (\Nexus\Services\FederationFeatureService::isTenantFederationEnabled()) {
            $federationEnabled = true;
            $userFedSettings = \Nexus\Core\Database::query(
                "SELECT federation_optin FROM federation_user_settings WHERE user_id = ?",
                [$_SESSION['user_id']]
            )->fetch();
            $userFederationOptedIn = $userFedSettings && $userFedSettings['federation_optin'];
        }

        View::render('listings/edit', [
            'listing' => $listing,
            'categories' => $categories,
            'user' => \Nexus\Models\User::findById($_SESSION['user_id']),
            'attributes' => $attributes,
            'selectedAttributes' => $selectedAttrs,
            'federationEnabled' => $federationEnabled,
            'userFederationOptedIn' => $userFederationOptedIn
        ]);
    }

    public function update()
    {
        $this->checkFeature();
        \Nexus\Core\Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $id = $_POST['id'] ?? null;
        if (!$id) die("Missing ID");

        $listing = Listing::find($id);
        $currentUser = \Nexus\Models\User::findById($_SESSION['user_id']);
        $isAdmin = ($currentUser['role'] === 'admin' || $currentUser['is_super_admin'] == 1);

        if (!$listing || ($listing['user_id'] != $_SESSION['user_id'] && !$isAdmin)) {
            die("Unauthorized");
        }

        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $type = $_POST['type'] ?? 'offer';
        $categoryId = $_POST['category_id'] ?? null;

        // Get location from user's profile (not from form)
        $userId = $_SESSION['user_id'];
        $user = \Nexus\Models\User::findById($userId);
        $userCoords = \Nexus\Models\User::getCoordinates($userId);
        $location = $user['location'] ?? null;
        $latitude = $userCoords['latitude'] ?? null;
        $longitude = $userCoords['longitude'] ?? null;

        // Image Upload
        $imageUrl = null;
        if (!empty($_FILES['image']['name'])) {
            try {
                $imageUrl = \Nexus\Core\ImageUploader::upload($_FILES['image']);
            } catch (\Exception $e) {
                die("Image Upload Failed: " . $e->getMessage());
            }
        }

        if ($title && $description) {
            // Handle federated visibility (only if user has opted into federation)
            $federatedVisibility = null; // null means don't change
            if (isset($_POST['federated_visibility'])) {
                $requestedVisibility = $_POST['federated_visibility'];
                if ($requestedVisibility === 'none') {
                    $federatedVisibility = 'none';
                } elseif (in_array($requestedVisibility, ['listed', 'bookable'])) {
                    // Check if user has opted into federation
                    if (\Nexus\Services\FederationFeatureService::isTenantFederationEnabled()) {
                        $userFedSettings = \Nexus\Core\Database::query(
                            "SELECT federation_optin FROM federation_user_settings WHERE user_id = ?",
                            [$userId]
                        )->fetch();

                        if ($userFedSettings && $userFedSettings['federation_optin']) {
                            $federatedVisibility = $requestedVisibility;
                        }
                    }
                }
            }

            Listing::update($id, $title, $description, $type, $categoryId, $imageUrl, $location, $latitude, $longitude, $federatedVisibility);

            // 2. SDGs
            if (!empty($_POST['sdg_goals'])) {
                $json = json_encode($_POST['sdg_goals']);
                \Nexus\Core\Database::query("UPDATE listings SET sdg_goals = ? WHERE id = ?", [$json, $id]);
            }

            // 3. Attributes - Only update if attributes were submitted in the form
            // This prevents data loss when editing from forms that don't include attributes section
            if (isset($_POST['attributes'])) {
                // Form explicitly submitted attributes section - safe to wipe and replace
                \Nexus\Core\Database::query("DELETE FROM listing_attributes WHERE listing_id = ?", [$id]);
                if (is_array($_POST['attributes'])) {
                    foreach ($_POST['attributes'] as $attrId => $val) {
                        if ($val) {
                            \Nexus\Core\Database::query(
                                "INSERT INTO listing_attributes (listing_id, attribute_id, value) VALUES (?, ?, ?)",
                                [$id, intval($attrId), '1']
                            );
                        }
                    }
                }
            }
            // If attributes key not in POST at all, preserve existing attributes

            // 4. Save SEO metadata if provided
            if (isset($_POST['seo']) && is_array($_POST['seo'])) {
                \Nexus\Models\SeoMetadata::save('listing', $id, [
                    'meta_title' => trim($_POST['seo']['meta_title'] ?? ''),
                    'meta_description' => trim($_POST['seo']['meta_description'] ?? ''),
                    'meta_keywords' => trim($_POST['seo']['meta_keywords'] ?? ''),
                    'canonical_url' => trim($_POST['seo']['canonical_url'] ?? ''),
                    'og_image_url' => trim($_POST['seo']['og_image_url'] ?? ''),
                    'noindex' => isset($_POST['seo']['noindex'])
                ]);
            }

            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/listings/' . $id);
            exit;
        }

        echo "Error: Missing required fields.";
    }

    public function delete()
    {
        $this->checkFeature();
        // 1. Method Check
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit;
        }

        // 2. Identify Input Type (JSON vs Form)
        $isJson = (isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);

        // Check if this is an AJAX request (expects JSON response)
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $wantsJson = $isJson || $isAjax || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        $id = null;
        if ($isJson) {
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? null;
            \Nexus\Core\Csrf::verifyOrDieJson();
        } else {
            // For form/AJAX submissions, use JSON response if client expects it
            if ($wantsJson) {
                \Nexus\Core\Csrf::verifyOrDieJson();
            } else {
                \Nexus\Core\Csrf::verifyOrDie();
            }
            $id = $_POST['id'] ?? null;
        }

        // 3. Auth Check
        if (!isset($_SESSION['user_id'])) {
            if ($wantsJson) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Unauthenticated']);
                exit;
            }
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        // 4. Listing Check
        if (!$id) {
            if ($wantsJson) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'No ID']);
                exit;
            }
            die("No ID provided");
        }

        $listing = Listing::find($id);
        if (!$listing || $listing['user_id'] != $_SESSION['user_id']) {
            if ($wantsJson) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Listing not found or unauthorized']);
                exit;
            }
            echo "Access Denied";
            exit;
        }

        // 5. Execute Delete
        Listing::delete($id);

        // 6. Response
        if ($wantsJson) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } else {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/listings');
        }
        exit;
    }

    /**
     * Handle AJAX actions for listing likes/comments
     */
    private function handleListingAjax($listing)
    {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Clear any buffered output before JSON response
        if (ob_get_level()) ob_clean();
        header('Content-Type: application/json');

        // CSRF protection for AJAX state-changing requests
        \Nexus\Core\Csrf::verifyOrDieJson();

        $userId = $_SESSION['user_id'] ?? 0;
        $tenantId = \Nexus\Core\TenantContext::getId();

        if (!$userId) {
            echo json_encode(['error' => 'Login required', 'redirect' => '/login']);
            return;
        }

        $action = $_POST['action'] ?? '';
        $targetType = 'listing';
        $targetId = (int)$listing['id'];

        try {
            // Get PDO instance directly - DatabaseWrapper adds tenant constraints that break JOINs
            $pdo = \Nexus\Core\Database::getInstance();

            // TOGGLE LIKE
            if ($action === 'toggle_like') {
                $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND target_type = ? AND target_id = ? AND tenant_id = ?");
                $stmt->execute([$userId, $targetType, $targetId, $tenantId]);
                $existing = $stmt->fetch();

                if ($existing) {
                    $stmt = $pdo->prepare("DELETE FROM likes WHERE id = ? AND tenant_id = ?");
                    $stmt->execute([$existing['id'], $tenantId]);

                    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM likes WHERE target_type = ? AND target_id = ? AND tenant_id = ?");
                    $stmt->execute([$targetType, $targetId, $tenantId]);
                    $countResult = $stmt->fetch();
                    echo json_encode(['status' => 'unliked', 'likes_count' => (int)($countResult['cnt'] ?? 0)]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO likes (user_id, target_type, target_id, tenant_id) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$userId, $targetType, $targetId, $tenantId]);

                    // Send notification to listing owner
                    if (class_exists('\Nexus\Services\SocialNotificationService')) {
                        $contentOwnerId = $listing['user_id'] ?? null;
                        if ($contentOwnerId && $contentOwnerId != $userId) {
                            $contentPreview = $listing['title'] ?? '';
                            \Nexus\Services\SocialNotificationService::notifyLike(
                                $contentOwnerId,
                                $userId,
                                $targetType,
                                $targetId,
                                $contentPreview
                            );
                        }
                    }

                    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM likes WHERE target_type = ? AND target_id = ?");
                    $stmt->execute([$targetType, $targetId]);
                    $countResult = $stmt->fetch();
                    echo json_encode(['status' => 'liked', 'likes_count' => (int)($countResult['cnt'] ?? 0)]);
                }
            }

            // SUBMIT COMMENT
            elseif ($action === 'submit_comment') {
                $content = trim($_POST['content'] ?? '');
                if (empty($content)) {
                    echo json_encode(['error' => 'Comment cannot be empty']);
                    return;
                }

                $stmt = $pdo->prepare("INSERT INTO comments (user_id, tenant_id, target_type, target_id, content, created_at) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $tenantId, $targetType, $targetId, $content, date('Y-m-d H:i:s')]);

                // Send notification to listing owner
                if (class_exists('\Nexus\Services\SocialNotificationService')) {
                    $contentOwnerId = $listing['user_id'] ?? null;
                    if ($contentOwnerId && $contentOwnerId != $userId) {
                        \Nexus\Services\SocialNotificationService::notifyComment(
                            $contentOwnerId,
                            $userId,
                            $targetType,
                            $targetId,
                            $content
                        );
                    }
                }

                echo json_encode(['status' => 'success', 'comment' => [
                    'author_name' => $_SESSION['user_name'] ?? 'Me',
                    'author_avatar' => $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.png',
                    'content' => $content
                ]]);
            }

            // FETCH COMMENTS (with nested replies and reactions)
            elseif ($action === 'fetch_comments') {
                $comments = \Nexus\Services\CommentService::fetchComments($targetType, $targetId, $userId);
                echo json_encode([
                    'status' => 'success',
                    'comments' => $comments,
                    'available_reactions' => \Nexus\Services\CommentService::getAvailableReactions()
                ]);
            }

            // DELETE COMMENT
            elseif ($action === 'delete_comment') {
                $commentId = (int)($_POST['comment_id'] ?? 0);
                $isSuperAdmin = !empty($_SESSION['is_super_admin']);
                $result = \Nexus\Services\CommentService::deleteComment($commentId, $userId, $isSuperAdmin);
                echo json_encode($result);
            }

            // EDIT COMMENT
            elseif ($action === 'edit_comment') {
                $commentId = (int)($_POST['comment_id'] ?? 0);
                $newContent = $_POST['content'] ?? '';
                $result = \Nexus\Services\CommentService::editComment($commentId, $userId, $newContent);
                echo json_encode($result);
            }

            // REPLY TO COMMENT
            elseif ($action === 'reply_comment') {
                $parentId = (int)($_POST['parent_id'] ?? 0);
                $content = trim($_POST['content'] ?? '');
                $result = \Nexus\Services\CommentService::addComment($userId, $tenantId, $targetType, $targetId, $content, $parentId);

                // Notify parent comment author
                if (isset($result['status']) && $result['status'] === 'success') {
                    $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
                    $stmt->execute([$parentId]);
                    $parent = $stmt->fetch();
                    if ($parent && $parent['user_id'] != $userId) {
                        if (class_exists('\Nexus\Services\SocialNotificationService')) {
                            \Nexus\Services\SocialNotificationService::notifyComment(
                                $parent['user_id'],
                                $userId,
                                'reply',
                                $parentId,
                                $content
                            );
                        }
                    }
                }
                echo json_encode($result);
            }

            // TOGGLE REACTION ON COMMENT
            elseif ($action === 'toggle_reaction') {
                $commentId = (int)($_POST['comment_id'] ?? 0);
                $emoji = $_POST['emoji'] ?? '';
                $result = \Nexus\Services\CommentService::toggleReaction($userId, $tenantId, $commentId, $emoji);
                echo json_encode($result);
            }

            // SEARCH USERS FOR @MENTION
            elseif ($action === 'search_users') {
                $query = $_POST['query'] ?? '';
                $users = \Nexus\Services\CommentService::searchUsersForMention($query, $tenantId);
                echo json_encode(['status' => 'success', 'users' => $users]);
            }

            // SHARE LISTING TO FEED
            elseif ($action === 'share_listing') {
                $stmt = $pdo->prepare("INSERT INTO feed_posts (user_id, tenant_id, content, parent_id, parent_type, visibility, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $shareContent = "Check out this listing: " . ($listing['title'] ?? 'Listing');
                $stmt->execute([
                    $userId,
                    $tenantId,
                    $shareContent,
                    $targetId,
                    'listing',
                    'public',
                    date('Y-m-d H:i:s')
                ]);

                // Notify listing owner
                if (class_exists('\Nexus\Services\SocialNotificationService')) {
                    $contentOwnerId = $listing['user_id'] ?? null;
                    if ($contentOwnerId && $contentOwnerId != $userId) {
                        \Nexus\Services\SocialNotificationService::notifyLike(
                            $contentOwnerId,
                            $userId,
                            'listing',
                            $targetId,
                            'shared your listing'
                        );
                    }
                }

                echo json_encode(['status' => 'success', 'message' => 'Listing shared to feed']);
            } else {
                echo json_encode(['error' => 'Unknown action']);
            }
        } catch (\Exception $e) {
            error_log("ListingController AJAX error: " . $e->getMessage());
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
