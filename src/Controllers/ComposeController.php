<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers;

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

/**
 * ComposeController
 *
 * Full-page compose interface for creating content
 * Optimized for mobile with support for multiple post types:
 * - Post (status update)
 * - Listing (offer/request)
 * - Event
 * - Goal
 * - Poll
 */
class ComposeController
{
    public function index()
    {
        // Require authentication
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            $basePath = TenantContext::getBasePath();
            // SECURITY: Validate redirect URL to prevent open redirect attacks
            $redirectUrl = $_SERVER['REQUEST_URI'] ?? '';
            // Only allow relative paths starting with / and no protocol/host manipulation
            if (!preg_match('#^/[^/]#', $redirectUrl) || preg_match('#[:\s]#', $redirectUrl)) {
                $redirectUrl = $basePath . '/compose';
            }
            header("Location: {$basePath}/login?redirect=" . urlencode($redirectUrl));
            exit;
        }

        // Get user data
        $userId = $_SESSION['user_id'];
        $userName = $_SESSION['user_name'] ?? 'User';
        $userAvatar = $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.png';

        // Get full user object
        $user = null;
        if (class_exists('\Nexus\Models\User')) {
            try {
                $user = \Nexus\Models\User::findById($userId);
            } catch (\Exception $e) {
                $user = [
                    'id' => $userId,
                    'name' => $userName,
                    'avatar_url' => $userAvatar
                ];
            }
        }

        // Get tenant features to show/hide certain post types
        $hasEvents = TenantContext::hasFeature('events');
        $hasGoals = TenantContext::hasFeature('goals');
        $hasPolls = TenantContext::hasFeature('polls');

        // Get groups for posting to
        $myGroups = [];
        if (class_exists('\Nexus\Models\Group')) {
            try {
                $myGroups = \Nexus\Models\Group::getUserGroups($userId);
            } catch (\Exception $e) {
                $myGroups = [];
            }
        }

        // Get listing categories
        $categories = [];
        if (class_exists('\Nexus\Models\Category')) {
            try {
                $categories = \Nexus\Models\Category::getByType('listing');
            } catch (\Exception $e) {
                $categories = [];
            }
        }

        // Page title
        $pageTitle = 'Create - ' . (TenantContext::get()['name'] ?? 'Nexus');

        // Default post type from query string
        $defaultType = $_GET['type'] ?? 'post';

