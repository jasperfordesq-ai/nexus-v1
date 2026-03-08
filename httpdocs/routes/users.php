<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.
// ============================================
// API V2 - USERS (RESTful User/Profile Management)
// ============================================
// List users (public directory)
$router->add('GET', '/api/v2/users', function () {
  try {
    header('Content-Type: application/json');
    $tenantId = \Nexus\Core\TenantContext::getId();

    // Resolve viewer identity early — needed to exclude them from queries and personalise ranking
    $viewerId = null;
    try {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $m)) {
            $payload = \Nexus\Services\TokenService::validateToken($m[1]);
            if ($payload && ($payload['type'] ?? 'access') === 'access' && isset($payload['user_id'])) {
                if ((int)($payload['tenant_id'] ?? 0) === $tenantId) {
                    $viewerId = (int)$payload['user_id'];
                }
            }
        }
        if (!$viewerId) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (!empty($_SESSION['user_id']) && (int)($_SESSION['tenant_id'] ?? 0) === $tenantId) {
                $viewerId = (int)$_SESSION['user_id'];
            }
        }
    } catch (\Throwable $e) {
        // Anonymous
    }

    // Pagination
    $limit = min(intval($_GET['limit'] ?? 50), 100);
    $offset = max(intval($_GET['offset'] ?? 0), 0);

    // Search
    $search = $_GET['q'] ?? '';

    // Sorting
    $sort = $_GET['sort'] ?? 'name';
    $order = strtoupper($_GET['order'] ?? 'ASC');
    if (!in_array($order, ['ASC', 'DESC'])) {
        $order = 'ASC';
    }

    // For subquery-based sorts, we need to use the calculated field name (alias)
    $validSorts = [
        'name' => 'u.name',
        'joined' => 'u.created_at',
        'rating' => '(SELECT AVG(rating) FROM reviews WHERE receiver_id = u.id AND tenant_id = u.tenant_id)',
        'hours_given' => '(SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE sender_id = u.id AND status = \'completed\' AND tenant_id = u.tenant_id)'
    ];
    $orderByField = $validSorts[$sort] ?? 'u.name';
    $orderBy = "$orderByField $order";

    // Build WHERE clause
    $params = [$tenantId, 'active'];
    $whereClause = "u.tenant_id = ? AND u.status = ?";

    if ($search) {
        // Meilisearch first, fall back to MySQL FULLTEXT
        $memberIds = \Nexus\Services\SearchService::searchUsers($search, $tenantId);
        if ($memberIds !== false && !empty($memberIds)) {
            // Meilisearch path: restrict to the ranked ID set
            $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
            $whereClause .= " AND u.id IN ($placeholders)";
            $params = array_merge($params, array_map('intval', $memberIds));
        } elseif ($memberIds !== false) {
            // Meilisearch available but returned no hits
            $whereClause .= " AND 1=0";
        } else {
            // Meilisearch unavailable — fall back to MySQL FULLTEXT + LIKE
            $whereClause .= " AND (
                MATCH(u.first_name, u.last_name, u.bio, u.skills) AGAINST(? IN BOOLEAN MODE)
                OR u.name LIKE ?
                OR u.location LIKE ?
            )";
            $params[] = $search;         // FULLTEXT — no % wrapping
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
    }

    // Exclude the viewer from the member directory (they know who they are)
    if ($viewerId) {
        $whereClause .= " AND u.id != ?";
        $params[] = $viewerId;
    }

    // Get total count for meta
    // nosemgrep: php.lang.security.injection.tainted-sql-string.tainted-sql-string -- $whereClause built from parameterized conditions only
    $countSql = "SELECT COUNT(*) as total FROM users u WHERE $whereClause";
    $totalCount = \Nexus\Core\Database::query($countSql, $params)->fetch()['total'] ?? 0;

    // Get users with pagination and calculated fields
    // Note: ORDER BY uses $orderByField from $validSorts allowlist — safe from injection
    // nosemgrep: tainted-sql-string — $orderBy from $validSorts allowlist, $whereClause from parameterized conditions
    $sql = "SELECT u.id, u.name, u.first_name, u.last_name,
                   u.avatar_url as avatar, u.bio as tagline,
                   u.location, u.latitude, u.longitude,
                   u.created_at, u.last_login_at, u.is_verified,
                   (SELECT AVG(rating) FROM reviews WHERE receiver_id = u.id AND tenant_id = u.tenant_id) as rating,
                   (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE sender_id = u.id AND status = 'completed' AND tenant_id = u.tenant_id) as total_hours_given,
                   (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE receiver_id = u.id AND status = 'completed' AND tenant_id = u.tenant_id) as total_hours_received,
                   (SELECT COUNT(*) FROM listings WHERE user_id = u.id AND status = 'active' AND type = 'offer' AND tenant_id = u.tenant_id) as offer_count,
                   (SELECT COUNT(*) FROM listings WHERE user_id = u.id AND status = 'active' AND type = 'request' AND tenant_id = u.tenant_id) as request_count
            FROM users u
            WHERE $whereClause
            ORDER BY $orderBy
            LIMIT ? OFFSET ?";
    $params[] = (int)$limit;
    $params[] = (int)$offset;

    $users = \Nexus\Core\Database::query($sql, $params)->fetchAll();

    // Cast types and create aliases needed by ranking service
    foreach ($users as &$user) {
        $user['rating'] = $user['rating'] ? (float)$user['rating'] : null;
        $user['total_hours_given'] = (int)$user['total_hours_given'];
        $user['hours_given'] = $user['total_hours_given'];  // alias — no duplicate DB query
        $user['total_hours_received'] = (int)$user['total_hours_received'];
        $user['offer_count'] = (int)$user['offer_count'];
        $user['request_count'] = (int)$user['request_count'];
        $user['is_verified'] = (bool)$user['is_verified'];
    }
    unset($user);

    // Apply CommunityRank for the default smart view (when no explicit sort param is set)
    if (!isset($_GET['sort']) && \Nexus\Services\MemberRankingService::isEnabled() && !empty($users)) {
        $users = \Nexus\Services\MemberRankingService::rankMembers($users, $viewerId, ['search' => $search]);
    }

    // Strip fields used only for ranking, plus private fields — not part of the public API contract
    // nosemgrep: php.lang.security.injection.echoed-request.echoed-request -- output is json_encode with Content-Type: application/json
    $users = array_map(static function (array $u): array {
        unset(
            $u['_community_rank'], $u['_score_breakdown'],
            $u['hours_given'],
            $u['offer_count'], $u['request_count'],
            $u['last_login_at']  // privacy: last active time is not for public display
        );
        return $u;
    }, $users);

    // nosemgrep: echoed-request — json_encode output with Content-Type: application/json prevents XSS
    echo json_encode([
        'data' => $users,
        'meta' => [
            'total_items' => (int)$totalCount,
            'per_page' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $totalCount
        ]
    ]);
  } catch (\Throwable $e) {
    error_log("API /v2/users error: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
  }
});
$router->add('GET', '/api/v2/users/me', 'Nexus\Controllers\Api\UsersApiController@me');
$router->add('PUT', '/api/v2/users/me', 'Nexus\Controllers\Api\UsersApiController@update');
$router->add('GET', '/api/v2/users/me/preferences', 'Nexus\Controllers\Api\UsersApiController@getPreferences');
$router->add('PUT', '/api/v2/users/me/preferences', 'Nexus\Controllers\Api\UsersApiController@updatePreferences');
$router->add('PUT', '/api/v2/users/me/theme', 'Nexus\Controllers\Api\UsersApiController@updateTheme');
$router->add('PUT', '/api/v2/users/me/language', 'Nexus\Controllers\Api\UsersApiController@updateLanguage');
$router->add('POST', '/api/v2/users/me/avatar', 'Nexus\Controllers\Api\UsersApiController@updateAvatar');
$router->add('POST', '/api/v2/users/me/password', 'Nexus\Controllers\Api\UsersApiController@updatePassword');
$router->add('DELETE', '/api/v2/users/me', 'Nexus\Controllers\Api\UsersApiController@deleteAccount');
$router->add('GET', '/api/v2/users/me/listings', 'Nexus\Controllers\Api\UsersApiController@myListings'); // Must be before {id}/listings
$router->add('GET', '/api/v2/users/me/notifications', 'Nexus\Controllers\Api\UsersApiController@notificationPreferences');
$router->add('PUT', '/api/v2/users/me/notifications', 'Nexus\Controllers\Api\UsersApiController@updateNotificationPreferences');
$router->add('GET', '/api/v2/users/me/consent', 'Nexus\Controllers\Api\UsersApiController@getConsent');
$router->add('PUT', '/api/v2/users/me/consent', 'Nexus\Controllers\Api\UsersApiController@updateConsent');
$router->add('POST', '/api/v2/users/me/gdpr-request', 'Nexus\Controllers\Api\UsersApiController@createGdprRequest');
$router->add('GET', '/api/v2/users/me/sessions', 'Nexus\Controllers\Api\UsersApiController@sessions');
$router->add('GET', '/api/v2/users/me/match-preferences', 'Nexus\Controllers\Api\MatchPreferencesApiController@show');
$router->add('PUT', '/api/v2/users/me/match-preferences', 'Nexus\Controllers\Api\MatchPreferencesApiController@update');
$router->add('GET', '/api/v2/users/me/insurance', 'Nexus\Controllers\Api\UserInsuranceApiController@list');
$router->add('POST', '/api/v2/users/me/insurance', 'Nexus\Controllers\Api\UserInsuranceApiController@upload');
$router->add('GET', '/api/v2/users/{id}', 'Nexus\Controllers\Api\UsersApiController@show');
$router->add('GET', '/api/v2/users/{id}/listings', 'Nexus\Controllers\Api\UsersApiController@listings');
$router->add('GET', '/api/v2/members/nearby', 'Nexus\Controllers\Api\UsersApiController@nearby');

