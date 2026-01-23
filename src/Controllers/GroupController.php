<?php

namespace Nexus\Controllers;

use Nexus\Models\Group;
use Nexus\Models\GroupPost;
use Nexus\Core\View;
use Nexus\Services\NotificationDispatcher;
use Nexus\Middleware\TenantModuleMiddleware;

class GroupController
{
    /**
     * Check if groups module is enabled
     */
    private function checkFeature()
    {
        TenantModuleMiddleware::require('groups');
    }

    public function index()
    {
        $this->checkFeature();
        $search = $_GET['q'] ?? null;

        // SEO
        \Nexus\Core\SEO::setTitle('Local Hubs');
        \Nexus\Core\SEO::setDescription("Explore neighborhood hubs and get involved in your local community.");
        \Nexus\Core\SEO::load('group', 0);

        // Get all hub groups
        $hubs = Group::getHubs($search, false);

        // Get featured hubs (manually marked by admin via is_featured flag)
        $featuredHubs = $search ? [] : Group::getFeaturedHubs();

        // API Support
        if (strpos($_SERVER['REQUEST_URI'], '/api') === 0) {
            header('Content-Type: application/json');
            echo json_encode(['data' => $hubs]);
            exit;
        }

        // Permissions - only admins can create hubs
        $userId = $_SESSION['user_id'] ?? 0;
        $canCreateHub = Group::canCreateHub($userId);

        View::render('groups/index', [
            'groups' => $hubs,
            'featuredGroups' => $featuredHubs,
            'pageTitle' => 'Local Hubs',
            'isHubsPage' => true,
            'canCreateGroup' => $canCreateHub
        ]);
    }

    public function myGroups()
    {
        $this->checkFeature();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $myGroups = Group::getUserGroups($userId);

        // SEO
        \Nexus\Core\SEO::setTitle('My Hubs');
        \Nexus\Core\SEO::setDescription('View and manage the community hubs you have joined.');

        View::render('groups/my-groups', [
            'myGroups' => $myGroups,
            'pageTitle' => 'My Hubs'
        ]);
    }

    public function create()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        if (!Group::canCreateHub($userId)) {
            $_SESSION['error'] = "Only administrators can create hubs";
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/groups');
            exit;
        }

        $hubType = \Nexus\Models\GroupType::getHubType();
        if (!$hubType) {
            $_SESSION['error'] = "Hub type not configured";
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/groups');
            exit;
        }

        // Federation settings
        $federationEnabled = false;
        $userFederationOptedIn = false;
        if (class_exists('\Nexus\Services\FederationFeatureService')) {
            try {
                $federationEnabled = \Nexus\Services\FederationFeatureService::isTenantFederationEnabled();
                if ($federationEnabled) {
                    $userFedSettings = \Nexus\Core\Database::query(
                        "SELECT federation_optin FROM federation_user_settings WHERE user_id = ?",
                        [$userId]
                    )->fetch();
                    $userFederationOptedIn = $userFedSettings && $userFedSettings['federation_optin'];
                }
            } catch (\Exception $e) {
                $federationEnabled = false;
            }
        }