        // Render the compose view
        \Nexus\Core\View::render('compose/index', [
            'user' => $user,
            'myGroups' => $myGroups,
            'categories' => $categories,
            'defaultType' => $defaultType,
            'hasEvents' => $hasEvents,
            'hasGoals' => $hasGoals,
            'hasPolls' => $hasPolls,
            'pageTitle' => $pageTitle
        ]);
    }

    public function store()
    {
        // Require authentication
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Verify CSRF token
        Csrf::verifyOrDie();

        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
            exit;
        }

        $postType = $_POST['post_type'] ?? 'post';
        $basePath = TenantContext::getBasePath();

        // Route to appropriate handler based on post type
        switch ($postType) {
            case 'listing':
                $this->createListing();
                break;

            case 'event':
                $this->createEvent();
                break;

            case 'goal':
                $this->createGoal();
                break;

            case 'poll':
                $this->createPoll();
                break;

            case 'post':
            default:
                // Handle post creation inline (similar to home.php feed post)
                $this->createPost();
                break;
        }
    }

    private function createListing()
    {
        $userId = $_SESSION['user_id'];
        $basePath = TenantContext::getBasePath();

        // Get form data
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $type = $_POST['listing_type'] ?? 'offer';
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $location = trim($_POST['location'] ?? '');
        $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;

        // Handle image upload - same as ListingController::store()
        $imageUrl = null;
        if (!empty($_FILES['image']['name'])) {
            try {
                $imageUrl = \Nexus\Core\ImageUploader::upload($_FILES['image']);
            } catch (\Exception $e) {
                error_log('Compose listing image upload error: ' . $e->getMessage());
                // Don't fail the whole listing, just skip the image
            }
        }

        // Get location from user's profile if not provided (same as ListingController)
        if (empty($location)) {
            $user = \Nexus\Models\User::findById($userId);
            $userCoords = \Nexus\Models\User::getCoordinates($userId);
            $location = $user['location'] ?? null;
            $latitude = $userCoords['latitude'] ?? null;
            $longitude = $userCoords['longitude'] ?? null;
        }

        // Validation
        if (empty($title)) {
            $_SESSION['compose_error'] = 'Please provide a title for your listing.';
            header("Location: {$basePath}/compose?type=listing");
            exit;
        }

        if (empty($description)) {
            $_SESSION['compose_error'] = 'Please provide a description for your listing.';
            header("Location: {$basePath}/compose?type=listing");
            exit;
        }

        // Handle federated visibility (only if user has opted into federation)
        $federatedVisibility = 'none';
        if (!empty($_POST['federated_visibility']) && in_array($_POST['federated_visibility'], ['listed', 'bookable'])) {
            // Check if user has opted into federation
            if (class_exists('\Nexus\Services\FederationFeatureService') &&
                \Nexus\Services\FederationFeatureService::isTenantFederationEnabled()) {
                $userFedSettings = \Nexus\Core\Database::query(
                    "SELECT federation_optin FROM federation_user_settings WHERE user_id = ?",
                    [$userId]
                )->fetch();

                if ($userFedSettings && $userFedSettings['federation_optin']) {
                    $federatedVisibility = $_POST['federated_visibility'];
                }
            }
        }

        // Create the listing
        try {
            if (class_exists('\Nexus\Models\Listing')) {
                $listingId = \Nexus\Models\Listing::create(
                    $userId,
                    $title,
                    $description,
                    $type,
                    $categoryId,
                    $imageUrl,
                    $location,
                    $latitude,
                    $longitude,
                    $federatedVisibility
                );

                // Save SDG Goals (same as ListingController::store)
                if (!empty($_POST['sdg_goals']) && is_array($_POST['sdg_goals'])) {
                    $json = json_encode($_POST['sdg_goals']);
                    \Nexus\Core\Database::query("UPDATE listings SET sdg_goals = ? WHERE id = ?", [$json, $listingId]);
                }

                // Save Attributes (same as ListingController::store)
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

                // Gamification Check (same as ListingController::store)
                try {
                    \Nexus\Services\GamificationService::checkListingBadges($userId);
                } catch (\Exception $e) {
                    // Ignore gamification errors
                }

                // Log Activity for Pulse Feed
                $action = ($type === 'offer') ? 'posted an Offer 🎁' : 'requested Help 🙋';
                \Nexus\Models\ActivityLog::log($userId, $action, $title, true, '/listings/' . $listingId);

                $_SESSION['compose_success'] = 'Listing created successfully!';

                // Redirect to the new listing
                header("Location: {$basePath}/listings/{$listingId}");
                exit;
            }
        } catch (\Exception $e) {
            // Log the actual error for debugging
            error_log('Compose listing error: ' . $e->getMessage());
            $_SESSION['compose_error'] = 'Failed to create listing: ' . $e->getMessage();
            header("Location: {$basePath}/compose?type=listing");
            exit;
        }

        // Fallback redirect
        header("Location: {$basePath}/listings");
        exit;
    }

    private function createPost()
    {
        $userId = $_SESSION['user_id'];
        $basePath = TenantContext::getBasePath();
        $tenantId = TenantContext::getId();
        $content = trim($_POST['content'] ?? '');
        $emoji = $_POST['emoji'] ?? null;
        $groupId = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;

        if (empty($content)) {
            $_SESSION['compose_error'] = 'Please write something to post.';
            header("Location: {$basePath}/compose");
            exit;
        }

        // If posting to a group, verify membership (same as GroupController::storePost)
        if ($groupId) {
            $group = \Nexus\Models\Group::findById($groupId);
            if (!$group) {
                $_SESSION['compose_error'] = 'Group not found.';
                header("Location: {$basePath}/compose?type=group");
                exit;
            }

            if (!\Nexus\Models\Group::isMember($groupId, $userId)) {
                $_SESSION['compose_error'] = 'You must be a member to post in this group.';
                header("Location: {$basePath}/compose?type=group");
                exit;
            }
        }

        // Create the feed post
        try {
            // Handle image upload for posts (same as GroupController::storePost)
            $imageUrl = null;
            if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/posts/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // SECURITY: Validate actual MIME type using finfo, not user-supplied data
                $allowedTypes = [
                    'image/jpeg' => ['jpg', 'jpeg'],
                    'image/png' => ['png'],
                    'image/gif' => ['gif'],
                    'image/webp' => ['webp']
                ];

                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $actualMime = $finfo->file($_FILES['image']['tmp_name']);

                if (isset($allowedTypes[$actualMime])) {
                    // Validate extension matches MIME type
                    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedTypes[$actualMime])) {
                        $ext = $allowedTypes[$actualMime][0];
                    }

                    // Also verify it's a valid image using getimagesize
                    $imageInfo = @getimagesize($_FILES['image']['tmp_name']);
                    if ($imageInfo !== false) {
                        // SECURITY: Use cryptographically secure random filename
                        $filename = 'post_' . bin2hex(random_bytes(16)) . '.' . $ext;
                        $targetPath = $uploadDir . $filename;
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                            $imageUrl = '/uploads/posts/' . $filename;
                        }
                    }
                }
            }

            // Insert directly into feed_posts (same as GroupController::storePost)
            $dbClass = class_exists('\Nexus\Core\Database') ? '\Nexus\Core\Database' : '\Nexus\Core\DatabaseWrapper';
            $dbClass::query("
                INSERT INTO feed_posts (tenant_id, user_id, group_id, content, image_url, visibility, likes_count, created_at)
                VALUES (?, ?, ?, ?, ?, 'public', 0, NOW())
            ", [$tenantId, $userId, $groupId, $content, $imageUrl]);
            $postId = $dbClass::lastInsertId();

            $_SESSION['compose_success'] = 'Post created successfully!';

            // Redirect to group page if posting to group, otherwise home
            if ($groupId) {
                header("Location: {$basePath}/groups/{$groupId}?posted=1");
            } else {
                header("Location: {$basePath}/");
            }
            exit;

        } catch (\Exception $e) {
            error_log('Compose post error: ' . $e->getMessage());
            $_SESSION['compose_error'] = 'Failed to create post. Please try again.';
            header("Location: {$basePath}/compose");
            exit;
        }

        // Fallback redirect
        header("Location: {$basePath}/");
        exit;
    }

    private function createEvent()
    {
        $userId = $_SESSION['user_id'];
        $basePath = TenantContext::getBasePath();
        $tenantId = TenantContext::getId();

        // Get form data (same as EventController::store)
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $startDate = $_POST['start_date'] ?? '';
        $startTime = $_POST['start_time'] ?? '09:00';
        $endDate = $_POST['end_date'] ?? '';
        $endTime = $_POST['end_time'] ?? '';
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $groupId = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;
        $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;

        // Combine date and time (same as EventController::store)
        $start = $startDate ? $startDate . ' ' . $startTime : null;
        $end = $endDate ? ($endDate . ' ' . ($endTime ?: '00:00')) : null;

        // Validation
        if (empty($title)) {
            $_SESSION['compose_error'] = 'Please provide a title for your event.';
            header("Location: {$basePath}/compose?type=event");
            exit;
        }

        if (empty($start)) {
            $_SESSION['compose_error'] = 'Please provide a start date for your event.';
            header("Location: {$basePath}/compose?type=event");
            exit;
        }

        // Verify group membership if group selected (same as EventController::store)
        if ($groupId) {
            $isMember = \Nexus\Models\Group::isMember($groupId, $userId);
            if (!$isMember) {
                $_SESSION['compose_error'] = 'Unauthorized to post in this group.';
                header("Location: {$basePath}/compose?type=event");
                exit;
            }
        }

        // Handle federated visibility (only if user has opted into federation)
        $federatedVisibility = 'none';
        if (!empty($_POST['federated_visibility']) && in_array($_POST['federated_visibility'], ['listed', 'joinable'])) {
            if (class_exists('\Nexus\Services\FederationFeatureService') &&
                \Nexus\Services\FederationFeatureService::isTenantFederationEnabled()) {
                $userFedSettings = \Nexus\Core\Database::query(
                    "SELECT federation_optin FROM federation_user_settings WHERE user_id = ?",
                    [$userId]
                )->fetch();

                if ($userFedSettings && $userFedSettings['federation_optin']) {
                    $federatedVisibility = $_POST['federated_visibility'];
                }
            }
        }

        // Create the event
        try {
            if (class_exists('\Nexus\Models\Event')) {
                $eventId = \Nexus\Models\Event::create(
                    $tenantId,
                    $userId,
                    $title,
                    $description,
                    !empty($location) ? $location : null,
                    $start,
                    $end,
                    $groupId,
                    $categoryId,
                    $latitude,
                    $longitude,
                    $federatedVisibility
                );

                // Handle SDG Tags (same as EventController::store)
                if (!empty($_POST['sdg_goals']) && is_array($_POST['sdg_goals'])) {
                    $goals = array_map('intval', $_POST['sdg_goals']);
                    $json = json_encode($goals);
                    \Nexus\Core\Database::query("UPDATE events SET sdg_goals = ? WHERE id = ?", [$json, $eventId]);
                }

                // Log Activity (same as EventController::store)
                \Nexus\Models\ActivityLog::log($userId, 'hosted an Event 🗓️', $title, true, '/events/' . $eventId);

                // Gamification: Check event host badges (same as EventController::store)
                try {
                    \Nexus\Services\GamificationService::checkEventBadges($userId, 'host');
                } catch (\Throwable $e) {
                    error_log("Gamification event host error: " . $e->getMessage());
                }

                $_SESSION['compose_success'] = 'Event created successfully!';
                header("Location: {$basePath}/events/{$eventId}");
                exit;
            }
        } catch (\Exception $e) {
            error_log('Compose event error: ' . $e->getMessage());
            $_SESSION['compose_error'] = 'Failed to create event: ' . $e->getMessage();
            header("Location: {$basePath}/compose?type=event");
            exit;
        }

        header("Location: {$basePath}/events");
        exit;
    }

    private function createGoal()
    {
        $userId = $_SESSION['user_id'];
        $basePath = TenantContext::getBasePath();
        $tenantId = TenantContext::getId();

        // Get form data
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $deadline = $_POST['deadline'] ?? null;
        $isPublic = isset($_POST['is_public']) ? 1 : 0;

        // Validation
        if (empty($title)) {
            $_SESSION['compose_error'] = 'Please provide a title for your goal.';
            header("Location: {$basePath}/compose?type=goal");
            exit;
        }

        // Create the goal
        try {
            if (class_exists('\Nexus\Models\Goal')) {
                $goalId = \Nexus\Models\Goal::create(
                    $tenantId,
                    $userId,
                    $title,
                    $description,
                    $deadline,
                    $isPublic
                );

                // Log Activity if Public (same as GoalController::store)
                if ($isPublic) {
                    \Nexus\Models\ActivityLog::log(
                        $tenantId,
                        $userId,
                        'created_goal',
                        $goalId,
                        'listing',
                        "set a new goal: $title"
                    );
                }

                $_SESSION['compose_success'] = 'Goal created successfully!';
                header("Location: {$basePath}/goals/{$goalId}");
                exit;
            }
        } catch (\Exception $e) {
            error_log('Compose goal error: ' . $e->getMessage());
            $_SESSION['compose_error'] = 'Failed to create goal: ' . $e->getMessage();
            header("Location: {$basePath}/compose?type=goal");
            exit;
        }

        header("Location: {$basePath}/goals");
        exit;
    }

    private function createPoll()
    {
        $userId = $_SESSION['user_id'];
        $basePath = TenantContext::getBasePath();
        $tenantId = TenantContext::getId();

        // Get form data
        $question = trim($_POST['question'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $endDate = $_POST['end_date'] ?? null;
        $options = $_POST['options'] ?? [];

        // Filter empty options
        $options = array_filter($options, function($opt) {
            return trim($opt) !== '';
        });

        // Validation
        if (empty($question)) {
            $_SESSION['compose_error'] = 'Please provide a question for your poll.';
            header("Location: {$basePath}/compose?type=poll");
            exit;
        }

        if (count($options) < 2) {
            $_SESSION['compose_error'] = 'Please provide at least 2 options for your poll.';
            header("Location: {$basePath}/compose?type=poll");
            exit;
        }

        // Create the poll
        try {
            if (class_exists('\Nexus\Models\Poll')) {
                // Format end date same as PollController::store
                $endDateFormatted = $endDate ? $endDate . ' 23:59:59' : null;

                $pollId = \Nexus\Models\Poll::create(
                    $tenantId,
                    $userId,
                    $question,
                    $description,
                    $endDateFormatted
                );

                // Add options (same as PollController::store)
                foreach ($options as $option) {
                    if (!empty(trim($option))) {
                        \Nexus\Models\Poll::addOption($pollId, trim($option));
                    }
                }

                // Log to Feed (same as PollController::store)
                \Nexus\Models\ActivityLog::log($userId, 'created a Poll 🗳️', $question, true, '/polls/' . $pollId);

                $_SESSION['compose_success'] = 'Poll created successfully!';
                header("Location: {$basePath}/polls/{$pollId}");
                exit;
            }
        } catch (\Exception $e) {
            error_log('Compose poll error: ' . $e->getMessage());
            $_SESSION['compose_error'] = 'Failed to create poll: ' . $e->getMessage();
            header("Location: {$basePath}/compose?type=poll");
            exit;
        }

        header("Location: {$basePath}/polls");
        exit;
    }
}