// ============================================
// API V2 - SKILLS TAXONOMY (M1)
// ============================================
$router->add('GET', '/api/v2/skills/categories', 'Nexus\Controllers\Api\SkillTaxonomyApiController@getCategories');
$router->add('GET', '/api/v2/skills/search', 'Nexus\Controllers\Api\SkillTaxonomyApiController@search');
$router->add('GET', '/api/v2/skills/members', 'Nexus\Controllers\Api\SkillTaxonomyApiController@getMembersWithSkill');
$router->add('GET', '/api/v2/skills/categories/{id}', 'Nexus\Controllers\Api\SkillTaxonomyApiController@getCategoryById');
$router->add('POST', '/api/v2/skills/categories', 'Nexus\Controllers\Api\SkillTaxonomyApiController@createCategory');
$router->add('PUT', '/api/v2/skills/categories/{id}', 'Nexus\Controllers\Api\SkillTaxonomyApiController@updateCategory');
$router->add('DELETE', '/api/v2/skills/categories/{id}', 'Nexus\Controllers\Api\SkillTaxonomyApiController@deleteCategory');
$router->add('GET', '/api/v2/users/me/skills', 'Nexus\Controllers\Api\SkillTaxonomyApiController@getMySkills');
$router->add('POST', '/api/v2/users/me/skills', 'Nexus\Controllers\Api\SkillTaxonomyApiController@addSkill');
$router->add('PUT', '/api/v2/users/me/skills/{id}', 'Nexus\Controllers\Api\SkillTaxonomyApiController@updateSkill');
$router->add('DELETE', '/api/v2/users/me/skills/{id}', 'Nexus\Controllers\Api\SkillTaxonomyApiController@removeSkill');
$router->add('GET', '/api/v2/users/{id}/skills', 'Nexus\Controllers\Api\SkillTaxonomyApiController@getUserSkills');