        View::render('groups/create', [
            'isHub' => true,
            'typeId' => $hubType['id'],
            'pageTitle' => 'Create Hub',
            'federationEnabled' => $federationEnabled,
            'userFederationOptedIn' => $userFederationOptedIn
        ]);
    }

    /**
     * Show regular community groups on /community-groups page
     */
    public function communityGroups()
    {
        $search = $_GET['q'] ?? null;
        $typeId = $_GET['type'] ?? null;
        $groups = Group::getRegularGroups($search, $typeId);

        $userId = $_SESSION['user_id'] ?? 0;
        $canCreateGroup = Group::canCreateRegularGroup($userId);

        $groupTypes = \Nexus\Models\GroupType::getRegularTypes(true);

        \Nexus\Core\SEO::setTitle('Community Groups');
        \Nexus\Core\SEO::setDescription("Join interest-based community groups and connect with like-minded people.");
        \Nexus\Core\SEO::load('group', 0);

        View::render('groups/index', [
            'groups' => $groups,
            'groupTypes' => $groupTypes,
            'selectedType' => $typeId,
            'pageTitle' => 'Community Groups',
            'isHubsPage' => false,
            'canCreateGroup' => $canCreateGroup
        ]);
    }

    /**
     * Create community group form (anyone)
     */
    public function createCommunityGroup()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        if (!Group::canCreateRegularGroup($userId)) {
            $_SESSION['error'] = "You must be logged in to create a group";
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $groupTypes = \Nexus\Models\GroupType::getRegularTypes(true);

        View::render('groups/create', [
            'isHub' => false,
            'availableTypes' => $groupTypes,
            'pageTitle' => 'Create Community Group'
        ]);
    }

    /**
     * NEW: Modern overlay-based group creation
     * Shows all group types, but restricts hub type to admins only
     */
    public function createGroupOverlay()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $isAdmin = !empty($_SESSION['is_admin']) && $_SESSION['is_admin'];

        // Get all group types
        $groupTypes = [];
        $hubType = \Nexus\Models\GroupType::getHubType();
        $regularTypes = \Nexus\Models\GroupType::getRegularTypes(true);

        // Add hub type if user is admin
        if ($isAdmin && $hubType) {
            $groupTypes[] = $hubType;
        }

        // Add all regular types
        foreach ($regularTypes as $type) {
            $groupTypes[] = $type;
        }

        // Default to first available type
        $defaultTypeId = !empty($groupTypes) ? $groupTypes[0]['id'] : null;

        View::render('groups/create-overlay', [
            'groupTypes' => $groupTypes,
            'defaultTypeId' => $defaultTypeId,
            'isAdmin' => $isAdmin,
            'pageTitle' => 'Create Group'
        ]);
    }

    /**
     * NEW: Modern overlay-based group editing (combining edit + invite tabs)
     */
    public function editGroupOverlay($id)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $group = Group::findById($id);
        if (!$group) {
            http_response_code(404);
            echo "Group not found.";
            exit;
        }

        // Authorization: Must be Owner or Site Admin
        if (!Group::isAdmin($id, $_SESSION['user_id']) && $group['owner_id'] != $_SESSION['user_id']) {
            die("Unauthorized: Only the group owner or administrators can edit settings.");
        }

        $userId = $_SESSION['user_id'];
        $isAdmin = !empty($_SESSION['is_admin']) && $_SESSION['is_admin'];

        // Get all group types for the edit form
        $groupTypes = [];
        $hubType = \Nexus\Models\GroupType::getHubType();
        $regularTypes = \Nexus\Models\GroupType::getRegularTypes(true);

        // Add hub type if user is admin
        if ($isAdmin && $hubType) {
            $groupTypes[] = $hubType;
        }

        // Add all regular types
        foreach ($regularTypes as $type) {
            $groupTypes[] = $type;
        }

        // Get available users for invite tab (excluding existing members)
        $existingMemberIds = \Nexus\Core\Database::query(
            "SELECT user_id FROM group_members WHERE group_id = ?",
            [$id]
        )->fetchAll(\PDO::FETCH_COLUMN);

        $allUsers = \Nexus\Core\Database::query(
            "SELECT id, name, avatar_url, email FROM users WHERE tenant_id = ? ORDER BY name",
            [\Nexus\Core\TenantContext::getId()]
        )->fetchAll();

        // Filter out existing members
        $availableUsers = array_filter($allUsers, function($user) use ($existingMemberIds) {
            return !in_array($user['id'], $existingMemberIds);
        });

        // Get default tab from query parameter
        $defaultTab = $_GET['tab'] ?? 'edit';

        View::render('groups/edit-overlay', [
            'group' => $group,
            'groupTypes' => $groupTypes,
            'isAdmin' => $isAdmin,
            'availableUsers' => $availableUsers,
            'defaultTab' => $defaultTab,
            'pageTitle' => 'Edit ' . $group['name']
        ]);
    }

    public function edit($id)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $group = Group::findById($id);
        if (!$group) {
            http_response_code(404);
            echo "Hub not found.";
            exit;
        }

        // Authorization: Must be Owner or Site Admin
        if (!Group::isAdmin($id, $_SESSION['user_id']) && $group['owner_id'] != $_SESSION['user_id']) {
            die("Unauthorized: Only the Hub owner or site admins can edit settings.");
        }

        // Federation settings
        $federationEnabled = false;
        $userFederationOptedIn = false;
        if (class_exists('\Nexus\Services\FederationFeatureService')) {
            try {
                $federationEnabled = \Nexus\Services\FederationFeatureService::isTenantFederationEnabled();
                if ($federationEnabled) {
                    $userFedSettings = \Nexus\Core\Database::query(
                        "SELECT federation_optin FROM federation_user_settings WHERE user_id = ?",
                        [$_SESSION['user_id']]
                    )->fetch();
                    $userFederationOptedIn = $userFedSettings && $userFedSettings['federation_optin'];
                }
            } catch (\Exception $e) {
                $federationEnabled = false;
            }
        }

        View::render('groups/edit', [
            'group' => $group,
            'pageTitle' => 'Edit Hub - ' . $group['name'],
            'federationEnabled' => $federationEnabled,
            'userFederationOptedIn' => $userFederationOptedIn
        ]);
    }

    public function store()
    {
        \Nexus\Core\Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $visibility = $_POST['visibility'] ?? 'public';
        $location = $_POST['location'] ?? '';
        $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
        $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
        $typeId = !empty($_POST['type_id']) ? (int)$_POST['type_id'] : null;

        // Authorization check based on type
        if ($typeId && \Nexus\Models\GroupType::isHubType($typeId)) {
            // Creating a hub - requires admin permission
            if (!Group::canCreateHub($_SESSION['user_id'])) {
                $_SESSION['error'] = "Only administrators can create hubs";
                header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/groups');
                exit;
            }
        } else {
            // Creating regular group - requires login
            if (!Group::canCreateRegularGroup($_SESSION['user_id'])) {
                $_SESSION['error'] = "You must be logged in to create a group";
                header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
                exit;
            }
        }

        if ($name) {
            // Handle federated visibility (only if user has opted into federation)
            $federatedVisibility = 'none';
            if (!empty($_POST['federated_visibility']) && in_array($_POST['federated_visibility'], ['listed', 'joinable'])) {
                if (class_exists('\Nexus\Services\FederationFeatureService') &&
                    \Nexus\Services\FederationFeatureService::isTenantFederationEnabled()) {
                    $userFedSettings = \Nexus\Core\Database::query(
                        "SELECT federation_optin FROM federation_user_settings WHERE user_id = ?",
                        [$_SESSION['user_id']]
                    )->fetch();

                    if ($userFedSettings && $userFedSettings['federation_optin']) {
                        $federatedVisibility = $_POST['federated_visibility'];
                    }
                }
            }

            $groupId = Group::create($_SESSION['user_id'], $name, $description, '', $visibility, $location, $latitude, $longitude, $typeId, $federatedVisibility);
            Group::join($groupId, $_SESSION['user_id']); // Owner joins automatically

            // Gamification: Check group creation badges
            try {
                \Nexus\Services\GamificationService::checkGroupBadges($_SESSION['user_id'], 'create');
            } catch (\Throwable $e) {
                error_log("Gamification group create error: " . $e->getMessage());
            }

            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/groups/' . $groupId);
            exit;
        }
    }

    public function show($id)
    {
        $this->checkFeature();
        $group = Group::findById($id);
        if (!$group) {
            echo "Group not found";
            return;
        }

        $members = Group::getMembers($id);

        // Extended Data for Legacy Layout
        $userId = $_SESSION['user_id'] ?? 0;
        $isMember = false;
        $isOrganizer = false;

        if ($userId) {
            $isMember = Group::isMember($id, $userId);
            // Corrected Logic: Organizer is Owner OR Admin
            $isOrganizer = Group::isAdmin($id, $userId);
        }

        $subGroups = Group::getSubGroups($id);
        $hasSubHubs = !empty($subGroups);

        // Get pending and invited members for organizers
        $pendingMembers = [];
        $invitedMembers = [];
        if ($isOrganizer) {
            $pendingMembers = Group::getPendingMembers($id);
            $invitedMembers = Group::getInvitedMembers($id);
        }

        // Default to feed tab, unless group has subgroups (then show sub-hubs first)
        $defaultTab = $hasSubHubs ? 'sub-hubs' : 'feed';
        $activeTab = $_GET['tab'] ?? $defaultTab;

        // Set SEO Data Dynamic
        \Nexus\Core\SEO::setTitle($group['name']);
        \Nexus\Core\SEO::setDescription($group['description']);
        if (!empty($group['cover_image_url'])) {
            \Nexus\Core\SEO::setImage($group['cover_image_url']);
        } elseif (!empty($group['image_url'])) { // Fallback to avatar if no cover
            \Nexus\Core\SEO::setImage($group['image_url']);
        }

        // Load Overrides
        \Nexus\Core\SEO::load('group', $id);

        // Auto-generate description if not set
        \Nexus\Core\SEO::autoDescription($group['description']);

        // Add JSON-LD LocalBusiness Schema for groups with location
        \Nexus\Core\SEO::autoSchema('localBusiness', $group);

        // Breadcrumbs
        \Nexus\Core\SEO::addBreadcrumbs([
            ['name' => 'Home', 'url' => '/'],
            ['name' => 'Groups', 'url' => '/groups'],
            ['name' => $group['name'], 'url' => '/groups/' . $id]
        ]);

        View::render('groups/show', [
            'group' => $group,
            'members' => $members,
            'isMember' => $isMember,
            'isOrganizer' => $isOrganizer,
            'subGroups' => $subGroups,
            'hasSubHubs' => $hasSubHubs,
            'pendingMembers' => $pendingMembers,
            'invitedMembers' => $invitedMembers,
            'activeTab' => $activeTab,
            'pageTitle' => $group['name']
        ]);
    }

    /**
     * Show post creation form
     * 2026-01-17: Removed abandoned mobile app redirect - all devices use responsive group page
     */
    public function createPost($groupId)
    {
        // Redirect to group page where users can post via modal (works on all devices)
        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/groups/' . $groupId);
        exit;
    }

    /**
     * Store a new post in a group (AJAX or form submit)
     */
    public function storePost($groupId)
    {
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $basePath = \Nexus\Core\TenantContext::getBasePath();
        $tenantId = \Nexus\Core\TenantContext::getId();
        $userId = $_SESSION['user_id'] ?? 0;

        // CSRF protection
        if ($isAjax) {
            \Nexus\Core\Csrf::verifyOrDieJson();
        } else {
            \Nexus\Core\Csrf::verifyOrDie();
        }

        // Must be logged in
        if (!$userId) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'You must be logged in']);
                exit;
            }
            header("Location: $basePath/login");
            exit;
        }

        // Check group exists
        $group = Group::findById($groupId);
        if (!$group) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Group not found']);
                exit;
            }
            header("Location: $basePath/groups");
            exit;
        }

        // Check membership
        if (!Group::isMember($groupId, $userId)) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'You must be a member to post']);
                exit;
            }
            header("Location: $basePath/groups/$groupId");
            exit;
        }

        try {
            $content = trim($_POST['content'] ?? '');
            if (strlen($content) < 1) {
                throw new \Exception('Please write something to post.');
            }

            // Handle image upload
            $imageUrl = null;
            if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/posts/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // Validate file extension
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (!in_array($ext, $allowedExt)) {
                    throw new \Exception('Invalid image format. Allowed: ' . implode(', ', $allowedExt));
                }

                // Validate MIME type using file content (prevents extension spoofing)
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($_FILES['image']['tmp_name']);
                $allowedMimes = [
                    'image/jpeg',
                    'image/png',
                    'image/gif',
                    'image/webp'
                ];
                if (!in_array($mimeType, $allowedMimes)) {
                    throw new \Exception('Invalid image file type. The file content does not match an allowed image format.');
                }

                $filename = uniqid('post_') . '.' . $ext;
                $targetPath = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                    $imageUrl = '/uploads/posts/' . $filename;
                }
            }

            // Create post
            $dbClass = class_exists('\Nexus\Core\Database') ? '\Nexus\Core\Database' : '\Nexus\Core\DatabaseWrapper';
            $dbClass::query("
                INSERT INTO feed_posts (tenant_id, user_id, group_id, content, image_url, visibility, likes_count, created_at)
                VALUES (?, ?, ?, ?, ?, 'public', 0, NOW())
            ", [$tenantId, $userId, $groupId, $content, $imageUrl]);
            $postId = $dbClass::lastInsertId();

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'post_id' => $postId]);
                exit;
            }

            header("Location: $basePath/groups/$groupId?posted=1");
            exit;

        } catch (\Exception $e) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            // For non-AJAX, redirect back with error
            header("Location: $basePath/groups/$groupId?error=" . urlencode($e->getMessage()));
            exit;
        }
    }

    public function join()
    {
        try {
            \Nexus\Core\Csrf::verifyOrDie();
            if (!isset($_SESSION['user_id'])) {
                if (!empty($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                    exit;
                }
                header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
                exit;
            }
            $groupId = $_POST['group_id'];
            $status = Group::join($groupId, $_SESSION['user_id']);

            // Gamification: Check group join badges (only if successfully joined as active)
            if ($status === 'active') {
                try {
                    \Nexus\Services\GamificationService::checkGroupBadges($_SESSION['user_id'], 'join');
                } catch (\Throwable $e) {
                    error_log("Gamification group join error: " . $e->getMessage());
                }
            }

            if (!empty($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'status' => $status]);
                exit;
            }
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/groups/' . $groupId);
        } catch (\Throwable $e) {
            error_log("Group Join Error: " . $e->getMessage());
            if (!empty($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                exit;
            }
            echo "An error occurred: " . $e->getMessage();
        }
    }

    public function leave()
    {
        try {
            \Nexus\Core\Csrf::verifyOrDie();
            if (!isset($_SESSION['user_id'])) {
                if (!empty($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                    exit;
                }
                header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
                exit;
            }

            $groupId = $_POST['group_id'];
            Group::leave($groupId, $_SESSION['user_id']);

            if (!empty($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            }
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/groups/' . $groupId);
        } catch (\Throwable $e) {
            error_log("Group Leave Error: " . $e->getMessage());
            if (!empty($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                exit;
            }
            echo "An error occurred: " . $e->getMessage();
        }
    }

    public function update()
    {
        \Nexus\Core\Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $groupId = $_POST['group_id'];
        $group = Group::findById($groupId);

        // Authorization: Must be Owner or Site Admin
        if (!Group::isAdmin($groupId, $_SESSION['user_id']) && $group['owner_id'] != $_SESSION['user_id']) {
            die("Unauthorized");
        }

        $updates = [
            'name' => $_POST['name'] ?? $group['name'],
            'description' => $_POST['description'] ?? $group['description'],
            'visibility' => $_POST['visibility'] ?? $group['visibility'],
            'location' => $_POST['location'] ?? $group['location'] ?? '',
            'latitude' => !empty($_POST['latitude']) ? floatval($_POST['latitude']) : ($group['latitude'] ?? null),
            'longitude' => !empty($_POST['longitude']) ? floatval($_POST['longitude']) : ($group['longitude'] ?? null)
        ];

        // Handle federated visibility (only if user has opted into federation)
        if (isset($_POST['federated_visibility'])) {
            $requestedVisibility = $_POST['federated_visibility'];
            if ($requestedVisibility === 'none') {
                $updates['federated_visibility'] = 'none';
            } elseif (in_array($requestedVisibility, ['listed', 'joinable'])) {
                if (class_exists('\Nexus\Services\FederationFeatureService') &&
                    \Nexus\Services\FederationFeatureService::isTenantFederationEnabled()) {
                    $userFedSettings = \Nexus\Core\Database::query(
                        "SELECT federation_optin FROM federation_user_settings WHERE user_id = ?",
                        [$_SESSION['user_id']]
                    )->fetch();

                    if ($userFedSettings && $userFedSettings['federation_optin']) {
                        $updates['federated_visibility'] = $requestedVisibility;
                    }
                }
            }
        }

        // Handle Featured flag (site admins only)
        if (!empty($_SESSION['is_admin']) && $_SESSION['is_admin']) {
            $updates['is_featured'] = isset($_POST['is_featured']) ? 1 : 0;
        }

        // Handle Image Uploads and Clearing
        if (!empty($_POST['clear_avatar'])) {
            // User explicitly requested to clear the avatar
            $updates['image_url'] = '__CLEAR__';
        } elseif (!empty($_FILES['image']['name'])) {
            try {
                // Avatar: Crop to 200x200 square
                $updates['image_url'] = \Nexus\Core\ImageUploader::upload($_FILES['image'], 'groups/avatars', [
                    'crop' => true,
                    'width' => 200,
                    'height' => 200
                ]);
            } catch (\Exception $e) {
                error_log("Group image upload failed: " . $e->getMessage());
            }
        }

        if (!empty($_POST['clear_cover'])) {
            // User explicitly requested to clear the cover image
            $updates['cover_image_url'] = '__CLEAR__';
        } elseif (!empty($_FILES['cover_image']['name'])) {
            try {
                // Cover: Resize to max width 1200, maintain aspect ratio
                $updates['cover_image_url'] = \Nexus\Core\ImageUploader::upload($_FILES['cover_image'], 'groups/covers', [
                    'width' => 1200
                ]);
            } catch (\Exception $e) {
                error_log("Group cover upload failed: " . $e->getMessage());
            }
        }

        Group::update($groupId, $updates);

        // Save SEO metadata if provided
        if (isset($_POST['seo']) && is_array($_POST['seo'])) {
            \Nexus\Models\SeoMetadata::save('group', $groupId, [
                'meta_title' => trim($_POST['seo']['meta_title'] ?? ''),
                'meta_description' => trim($_POST['seo']['meta_description'] ?? ''),
                'meta_keywords' => trim($_POST['seo']['meta_keywords'] ?? ''),
                'canonical_url' => trim($_POST['seo']['canonical_url'] ?? ''),
                'og_image_url' => trim($_POST['seo']['og_image_url'] ?? ''),
                'noindex' => isset($_POST['seo']['noindex'])
            ]);
        }

        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/groups/' . $groupId . '?tab=settings');
    }

    public function manageMember()
    {
        \Nexus\Core\Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) {
            die("Unauthorized");
        }

        $groupId = $_POST['group_id'];
        $memberId = $_POST['user_id'];
        $action = $_POST['action'];

        // Verify Organizer (owner, admin, or site admin)
        if (!Group::isAdmin($groupId, $_SESSION['user_id'])) {
            die("Unauthorized");
        }

        $group = Group::findById($groupId);

        switch ($action) {
            case 'approve':
                Group::updateMemberStatus($groupId, $memberId, 'active');
                // Notify the user that their request was approved
                if ($group) {
                    $approverName = $_SESSION['user_name'] ?? 'An organiser';
                    NotificationDispatcher::dispatch(
                        $memberId,
                        'group',
                        $groupId,
                        'join_approved',
                        "Your request to join {$group['name']} has been approved!",
                        '/groups/' . $groupId,
                        '<p>Great news! <strong>' . htmlspecialchars($approverName) . '</strong> has approved your request to join <strong>' . htmlspecialchars($group['name']) . '</strong>. You are now a member!</p>',
                        false
                    );
                }
                break;
            case 'deny':
            case 'kick':
                Group::leave($groupId, $memberId);
                break;
            case 'promote':
                Group::updateMemberRole($groupId, $memberId, 'admin');
                break;
            case 'demote':
                Group::updateMemberRole($groupId, $memberId, 'member');
                break;
        }

        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/groups/' . $groupId . '?tab=settings');
    }
    public function createDiscussion($groupId)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $group = Group::findById($groupId);
        if (!$group) {
            die("Group not found");
        }

        // Verify Membership
        if (!Group::isMember($groupId, $_SESSION['user_id'])) {
            die("You must join this hub to start a discussion.");
        }

        View::render('groups/discussions/create', ['group' => $group]);
    }

    public function storeDiscussion($groupId)
    {
        \Nexus\Core\Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) {
            die("Unauthorized");
        }

        $title = trim($_POST['title']);
        $content = trim($_POST['content'] ?? '');

        if (empty($title) || empty($content)) {
            die("Title and Message are required");
        }

        $group = Group::findById($groupId);
        if (!$group) {
            die("Group not found");
        }

        // Verify Membership
        if (!Group::isMember($groupId, $_SESSION['user_id'])) {
            die("You must join this hub to start a discussion.");
        }

        $discussionId = \Nexus\Models\GroupDiscussion::create($groupId, $_SESSION['user_id'], $title);
        GroupPost::create($discussionId, $_SESSION['user_id'], $content);

        // NOTIFICATION LOGIC (Using Dispatcher)
        try {
            $ownerId = $group['owner_id'];
            $link = "/groups/" . $groupId . "?tab=discussions&active_discussion=" . $discussionId;
            // Note: Dispatcher needs relative link for In-App, but maybe absolute for Email?
            // Actually, Dispatcher separates them? 
            // Phase 2 plan says: "dispatch($userId, ..., $link, $htmlContent, ...)"
            // Let's pass the relative link, and let Dispatcher/Mailer prepend APP_URL if needed?
            // Wait, sendInstantEmail in Dispatcher uses $link. And Mailer expects HTML.
            // My sendInstantEmail implementation in Dispatcher used $link directly in email??
            // NO. It just passed $link to sendInstantEmail but sendInstantEmail used $htmlContent for body.
            // Ah, sendInstantEmail had $htmlContent.
            // So we must generating the HTML here.

            $appUrl = \Nexus\Core\Env::get('APP_URL');
            $absoluteLink = $appUrl . $link;

            // Generate "Email Body" for Immediate Sends (This will be used if Frequency = Instant)
            // We need 2 versions? Or just one generic?
            // Let's create a generic "New Topic" email body.
            $emailBodyBase = "<h2>New Discussion in " . htmlspecialchars($group['name']) . "</h2>";
            $emailBodyBase .= "<p><strong>" . htmlspecialchars($_SESSION['user_name'] ?? 'A member') . "</strong> started a new topic.</p>";
            $emailBodyBase .= "<h3>" . htmlspecialchars($title) . "</h3>";
            $emailBodyBase .= "<p>" . nl2br(htmlspecialchars(substr($content, 0, 300))) . "...</p>";
            $emailBodyBase .= "<p><a href='{$absoluteLink}' style='background: #db2777; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Discussion</a></p>";

            // Rule #1: Organizer posted -> Notify ALL members (Rule 2 actually)
            if ($_SESSION['user_id'] == $ownerId) {
                $members = Group::getMembers($groupId);
                foreach ($members as $member) {
                    if ($member['id'] != $_SESSION['user_id']) {
                        // Dispatch (Organizer -> Member)
                        NotificationDispatcher::dispatch(
                            $member['id'],
                            'group',
                            $groupId,
                            'new_topic',
                            "New Discussion in " . $group['name'] . ": " . $title,
                            $link,
                            $emailBodyBase,
                            false // Not Organizer Priority (Recipients are members)
                        );
                    }
                }
            } else {
                // Rule #1: Member posted -> Notify Organizer only
                // Dispatch (Member -> Organizer)
                // Organizer Priority = TRUE
                NotificationDispatcher::dispatch(
                    $ownerId,
                    'group',
                    $groupId,
                    'new_topic',
                    "New Discussion in " . $group['name'] . ": " . $title,
                    $link,
                    $emailBodyBase,
                    true // Enforce Organizer Rule
                );
            }
        } catch (\Exception $e) {
            error_log("Failed to dispatch notification: " . $e->getMessage());
        }


        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/groups/' . $groupId . '?tab=discussions');
        exit;
    }
    public function showDiscussion($groupId, $discussionId)
    {
        $group = Group::findById($groupId);
        if (!$group) {
            die("Hub not found");
        }

        $discussion = \Nexus\Models\GroupDiscussion::findById($discussionId);
        if (!$discussion || $discussion['group_id'] != $groupId) {
            die("Discussion not found");
        }

        $posts = \Nexus\Models\GroupPost::getForDiscussion($discussionId);

        // Membership Check (for reply box)
        $userId = $_SESSION['user_id'] ?? 0;
        $isMember = false;
        if ($userId) {
            $isMember = Group::isMember($groupId, $userId);
        }

        // 3. Set SEO
        \Nexus\Core\SEO::setTitle($discussion['title'] . ' - ' . $group['name']);

        // 4. Render Group Show (with active tab 'discussions' and 'activeDiscussion' data)
        // 1. Load Main Group Data (Same as show()) - We need to re-fetch this context
        $members = Group::getMembers($groupId);
        $subGroups = Group::getSubGroups($groupId);
        $hasSubHubs = !empty($subGroups);
        $isOrganizer = ($group['owner_id'] == ($userId ?? 0));

        View::render('groups/show', [
            'group' => $group,
            'members' => $members,
            'isMember' => $isMember,
            'isOrganizer' => $isOrganizer,
            'subGroups' => $subGroups,
            'hasSubHubs' => $hasSubHubs,
            'activeTab' => 'discussions',
            'pageTitle' => $group['name'],
            'activeDiscussion' => $discussion, // Triggers Chat View in Tab
            'activePosts' => $posts
        ]);
    }

    public function replyDiscussion($groupId, $discussionId)
    {
        \Nexus\Core\Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) {
            die("Unauthorized");
        }

        // Verify Membership
        if (!Group::isMember($groupId, $_SESSION['user_id'])) {
            die("You must join this hub to reply.");
        }

        $content = trim($_POST['content'] ?? '');
        if (empty($content)) {
            die("Message is required");
        }

        \Nexus\Models\GroupPost::create($discussionId, $_SESSION['user_id'], $content);

        // Auto-subscribe the replier (Opt-in by participation)
        \Nexus\Models\GroupDiscussionSubscriber::subscribe($_SESSION['user_id'], $discussionId);

        // NOTIFICATION: Email Subscribers (Using Dispatcher)
        try {
            $subscribers = \Nexus\Models\GroupDiscussionSubscriber::getSubscribers($discussionId);
            if (!empty($subscribers)) {
                $group = Group::findById($groupId);
                $discussion = \Nexus\Models\GroupDiscussion::findById($discussionId);

                $link = "/groups/" . $groupId . "?tab=discussions&active_discussion=" . $discussionId;
                $appUrl = \Nexus\Core\Env::get('APP_URL');
                $absoluteLink = $appUrl . $link;

                $emailBody = "<h2>New Reply in " . htmlspecialchars($group['name']) . "</h2>";
                $emailBody .= "<p><strong>" . htmlspecialchars($_SESSION['user_name'] ?? 'A member') . "</strong> replied to <strong>" . htmlspecialchars($discussion['title']) . "</strong>.</p>";
                $emailBody .= "<p>\"" . nl2br(htmlspecialchars(substr($content, 0, 200))) . "...\"</p>";
                $emailBody .= "<p><a href='{$absoluteLink}' style='background: #db2777; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Reply</a></p>";

                foreach ($subscribers as $sub) {
                    // Don't notify the sender
                    if ($sub['id'] != $_SESSION['user_id']) {
                        NotificationDispatcher::dispatch(
                            $sub['id'],
                            'thread', // Context is Thread
                            $discussionId,
                            'new_reply',
                            "New Reply in " . $discussion['title'],
                            $link,
                            $emailBody,
                            false
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Failed to send reply notifications: " . $e->getMessage());
        }

        // Update last reply timestamp for sorting
        // (Optional optimization: GroupDiscussion::touch($discussionId))

        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/groups/' . $groupId . '/discussions/' . $discussionId);
        exit;
    }

    public function toggleSubscription($groupId, $discussionId)
    {
        \Nexus\Core\Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $userId = $_SESSION['user_id'];
        $isSubscribed = \Nexus\Models\GroupDiscussionSubscriber::isSubscribed($userId, $discussionId);

        if ($isSubscribed) {
            \Nexus\Models\GroupDiscussionSubscriber::unsubscribe($userId, $discussionId);
            $newStatus = false;
        } else {
            \Nexus\Models\GroupDiscussionSubscriber::subscribe($userId, $discussionId);
            $newStatus = true;
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'is_subscribed' => $newStatus]);
        exit;
    }

    /**
     * Submit feedback for a group (members only)
     */
    public function submitFeedback($groupId)
    {
        \Nexus\Core\Csrf::verifyOrDie();

        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $userId = $_SESSION['user_id'];

        // Must be a member to submit feedback
        if (!Group::isMember($groupId, $userId)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'You must be a member to submit feedback']);
            exit;
        }

        $rating = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        if ($rating < 1 || $rating > 5) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
            exit;
        }

        \Nexus\Models\GroupFeedback::submit($groupId, $userId, $rating, $comment ?: null);

        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Feedback submitted']);
            exit;
        }

        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/groups/' . $groupId . '?tab=feedback&submitted=1');
        exit;
    }

    /**
     * View feedback for a group (organisers only)
     */
    public function viewFeedback($groupId)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $group = Group::findById($groupId);
        if (!$group) {
            die("Group not found");
        }

        // Only organisers can view feedback
        if (!Group::isAdmin($groupId, $_SESSION['user_id'])) {
            die("Unauthorized: Only organisers can view feedback");
        }

        $feedback = \Nexus\Models\GroupFeedback::getForGroup($groupId);
        $stats = \Nexus\Models\GroupFeedback::getAverageRating($groupId);
        $breakdown = \Nexus\Models\GroupFeedback::getRatingBreakdown($groupId);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'feedback' => $feedback,
            'average_rating' => round($stats['avg_rating'] ?? 0, 1),
            'total_count' => (int)($stats['total_count'] ?? 0),
            'breakdown' => $breakdown
        ]);
        exit;
    }

    /**
     * Get all member reviews within a group (JSON API)
     */
    public function getReviews($groupId)
    {
        $group = Group::findById($groupId);
        if (!$group) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Group not found']);
            exit;
        }

        $reviews = \Nexus\Models\Review::getForGroup($groupId);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'reviews' => $reviews
        ]);
        exit;
    }

    /**
     * Submit a review for a group member
     */
    public function submitReview($groupId)
    {
        \Nexus\Core\Csrf::verifyOrDie();

        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $reviewerId = $_SESSION['user_id'];
        $receiverId = (int)($_POST['receiver_id'] ?? 0);
        $rating = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        // Validate
        if (!$receiverId) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid member']);
            exit;
        }

        if ($reviewerId === $receiverId) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'You cannot review yourself']);
            exit;
        }

        if ($rating < 1 || $rating > 5) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
            exit;
        }

        // Both users must be members of the group
        if (!Group::isMember($groupId, $reviewerId) || !Group::isMember($groupId, $receiverId)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Both users must be group members']);
            exit;
        }

        // Check if already reviewed this user in this group
        $isUpdate = false;
        if (\Nexus\Models\Review::hasReviewedInGroup($reviewerId, $receiverId, $groupId)) {
            // Update existing review
            $sql = "SELECT id FROM reviews WHERE reviewer_id = ? AND receiver_id = ? AND group_id = ?";
            $existing = \Nexus\Core\Database::query($sql, [$reviewerId, $receiverId, $groupId])->fetch();
            if ($existing) {
                \Nexus\Models\Review::update($existing['id'], $rating, $comment ?: null);
                $isUpdate = true;
            }
        } else {
            // Create new review
            \Nexus\Models\Review::create($reviewerId, $receiverId, null, $rating, $comment ?: null, $groupId);
        }

        // Send notification to the receiver (only for new reviews, not updates)
        if (!$isUpdate) {
            $reviewer = \Nexus\Models\User::findById($reviewerId);
            $group = Group::findById($groupId);
            $reviewerName = $reviewer['first_name'] ?? $reviewer['name'] ?? 'Someone';
            $groupName = $group['name'] ?? 'a hub';

            $content = "You received a {$rating}-star review from {$reviewerName} in {$groupName}.";
            $html = "<h2>New Review</h2><p><strong>Rating: {$rating}/5</strong></p>" .
                    ($comment ? "<p>\"{$comment}\"</p>" : "") .
                    "<p>From: {$reviewerName} in {$groupName}</p>";

            NotificationDispatcher::dispatch(
                $receiverId,
                'group',
                $groupId,
                'new_review',
                $content,
                '/groups/' . $groupId . '?tab=reviews',
                $html
            );
        }

        // Check if AJAX request
        if (!empty($_POST['ajax']) || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Review submitted']);
            exit;
        }

        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/groups/' . $groupId . '?tab=reviews&submitted=1');
        exit;
    }

    /**
     * Show review form for a specific member (modal/page)
     */
    /**
     * Show invite page for a group (organisers only)
     */
    public function invite($groupId)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $group = Group::findById($groupId);
        if (!$group) {
            die("Group not found");
        }

        // Only organisers can invite
        if (!Group::isAdmin($groupId, $_SESSION['user_id'])) {
            die("Unauthorized: Only organisers can invite members");
        }

        // Get ALL users in group_members (any status: active, invited, pending)
        $existingMemberIds = \Nexus\Core\Database::query(
            "SELECT user_id FROM group_members WHERE group_id = ?",
            [$groupId]
        )->fetchAll(\PDO::FETCH_COLUMN);

        // Get all users for search (excluding anyone already in group_members)
        $allUsers = \Nexus\Core\Database::query(
            "SELECT id, name, avatar_url, email FROM users WHERE tenant_id = ? ORDER BY name",
            [\Nexus\Core\TenantContext::getId()]
        )->fetchAll();

        // Filter out existing members (any status)
        $availableUsers = array_filter($allUsers, function($user) use ($existingMemberIds) {
            return !in_array($user['id'], $existingMemberIds);
        });

        \Nexus\Core\SEO::setTitle('Invite Members - ' . $group['name']);

        View::render('groups/invite', [
            'group' => $group,
            'availableUsers' => array_values($availableUsers),
            'pageTitle' => 'Invite Members'
        ]);
    }

    /**
     * Send invitations to selected users
     */
    public function sendInvites($groupId)
    {
        \Nexus\Core\Csrf::verifyOrDie();

        if (!isset($_SESSION['user_id'])) {
            die("Login required");
        }

        $group = Group::findById($groupId);
        if (!$group) {
            die("Group not found");
        }

        // Only organisers can invite
        if (!Group::isAdmin($groupId, $_SESSION['user_id'])) {
            die("Unauthorized to invite");
        }

        $userIds = $_POST['user_ids'] ?? [];

        if (empty($userIds)) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/groups/' . $groupId . '/invite?err=no_users');
            exit;
        }

        $inviterName = $_SESSION['user_name'] ?? 'Someone';
        $addDirectly = !empty($_POST['add_directly']);
        $processedCount = 0;

        foreach ($userIds as $uid) {
            // Check if already a member or invited
            $existing = \Nexus\Core\Database::query(
                "SELECT id FROM group_members WHERE group_id = ? AND user_id = ?",
                [$groupId, $uid]
            )->fetch();

            if ($existing) {
                continue;
            }

            if ($addDirectly) {
                // Add directly as active member
                \Nexus\Core\Database::query(
                    "INSERT INTO group_members (group_id, user_id, status, role) VALUES (?, ?, 'active', 'member')",
                    [$groupId, $uid]
                );
                $processedCount++;

                // Send Notification that they've been added
                NotificationDispatcher::dispatch(
                    $uid,
                    'group',
                    $groupId,
                    'added_to_group',
                    "$inviterName added you to " . $group['name'],
                    '/groups/' . $groupId,
                    '<p><strong>' . htmlspecialchars($inviterName) . '</strong> has added you to <strong>' . htmlspecialchars($group['name']) . '</strong>. You are now a member!</p>',
                    false
                );

                // Send Email
                $addedUser = \Nexus\Models\User::findById($uid);
                if ($addedUser && !empty($addedUser['email'])) {
                    $mailer = new \Nexus\Core\Mailer();
                    $subject = "You've been added to " . $group['name'];
                    $body = "
                        <div style='font-family: sans-serif; color: #333; line-height: 1.6;'>
                            <h2>Welcome to the Hub!</h2>
                            <p><strong>$inviterName</strong> has added you to:</p>
                            <div style='background: #f0fdf4; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0;'>
                                <h3 style='margin: 0 0 10px 0; color: #166534;'>{$group['name']}</h3>
                                <p style='margin: 0;'>" . htmlspecialchars(substr($group['description'], 0, 200)) . "</p>
                            </div>
                            <p>You're now a member! Click below to view the hub:</p>
                            <p><a href='" . \Nexus\Core\TenantContext::getDomain() . "/groups/{$groupId}' style='background: #10b981; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>View Hub</a></p>
                        </div>
                    ";
                    $mailer->send($addedUser['email'], $subject, $body);
                }
            } else {
                // Add as invited member (existing behaviour)
                \Nexus\Core\Database::query(
                    "INSERT INTO group_members (group_id, user_id, status, role) VALUES (?, ?, 'invited', 'member')",
                    [$groupId, $uid]
                );
                $processedCount++;

                // Send Notification
                \Nexus\Models\Notification::create(
                    $uid,
                    "$inviterName invited you to join " . $group['name'],
                    '/groups/' . $groupId
                );

                // Send Email
                $invitedUser = \Nexus\Models\User::findById($uid);
                if ($invitedUser && !empty($invitedUser['email'])) {
                    $mailer = new \Nexus\Core\Mailer();
                    $subject = "You're invited to join " . $group['name'];
                    $body = "
                        <div style='font-family: sans-serif; color: #333; line-height: 1.6;'>
                            <h2>You're Invited!</h2>
                            <p><strong>$inviterName</strong> has invited you to join a hub:</p>
                            <div style='background: #fdf2f8; border-left: 4px solid #db2777; padding: 15px; margin: 20px 0;'>
                                <h3 style='margin: 0 0 10px 0; color: #be185d;'>{$group['name']}</h3>
                                <p style='margin: 0;'>" . htmlspecialchars(substr($group['description'], 0, 200)) . "</p>
                            </div>
                            <p>Click below to view and join:</p>
                            <p><a href='" . \Nexus\Core\TenantContext::getDomain() . "/groups/{$groupId}' style='background: #db2777; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>View Hub</a></p>
                        </div>
                    ";
                    $mailer->send($invitedUser['email'], $subject, $body);
                }
            }
        }

        $param = $addDirectly ? 'added' : 'invited';
        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/groups/' . $groupId . '?tab=settings&' . $param . '=' . $processedCount);
        exit;
    }

    public function showReviewForm($groupId, $memberId)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $group = Group::findById($groupId);
        if (!$group) {
            die("Group not found");
        }

        $member = \Nexus\Core\Database::query(
            "SELECT id, name, avatar_url FROM users WHERE id = ?",
            [$memberId]
        )->fetch();

        if (!$member) {
            die("Member not found");
        }

        // Check existing review
        $existingReview = null;
        $sql = "SELECT * FROM reviews WHERE reviewer_id = ? AND receiver_id = ? AND group_id = ?";
        $existingReview = \Nexus\Core\Database::query($sql, [$_SESSION['user_id'], $memberId, $groupId])->fetch();

        \Nexus\Core\View::render('groups/review-member', [
            'group' => $group,
            'member' => $member,
            'existingReview' => $existingReview
        ]);
    }
}