// ============================================
// API V2 - MEMBER AVAILABILITY (M2)
// ============================================
$router->add('GET', '/api/v2/users/me/availability', 'Nexus\Controllers\Api\MemberAvailabilityApiController@getMyAvailability');
$router->add('PUT', '/api/v2/users/me/availability', 'Nexus\Controllers\Api\MemberAvailabilityApiController@setBulkAvailability');
$router->add('PUT', '/api/v2/users/me/availability/{day}', 'Nexus\Controllers\Api\MemberAvailabilityApiController@setDayAvailability');
$router->add('POST', '/api/v2/users/me/availability/date', 'Nexus\Controllers\Api\MemberAvailabilityApiController@addSpecificDate');
$router->add('DELETE', '/api/v2/users/me/availability/{id}', 'Nexus\Controllers\Api\MemberAvailabilityApiController@deleteSlot');
$router->add('GET', '/api/v2/users/{id}/availability', 'Nexus\Controllers\Api\MemberAvailabilityApiController@getUserAvailability');
$router->add('GET', '/api/v2/members/availability/compatible', 'Nexus\Controllers\Api\MemberAvailabilityApiController@findCompatibleTimes');
$router->add('GET', '/api/v2/members/availability/available', 'Nexus\Controllers\Api\MemberAvailabilityApiController@getAvailableMembers');

// ============================================
// API V2 - ENDORSEMENTS (M3)
// ============================================
$router->add('POST', '/api/v2/members/{id}/endorse', 'Nexus\Controllers\Api\EndorsementApiController@endorse');
$router->add('DELETE', '/api/v2/members/{id}/endorse', 'Nexus\Controllers\Api\EndorsementApiController@removeEndorsement');
$router->add('GET', '/api/v2/members/{id}/endorsements', 'Nexus\Controllers\Api\EndorsementApiController@getEndorsements');
$router->add('GET', '/api/v2/members/top-endorsed', 'Nexus\Controllers\Api\EndorsementApiController@getTopEndorsed');

// ============================================
// API V2 - ACTIVITY DASHBOARD (M4)
// ============================================
$router->add('GET', '/api/v2/users/me/activity/dashboard', 'Nexus\Controllers\Api\MemberActivityApiController@getDashboard');
$router->add('GET', '/api/v2/users/me/activity/timeline', 'Nexus\Controllers\Api\MemberActivityApiController@getTimeline');
$router->add('GET', '/api/v2/users/me/activity/hours', 'Nexus\Controllers\Api\MemberActivityApiController@getHours');
$router->add('GET', '/api/v2/users/me/activity/monthly', 'Nexus\Controllers\Api\MemberActivityApiController@getMonthlyHours');
$router->add('GET', '/api/v2/users/{id}/activity/dashboard', 'Nexus\Controllers\Api\MemberActivityApiController@getPublicDashboard');

// ============================================
// API V2 - VERIFICATION BADGES (M5)
// ============================================
$router->add('GET', '/api/v2/users/{id}/verification-badges', 'Nexus\Controllers\Api\MemberVerificationBadgeApiController@getUserBadges');
$router->add('POST', '/api/v2/admin/users/{id}/badges', 'Nexus\Controllers\Api\MemberVerificationBadgeApiController@grantBadge');
$router->add('DELETE', '/api/v2/admin/users/{id}/badges/{type}', 'Nexus\Controllers\Api\MemberVerificationBadgeApiController@revokeBadge');
$router->add('GET', '/api/v2/admin/users/{id}/badges', 'Nexus\Controllers\Api\MemberVerificationBadgeApiController@getAdminBadgeList');

// ============================================
// API V2 - SUB-ACCOUNTS / FAMILY (M6)
// ============================================
$router->add('GET', '/api/v2/users/me/sub-accounts', 'Nexus\Controllers\Api\SubAccountApiController@getChildAccounts');
$router->add('GET', '/api/v2/users/me/parent-accounts', 'Nexus\Controllers\Api\SubAccountApiController@getParentAccounts');
$router->add('POST', '/api/v2/users/me/sub-accounts', 'Nexus\Controllers\Api\SubAccountApiController@requestRelationship');
$router->add('PUT', '/api/v2/users/me/sub-accounts/{id}/approve', 'Nexus\Controllers\Api\SubAccountApiController@approveRelationship');
$router->add('PUT', '/api/v2/users/me/sub-accounts/{id}/permissions', 'Nexus\Controllers\Api\SubAccountApiController@updatePermissions');
$router->add('DELETE', '/api/v2/users/me/sub-accounts/{id}', 'Nexus\Controllers\Api\SubAccountApiController@revokeRelationship');
$router->add('GET', '/api/v2/users/me/sub-accounts/{childId}/activity', 'Nexus\Controllers\Api\SubAccountApiController@getChildActivity');
