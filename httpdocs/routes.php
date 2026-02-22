<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Project NEXUS - Route Definitions
 * ---------------------------------
 */

use Nexus\Core\Router;
use Nexus\Core\TenantContext;

$router = new Router();

// --------------------------------------------------------------------------
// SUPER ADMIN PANEL (Hierarchical Tenancy Management)
// --------------------------------------------------------------------------
// Dashboard
$router->add('GET', '/super-admin', 'Nexus\Controllers\SuperAdmin\DashboardController@index');
$router->add('GET', '/super-admin/dashboard', 'Nexus\Controllers\SuperAdmin\DashboardController@index');

// Tenant Management
$router->add('GET', '/super-admin/tenants', 'Nexus\Controllers\SuperAdmin\TenantController@index');
$router->add('GET', '/super-admin/tenants/hierarchy', 'Nexus\Controllers\SuperAdmin\TenantController@hierarchy');
$router->add('GET', '/super-admin/tenants/create', 'Nexus\Controllers\SuperAdmin\TenantController@create');
$router->add('POST', '/super-admin/tenants/store', 'Nexus\Controllers\SuperAdmin\TenantController@store');
$router->add('GET', '/super-admin/tenants/{id}', 'Nexus\Controllers\SuperAdmin\TenantController@show');
$router->add('GET', '/super-admin/tenants/{id}/edit', 'Nexus\Controllers\SuperAdmin\TenantController@edit');
$router->add('POST', '/super-admin/tenants/{id}/update', 'Nexus\Controllers\SuperAdmin\TenantController@update');
$router->add('POST', '/super-admin/tenants/{id}/delete', 'Nexus\Controllers\SuperAdmin\TenantController@delete');
$router->add('POST', '/super-admin/tenants/{id}/reactivate', 'Nexus\Controllers\SuperAdmin\TenantController@reactivate');
$router->add('POST', '/super-admin/tenants/{id}/toggle-hub', 'Nexus\Controllers\SuperAdmin\TenantController@toggleHub');
$router->add('POST', '/super-admin/tenants/{id}/move', 'Nexus\Controllers\SuperAdmin\TenantController@move');

// User Management (Cross-Tenant)
$router->add('GET', '/super-admin/users', 'Nexus\Controllers\SuperAdmin\UserController@index');
$router->add('GET', '/super-admin/users/create', 'Nexus\Controllers\SuperAdmin\UserController@create');
$router->add('POST', '/super-admin/users/store', 'Nexus\Controllers\SuperAdmin\UserController@store');
$router->add('GET', '/super-admin/users/{id}', 'Nexus\Controllers\SuperAdmin\UserController@show');
$router->add('GET', '/super-admin/users/{id}/edit', 'Nexus\Controllers\SuperAdmin\UserController@edit');
$router->add('POST', '/super-admin/users/{id}/update', 'Nexus\Controllers\SuperAdmin\UserController@update');
$router->add('POST', '/super-admin/users/{id}/grant-super-admin', 'Nexus\Controllers\SuperAdmin\UserController@grantSuperAdmin');
$router->add('POST', '/super-admin/users/{id}/revoke-super-admin', 'Nexus\Controllers\SuperAdmin\UserController@revokeSuperAdmin');
$router->add('POST', '/super-admin/users/{id}/grant-global-super-admin', 'Nexus\Controllers\SuperAdmin\UserController@grantGlobalSuperAdmin');
$router->add('POST', '/super-admin/users/{id}/revoke-global-super-admin', 'Nexus\Controllers\SuperAdmin\UserController@revokeGlobalSuperAdmin');
$router->add('POST', '/super-admin/users/{id}/move-tenant', 'Nexus\Controllers\SuperAdmin\UserController@moveTenant');
$router->add('POST', '/super-admin/users/{id}/move-and-promote', 'Nexus\Controllers\SuperAdmin\UserController@moveAndPromote');

// Bulk Operations
$router->add('GET', '/super-admin/bulk', 'Nexus\Controllers\SuperAdmin\BulkController@index');
$router->add('POST', '/super-admin/bulk/move-users', 'Nexus\Controllers\SuperAdmin\BulkController@moveUsers');
$router->add('POST', '/super-admin/bulk/update-tenants', 'Nexus\Controllers\SuperAdmin\BulkController@updateTenants');

// Audit Log
$router->add('GET', '/super-admin/audit', 'Nexus\Controllers\SuperAdmin\AuditController@index');

// Super Admin API Endpoints
$router->add('GET', '/super-admin/api/tenants', 'Nexus\Controllers\SuperAdmin\TenantController@apiList');
$router->add('GET', '/super-admin/api/tenants/hierarchy', 'Nexus\Controllers\SuperAdmin\TenantController@apiHierarchy');
$router->add('GET', '/super-admin/api/users/search', 'Nexus\Controllers\SuperAdmin\UserController@apiSearch');
$router->add('GET', '/super-admin/api/bulk/users', 'Nexus\Controllers\SuperAdmin\BulkController@apiGetUsers');
$router->add('GET', '/super-admin/api/audit', 'Nexus\Controllers\SuperAdmin\AuditController@apiLog');

// --------------------------------------------------------------------------
// SUPER ADMIN FEDERATION (Platform-Wide Federation Management)
// --------------------------------------------------------------------------
$router->add('GET', '/super-admin/federation', 'Nexus\Controllers\SuperAdmin\FederationController@index');
$router->add('GET', '/super-admin/federation/system-controls', 'Nexus\Controllers\SuperAdmin\FederationController@systemControls');
$router->add('POST', '/super-admin/federation/update-system-controls', 'Nexus\Controllers\SuperAdmin\FederationController@updateSystemControls');
$router->add('POST', '/super-admin/federation/emergency-lockdown', 'Nexus\Controllers\SuperAdmin\FederationController@emergencyLockdown');
$router->add('POST', '/super-admin/federation/lift-lockdown', 'Nexus\Controllers\SuperAdmin\FederationController@liftLockdown');
$router->add('GET', '/super-admin/federation/whitelist', 'Nexus\Controllers\SuperAdmin\FederationController@whitelist');
$router->add('POST', '/super-admin/federation/add-to-whitelist', 'Nexus\Controllers\SuperAdmin\FederationController@addToWhitelist');
$router->add('POST', '/super-admin/federation/remove-from-whitelist', 'Nexus\Controllers\SuperAdmin\FederationController@removeFromWhitelist');
$router->add('GET', '/super-admin/federation/partnerships', 'Nexus\Controllers\SuperAdmin\FederationController@partnerships');
$router->add('POST', '/super-admin/federation/suspend-partnership', 'Nexus\Controllers\SuperAdmin\FederationController@suspendPartnership');
$router->add('POST', '/super-admin/federation/terminate-partnership', 'Nexus\Controllers\SuperAdmin\FederationController@terminatePartnership');
$router->add('GET', '/super-admin/federation/audit', 'Nexus\Controllers\SuperAdmin\FederationController@auditLog');
$router->add('GET', '/super-admin/federation/tenant/{id}', 'Nexus\Controllers\SuperAdmin\FederationController@tenantFeatures');
$router->add('POST', '/super-admin/federation/update-tenant-feature', 'Nexus\Controllers\SuperAdmin\FederationController@updateTenantFeature');

// --------------------------------------------------------------------------
// DEBUG/TEST ROUTES REMOVED FOR SECURITY (2026-01-23)
// --------------------------------------------------------------------------
// Previously contained 30+ debug/test endpoints that exposed sensitive data.
// These routes have been removed from production. If needed for development,
// access them via CLI scripts in /scripts/ directory instead.
// --------------------------------------------------------------------------

// --------------------------------------------------------------------------
// FEDERATION API (External Partner Integration)
// --------------------------------------------------------------------------
$router->add('GET', '/api/v1/federation', 'Nexus\Controllers\Api\FederationApiController@index');
$router->add('GET', '/api/v1/federation/timebanks', 'Nexus\Controllers\Api\FederationApiController@timebanks');
$router->add('GET', '/api/v1/federation/members', 'Nexus\Controllers\Api\FederationApiController@members');
$router->add('GET', '/api/v1/federation/members/{id}', 'Nexus\Controllers\Api\FederationApiController@member');
$router->add('GET', '/api/v1/federation/listings', 'Nexus\Controllers\Api\FederationApiController@listings');
$router->add('GET', '/api/v1/federation/listings/{id}', 'Nexus\Controllers\Api\FederationApiController@listing');
$router->add('POST', '/api/v1/federation/messages', 'Nexus\Controllers\Api\FederationApiController@sendMessage');
$router->add('POST', '/api/v1/federation/transactions', 'Nexus\Controllers\Api\FederationApiController@createTransaction');
$router->add('POST', '/api/v1/federation/oauth/token', 'Nexus\Controllers\Api\FederationApiController@oauthToken');
$router->add('POST', '/api/v1/federation/webhooks/test', 'Nexus\Controllers\Api\FederationApiController@testWebhook');

// --------------------------------------------------------------------------
// LEGACY V1 API ROUTES — DEPRECATED
// --------------------------------------------------------------------------
// These routes predate the V2 API and are kept for backward compatibility
// with the mobile app (Capacitor). They are superseded by /api/v2/* endpoints.
//
// Deprecated: 2026-02-19 (QA Phase 7.3)
// Migrate consumers to /api/v2/* equivalents before removing.
// V2 equivalents: /api/v2/polls, /api/v2/goals, /api/v2/volunteering,
//   /api/v2/events, /api/v2/wallet/*, /api/v2/listings, /api/v2/notifications,
//   /api/v2/users (members)
// --------------------------------------------------------------------------

// Polls (deprecated — use /api/v2/polls)
$router->add('GET', '/api/polls', 'Nexus\Controllers\Api\PollApiController@index');
$router->add('POST', '/api/polls/vote', 'Nexus\Controllers\Api\PollApiController@vote');

// Goals (deprecated — use /api/v2/goals)
$router->add('GET', '/api/goals', 'Nexus\Controllers\Api\GoalApiController@index');
$router->add('POST', '/api/goals/update', 'Nexus\Controllers\Api\GoalApiController@updateProgress'); // Method is updateProgress
$router->add('POST', '/api/goals/offer-buddy', 'Nexus\Controllers\Api\GoalApiController@offerBuddy'); // Offer to be a goal buddy

// Volunteering (deprecated — use /api/v2/volunteering)
$router->add('GET', '/api/vol_opportunities', 'Nexus\Controllers\Api\VolunteeringApiController@index');

// Events (deprecated — use /api/v2/events)
$router->add('GET', '/api/events', 'Nexus\Controllers\Api\EventApiController@index');
$router->add('POST', '/api/events/rsvp', 'Nexus\Controllers\Api\EventApiController@rsvp');

// Wallet (deprecated — use /api/v2/wallet/*)
$router->add('GET', '/api/wallet/balance', 'Nexus\Controllers\Api\WalletApiController@balance');

// Cookie Consent API (EU Compliance)
$router->add('GET', '/api/cookie-consent', 'Nexus\Controllers\Api\CookieConsentController@show');
$router->add('POST', '/api/cookie-consent', 'Nexus\Controllers\Api\CookieConsentController@store');
$router->add('PUT', '/api/cookie-consent/{id}', 'Nexus\Controllers\Api\CookieConsentController@update');
$router->add('DELETE', '/api/cookie-consent/{id}', 'Nexus\Controllers\Api\CookieConsentController@withdraw');
$router->add('GET', '/api/cookie-consent/inventory', 'Nexus\Controllers\Api\CookieConsentController@inventory');
$router->add('GET', '/api/cookie-consent/check/{category}', 'Nexus\Controllers\Api\CookieConsentController@check');

// Legal Documents API (Public Content + User Acceptance Tracking)
$router->add('GET', '/api/v2/legal/{type}', 'Nexus\Controllers\LegalDocumentController@apiGetDocument');
$router->add('GET', '/api/v2/legal/{type}/versions', 'Nexus\Controllers\LegalDocumentController@apiGetVersions');
$router->add('GET', '/api/v2/legal/version/{versionId}', 'Nexus\Controllers\LegalDocumentController@apiGetVersion');
$router->add('POST', '/api/legal/accept', 'Nexus\Controllers\LegalDocumentController@accept');
$router->add('POST', '/api/legal/accept-all', 'Nexus\Controllers\LegalDocumentController@acceptAll');
$router->add('GET', '/api/legal/status', 'Nexus\Controllers\LegalDocumentController@status');

// Nexus Score API
$router->add('GET', '/api/nexus-score', 'Nexus\Controllers\NexusScoreController@apiGetScore');
$router->add('POST', '/api/nexus-score/recalculate', 'Nexus\Controllers\NexusScoreController@apiRecalculateScores');
// Wallet continued (deprecated — use /api/v2/wallet/*)
$router->add('GET', '/api/wallet/transactions', 'Nexus\Controllers\Api\WalletApiController@transactions');
$router->add('GET', '/api/wallet/pending-count', 'Nexus\Controllers\Api\WalletApiController@pendingCount'); // Badge updates
$router->add('POST', '/api/wallet/transfer', 'Nexus\Controllers\Api\WalletApiController@transfer');
$router->add('POST', '/api/wallet/delete', 'Nexus\Controllers\Api\WalletApiController@delete');
$router->add('POST', '/api/wallet/user-search', 'Nexus\Controllers\Api\WalletApiController@userSearch'); // User autocomplete

// Core — Directory, Feed, etc. (deprecated — use /api/v2/users, /api/v2/listings, /api/v2/groups, /api/v2/messages, /api/v2/notifications)
$router->add('GET', '/api/members', 'Nexus\Controllers\Api\CoreApiController@members');
$router->add('GET', '/api/listings', 'Nexus\Controllers\Api\CoreApiController@listings');
$router->add('GET', '/api/groups', 'Nexus\Controllers\Api\CoreApiController@groups');
$router->add('GET', '/api/messages', 'Nexus\Controllers\Api\CoreApiController@messages');
$router->add('GET', '/api/notifications', 'Nexus\Controllers\Api\CoreApiController@notifications');
$router->add('GET', '/api/notifications/check', 'Nexus\Controllers\Api\CoreApiController@checkNotifications'); // ADDED
$router->add('GET', '/api/notifications/unread-count', 'Nexus\Controllers\Api\CoreApiController@unreadCount'); // Badge updates
$router->add('GET', '/api/notifications/poll', 'Nexus\Controllers\NotificationController@poll'); // Lightweight polling for badge updates
$router->add('POST', '/api/notifications/read', 'Nexus\Controllers\NotificationController@markRead');
$router->add('POST', '/api/notifications/delete', 'Nexus\Controllers\NotificationController@delete'); // New Delete API

// Listings delete (deprecated — use DELETE /api/v2/listings/{id})
$router->add('POST', '/api/listings/delete', 'Nexus\Controllers\ListingController@delete'); // Listings Delete API

// ============================================
// TENANT BOOTSTRAP API v2 - Public tenant configuration
// Returns branding, features, and config for frontend init
// ============================================
$router->add('GET', '/api/v2/tenant/bootstrap', 'Nexus\Controllers\Api\TenantBootstrapController@bootstrap');
$router->add('GET', '/api/v2/tenants', 'Nexus\Controllers\Api\TenantBootstrapController@list');
$router->add('GET', '/api/v2/platform/stats', 'Nexus\Controllers\Api\TenantBootstrapController@platformStats');

// ============================================
// LISTINGS API v2 - RESTful CRUD
// Full API for mobile/SPA with standardized responses
// ============================================

// Categories endpoint (public - for listing/event forms)
$router->add('GET', '/api/v2/categories', function () {
    header('Content-Type: application/json');
    $type = $_GET['type'] ?? 'listing';
    $categories = \Nexus\Models\Category::getByType($type);
    echo json_encode(['data' => $categories]);
});

$router->add('GET', '/api/v2/listings', 'Nexus\Controllers\Api\ListingsApiController@index');
$router->add('GET', '/api/v2/listings/nearby', 'Nexus\Controllers\Api\ListingsApiController@nearby');
$router->add('POST', '/api/v2/listings', 'Nexus\Controllers\Api\ListingsApiController@store');
$router->add('GET', '/api/v2/listings/{id}', 'Nexus\Controllers\Api\ListingsApiController@show');
$router->add('PUT', '/api/v2/listings/{id}', 'Nexus\Controllers\Api\ListingsApiController@update');
$router->add('DELETE', '/api/v2/listings/{id}', 'Nexus\Controllers\Api\ListingsApiController@destroy');
$router->add('POST', '/api/v2/listings/{id}/image', 'Nexus\Controllers\Api\ListingsApiController@uploadImage');

// ============================================
// API V2 - USERS (RESTful User/Profile Management)
// ============================================
// List users (public directory)
$router->add('GET', '/api/v2/users', function () {
    header('Content-Type: application/json');
    $tenantId = \Nexus\Core\TenantContext::getId();

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
        $whereClause .= " AND (u.name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.bio LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    // Get total count for meta
    $countSql = "SELECT COUNT(*) as total FROM users u WHERE $whereClause";
    $totalCount = \Nexus\Core\Database::query($countSql, $params)->fetch()['total'] ?? 0;

    // Get users with pagination and calculated fields
    $sql = "SELECT u.id, u.name, u.first_name, u.last_name,
                   u.avatar_url as avatar, u.bio as tagline,
                   u.location, u.latitude, u.longitude,
                   u.created_at,
                   (SELECT AVG(rating) FROM reviews WHERE receiver_id = u.id AND tenant_id = u.tenant_id) as rating,
                   (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE sender_id = u.id AND status = 'completed' AND tenant_id = u.tenant_id) as total_hours_given
            FROM users u
            WHERE $whereClause
            ORDER BY $orderBy
            LIMIT $limit OFFSET $offset";

    $users = \Nexus\Core\Database::query($sql, $params)->fetchAll();

    // Convert numeric fields from strings to numbers
    foreach ($users as &$user) {
        $user['rating'] = $user['rating'] ? (float)$user['rating'] : null;
        $user['total_hours_given'] = (int)$user['total_hours_given'];
    }
    unset($user); // Break reference

    echo json_encode([
        'data' => $users,
        'meta' => [
            'total_items' => (int)$totalCount,
            'per_page' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $totalCount
        ]
    ]);
});
$router->add('GET', '/api/v2/users/me', 'Nexus\Controllers\Api\UsersApiController@me');
$router->add('PUT', '/api/v2/users/me', 'Nexus\Controllers\Api\UsersApiController@update');
$router->add('PUT', '/api/v2/users/me/preferences', 'Nexus\Controllers\Api\UsersApiController@updatePreferences');
$router->add('PUT', '/api/v2/users/me/theme', 'Nexus\Controllers\Api\UsersApiController@updateTheme');
$router->add('POST', '/api/v2/users/me/avatar', 'Nexus\Controllers\Api\UsersApiController@updateAvatar');
$router->add('POST', '/api/v2/users/me/password', 'Nexus\Controllers\Api\UsersApiController@updatePassword');
$router->add('DELETE', '/api/v2/users/me', 'Nexus\Controllers\Api\UsersApiController@deleteAccount');
$router->add('GET', '/api/v2/users/{id}', 'Nexus\Controllers\Api\UsersApiController@show');
$router->add('GET', '/api/v2/users/{id}/listings', 'Nexus\Controllers\Api\UsersApiController@listings');
$router->add('GET', '/api/v2/users/me/notifications', 'Nexus\Controllers\Api\UsersApiController@notificationPreferences');
$router->add('PUT', '/api/v2/users/me/notifications', 'Nexus\Controllers\Api\UsersApiController@updateNotificationPreferences');
$router->add('GET', '/api/v2/members/nearby', 'Nexus\Controllers\Api\UsersApiController@nearby');

// ============================================
// API V2 - MESSAGES (RESTful Messaging)
// ============================================
$router->add('GET', '/api/v2/messages', 'Nexus\Controllers\Api\MessagesApiController@conversations');
$router->add('GET', '/api/v2/messages/unread-count', 'Nexus\Controllers\Api\MessagesApiController@unreadCount');
$router->add('POST', '/api/v2/messages', 'Nexus\Controllers\Api\MessagesApiController@send');
$router->add('POST', '/api/v2/messages/typing', 'Nexus\Controllers\Api\MessagesApiController@typing');
$router->add('POST', '/api/v2/messages/upload-voice', 'Nexus\Controllers\Api\MessagesApiController@uploadVoice');
$router->add('POST', '/api/v2/messages/voice', 'Nexus\Controllers\Api\MessagesApiController@sendVoice');
$router->add('DELETE', '/api/v2/messages/conversations/{id}', 'Nexus\Controllers\Api\MessagesApiController@archiveConversation');
$router->add('GET', '/api/v2/messages/{id}', 'Nexus\Controllers\Api\MessagesApiController@show');
$router->add('PUT', '/api/v2/messages/{id}/read', 'Nexus\Controllers\Api\MessagesApiController@markRead');
$router->add('POST', '/api/v2/messages/{id}/reactions', 'Nexus\Controllers\Api\MessagesApiController@toggleReaction');
$router->add('PUT', '/api/v2/messages/{id}', 'Nexus\Controllers\Api\MessagesApiController@update');
$router->add('DELETE', '/api/v2/messages/{id}', 'Nexus\Controllers\Api\MessagesApiController@deleteMessage');
$router->add('DELETE', '/api/v2/conversations/{id}', 'Nexus\Controllers\Api\MessagesApiController@archive');
$router->add('POST', '/api/v2/messages/conversations/{id}/restore', 'Nexus\Controllers\Api\MessagesApiController@restoreConversation');

// ============================================
// API V2 - EXCHANGES (Exchange Workflow System)
// ============================================
$router->add('GET', '/api/v2/exchanges/config', 'Nexus\Controllers\Api\ExchangesApiController@config');
$router->add('GET', '/api/v2/exchanges/check', 'Nexus\Controllers\Api\ExchangesApiController@check');
$router->add('GET', '/api/v2/exchanges', 'Nexus\Controllers\Api\ExchangesApiController@index');
$router->add('POST', '/api/v2/exchanges', 'Nexus\Controllers\Api\ExchangesApiController@store');
$router->add('GET', '/api/v2/exchanges/{id}', 'Nexus\Controllers\Api\ExchangesApiController@show');
$router->add('POST', '/api/v2/exchanges/{id}/accept', 'Nexus\Controllers\Api\ExchangesApiController@accept');
$router->add('POST', '/api/v2/exchanges/{id}/decline', 'Nexus\Controllers\Api\ExchangesApiController@decline');
$router->add('POST', '/api/v2/exchanges/{id}/start', 'Nexus\Controllers\Api\ExchangesApiController@start');
$router->add('POST', '/api/v2/exchanges/{id}/complete', 'Nexus\Controllers\Api\ExchangesApiController@complete');
$router->add('POST', '/api/v2/exchanges/{id}/confirm', 'Nexus\Controllers\Api\ExchangesApiController@confirm');
$router->add('DELETE', '/api/v2/exchanges/{id}', 'Nexus\Controllers\Api\ExchangesApiController@cancel');

// ============================================
// API V2 - EVENTS (RESTful Event Management)
// ============================================
$router->add('GET', '/api/v2/events', 'Nexus\Controllers\Api\EventsApiController@index');
$router->add('GET', '/api/v2/events/nearby', 'Nexus\Controllers\Api\EventsApiController@nearby');
$router->add('POST', '/api/v2/events', 'Nexus\Controllers\Api\EventsApiController@store');
$router->add('GET', '/api/v2/events/{id}', 'Nexus\Controllers\Api\EventsApiController@show');
$router->add('PUT', '/api/v2/events/{id}', 'Nexus\Controllers\Api\EventsApiController@update');
$router->add('DELETE', '/api/v2/events/{id}', 'Nexus\Controllers\Api\EventsApiController@destroy');
$router->add('POST', '/api/v2/events/{id}/rsvp', 'Nexus\Controllers\Api\EventsApiController@rsvp');
$router->add('DELETE', '/api/v2/events/{id}/rsvp', 'Nexus\Controllers\Api\EventsApiController@removeRsvp');
$router->add('GET', '/api/v2/events/{id}/attendees', 'Nexus\Controllers\Api\EventsApiController@attendees');
$router->add('POST', '/api/v2/events/{id}/attendees/{attendeeId}/check-in', 'Nexus\Controllers\Api\EventsApiController@checkIn');
$router->add('POST', '/api/v2/events/{id}/image', 'Nexus\Controllers\Api\EventsApiController@uploadImage');

// ============================================
// API V2 - GROUPS (RESTful Group Management)
// ============================================
$router->add('GET', '/api/v2/groups', 'Nexus\Controllers\Api\GroupsApiController@index');
$router->add('POST', '/api/v2/groups', 'Nexus\Controllers\Api\GroupsApiController@store');
$router->add('GET', '/api/v2/groups/{id}', 'Nexus\Controllers\Api\GroupsApiController@show');
$router->add('PUT', '/api/v2/groups/{id}', 'Nexus\Controllers\Api\GroupsApiController@update');
$router->add('DELETE', '/api/v2/groups/{id}', 'Nexus\Controllers\Api\GroupsApiController@destroy');
$router->add('POST', '/api/v2/groups/{id}/join', 'Nexus\Controllers\Api\GroupsApiController@join');
$router->add('DELETE', '/api/v2/groups/{id}/membership', 'Nexus\Controllers\Api\GroupsApiController@leave');
$router->add('GET', '/api/v2/groups/{id}/members', 'Nexus\Controllers\Api\GroupsApiController@members');
$router->add('PUT', '/api/v2/groups/{id}/members/{userId}', 'Nexus\Controllers\Api\GroupsApiController@updateMember');
$router->add('DELETE', '/api/v2/groups/{id}/members/{userId}', 'Nexus\Controllers\Api\GroupsApiController@removeMember');
$router->add('GET', '/api/v2/groups/{id}/requests', 'Nexus\Controllers\Api\GroupsApiController@pendingRequests');
$router->add('POST', '/api/v2/groups/{id}/requests/{userId}', 'Nexus\Controllers\Api\GroupsApiController@handleRequest');
$router->add('GET', '/api/v2/groups/{id}/discussions', 'Nexus\Controllers\Api\GroupsApiController@discussions');
$router->add('POST', '/api/v2/groups/{id}/discussions', 'Nexus\Controllers\Api\GroupsApiController@createDiscussion');
$router->add('GET', '/api/v2/groups/{id}/discussions/{discussionId}', 'Nexus\Controllers\Api\GroupsApiController@discussionMessages');
$router->add('POST', '/api/v2/groups/{id}/discussions/{discussionId}/messages', 'Nexus\Controllers\Api\GroupsApiController@postToDiscussion');
$router->add('POST', '/api/v2/groups/{id}/image', 'Nexus\Controllers\Api\GroupsApiController@uploadImage');

// ============================================
// API V2 - CONNECTIONS (User Friend Requests)
// ============================================
$router->add('GET', '/api/v2/connections', 'Nexus\Controllers\Api\ConnectionsApiController@index');
$router->add('GET', '/api/v2/connections/pending', 'Nexus\Controllers\Api\ConnectionsApiController@pendingCounts');
$router->add('GET', '/api/v2/connections/status/{userId}', 'Nexus\Controllers\Api\ConnectionsApiController@status');
$router->add('POST', '/api/v2/connections/request', 'Nexus\Controllers\Api\ConnectionsApiController@request');
$router->add('POST', '/api/v2/connections/{id}/accept', 'Nexus\Controllers\Api\ConnectionsApiController@accept');
$router->add('DELETE', '/api/v2/connections/{id}', 'Nexus\Controllers\Api\ConnectionsApiController@destroy');

// ============================================
// API V2 - WALLET (Time Credit Transactions)
// ============================================
$router->add('GET', '/api/v2/wallet/balance', 'Nexus\Controllers\Api\WalletApiController@balanceV2');
$router->add('GET', '/api/v2/wallet/transactions', 'Nexus\Controllers\Api\WalletApiController@transactionsV2');
$router->add('GET', '/api/v2/wallet/transactions/{id}', 'Nexus\Controllers\Api\WalletApiController@showTransaction');
$router->add('POST', '/api/v2/wallet/transfer', 'Nexus\Controllers\Api\WalletApiController@transferV2');
$router->add('DELETE', '/api/v2/wallet/transactions/{id}', 'Nexus\Controllers\Api\WalletApiController@destroyTransaction');
$router->add('GET', '/api/v2/wallet/user-search', 'Nexus\Controllers\Api\WalletApiController@userSearchV2');
$router->add('GET', '/api/v2/wallet/pending-count', 'Nexus\Controllers\Api\WalletApiController@pendingCount');

// ============================================
// API V2 - FEED (Social Feed with Cursor Pagination)
// ============================================
$router->add('GET', '/api/v2/feed', 'Nexus\Controllers\Api\SocialApiController@feedV2');
$router->add('POST', '/api/v2/feed/posts', 'Nexus\Controllers\Api\SocialApiController@createPostV2');
$router->add('POST', '/api/v2/feed/like', 'Nexus\Controllers\Api\SocialApiController@likeV2');
$router->add('POST', '/api/v2/feed/polls', 'Nexus\Controllers\Api\SocialApiController@createPollV2');
$router->add('GET', '/api/v2/feed/polls/{id}', 'Nexus\Controllers\Api\SocialApiController@getPollV2');
$router->add('POST', '/api/v2/feed/polls/{id}/vote', 'Nexus\Controllers\Api\SocialApiController@votePollV2');
$router->add('POST', '/api/v2/feed/posts/{id}/hide', 'Nexus\Controllers\Api\SocialApiController@hidePostV2');
$router->add('POST', '/api/v2/feed/posts/{id}/report', 'Nexus\Controllers\Api\SocialApiController@reportPostV2');
$router->add('POST', '/api/v2/feed/posts/{id}/delete', 'Nexus\Controllers\Api\SocialApiController@deletePostV2');
$router->add('POST', '/api/v2/feed/users/{id}/mute', 'Nexus\Controllers\Api\SocialApiController@muteUserV2');

// ============================================
// API V2 - REALTIME (Pusher Configuration)
// ============================================
$router->add('GET', '/api/v2/realtime/config', function () {
    header('Content-Type: application/json');
    $config = \Nexus\Services\RealtimeService::getFrontendConfig();
    echo json_encode(['data' => $config]);
});

// ============================================
// API V2 - NOTIFICATIONS (Cursor Paginated)
// ============================================
$router->add('GET', '/api/v2/notifications', 'Nexus\Controllers\Api\NotificationsApiController@index');
$router->add('GET', '/api/v2/notifications/counts', 'Nexus\Controllers\Api\NotificationsApiController@counts');
$router->add('POST', '/api/v2/notifications/read-all', 'Nexus\Controllers\Api\NotificationsApiController@markAllRead');
$router->add('DELETE', '/api/v2/notifications', 'Nexus\Controllers\Api\NotificationsApiController@destroyAll');
$router->add('GET', '/api/v2/notifications/{id}', 'Nexus\Controllers\Api\NotificationsApiController@show');
$router->add('POST', '/api/v2/notifications/{id}/read', 'Nexus\Controllers\Api\NotificationsApiController@markRead');
$router->add('DELETE', '/api/v2/notifications/{id}', 'Nexus\Controllers\Api\NotificationsApiController@destroy');

// ============================================
// API V2 - REVIEWS (Trust System)
// ============================================
$router->add('GET', '/api/v2/reviews/pending', 'Nexus\Controllers\Api\ReviewsApiController@pending');
$router->add('GET', '/api/v2/reviews/user/{userId}', 'Nexus\Controllers\Api\ReviewsApiController@userReviews');
$router->add('GET', '/api/v2/users/{userId}/reviews', 'Nexus\Controllers\Api\ReviewsApiController@userReviews');
$router->add('GET', '/api/v2/reviews/user/{userId}/stats', 'Nexus\Controllers\Api\ReviewsApiController@userStats');
$router->add('GET', '/api/v2/reviews/user/{userId}/trust', 'Nexus\Controllers\Api\ReviewsApiController@userTrust');
$router->add('GET', '/api/v2/reviews/{id}', 'Nexus\Controllers\Api\ReviewsApiController@show');
$router->add('POST', '/api/v2/reviews', 'Nexus\Controllers\Api\ReviewsApiController@store');
$router->add('DELETE', '/api/v2/reviews/{id}', 'Nexus\Controllers\Api\ReviewsApiController@destroy');

// ============================================
// API V2 - SEARCH (Unified Search)
// ============================================
$router->add('GET', '/api/v2/search', 'Nexus\Controllers\Api\SearchApiController@index');
$router->add('GET', '/api/v2/search/suggestions', 'Nexus\Controllers\Api\SearchApiController@suggestions');

// ============================================
// API V2 - METRICS (Performance Monitoring)
// ============================================
$router->add('POST', '/api/v2/metrics', 'Nexus\Controllers\Api\MetricsApiController@store');
$router->add('GET', '/api/v2/metrics/summary', 'Nexus\Controllers\Api\MetricsApiController@summary');

// ============================================
// API V2 - POLLS (Full CRUD)
// ============================================
$router->add('GET', '/api/v2/polls', 'Nexus\Controllers\Api\PollsApiController@index');
$router->add('POST', '/api/v2/polls', 'Nexus\Controllers\Api\PollsApiController@store');
$router->add('GET', '/api/v2/polls/{id}', 'Nexus\Controllers\Api\PollsApiController@show');
$router->add('PUT', '/api/v2/polls/{id}', 'Nexus\Controllers\Api\PollsApiController@update');
$router->add('DELETE', '/api/v2/polls/{id}', 'Nexus\Controllers\Api\PollsApiController@destroy');
$router->add('POST', '/api/v2/polls/{id}/vote', 'Nexus\Controllers\Api\PollsApiController@vote');

// ============================================
// API V2 - GOALS (Full CRUD + Progress Tracking)
// ============================================
$router->add('GET', '/api/v2/goals', 'Nexus\Controllers\Api\GoalsApiController@index');
$router->add('POST', '/api/v2/goals', 'Nexus\Controllers\Api\GoalsApiController@store');
$router->add('GET', '/api/v2/goals/discover', 'Nexus\Controllers\Api\GoalsApiController@discover');
$router->add('GET', '/api/v2/goals/{id}', 'Nexus\Controllers\Api\GoalsApiController@show');
$router->add('PUT', '/api/v2/goals/{id}', 'Nexus\Controllers\Api\GoalsApiController@update');
$router->add('DELETE', '/api/v2/goals/{id}', 'Nexus\Controllers\Api\GoalsApiController@destroy');
$router->add('POST', '/api/v2/goals/{id}/progress', 'Nexus\Controllers\Api\GoalsApiController@progress');
$router->add('POST', '/api/v2/goals/{id}/buddy', 'Nexus\Controllers\Api\GoalsApiController@buddy');
$router->add('POST', '/api/v2/goals/{id}/complete', 'Nexus\Controllers\Api\GoalsApiController@complete');

// ============================================
// API V2 - GAMIFICATION (XP, Badges, Leaderboards)
// ============================================
$router->add('GET', '/api/v2/gamification/profile', 'Nexus\Controllers\Api\GamificationV2ApiController@profile');
$router->add('GET', '/api/v2/gamification/badges', 'Nexus\Controllers\Api\GamificationV2ApiController@badges');
$router->add('GET', '/api/v2/gamification/badges/{key}', 'Nexus\Controllers\Api\GamificationV2ApiController@showBadge');
$router->add('GET', '/api/v2/gamification/leaderboard', 'Nexus\Controllers\Api\GamificationV2ApiController@leaderboard');
$router->add('GET', '/api/v2/gamification/challenges', 'Nexus\Controllers\Api\GamificationV2ApiController@challenges');
$router->add('GET', '/api/v2/gamification/collections', 'Nexus\Controllers\Api\GamificationV2ApiController@collections');
$router->add('GET', '/api/v2/gamification/daily-reward', 'Nexus\Controllers\Api\GamificationV2ApiController@dailyRewardStatus');
$router->add('POST', '/api/v2/gamification/daily-reward', 'Nexus\Controllers\Api\GamificationV2ApiController@claimDailyReward');
$router->add('GET', '/api/v2/gamification/shop', 'Nexus\Controllers\Api\GamificationV2ApiController@shop');
$router->add('POST', '/api/v2/gamification/shop/purchase', 'Nexus\Controllers\Api\GamificationV2ApiController@purchase');
$router->add('PUT', '/api/v2/gamification/showcase', 'Nexus\Controllers\Api\GamificationV2ApiController@updateShowcase');
$router->add('GET', '/api/v2/gamification/seasons', 'Nexus\Controllers\Api\GamificationV2ApiController@seasons');
$router->add('GET', '/api/v2/gamification/seasons/current', 'Nexus\Controllers\Api\GamificationV2ApiController@currentSeason');
$router->add('POST', '/api/v2/gamification/challenges/{id}/claim', 'Nexus\Controllers\Api\GamificationV2ApiController@claimChallenge');

// ============================================
// API V2 - VOLUNTEERING (Full Module)
// ============================================
// Opportunities
$router->add('GET', '/api/v2/volunteering/opportunities', 'Nexus\Controllers\Api\VolunteerApiController@opportunities');
$router->add('POST', '/api/v2/volunteering/opportunities', 'Nexus\Controllers\Api\VolunteerApiController@createOpportunity');
$router->add('GET', '/api/v2/volunteering/opportunities/{id}', 'Nexus\Controllers\Api\VolunteerApiController@showOpportunity');
$router->add('PUT', '/api/v2/volunteering/opportunities/{id}', 'Nexus\Controllers\Api\VolunteerApiController@updateOpportunity');
$router->add('DELETE', '/api/v2/volunteering/opportunities/{id}', 'Nexus\Controllers\Api\VolunteerApiController@deleteOpportunity');
$router->add('GET', '/api/v2/volunteering/opportunities/{id}/shifts', 'Nexus\Controllers\Api\VolunteerApiController@shifts');
$router->add('GET', '/api/v2/volunteering/opportunities/{id}/applications', 'Nexus\Controllers\Api\VolunteerApiController@opportunityApplications');
$router->add('POST', '/api/v2/volunteering/opportunities/{id}/apply', 'Nexus\Controllers\Api\VolunteerApiController@apply');

// Applications
$router->add('GET', '/api/v2/volunteering/applications', 'Nexus\Controllers\Api\VolunteerApiController@myApplications');
$router->add('PUT', '/api/v2/volunteering/applications/{id}', 'Nexus\Controllers\Api\VolunteerApiController@handleApplication');
$router->add('DELETE', '/api/v2/volunteering/applications/{id}', 'Nexus\Controllers\Api\VolunteerApiController@withdrawApplication');

// Shifts
$router->add('GET', '/api/v2/volunteering/shifts', 'Nexus\Controllers\Api\VolunteerApiController@myShifts');
$router->add('POST', '/api/v2/volunteering/shifts/{id}/signup', 'Nexus\Controllers\Api\VolunteerApiController@signUp');
$router->add('DELETE', '/api/v2/volunteering/shifts/{id}/signup', 'Nexus\Controllers\Api\VolunteerApiController@cancelSignup');

// Hours
$router->add('GET', '/api/v2/volunteering/hours', 'Nexus\Controllers\Api\VolunteerApiController@myHours');
$router->add('POST', '/api/v2/volunteering/hours', 'Nexus\Controllers\Api\VolunteerApiController@logHours');
$router->add('GET', '/api/v2/volunteering/hours/summary', 'Nexus\Controllers\Api\VolunteerApiController@hoursSummary');
$router->add('PUT', '/api/v2/volunteering/hours/{id}/verify', 'Nexus\Controllers\Api\VolunteerApiController@verifyHours');

// Organisations
$router->add('GET', '/api/v2/volunteering/organisations', 'Nexus\Controllers\Api\VolunteerApiController@organisations');
$router->add('GET', '/api/v2/volunteering/organisations/{id}', 'Nexus\Controllers\Api\VolunteerApiController@showOrganisation');

// Volunteering Reviews (separate from main reviews)
$router->add('POST', '/api/v2/volunteering/reviews', 'Nexus\Controllers\Api\VolunteerApiController@createReview');
$router->add('GET', '/api/v2/volunteering/reviews/{type}/{id}', 'Nexus\Controllers\Api\VolunteerApiController@getReviews');

// ============================================
// API V2 - COMMENTS (Threaded comments for React frontend)
// ============================================
$router->add('GET', '/api/v2/comments', 'Nexus\Controllers\Api\CommentsV2ApiController@index');
$router->add('POST', '/api/v2/comments', 'Nexus\Controllers\Api\CommentsV2ApiController@store');
$router->add('PUT', '/api/v2/comments/{id}', 'Nexus\Controllers\Api\CommentsV2ApiController@update');
$router->add('DELETE', '/api/v2/comments/{id}', 'Nexus\Controllers\Api\CommentsV2ApiController@destroy');
$router->add('POST', '/api/v2/comments/{id}/reactions', 'Nexus\Controllers\Api\CommentsV2ApiController@reactions');

// ============================================
// API V2 - BLOG (Public, for React frontend)
// ============================================
$router->add('GET', '/api/v2/blog', 'Nexus\Controllers\Api\BlogPublicApiController@index');
$router->add('GET', '/api/v2/blog/categories', 'Nexus\Controllers\Api\BlogPublicApiController@categories');
$router->add('GET', '/api/v2/blog/{slug}', 'Nexus\Controllers\Api\BlogPublicApiController@show');

// ============================================
// API V2 - RESOURCES (Public, for React frontend)
// ============================================
$router->add('GET', '/api/v2/resources', 'Nexus\Controllers\Api\ResourcesPublicApiController@index');
$router->add('GET', '/api/v2/resources/categories', 'Nexus\Controllers\Api\ResourcesPublicApiController@categories');
$router->add('POST', '/api/v2/resources', 'Nexus\Controllers\Api\ResourcesPublicApiController@store');

// ============================================
// API V2 - ADMIN (React Admin Panel)
// Dashboard, Users, Listings, Config, Cache, Jobs
// ============================================

// Admin Dashboard
$router->add('GET', '/api/v2/admin/dashboard/stats', 'Nexus\Controllers\Api\AdminDashboardApiController@stats');
$router->add('GET', '/api/v2/admin/dashboard/trends', 'Nexus\Controllers\Api\AdminDashboardApiController@trends');
$router->add('GET', '/api/v2/admin/dashboard/activity', 'Nexus\Controllers\Api\AdminDashboardApiController@activity');

// Admin Users
$router->add('GET', '/api/v2/admin/users', 'Nexus\Controllers\Api\AdminUsersApiController@index');
$router->add('POST', '/api/v2/admin/users', 'Nexus\Controllers\Api\AdminUsersApiController@store');
$router->add('POST', '/api/v2/admin/users/import', 'Nexus\Controllers\Api\AdminUsersApiController@import');
$router->add('GET', '/api/v2/admin/users/import/template', 'Nexus\Controllers\Api\AdminUsersApiController@importTemplate');
$router->add('GET', '/api/v2/admin/users/{id}', 'Nexus\Controllers\Api\AdminUsersApiController@show');
$router->add('PUT', '/api/v2/admin/users/{id}', 'Nexus\Controllers\Api\AdminUsersApiController@update');
$router->add('DELETE', '/api/v2/admin/users/{id}', 'Nexus\Controllers\Api\AdminUsersApiController@destroy');
$router->add('POST', '/api/v2/admin/users/{id}/approve', 'Nexus\Controllers\Api\AdminUsersApiController@approve');
$router->add('POST', '/api/v2/admin/users/{id}/suspend', 'Nexus\Controllers\Api\AdminUsersApiController@suspend');
$router->add('POST', '/api/v2/admin/users/{id}/ban', 'Nexus\Controllers\Api\AdminUsersApiController@ban');
$router->add('POST', '/api/v2/admin/users/{id}/reactivate', 'Nexus\Controllers\Api\AdminUsersApiController@reactivate');
$router->add('POST', '/api/v2/admin/users/{id}/reset-2fa', 'Nexus\Controllers\Api\AdminUsersApiController@reset2fa');
$router->add('POST', '/api/v2/admin/users/badges/recheck-all', 'Nexus\Controllers\Api\AdminGamificationApiController@recheckAll');
$router->add('POST', '/api/v2/admin/users/{id}/badges', 'Nexus\Controllers\Api\AdminUsersApiController@addBadge');
$router->add('DELETE', '/api/v2/admin/users/{id}/badges/{badgeId}', 'Nexus\Controllers\Api\AdminUsersApiController@removeBadge');
$router->add('POST', '/api/v2/admin/users/{id}/impersonate', 'Nexus\Controllers\Api\AdminUsersApiController@impersonate');
$router->add('PUT', '/api/v2/admin/users/{id}/super-admin', 'Nexus\Controllers\Api\AdminUsersApiController@setSuperAdmin');
$router->add('POST', '/api/v2/admin/users/{id}/badges/recheck', 'Nexus\Controllers\Api\AdminUsersApiController@recheckBadges');
$router->add('GET', '/api/v2/admin/users/{id}/consents', 'Nexus\Controllers\Api\AdminUsersApiController@getConsents');
$router->add('POST', '/api/v2/admin/users/{id}/password', 'Nexus\Controllers\Api\AdminUsersApiController@setPassword');
$router->add('POST', '/api/v2/admin/users/{id}/send-password-reset', 'Nexus\Controllers\Api\AdminUsersApiController@sendPasswordReset');
$router->add('POST', '/api/v2/admin/users/{id}/send-welcome-email', 'Nexus\Controllers\Api\AdminUsersApiController@sendWelcomeEmail');

// Admin Listings/Content
$router->add('GET', '/api/v2/admin/listings', 'Nexus\Controllers\Api\AdminListingsApiController@index');
$router->add('GET', '/api/v2/admin/listings/{id}', 'Nexus\Controllers\Api\AdminListingsApiController@show');
$router->add('POST', '/api/v2/admin/listings/{id}/approve', 'Nexus\Controllers\Api\AdminListingsApiController@approve');
$router->add('DELETE', '/api/v2/admin/listings/{id}', 'Nexus\Controllers\Api\AdminListingsApiController@destroy');

// Admin Categories
$router->add('GET', '/api/v2/admin/categories', 'Nexus\Controllers\Api\AdminCategoriesApiController@index');
$router->add('POST', '/api/v2/admin/categories', 'Nexus\Controllers\Api\AdminCategoriesApiController@store');
$router->add('PUT', '/api/v2/admin/categories/{id}', 'Nexus\Controllers\Api\AdminCategoriesApiController@update');
$router->add('DELETE', '/api/v2/admin/categories/{id}', 'Nexus\Controllers\Api\AdminCategoriesApiController@destroy');

// Admin Attributes
$router->add('GET', '/api/v2/admin/attributes', 'Nexus\Controllers\Api\AdminCategoriesApiController@listAttributes');
$router->add('POST', '/api/v2/admin/attributes', 'Nexus\Controllers\Api\AdminCategoriesApiController@storeAttribute');
$router->add('PUT', '/api/v2/admin/attributes/{id}', 'Nexus\Controllers\Api\AdminCategoriesApiController@updateAttribute');
$router->add('DELETE', '/api/v2/admin/attributes/{id}', 'Nexus\Controllers\Api\AdminCategoriesApiController@destroyAttribute');

// Admin Config (Features & Modules)
$router->add('GET', '/api/v2/admin/config', 'Nexus\Controllers\Api\AdminConfigApiController@getConfig');
$router->add('PUT', '/api/v2/admin/config/features', 'Nexus\Controllers\Api\AdminConfigApiController@updateFeature');
$router->add('PUT', '/api/v2/admin/config/modules', 'Nexus\Controllers\Api\AdminConfigApiController@updateModule');

// Admin Cache
$router->add('GET', '/api/v2/admin/cache/stats', 'Nexus\Controllers\Api\AdminConfigApiController@cacheStats');
$router->add('POST', '/api/v2/admin/cache/clear', 'Nexus\Controllers\Api\AdminConfigApiController@clearCache');

// Admin Background Jobs
$router->add('GET', '/api/v2/admin/jobs', 'Nexus\Controllers\Api\AdminConfigApiController@getJobs');
$router->add('POST', '/api/v2/admin/jobs/{id}/run', 'Nexus\Controllers\Api\AdminConfigApiController@runJob');

// Admin Settings (General Tenant Settings)
$router->add('GET', '/api/v2/admin/settings', 'Nexus\Controllers\Api\AdminConfigApiController@getSettings');
$router->add('PUT', '/api/v2/admin/settings', 'Nexus\Controllers\Api\AdminConfigApiController@updateSettings');

// Admin Config - AI
$router->add('GET', '/api/v2/admin/config/ai', 'Nexus\Controllers\Api\AdminConfigApiController@getAiConfig');
$router->add('PUT', '/api/v2/admin/config/ai', 'Nexus\Controllers\Api\AdminConfigApiController@updateAiConfig');

// Admin Config - Feed Algorithm
$router->add('GET', '/api/v2/admin/config/feed-algorithm', 'Nexus\Controllers\Api\AdminConfigApiController@getFeedAlgorithmConfig');
$router->add('PUT', '/api/v2/admin/config/feed-algorithm', 'Nexus\Controllers\Api\AdminConfigApiController@updateFeedAlgorithmConfig');

// Admin Config - Images
$router->add('GET', '/api/v2/admin/config/images', 'Nexus\Controllers\Api\AdminConfigApiController@getImageConfig');
$router->add('PUT', '/api/v2/admin/config/images', 'Nexus\Controllers\Api\AdminConfigApiController@updateImageConfig');

// Admin Config - SEO
$router->add('GET', '/api/v2/admin/config/seo', 'Nexus\Controllers\Api\AdminConfigApiController@getSeoConfig');
$router->add('PUT', '/api/v2/admin/config/seo', 'Nexus\Controllers\Api\AdminConfigApiController@updateSeoConfig');

// Admin Config - Native App / PWA
$router->add('GET', '/api/v2/admin/config/native-app', 'Nexus\Controllers\Api\AdminConfigApiController@getNativeAppConfig');
$router->add('PUT', '/api/v2/admin/config/native-app', 'Nexus\Controllers\Api\AdminConfigApiController@updateNativeAppConfig');

// Admin System - Cron Jobs
$router->add('GET', '/api/v2/admin/system/cron-jobs', 'Nexus\Controllers\Api\AdminConfigApiController@getCronJobs');
$router->add('POST', '/api/v2/admin/system/cron-jobs/{id}/run', 'Nexus\Controllers\Api\AdminConfigApiController@runCronJob');

// Admin System - Cron Jobs - Logs
$router->add('GET', '/api/v2/admin/system/cron-jobs/logs', 'Nexus\Controllers\Api\AdminCronApiController@getLogs');
$router->add('GET', '/api/v2/admin/system/cron-jobs/logs/{id}', 'Nexus\Controllers\Api\AdminCronApiController@getLogDetail');
$router->add('DELETE', '/api/v2/admin/system/cron-jobs/logs', 'Nexus\Controllers\Api\AdminCronApiController@clearLogs');

// Admin System - Cron Jobs - Settings
$router->add('GET', '/api/v2/admin/system/cron-jobs/{jobId}/settings', 'Nexus\Controllers\Api\AdminCronApiController@getJobSettings');
$router->add('PUT', '/api/v2/admin/system/cron-jobs/{jobId}/settings', 'Nexus\Controllers\Api\AdminCronApiController@updateJobSettings');
$router->add('GET', '/api/v2/admin/system/cron-jobs/settings', 'Nexus\Controllers\Api\AdminCronApiController@getGlobalSettings');
$router->add('PUT', '/api/v2/admin/system/cron-jobs/settings', 'Nexus\Controllers\Api\AdminCronApiController@updateGlobalSettings');

// Admin System - Cron Jobs - Health
$router->add('GET', '/api/v2/admin/system/cron-jobs/health', 'Nexus\Controllers\Api\AdminCronApiController@getHealthMetrics');

// Admin System - Activity Log
$router->add('GET', '/api/v2/admin/system/activity-log', 'Nexus\Controllers\Api\AdminDashboardApiController@activity');

// Admin System - Email
$router->add('GET', '/api/v2/admin/email/status', 'Nexus\Controllers\Api\EmailAdminApiController@status');
$router->add('POST', '/api/v2/admin/email/test', 'Nexus\Controllers\Api\EmailAdminApiController@test');
$router->add('POST', '/api/v2/admin/email/test-gmail', 'Nexus\Controllers\Api\EmailAdminApiController@testGmail');

// Admin Matching - Config, Stats, Cache
$router->add('GET', '/api/v2/admin/matching/config', 'Nexus\Controllers\Api\AdminMatchingApiController@getConfig');
$router->add('PUT', '/api/v2/admin/matching/config', 'Nexus\Controllers\Api\AdminMatchingApiController@updateConfig');
$router->add('POST', '/api/v2/admin/matching/cache/clear', 'Nexus\Controllers\Api\AdminMatchingApiController@clearCache');
$router->add('GET', '/api/v2/admin/matching/stats', 'Nexus\Controllers\Api\AdminMatchingApiController@getStats');

// Admin Matching - Approvals
$router->add('GET', '/api/v2/admin/matching/approvals', 'Nexus\Controllers\Api\AdminMatchingApiController@index');
$router->add('GET', '/api/v2/admin/matching/approvals/stats', 'Nexus\Controllers\Api\AdminMatchingApiController@approvalStats');
$router->add('GET', '/api/v2/admin/matching/approvals/{id}', 'Nexus\Controllers\Api\AdminMatchingApiController@show');
$router->add('POST', '/api/v2/admin/matching/approvals/{id}/approve', 'Nexus\Controllers\Api\AdminMatchingApiController@approve');
$router->add('POST', '/api/v2/admin/matching/approvals/{id}/reject', 'Nexus\Controllers\Api\AdminMatchingApiController@reject');

// Admin Blog
$router->add('GET', '/api/v2/admin/blog', 'Nexus\Controllers\Api\AdminBlogApiController@index');
$router->add('POST', '/api/v2/admin/blog', 'Nexus\Controllers\Api\AdminBlogApiController@store');
$router->add('GET', '/api/v2/admin/blog/{id}', 'Nexus\Controllers\Api\AdminBlogApiController@show');
$router->add('PUT', '/api/v2/admin/blog/{id}', 'Nexus\Controllers\Api\AdminBlogApiController@update');
$router->add('DELETE', '/api/v2/admin/blog/{id}', 'Nexus\Controllers\Api\AdminBlogApiController@destroy');
$router->add('POST', '/api/v2/admin/blog/{id}/toggle-status', 'Nexus\Controllers\Api\AdminBlogApiController@toggleStatus');

// Admin Content Moderation - Feed Posts
$router->add('GET', '/api/v2/admin/feed/posts', 'Nexus\Controllers\Api\AdminFeedApiController@index');
$router->add('GET', '/api/v2/admin/feed/posts/{id}', 'Nexus\Controllers\Api\AdminFeedApiController@show');
$router->add('POST', '/api/v2/admin/feed/posts/{id}/hide', 'Nexus\Controllers\Api\AdminFeedApiController@hide');
$router->add('DELETE', '/api/v2/admin/feed/posts/{id}', 'Nexus\Controllers\Api\AdminFeedApiController@destroy');
$router->add('GET', '/api/v2/admin/feed/stats', 'Nexus\Controllers\Api\AdminFeedApiController@stats');

// Admin Content Moderation - Comments
$router->add('GET', '/api/v2/admin/comments', 'Nexus\Controllers\Api\AdminCommentsApiController@index');
$router->add('GET', '/api/v2/admin/comments/{id}', 'Nexus\Controllers\Api\AdminCommentsApiController@show');
$router->add('POST', '/api/v2/admin/comments/{id}/hide', 'Nexus\Controllers\Api\AdminCommentsApiController@hide');
$router->add('DELETE', '/api/v2/admin/comments/{id}', 'Nexus\Controllers\Api\AdminCommentsApiController@destroy');

// Admin Content Moderation - Reviews
$router->add('GET', '/api/v2/admin/reviews', 'Nexus\Controllers\Api\AdminReviewsApiController@index');
$router->add('GET', '/api/v2/admin/reviews/{id}', 'Nexus\Controllers\Api\AdminReviewsApiController@show');
$router->add('POST', '/api/v2/admin/reviews/{id}/flag', 'Nexus\Controllers\Api\AdminReviewsApiController@flag');
$router->add('POST', '/api/v2/admin/reviews/{id}/hide', 'Nexus\Controllers\Api\AdminReviewsApiController@hide');
$router->add('DELETE', '/api/v2/admin/reviews/{id}', 'Nexus\Controllers\Api\AdminReviewsApiController@destroy');

// Admin Content Moderation - Reports
$router->add('GET', '/api/v2/admin/reports', 'Nexus\Controllers\Api\AdminReportsApiController@index');
$router->add('GET', '/api/v2/admin/reports/stats', 'Nexus\Controllers\Api\AdminReportsApiController@stats');
$router->add('GET', '/api/v2/admin/reports/{id}', 'Nexus\Controllers\Api\AdminReportsApiController@show');
$router->add('POST', '/api/v2/admin/reports/{id}/resolve', 'Nexus\Controllers\Api\AdminReportsApiController@resolve');
$router->add('POST', '/api/v2/admin/reports/{id}/dismiss', 'Nexus\Controllers\Api\AdminReportsApiController@dismiss');

// Admin Gamification
$router->add('GET', '/api/v2/admin/gamification/stats', 'Nexus\Controllers\Api\AdminGamificationApiController@stats');
$router->add('GET', '/api/v2/admin/gamification/badges', 'Nexus\Controllers\Api\AdminGamificationApiController@badges');
$router->add('POST', '/api/v2/admin/gamification/badges', 'Nexus\Controllers\Api\AdminGamificationApiController@createBadge');
$router->add('DELETE', '/api/v2/admin/gamification/badges/{id}', 'Nexus\Controllers\Api\AdminGamificationApiController@deleteBadge');
$router->add('GET', '/api/v2/admin/gamification/campaigns', 'Nexus\Controllers\Api\AdminGamificationApiController@campaigns');
$router->add('POST', '/api/v2/admin/gamification/campaigns', 'Nexus\Controllers\Api\AdminGamificationApiController@createCampaign');
$router->add('PUT', '/api/v2/admin/gamification/campaigns/{id}', 'Nexus\Controllers\Api\AdminGamificationApiController@updateCampaign');
$router->add('DELETE', '/api/v2/admin/gamification/campaigns/{id}', 'Nexus\Controllers\Api\AdminGamificationApiController@deleteCampaign');
$router->add('POST', '/api/v2/admin/gamification/recheck-all', 'Nexus\Controllers\Api\AdminGamificationApiController@recheckAll');
$router->add('POST', '/api/v2/admin/gamification/bulk-award', 'Nexus\Controllers\Api\AdminGamificationApiController@bulkAward');

// Admin Groups
$router->add('GET', '/api/v2/admin/groups', 'Nexus\Controllers\Api\AdminGroupsApiController@index');
$router->add('GET', '/api/v2/admin/groups/analytics', 'Nexus\Controllers\Api\AdminGroupsApiController@analytics');
$router->add('GET', '/api/v2/admin/groups/approvals', 'Nexus\Controllers\Api\AdminGroupsApiController@approvals');
$router->add('POST', '/api/v2/admin/groups/approvals/{id}/approve', 'Nexus\Controllers\Api\AdminGroupsApiController@approveMember');
$router->add('POST', '/api/v2/admin/groups/approvals/{id}/reject', 'Nexus\Controllers\Api\AdminGroupsApiController@rejectMember');
$router->add('GET', '/api/v2/admin/groups/moderation', 'Nexus\Controllers\Api\AdminGroupsApiController@moderation');
$router->add('PUT', '/api/v2/admin/groups/{id}/status', 'Nexus\Controllers\Api\AdminGroupsApiController@updateStatus');
$router->add('DELETE', '/api/v2/admin/groups/{id}', 'Nexus\Controllers\Api\AdminGroupsApiController@deleteGroup');

// Admin Groups - Types & Policies (Phase 3)
$router->add('GET', '/api/v2/admin/groups/types', 'Nexus\Controllers\Api\AdminGroupsApiController@getGroupTypes');
$router->add('POST', '/api/v2/admin/groups/types', 'Nexus\Controllers\Api\AdminGroupsApiController@createGroupType');
$router->add('PUT', '/api/v2/admin/groups/types/{id}', 'Nexus\Controllers\Api\AdminGroupsApiController@updateGroupType');
$router->add('DELETE', '/api/v2/admin/groups/types/{id}', 'Nexus\Controllers\Api\AdminGroupsApiController@deleteGroupType');
$router->add('GET', '/api/v2/admin/groups/types/{id}/policies', 'Nexus\Controllers\Api\AdminGroupsApiController@getPolicies');
$router->add('PUT', '/api/v2/admin/groups/types/{id}/policies', 'Nexus\Controllers\Api\AdminGroupsApiController@setPolicy');

// Admin Groups - Detail & Members (Phase 3)
$router->add('GET', '/api/v2/admin/groups/{id}', 'Nexus\Controllers\Api\AdminGroupsApiController@getGroup');
$router->add('PUT', '/api/v2/admin/groups/{id}', 'Nexus\Controllers\Api\AdminGroupsApiController@updateGroup');
$router->add('GET', '/api/v2/admin/groups/{groupId}/members', 'Nexus\Controllers\Api\AdminGroupsApiController@getMembers');
$router->add('POST', '/api/v2/admin/groups/{groupId}/members/{userId}/promote', 'Nexus\Controllers\Api\AdminGroupsApiController@promoteMember');
$router->add('POST', '/api/v2/admin/groups/{groupId}/members/{userId}/demote', 'Nexus\Controllers\Api\AdminGroupsApiController@demoteMember');
$router->add('DELETE', '/api/v2/admin/groups/{groupId}/members/{userId}', 'Nexus\Controllers\Api\AdminGroupsApiController@kickMember');

// Admin Groups - Geocoding (Phase 3)
$router->add('POST', '/api/v2/admin/groups/{id}/geocode', 'Nexus\Controllers\Api\AdminGroupsApiController@geocodeGroup');
$router->add('POST', '/api/v2/admin/groups/batch-geocode', 'Nexus\Controllers\Api\AdminGroupsApiController@batchGeocode');

// Admin Groups - Recommendations & Ranking (Phase 3)
$router->add('GET', '/api/v2/admin/groups/recommendations', 'Nexus\Controllers\Api\AdminGroupsApiController@getRecommendationData');
$router->add('GET', '/api/v2/admin/groups/featured', 'Nexus\Controllers\Api\AdminGroupsApiController@getFeaturedGroups');
$router->add('POST', '/api/v2/admin/groups/featured/update', 'Nexus\Controllers\Api\AdminGroupsApiController@updateFeaturedGroups');
$router->add('PUT', '/api/v2/admin/groups/{id}/toggle-featured', 'Nexus\Controllers\Api\AdminGroupsApiController@toggleFeatured');

// Admin Timebanking
$router->add('GET', '/api/v2/admin/timebanking/stats', 'Nexus\Controllers\Api\AdminTimebankingApiController@stats');
$router->add('GET', '/api/v2/admin/timebanking/alerts', 'Nexus\Controllers\Api\AdminTimebankingApiController@alerts');
$router->add('PUT', '/api/v2/admin/timebanking/alerts/{id}', 'Nexus\Controllers\Api\AdminTimebankingApiController@updateAlert');
$router->add('POST', '/api/v2/admin/timebanking/adjust-balance', 'Nexus\Controllers\Api\AdminTimebankingApiController@adjustBalance');
$router->add('GET', '/api/v2/admin/timebanking/org-wallets', 'Nexus\Controllers\Api\AdminTimebankingApiController@orgWallets');
$router->add('GET', '/api/v2/admin/timebanking/user-report', 'Nexus\Controllers\Api\AdminTimebankingApiController@userReport');
$router->add('GET', '/api/v2/admin/timebanking/user-statement', 'Nexus\Controllers\Api\AdminTimebankingApiController@userStatement');

// Admin Enterprise
$router->add('GET', '/api/v2/admin/enterprise/dashboard', 'Nexus\Controllers\Api\AdminEnterpriseApiController@dashboard');
$router->add('GET', '/api/v2/admin/enterprise/roles', 'Nexus\Controllers\Api\AdminEnterpriseApiController@roles');
$router->add('POST', '/api/v2/admin/enterprise/roles', 'Nexus\Controllers\Api\AdminEnterpriseApiController@createRole');
$router->add('GET', '/api/v2/admin/enterprise/roles/{id}', 'Nexus\Controllers\Api\AdminEnterpriseApiController@showRole');
$router->add('PUT', '/api/v2/admin/enterprise/roles/{id}', 'Nexus\Controllers\Api\AdminEnterpriseApiController@updateRole');
$router->add('DELETE', '/api/v2/admin/enterprise/roles/{id}', 'Nexus\Controllers\Api\AdminEnterpriseApiController@deleteRole');
$router->add('GET', '/api/v2/admin/enterprise/permissions', 'Nexus\Controllers\Api\AdminEnterpriseApiController@permissions');
$router->add('GET', '/api/v2/admin/enterprise/gdpr/dashboard', 'Nexus\Controllers\Api\AdminEnterpriseApiController@gdprDashboard');
$router->add('GET', '/api/v2/admin/enterprise/gdpr/requests', 'Nexus\Controllers\Api\AdminEnterpriseApiController@gdprRequests');
$router->add('PUT', '/api/v2/admin/enterprise/gdpr/requests/{id}', 'Nexus\Controllers\Api\AdminEnterpriseApiController@updateGdprRequest');
$router->add('GET', '/api/v2/admin/enterprise/gdpr/consents', 'Nexus\Controllers\Api\AdminEnterpriseApiController@gdprConsents');
$router->add('GET', '/api/v2/admin/enterprise/gdpr/breaches', 'Nexus\Controllers\Api\AdminEnterpriseApiController@gdprBreaches');
$router->add('POST', '/api/v2/admin/enterprise/gdpr/breaches', 'Nexus\Controllers\Api\AdminEnterpriseApiController@createBreach');
$router->add('GET', '/api/v2/admin/enterprise/gdpr/audit', 'Nexus\Controllers\Api\AdminEnterpriseApiController@gdprAudit');
$router->add('GET', '/api/v2/admin/enterprise/monitoring', 'Nexus\Controllers\Api\AdminEnterpriseApiController@monitoring');
$router->add('GET', '/api/v2/admin/enterprise/monitoring/health', 'Nexus\Controllers\Api\AdminEnterpriseApiController@healthCheck');
$router->add('GET', '/api/v2/admin/enterprise/monitoring/logs', 'Nexus\Controllers\Api\AdminEnterpriseApiController@logs');
$router->add('GET', '/api/v2/admin/enterprise/config', 'Nexus\Controllers\Api\AdminEnterpriseApiController@config');
$router->add('PUT', '/api/v2/admin/enterprise/config', 'Nexus\Controllers\Api\AdminEnterpriseApiController@updateConfig');
$router->add('GET', '/api/v2/admin/enterprise/config/secrets', 'Nexus\Controllers\Api\AdminEnterpriseApiController@secrets');
$router->add('GET', '/api/v2/admin/legal-documents', 'Nexus\Controllers\Api\AdminEnterpriseApiController@legalDocs');
$router->add('POST', '/api/v2/admin/legal-documents', 'Nexus\Controllers\Api\AdminEnterpriseApiController@createLegalDoc');
$router->add('GET', '/api/v2/admin/legal-documents/{id}', 'Nexus\Controllers\Api\AdminEnterpriseApiController@showLegalDoc');
$router->add('PUT', '/api/v2/admin/legal-documents/{id}', 'Nexus\Controllers\Api\AdminEnterpriseApiController@updateLegalDoc');
$router->add('DELETE', '/api/v2/admin/legal-documents/{id}', 'Nexus\Controllers\Api\AdminEnterpriseApiController@deleteLegalDoc');

// Legal Document Version Management
$router->add('GET', '/api/v2/admin/legal-documents/{docId}/versions', 'Nexus\Controllers\Api\AdminLegalDocController@getVersions');
$router->add('GET', '/api/v2/admin/legal-documents/{docId}/versions/compare', 'Nexus\Controllers\Api\AdminLegalDocController@compareVersions');
$router->add('POST', '/api/v2/admin/legal-documents/{docId}/versions', 'Nexus\Controllers\Api\AdminLegalDocController@createVersion');
$router->add('POST', '/api/v2/admin/legal-documents/versions/{versionId}/publish', 'Nexus\Controllers\Api\AdminLegalDocController@publishVersion');
$router->add('GET', '/api/v2/admin/legal-documents/compliance', 'Nexus\Controllers\Api\AdminLegalDocController@getComplianceStats');
$router->add('GET', '/api/v2/admin/legal-documents/versions/{versionId}/acceptances', 'Nexus\Controllers\Api\AdminLegalDocController@getAcceptances');
$router->add('GET', '/api/v2/admin/legal-documents/{docId}/acceptances/export', 'Nexus\Controllers\Api\AdminLegalDocController@exportAcceptances');
$router->add('POST', '/api/v2/admin/legal-documents/{docId}/versions/{versionId}/notify', 'Nexus\Controllers\Api\AdminLegalDocController@notifyUsers');
$router->add('GET', '/api/v2/admin/legal-documents/{docId}/versions/{versionId}/pending-count', 'Nexus\Controllers\Api\AdminLegalDocController@getUsersPendingCount');

// Admin Broker Controls
$router->add('GET', '/api/v2/admin/broker/dashboard', 'Nexus\Controllers\Api\AdminBrokerApiController@dashboard');
$router->add('GET', '/api/v2/admin/broker/exchanges', 'Nexus\Controllers\Api\AdminBrokerApiController@exchanges');
$router->add('POST', '/api/v2/admin/broker/exchanges/{id}/approve', 'Nexus\Controllers\Api\AdminBrokerApiController@approveExchange');
$router->add('POST', '/api/v2/admin/broker/exchanges/{id}/reject', 'Nexus\Controllers\Api\AdminBrokerApiController@rejectExchange');
$router->add('GET', '/api/v2/admin/broker/risk-tags', 'Nexus\Controllers\Api\AdminBrokerApiController@riskTags');
$router->add('GET', '/api/v2/admin/broker/messages', 'Nexus\Controllers\Api\AdminBrokerApiController@messages');
$router->add('POST', '/api/v2/admin/broker/messages/{id}/review', 'Nexus\Controllers\Api\AdminBrokerApiController@reviewMessage');
$router->add('GET', '/api/v2/admin/broker/monitoring', 'Nexus\Controllers\Api\AdminBrokerApiController@monitoring');
$router->add('POST', '/api/v2/admin/broker/messages/{id}/flag', 'Nexus\Controllers\Api\AdminBrokerApiController@flagMessage');
$router->add('POST', '/api/v2/admin/broker/monitoring/{userId}', 'Nexus\Controllers\Api\AdminBrokerApiController@setMonitoring');
$router->add('POST', '/api/v2/admin/broker/risk-tags/{listingId}', 'Nexus\Controllers\Api\AdminBrokerApiController@saveRiskTag');
$router->add('DELETE', '/api/v2/admin/broker/risk-tags/{listingId}', 'Nexus\Controllers\Api\AdminBrokerApiController@removeRiskTag');
$router->add('GET', '/api/v2/admin/broker/configuration', 'Nexus\Controllers\Api\AdminBrokerApiController@getConfiguration');
$router->add('POST', '/api/v2/admin/broker/configuration', 'Nexus\Controllers\Api\AdminBrokerApiController@saveConfiguration');
$router->add('GET', '/api/v2/admin/broker/exchanges/{id}', 'Nexus\Controllers\Api\AdminBrokerApiController@showExchange');
$router->add('GET', '/api/v2/admin/broker/messages/{id}', 'Nexus\Controllers\Api\AdminBrokerApiController@showMessage');
$router->add('POST', '/api/v2/admin/broker/messages/{id}/approve', 'Nexus\Controllers\Api\AdminBrokerApiController@approveMessage');
$router->add('GET', '/api/v2/admin/broker/archives', 'Nexus\Controllers\Api\AdminBrokerApiController@archives');
$router->add('GET', '/api/v2/admin/broker/archives/{id}', 'Nexus\Controllers\Api\AdminBrokerApiController@showArchive');

// Admin Vetting Records (TOL2 compliance — DBS/Garda vetting)
$router->add('GET', '/api/v2/admin/vetting/stats', 'Nexus\Controllers\Api\AdminVettingApiController@stats');
$router->add('GET', '/api/v2/admin/vetting/user/{userId}', 'Nexus\Controllers\Api\AdminVettingApiController@getUserRecords');
$router->add('GET', '/api/v2/admin/vetting', 'Nexus\Controllers\Api\AdminVettingApiController@list');
$router->add('GET', '/api/v2/admin/vetting/{id}', 'Nexus\Controllers\Api\AdminVettingApiController@show');
$router->add('POST', '/api/v2/admin/vetting', 'Nexus\Controllers\Api\AdminVettingApiController@store');
$router->add('PUT', '/api/v2/admin/vetting/{id}', 'Nexus\Controllers\Api\AdminVettingApiController@update');
$router->add('POST', '/api/v2/admin/vetting/{id}/verify', 'Nexus\Controllers\Api\AdminVettingApiController@verify');
$router->add('POST', '/api/v2/admin/vetting/{id}/reject', 'Nexus\Controllers\Api\AdminVettingApiController@reject');
$router->add('DELETE', '/api/v2/admin/vetting/{id}', 'Nexus\Controllers\Api\AdminVettingApiController@destroy');

// Admin Newsletters
$router->add('GET', '/api/v2/admin/newsletters', 'Nexus\Controllers\Api\AdminNewsletterApiController@index');
$router->add('POST', '/api/v2/admin/newsletters', 'Nexus\Controllers\Api\AdminNewsletterApiController@store');
$router->add('GET', '/api/v2/admin/newsletters/subscribers', 'Nexus\Controllers\Api\AdminNewsletterApiController@subscribers');
$router->add('GET', '/api/v2/admin/newsletters/segments', 'Nexus\Controllers\Api\AdminNewsletterApiController@segments');
$router->add('GET', '/api/v2/admin/newsletters/templates', 'Nexus\Controllers\Api\AdminNewsletterApiController@templates');
$router->add('GET', '/api/v2/admin/newsletters/analytics', 'Nexus\Controllers\Api\AdminNewsletterApiController@analytics');
$router->add('GET', '/api/v2/admin/newsletters/bounces', 'Nexus\Controllers\Api\AdminNewsletterApiController@getBounces');
$router->add('GET', '/api/v2/admin/newsletters/suppression-list', 'Nexus\Controllers\Api\AdminNewsletterApiController@getSuppressionList');
$router->add('POST', '/api/v2/admin/newsletters/suppression-list/{email}/unsuppress', 'Nexus\Controllers\Api\AdminNewsletterApiController@unsuppress');
$router->add('POST', '/api/v2/admin/newsletters/suppression-list/{email}/suppress', 'Nexus\Controllers\Api\AdminNewsletterApiController@suppress');
$router->add('GET', '/api/v2/admin/newsletters/send-time-optimizer', 'Nexus\Controllers\Api\AdminNewsletterApiController@getSendTimeData');
$router->add('GET', '/api/v2/admin/newsletters/diagnostics', 'Nexus\Controllers\Api\AdminNewsletterApiController@getDiagnostics');
$router->add('GET', '/api/v2/admin/newsletters/{id}', 'Nexus\Controllers\Api\AdminNewsletterApiController@show');
$router->add('GET', '/api/v2/admin/newsletters/{id}/resend-info', 'Nexus\Controllers\Api\AdminNewsletterApiController@getResendInfo');
$router->add('POST', '/api/v2/admin/newsletters/{id}/resend', 'Nexus\Controllers\Api\AdminNewsletterApiController@resend');
$router->add('PUT', '/api/v2/admin/newsletters/{id}', 'Nexus\Controllers\Api\AdminNewsletterApiController@update');
$router->add('DELETE', '/api/v2/admin/newsletters/{id}', 'Nexus\Controllers\Api\AdminNewsletterApiController@destroy');

// Admin Volunteering
$router->add('GET', '/api/v2/admin/volunteering', 'Nexus\Controllers\Api\AdminVolunteeringApiController@index');
$router->add('GET', '/api/v2/admin/volunteering/approvals', 'Nexus\Controllers\Api\AdminVolunteeringApiController@approvals');
$router->add('GET', '/api/v2/admin/volunteering/organizations', 'Nexus\Controllers\Api\AdminVolunteeringApiController@organizations');
$router->add('POST', '/api/v2/admin/volunteering/approvals/{id}/approve', 'Nexus\Controllers\Api\AdminVolunteeringApiController@approveApplication');
$router->add('POST', '/api/v2/admin/volunteering/approvals/{id}/decline', 'Nexus\Controllers\Api\AdminVolunteeringApiController@declineApplication');

// Admin Federation
$router->add('GET', '/api/v2/admin/federation/settings', 'Nexus\Controllers\Api\AdminFederationApiController@settings');
$router->add('PUT', '/api/v2/admin/federation/settings', 'Nexus\Controllers\Api\AdminFederationApiController@updateSettings');
$router->add('GET', '/api/v2/admin/federation/partnerships', 'Nexus\Controllers\Api\AdminFederationApiController@partnerships');
$router->add('POST', '/api/v2/admin/federation/partnerships/{id}/approve', 'Nexus\Controllers\Api\AdminFederationApiController@approvePartnership');
$router->add('POST', '/api/v2/admin/federation/partnerships/{id}/reject', 'Nexus\Controllers\Api\AdminFederationApiController@rejectPartnership');
$router->add('POST', '/api/v2/admin/federation/partnerships/{id}/terminate', 'Nexus\Controllers\Api\AdminFederationApiController@terminatePartnership');
$router->add('GET', '/api/v2/admin/federation/directory', 'Nexus\Controllers\Api\AdminFederationApiController@directory');
$router->add('GET', '/api/v2/admin/federation/directory/profile', 'Nexus\Controllers\Api\AdminFederationApiController@profile');
$router->add('PUT', '/api/v2/admin/federation/directory/profile', 'Nexus\Controllers\Api\AdminFederationApiController@updateProfile');
$router->add('GET', '/api/v2/admin/federation/analytics', 'Nexus\Controllers\Api\AdminFederationApiController@analytics');
$router->add('GET', '/api/v2/admin/federation/api-keys', 'Nexus\Controllers\Api\AdminFederationApiController@apiKeys');
$router->add('POST', '/api/v2/admin/federation/api-keys', 'Nexus\Controllers\Api\AdminFederationApiController@createApiKey');
$router->add('GET', '/api/v2/admin/federation/data', 'Nexus\Controllers\Api\AdminFederationApiController@dataManagement');

// Federation V2 (user-facing, for React frontend)
$router->add('GET', '/api/v2/federation/status', 'Nexus\Controllers\Api\FederationV2ApiController@status');
$router->add('POST', '/api/v2/federation/opt-in', 'Nexus\Controllers\Api\FederationV2ApiController@optIn');
$router->add('POST', '/api/v2/federation/opt-out', 'Nexus\Controllers\Api\FederationV2ApiController@optOut');
$router->add('GET', '/api/v2/federation/partners', 'Nexus\Controllers\Api\FederationV2ApiController@partners');
$router->add('GET', '/api/v2/federation/activity', 'Nexus\Controllers\Api\FederationV2ApiController@activity');
$router->add('GET', '/api/v2/federation/events', 'Nexus\Controllers\Api\FederationV2ApiController@events');
$router->add('GET', '/api/v2/federation/listings', 'Nexus\Controllers\Api\FederationV2ApiController@listings');
$router->add('GET', '/api/v2/federation/members', 'Nexus\Controllers\Api\FederationV2ApiController@members');
$router->add('GET', '/api/v2/federation/members/{id}', 'Nexus\Controllers\Api\FederationV2ApiController@member');
$router->add('GET', '/api/v2/federation/messages', 'Nexus\Controllers\Api\FederationV2ApiController@messages');
$router->add('POST', '/api/v2/federation/messages', 'Nexus\Controllers\Api\FederationV2ApiController@sendMessage');
$router->add('POST', '/api/v2/federation/messages/{id}/mark-read', 'Nexus\Controllers\Api\FederationV2ApiController@markMessageRead');
$router->add('GET', '/api/v2/federation/settings', 'Nexus\Controllers\Api\FederationV2ApiController@getSettings');
$router->add('PUT', '/api/v2/federation/settings', 'Nexus\Controllers\Api\FederationV2ApiController@updateSettings');

// Admin Pages
$router->add('GET', '/api/v2/admin/pages', 'Nexus\Controllers\Api\AdminContentApiController@getPages');
$router->add('POST', '/api/v2/admin/pages', 'Nexus\Controllers\Api\AdminContentApiController@createPage');
$router->add('GET', '/api/v2/admin/pages/{id}', 'Nexus\Controllers\Api\AdminContentApiController@getPage');
$router->add('PUT', '/api/v2/admin/pages/{id}', 'Nexus\Controllers\Api\AdminContentApiController@updatePage');
$router->add('DELETE', '/api/v2/admin/pages/{id}', 'Nexus\Controllers\Api\AdminContentApiController@deletePage');

// Admin Menus
$router->add('GET', '/api/v2/admin/menus', 'Nexus\Controllers\Api\AdminContentApiController@getMenus');
$router->add('POST', '/api/v2/admin/menus', 'Nexus\Controllers\Api\AdminContentApiController@createMenu');
$router->add('GET', '/api/v2/admin/menus/{id}', 'Nexus\Controllers\Api\AdminContentApiController@getMenu');
$router->add('PUT', '/api/v2/admin/menus/{id}', 'Nexus\Controllers\Api\AdminContentApiController@updateMenu');
$router->add('DELETE', '/api/v2/admin/menus/{id}', 'Nexus\Controllers\Api\AdminContentApiController@deleteMenu');
$router->add('GET', '/api/v2/admin/menus/{id}/items', 'Nexus\Controllers\Api\AdminContentApiController@getMenuItems');
$router->add('POST', '/api/v2/admin/menus/{id}/items', 'Nexus\Controllers\Api\AdminContentApiController@createMenuItem');
$router->add('POST', '/api/v2/admin/menus/{id}/items/reorder', 'Nexus\Controllers\Api\AdminContentApiController@reorderMenuItems');

// Admin Menu Items (direct item operations)
$router->add('PUT', '/api/v2/admin/menu-items/{id}', 'Nexus\Controllers\Api\AdminContentApiController@updateMenuItem');
$router->add('DELETE', '/api/v2/admin/menu-items/{id}', 'Nexus\Controllers\Api\AdminContentApiController@deleteMenuItem');

// Admin Plans & Subscriptions
$router->add('GET', '/api/v2/admin/plans', 'Nexus\Controllers\Api\AdminContentApiController@getPlans');
$router->add('POST', '/api/v2/admin/plans', 'Nexus\Controllers\Api\AdminContentApiController@createPlan');
$router->add('GET', '/api/v2/admin/plans/{id}', 'Nexus\Controllers\Api\AdminContentApiController@getPlan');
$router->add('PUT', '/api/v2/admin/plans/{id}', 'Nexus\Controllers\Api\AdminContentApiController@updatePlan');
$router->add('DELETE', '/api/v2/admin/plans/{id}', 'Nexus\Controllers\Api\AdminContentApiController@deletePlan');
$router->add('GET', '/api/v2/admin/subscriptions', 'Nexus\Controllers\Api\AdminContentApiController@getSubscriptions');

// Admin Tools - SEO & Redirects
$router->add('GET', '/api/v2/admin/tools/redirects', 'Nexus\Controllers\Api\AdminToolsApiController@getRedirects');
$router->add('POST', '/api/v2/admin/tools/redirects', 'Nexus\Controllers\Api\AdminToolsApiController@createRedirect');
$router->add('DELETE', '/api/v2/admin/tools/redirects/{id}', 'Nexus\Controllers\Api\AdminToolsApiController@deleteRedirect');

// Admin Tools - 404 Error Tracking
$router->add('GET', '/api/v2/admin/tools/404-errors', 'Nexus\Controllers\Api\AdminToolsApiController@get404Errors');
$router->add('DELETE', '/api/v2/admin/tools/404-errors/{id}', 'Nexus\Controllers\Api\AdminToolsApiController@delete404Error');

// Admin Tools - Health Check, WebP, Seed, Blog Backups
$router->add('POST', '/api/v2/admin/tools/health-check', 'Nexus\Controllers\Api\AdminToolsApiController@runHealthCheck');
$router->add('GET', '/api/v2/admin/tools/webp-stats', 'Nexus\Controllers\Api\AdminToolsApiController@getWebpStats');
$router->add('POST', '/api/v2/admin/tools/webp-convert', 'Nexus\Controllers\Api\AdminToolsApiController@runWebpConversion');
$router->add('POST', '/api/v2/admin/tools/seed', 'Nexus\Controllers\Api\AdminToolsApiController@runSeedGenerator');
$router->add('GET', '/api/v2/admin/tools/blog-backups', 'Nexus\Controllers\Api\AdminToolsApiController@getBlogBackups');
$router->add('POST', '/api/v2/admin/tools/blog-backups/{id}/restore', 'Nexus\Controllers\Api\AdminToolsApiController@restoreBlogBackup');

// Admin Tools - SEO Audit
$router->add('GET', '/api/v2/admin/tools/seo-audit', 'Nexus\Controllers\Api\AdminToolsApiController@getSeoAudit');
$router->add('POST', '/api/v2/admin/tools/seo-audit', 'Nexus\Controllers\Api\AdminToolsApiController@runSeoAudit');

// Admin Deliverability
$router->add('GET', '/api/v2/admin/deliverability/dashboard', 'Nexus\Controllers\Api\AdminDeliverabilityApiController@getDashboard');
$router->add('GET', '/api/v2/admin/deliverability/analytics', 'Nexus\Controllers\Api\AdminDeliverabilityApiController@getAnalytics');
$router->add('GET', '/api/v2/admin/deliverability', 'Nexus\Controllers\Api\AdminDeliverabilityApiController@getDeliverables');
$router->add('POST', '/api/v2/admin/deliverability', 'Nexus\Controllers\Api\AdminDeliverabilityApiController@createDeliverable');
$router->add('GET', '/api/v2/admin/deliverability/{id}', 'Nexus\Controllers\Api\AdminDeliverabilityApiController@getDeliverable');
$router->add('PUT', '/api/v2/admin/deliverability/{id}', 'Nexus\Controllers\Api\AdminDeliverabilityApiController@updateDeliverable');
$router->add('DELETE', '/api/v2/admin/deliverability/{id}', 'Nexus\Controllers\Api\AdminDeliverabilityApiController@deleteDeliverable');
$router->add('POST', '/api/v2/admin/deliverability/{id}/comments', 'Nexus\Controllers\Api\AdminDeliverabilityApiController@addComment');

// Super Admin Panel (requires super_admin or god role)
// Dashboard
$router->add('GET', '/api/v2/admin/super/dashboard', 'Nexus\Controllers\Api\AdminSuperApiController@dashboard');

// Tenants
$router->add('GET', '/api/v2/admin/super/tenants', 'Nexus\Controllers\Api\AdminSuperApiController@tenantList');
$router->add('GET', '/api/v2/admin/super/tenants/hierarchy', 'Nexus\Controllers\Api\AdminSuperApiController@tenantHierarchy');
$router->add('POST', '/api/v2/admin/super/tenants', 'Nexus\Controllers\Api\AdminSuperApiController@tenantCreate');
$router->add('GET', '/api/v2/admin/super/tenants/{id}', 'Nexus\Controllers\Api\AdminSuperApiController@tenantShow');
$router->add('PUT', '/api/v2/admin/super/tenants/{id}', 'Nexus\Controllers\Api\AdminSuperApiController@tenantUpdate');
$router->add('DELETE', '/api/v2/admin/super/tenants/{id}', 'Nexus\Controllers\Api\AdminSuperApiController@tenantDelete');
$router->add('POST', '/api/v2/admin/super/tenants/{id}/reactivate', 'Nexus\Controllers\Api\AdminSuperApiController@tenantReactivate');
$router->add('POST', '/api/v2/admin/super/tenants/{id}/toggle-hub', 'Nexus\Controllers\Api\AdminSuperApiController@tenantToggleHub');
$router->add('POST', '/api/v2/admin/super/tenants/{id}/move', 'Nexus\Controllers\Api\AdminSuperApiController@tenantMove');

// Users (Cross-Tenant)
$router->add('GET', '/api/v2/admin/super/users', 'Nexus\Controllers\Api\AdminSuperApiController@userList');
$router->add('POST', '/api/v2/admin/super/users', 'Nexus\Controllers\Api\AdminSuperApiController@userCreate');
$router->add('GET', '/api/v2/admin/super/users/{id}', 'Nexus\Controllers\Api\AdminSuperApiController@userShow');
$router->add('PUT', '/api/v2/admin/super/users/{id}', 'Nexus\Controllers\Api\AdminSuperApiController@userUpdate');
$router->add('POST', '/api/v2/admin/super/users/{id}/grant-super-admin', 'Nexus\Controllers\Api\AdminSuperApiController@userGrantSuperAdmin');
$router->add('POST', '/api/v2/admin/super/users/{id}/revoke-super-admin', 'Nexus\Controllers\Api\AdminSuperApiController@userRevokeSuperAdmin');
$router->add('POST', '/api/v2/admin/super/users/{id}/grant-global-super-admin', 'Nexus\Controllers\Api\AdminSuperApiController@userGrantGlobalSuperAdmin');
$router->add('POST', '/api/v2/admin/super/users/{id}/revoke-global-super-admin', 'Nexus\Controllers\Api\AdminSuperApiController@userRevokeGlobalSuperAdmin');
$router->add('POST', '/api/v2/admin/super/users/{id}/move-tenant', 'Nexus\Controllers\Api\AdminSuperApiController@userMoveTenant');
$router->add('POST', '/api/v2/admin/super/users/{id}/move-and-promote', 'Nexus\Controllers\Api\AdminSuperApiController@userMoveAndPromote');

// Bulk Operations
$router->add('POST', '/api/v2/admin/super/bulk/move-users', 'Nexus\Controllers\Api\AdminSuperApiController@bulkMoveUsers');
$router->add('POST', '/api/v2/admin/super/bulk/update-tenants', 'Nexus\Controllers\Api\AdminSuperApiController@bulkUpdateTenants');

// Audit
$router->add('GET', '/api/v2/admin/super/audit', 'Nexus\Controllers\Api\AdminSuperApiController@audit');

// Federation Controls
$router->add('GET', '/api/v2/admin/super/federation', 'Nexus\Controllers\Api\AdminSuperApiController@federationOverview');
$router->add('GET', '/api/v2/admin/super/federation/system-controls', 'Nexus\Controllers\Api\AdminSuperApiController@federationGetSystemControls');
$router->add('PUT', '/api/v2/admin/super/federation/system-controls', 'Nexus\Controllers\Api\AdminSuperApiController@federationUpdateSystemControls');
$router->add('POST', '/api/v2/admin/super/federation/emergency-lockdown', 'Nexus\Controllers\Api\AdminSuperApiController@federationEmergencyLockdown');
$router->add('POST', '/api/v2/admin/super/federation/lift-lockdown', 'Nexus\Controllers\Api\AdminSuperApiController@federationLiftLockdown');
$router->add('GET', '/api/v2/admin/super/federation/whitelist', 'Nexus\Controllers\Api\AdminSuperApiController@federationGetWhitelist');
$router->add('POST', '/api/v2/admin/super/federation/whitelist', 'Nexus\Controllers\Api\AdminSuperApiController@federationAddToWhitelist');
$router->add('DELETE', '/api/v2/admin/super/federation/whitelist/{tenantId}', 'Nexus\Controllers\Api\AdminSuperApiController@federationRemoveFromWhitelist');
$router->add('GET', '/api/v2/admin/super/federation/partnerships', 'Nexus\Controllers\Api\AdminSuperApiController@federationPartnerships');
$router->add('POST', '/api/v2/admin/super/federation/partnerships/{id}/suspend', 'Nexus\Controllers\Api\AdminSuperApiController@federationSuspendPartnership');
$router->add('POST', '/api/v2/admin/super/federation/partnerships/{id}/terminate', 'Nexus\Controllers\Api\AdminSuperApiController@federationTerminatePartnership');
$router->add('GET', '/api/v2/admin/super/federation/tenant/{id}/features', 'Nexus\Controllers\Api\AdminSuperApiController@federationGetTenantFeatures');
$router->add('PUT', '/api/v2/admin/super/federation/tenant/{id}/features', 'Nexus\Controllers\Api\AdminSuperApiController@federationUpdateTenantFeature');

// ============================================
// MASTER PLATFORM SOCIAL MEDIA MODULE API (Legacy V1)
// Unified social interactions for ALL layouts
// ============================================
$router->add('GET', '/api/social/test', 'Nexus\Controllers\Api\SocialApiController@test');
$router->add('POST', '/api/social/like', 'Nexus\Controllers\Api\SocialApiController@like');
$router->add('POST', '/api/social/likers', 'Nexus\Controllers\Api\SocialApiController@likers');
$router->add('POST', '/api/social/comments', 'Nexus\Controllers\Api\SocialApiController@comments');
$router->add('POST', '/api/social/share', 'Nexus\Controllers\Api\SocialApiController@share');
$router->add('POST', '/api/social/delete', 'Nexus\Controllers\Api\SocialApiController@delete');
$router->add('POST', '/api/social/reaction', 'Nexus\Controllers\Api\SocialApiController@reaction');
$router->add('POST', '/api/social/reply', 'Nexus\Controllers\Api\SocialApiController@reply');
$router->add('POST', '/api/social/edit-comment', 'Nexus\Controllers\Api\SocialApiController@editComment');
$router->add('POST', '/api/social/delete-comment', 'Nexus\Controllers\Api\SocialApiController@deleteComment');
$router->add('POST', '/api/social/mention-search', 'Nexus\Controllers\Api\SocialApiController@mentionSearch');
$router->add('POST', '/api/social/feed', 'Nexus\Controllers\Api\SocialApiController@feed');
$router->add('POST', '/api/social/create-post', 'Nexus\Controllers\Api\SocialApiController@createPost');

// Asset Upload API (for Page Builder, Newsletter Editor, etc.)
$router->add('POST', '/api/upload', 'Nexus\Controllers\Api\UploadController@store');

// Push Notifications API (Web Push / PWA)
$router->add('GET', '/api/push/vapid-key', 'Nexus\Controllers\Api\PushApiController@vapidKey');
$router->add('GET', '/api/push/vapid-public-key', 'Nexus\Controllers\Api\PushApiController@vapidKey'); // Alias for CivicOne
$router->add('POST', '/api/push/subscribe', 'Nexus\Controllers\Api\PushApiController@subscribe');
$router->add('POST', '/api/push/unsubscribe', 'Nexus\Controllers\Api\PushApiController@unsubscribe');
$router->add('POST', '/api/push/send', 'Nexus\Controllers\Api\PushApiController@send');
$router->add('GET', '/api/push/status', 'Nexus\Controllers\Api\PushApiController@status');

// Native Push Notifications API (FCM for Android/Capacitor app)
$router->add('POST', '/api/push/register-device', 'Nexus\Controllers\Api\PushApiController@registerDevice');
$router->add('POST', '/api/push/unregister-device', 'Nexus\Controllers\Api\PushApiController@unregisterDevice');

// Session/Auth API (for mobile app session management)
$router->add('POST', '/api/auth/heartbeat', 'Nexus\Controllers\Api\AuthController@heartbeat');
$router->add('GET', '/api/auth/check-session', 'Nexus\Controllers\Api\AuthController@checkSession');
$router->add('POST', '/api/auth/refresh-session', 'Nexus\Controllers\Api\AuthController@refreshSession');
$router->add('POST', '/api/auth/restore-session', 'Nexus\Controllers\Api\AuthController@restoreSession');

// Token-based Auth API (for mobile apps - more reliable than session cookies)
$router->add('POST', '/api/auth/login', 'Nexus\Controllers\Api\AuthController@login');
$router->add('POST', '/api/auth/logout', 'Nexus\Controllers\Api\AuthController@logout');
$router->add('POST', '/api/auth/refresh-token', 'Nexus\Controllers\Api\AuthController@refreshToken');
$router->add('POST', '/api/auth/validate-token', 'Nexus\Controllers\Api\AuthController@validateToken');
$router->add('GET', '/api/auth/validate-token', 'Nexus\Controllers\Api\AuthController@validateToken');

// Token Revocation API (for logout-everywhere functionality)
$router->add('POST', '/api/auth/revoke', 'Nexus\Controllers\Api\AuthController@revokeToken');
$router->add('POST', '/api/auth/revoke-all', 'Nexus\Controllers\Api\AuthController@revokeAllTokens');

// JWT-to-Session bridge for legacy admin access from React frontend
$router->add('GET', '/api/auth/admin-session', 'Nexus\Controllers\Api\AuthController@adminSession');

// CSRF Token API (for SPAs using session auth - Bearer clients don't need this)
$router->add('GET', '/api/auth/csrf-token', 'Nexus\Controllers\Api\AuthController@getCsrfToken');
$router->add('GET', '/api/v2/csrf-token', 'Nexus\Controllers\Api\AuthController@getCsrfToken'); // V2 alias

// V2 Registration API (returns tokens immediately, field-level errors)
$router->add('POST', '/api/v2/auth/register', 'Nexus\Controllers\Api\RegistrationApiController@register');

// OpenAPI Documentation (accessible without auth)
$router->add('GET', '/api/docs', 'Nexus\Controllers\Api\OpenApiController@ui');
$router->add('GET', '/api/docs/openapi.json', 'Nexus\Controllers\Api\OpenApiController@json');
$router->add('GET', '/api/docs/openapi.yaml', 'Nexus\Controllers\Api\OpenApiController@yaml');

// Password Reset API (stateless, v2 response format)
$router->add('POST', '/api/auth/forgot-password', 'Nexus\Controllers\Api\PasswordResetApiController@forgotPassword');
$router->add('POST', '/api/auth/reset-password', 'Nexus\Controllers\Api\PasswordResetApiController@resetPassword');

// Email Verification API (stateless, v2 response format)
$router->add('POST', '/api/auth/verify-email', 'Nexus\Controllers\Api\EmailVerificationApiController@verifyEmail');
$router->add('POST', '/api/auth/resend-verification', 'Nexus\Controllers\Api\EmailVerificationApiController@resendVerification');

// TOTP 2FA API
$router->add('POST', '/api/totp/verify', 'Nexus\Controllers\Api\TotpApiController@verify');
$router->add('GET', '/api/totp/status', 'Nexus\Controllers\Api\TotpApiController@status');

// Mobile App API (version checking, updates)
$router->add('POST', '/api/app/check-version', 'Nexus\Controllers\Api\AppController@checkVersion');
$router->add('GET', '/api/app/version', 'Nexus\Controllers\Api\AppController@version');
$router->add('POST', '/api/app/log', 'Nexus\Controllers\Api\AppController@log');

// Pusher Realtime API (WebSocket authentication)
$router->add('POST', '/api/pusher/auth', 'Nexus\Controllers\Api\PusherAuthController@auth');
$router->add('GET', '/api/pusher/config', 'Nexus\Controllers\Api\PusherAuthController@config');
// SECURITY: Debug endpoint removed - exposed sensitive configuration

// WebAuthn Biometric Auth API (Primary endpoints - used by nexus-native.js)
$router->add('POST', '/api/webauthn/register-challenge', 'Nexus\Controllers\Api\WebAuthnApiController@registerChallenge');
$router->add('POST', '/api/webauthn/register-verify', 'Nexus\Controllers\Api\WebAuthnApiController@registerVerify');
$router->add('POST', '/api/webauthn/auth-challenge', 'Nexus\Controllers\Api\WebAuthnApiController@authChallenge');
$router->add('POST', '/api/webauthn/auth-verify', 'Nexus\Controllers\Api\WebAuthnApiController@authVerify');
$router->add('POST', '/api/webauthn/remove', 'Nexus\Controllers\Api\WebAuthnApiController@remove');
$router->add('POST', '/api/webauthn/remove-all', 'Nexus\Controllers\Api\WebAuthnApiController@removeAll'); // SECURITY: Changed to POST only
$router->add('GET', '/api/webauthn/credentials', 'Nexus\Controllers\Api\WebAuthnApiController@credentials');
$router->add('GET', '/api/webauthn/status', 'Nexus\Controllers\Api\WebAuthnApiController@status'); // Status endpoint

// WebAuthn Aliases (for CivicOne layout compatibility)
$router->add('POST', '/api/webauthn/register/options', 'Nexus\Controllers\Api\WebAuthnApiController@registerChallenge');
$router->add('POST', '/api/webauthn/register/verify', 'Nexus\Controllers\Api\WebAuthnApiController@registerVerify');
$router->add('POST', '/api/webauthn/login/options', 'Nexus\Controllers\Api\WebAuthnApiController@authChallenge');
$router->add('POST', '/api/webauthn/login/verify', 'Nexus\Controllers\Api\WebAuthnApiController@authVerify');

// ============================================
// AI ASSISTANT API
// Chat, content generation, recommendations
// ============================================

// AI Chat & Conversations
$router->add('POST', '/api/ai/chat', 'Nexus\Controllers\Api\Ai\AiChatController@chat');
$router->add('POST', '/api/ai/chat/stream', 'Nexus\Controllers\Api\Ai\AiChatController@streamChat');
$router->add('GET', '/api/ai/conversations', 'Nexus\Controllers\Api\Ai\AiChatController@listConversations');
$router->add('GET', '/api/ai/conversations/([0-9]+)', 'Nexus\Controllers\Api\Ai\AiChatController@getConversation');
$router->add('POST', '/api/ai/conversations', 'Nexus\Controllers\Api\Ai\AiChatController@createConversation');
$router->add('DELETE', '/api/ai/conversations/([0-9]+)', 'Nexus\Controllers\Api\Ai\AiChatController@deleteConversation');

// AI Provider & Limits
$router->add('GET', '/api/ai/providers', 'Nexus\Controllers\Api\Ai\AiProviderController@getProviders');
$router->add('GET', '/api/ai/limits', 'Nexus\Controllers\Api\Ai\AiProviderController@getLimits');
$router->add('POST', '/api/ai/test-provider', 'Nexus\Controllers\Api\Ai\AiProviderController@testProvider');

// AI User Content Generation
$router->add('POST', '/api/ai/generate/listing', 'Nexus\Controllers\Api\Ai\AiContentController@generateListing');
$router->add('POST', '/api/ai/generate/event', 'Nexus\Controllers\Api\Ai\AiContentController@generateEvent');
$router->add('POST', '/api/ai/generate/message', 'Nexus\Controllers\Api\Ai\AiContentController@generateMessage');
$router->add('POST', '/api/ai/generate/bio', 'Nexus\Controllers\Api\Ai\AiContentController@generateBio');

// AI Admin Content Generation
$router->add('POST', '/api/ai/generate/newsletter', 'Nexus\Controllers\Api\Ai\AiAdminContentController@generateNewsletter');
$router->add('POST', '/api/ai/generate/blog', 'Nexus\Controllers\Api\Ai\AiAdminContentController@generateBlog');
$router->add('POST', '/api/ai/generate/page', 'Nexus\Controllers\Api\Ai\AiAdminContentController@generatePage');

// AI Web Pages
$router->add('GET', '/ai', 'Nexus\Controllers\AiController@index');
$router->add('GET', '/ai/chat', 'Nexus\Controllers\AiController@chat');
$router->add('GET', '/ai/chat/([0-9]+)', 'Nexus\Controllers\AiController@chat');

// Menu API (for mobile apps)
$router->add('GET', '/api/menus', 'Nexus\Controllers\Api\MenuApiController@index');
$router->add('GET', '/api/menus/config', 'Nexus\Controllers\Api\MenuApiController@config');
$router->add('GET', '/api/menus/mobile', 'Nexus\Controllers\Api\MenuApiController@mobile');
$router->add('GET', '/api/menus/{slug}', 'Nexus\Controllers\Api\MenuApiController@show');
$router->add('POST', '/api/menus/clear-cache', 'Nexus\Controllers\Api\MenuApiController@clearCache');

// Management Views
$router->add('GET', '/notifications', 'Nexus\Controllers\NotificationController@manage');
$router->add('POST', '/notifications', 'Nexus\Controllers\NotificationController@manage'); // Handle mark all as read form

// Share Target (PWA Web Share Target API)
$router->add('POST', '/share-target', 'Nexus\Controllers\ShareTargetController@receive');
$router->add('GET', '/share-target/compose', 'Nexus\Controllers\ShareTargetController@compose');
$router->add('GET', '/share-target/pending', 'Nexus\Controllers\ShareTargetController@pending');
$router->add('POST', '/share-target/create', 'Nexus\Controllers\ShareTargetController@create');


// --------------------------------------------------------------------------

// 1. PUBLIC PAGES
// --------------------------------------------------------------------------

$router->add('GET', '/dashboard', 'Nexus\Controllers\DashboardController@index');
$router->add('GET', '/dashboard/notifications', 'Nexus\Controllers\DashboardController@notifications');
$router->add('GET', '/dashboard/hubs', 'Nexus\Controllers\DashboardController@hubs');
$router->add('GET', '/dashboard/listings', 'Nexus\Controllers\DashboardController@listings');
$router->add('GET', '/dashboard/wallet', 'Nexus\Controllers\DashboardController@wallet');
$router->add('GET', '/dashboard/events', 'Nexus\Controllers\DashboardController@events');
$router->add('POST', '/dashboard/switch_layout', 'Nexus\Controllers\HomeController@switchLayout');
$router->add('GET', '/dashboard/switch_layout', 'Nexus\Controllers\HomeController@switchLayout'); // Support GET link

// NEXUS SCORE SYSTEM
$router->add('GET', '/nexus-score', 'Nexus\Controllers\NexusScoreController@dashboard');
$router->add('GET', '/nexus-score/report', 'Nexus\Controllers\NexusScoreController@impactReport');
$router->add('GET', '/nexus-score/leaderboard', 'Nexus\Controllers\NexusScoreController@leaderboard');
$router->add('GET', '/profile/{id}/score', 'Nexus\Controllers\NexusScoreController@publicProfile');

// Homepage
$router->add('GET', '/', 'Nexus\Controllers\HomeController@index');
$router->add('POST', '/', 'Nexus\Controllers\HomeController@index'); // Support Feed Post
$router->add('GET', '/home', 'Nexus\Controllers\HomeController@index'); // Alias
$router->add('POST', '/home', 'Nexus\Controllers\HomeController@index'); // Support Feed Post

// Compose - Full-page multi-form for creating content (mobile-optimized)
$router->add('GET', '/compose', 'Nexus\Controllers\ComposeController@index');
$router->add('POST', '/compose', 'Nexus\Controllers\ComposeController@store');

$router->add('GET', '/login', 'Nexus\Controllers\AuthController@showLogin');
$router->add('GET', '/login/oauth/redirect/{provider}', 'Nexus\Controllers\SocialAuthController@redirect');
$router->add('GET', '/login/oauth/{provider}', 'Nexus\Controllers\SocialAuthController@redirect'); // Alias for cleaner URLs
$router->add('GET', '/login/oauth/callback/{provider}', 'Nexus\Controllers\SocialAuthController@callback');
$router->add('POST', '/login', 'Nexus\Controllers\AuthController@login');
$router->add('GET', '/register', 'Nexus\Controllers\AuthController@showRegister');
$router->add('POST', '/register', 'Nexus\Controllers\AuthController@register');
$router->add('GET', '/logout', 'Nexus\Controllers\AuthController@logout');

// Onboarding
$router->add('GET', '/onboarding', 'Nexus\Controllers\OnboardingController@index');
$router->add('POST', '/onboarding/store', 'Nexus\Controllers\OnboardingController@store');

// Password Reset
$router->add('GET', '/password/forgot', 'Nexus\Controllers\AuthController@showForgot');
$router->add('POST', '/password/email', 'Nexus\Controllers\AuthController@sendResetLink');
$router->add('GET', '/password/reset', 'Nexus\Controllers\AuthController@showReset');
$router->add('POST', '/password/reset', 'Nexus\Controllers\AuthController@resetPassword');

// Two-Factor Authentication (TOTP)
$router->add('GET', '/auth/2fa', 'Nexus\Controllers\TotpController@showVerify');
$router->add('POST', '/auth/2fa', 'Nexus\Controllers\TotpController@verify');
$router->add('GET', '/auth/2fa/setup', 'Nexus\Controllers\TotpController@showSetup');
$router->add('POST', '/auth/2fa/setup', 'Nexus\Controllers\TotpController@completeSetup');
$router->add('GET', '/auth/2fa/backup-codes', 'Nexus\Controllers\TotpController@showBackupCodes');
$router->add('POST', '/auth/2fa/backup-codes/regenerate', 'Nexus\Controllers\TotpController@regenerateBackupCodes');
$router->add('GET', '/settings/2fa', 'Nexus\Controllers\TotpController@settings');
$router->add('POST', '/settings/2fa/disable', 'Nexus\Controllers\TotpController@disable');
$router->add('POST', '/settings/2fa/devices/revoke', 'Nexus\Controllers\TotpController@revokeDevice');
$router->add('POST', '/settings/2fa/devices/revoke-all', 'Nexus\Controllers\TotpController@revokeAllDevices');

// Search
$router->add('GET', '/search', 'Nexus\Controllers\SearchController@index');

// Static Content Pages
$router->add('GET', '/about', 'Nexus\Controllers\PageController@about');
$router->add('GET', '/contact', 'Nexus\Controllers\PageController@contact');
$router->add('POST', '/contact/submit', 'Nexus\Controllers\ContactController@submit');
$router->add('POST', '/contact/send', 'Nexus\Controllers\ContactController@submit');
$router->add('POST', '/api/v2/contact', 'Nexus\Controllers\ContactController@apiSubmit');
$router->add('GET', '/faq', 'Nexus\Controllers\PageController@faq');
$router->add('GET', '/help', 'Nexus\Controllers\HelpController@index');
$router->add('GET', '/help/search', 'Nexus\Controllers\HelpController@search');
$router->add('POST', '/api/help/feedback', 'Nexus\Controllers\HelpController@feedback');
$router->add('GET', '/help/{slug}', 'Nexus\Controllers\HelpController@show');
$router->add('GET', '/how-it-works', 'Nexus\Controllers\PageController@howItWorks');
$router->add('GET', '/our-story', 'Nexus\Controllers\PageController@ourStory');
$router->add('GET', '/partner', 'Nexus\Controllers\PageController@partner');
$router->add('GET', '/social-prescribing', 'Nexus\Controllers\PageController@socialPrescribing');
$router->add('GET', '/timebanking-guide', 'Nexus\Controllers\PageController@timebankingGuide');
$router->add('GET', '/impact-summary', 'Nexus\Controllers\PageController@impactSummary');
$router->add('GET', '/impact-report', 'Nexus\Controllers\PageController@impactReport');
$router->add('GET', '/strategic-plan', 'Nexus\Controllers\PageController@strategicPlan');
// Legal Documents (versioned with acceptance tracking - falls back to legacy files if no DB content)
$router->add('GET', '/terms', 'Nexus\Controllers\LegalDocumentController@terms');
$router->add('GET', '/privacy', 'Nexus\Controllers\LegalDocumentController@privacy');
$router->add('GET', '/accessibility', 'Nexus\Controllers\LegalDocumentController@accessibility');
$router->add('GET', '/terms/versions', 'Nexus\Controllers\LegalDocumentController@termsVersionHistory');
$router->add('GET', '/privacy/versions', 'Nexus\Controllers\LegalDocumentController@privacyVersionHistory');
$router->add('GET', '/legal/version/{versionId}', 'Nexus\Controllers\LegalDocumentController@showVersion');
$router->add('GET', '/sitemap.xml', 'Nexus\Controllers\SitemapController@index');
$router->add('GET', '/robots.txt', 'Nexus\Controllers\RobotsController@index');

// Legal
$router->add('GET', '/legal', 'Nexus\Controllers\PageController@legal');
$router->add('GET', '/legal/cookies', 'Nexus\Controllers\CookiePolicyController@index');

// Cookie Preferences
$router->add('GET', '/cookie-preferences', 'Nexus\Controllers\CookiePreferencesController@index');

// CMS Pages (created via Page Builder)
$router->add('GET', '/page/{slug}', 'Nexus\Controllers\PageController@show');

// 1.5. BLOG / NEWS
// --------------------------------------------------------------------------
$router->add('GET', '/news', 'Nexus\Controllers\BlogController@index');
$router->add('GET', '/news/{slug}', 'Nexus\Controllers\BlogController@show');
$router->add('GET', '/blog', 'Nexus\Controllers\BlogController@index'); // Alias
$router->add('GET', '/blog/{slug}', 'Nexus\Controllers\BlogController@show'); // Alias

// Dynamic Tenant Pages
// Automatically register routes for pages found in views/tenants/{slug}/pages/ AND layout overrides
$tenantPages = [];
foreach ([null, 'modern'] as $layout) {
    $found = TenantContext::getCustomPages($layout);
    foreach ($found as $p) {
        $tenantPages[$p['url']] = $p; // Key by URL to deduplicate
    }
}

foreach ($tenantPages as $page) {
    // Extract slug from URL (which might include tenant prefix)
    // Custom pages are flat files, so basename is safe and correct.
    $slug = basename($page['url']);

    // Don't overwrite existing Core Static Routes (like /about, '/contact') 
    // because they have dedicated controllers with SEO logic.
    // The View system will still pick up the override file content.
    // We only need dynamic routes for NEW pages (e.g. /about-story).
    if ($slug && !$router->hasRoute('GET', '/' . $slug)) {
        $router->add('GET', '/' . $slug, function () use ($slug) {
            (new Nexus\Controllers\PageController())->show($slug);
        });
    }
}

// --------------------------------------------------------------------------
// 2. LISTINGS (Offers & Requests)
// --------------------------------------------------------------------------
$router->add('GET', '/listings', 'Nexus\Controllers\ListingController@index');
$router->add('GET', '/listings/create', 'Nexus\Controllers\ListingController@create');
$router->add('POST', '/listings/store', 'Nexus\Controllers\ListingController@store');
$router->add('GET', '/listings/edit/{id}', 'Nexus\Controllers\ListingController@edit');
$router->add('POST', '/listings/update', 'Nexus\Controllers\ListingController@update');
$router->add('POST', '/listings/delete', 'Nexus\Controllers\ListingController@delete');
// Use explicit regex for ID if Router supports it, otherwise generic wildcard
$router->add('GET', '/listings/{id}', 'Nexus\Controllers\ListingController@show');
$router->add('POST', '/listings/{id}', 'Nexus\Controllers\ListingController@show'); // AJAX actions (likes/comments)

// --------------------------------------------------------------------------
// 2b. EXCHANGES (Broker-controlled exchange workflow)
// --------------------------------------------------------------------------
$router->add('GET', '/exchanges', 'Nexus\Controllers\ExchangesController@index');
$router->add('GET', '/exchanges/request/{listingId}', 'Nexus\Controllers\ExchangesController@create');
$router->add('POST', '/exchanges', 'Nexus\Controllers\ExchangesController@store');
$router->add('GET', '/exchanges/{id}', 'Nexus\Controllers\ExchangesController@show');
$router->add('POST', '/exchanges/{id}/accept', 'Nexus\Controllers\ExchangesController@accept');
$router->add('POST', '/exchanges/{id}/decline', 'Nexus\Controllers\ExchangesController@decline');
$router->add('POST', '/exchanges/{id}/start', 'Nexus\Controllers\ExchangesController@start');
$router->add('POST', '/exchanges/{id}/confirm', 'Nexus\Controllers\ExchangesController@confirm');
$router->add('POST', '/exchanges/{id}/cancel', 'Nexus\Controllers\ExchangesController@cancel');

// --------------------------------------------------------------------------
// 3. GROUPS (Community Hubs)
// --------------------------------------------------------------------------
// HUBS (Admin-curated geographic communities)
$router->add('GET', '/groups', 'Nexus\Controllers\GroupController@index'); // Only hubs
$router->add('GET', '/groups/create', 'Nexus\Controllers\GroupController@create'); // Admin only - OLD form (keep for fallback)

// COMMUNITY GROUPS (User-created interest groups)
$router->add('GET', '/community-groups', 'Nexus\Controllers\GroupController@communityGroups');
$router->add('GET', '/community-groups/create', 'Nexus\Controllers\GroupController@createCommunityGroup'); // OLD form (keep for fallback)

// NEW: Modern overlay-based group creation
$router->add('GET', '/create-group', 'Nexus\Controllers\GroupController@createGroupOverlay');

// NEW: Modern overlay-based group editing
$router->add('GET', '/edit-group/{id}', 'Nexus\Controllers\GroupController@editGroupOverlay');

// Shared routes (work for both hubs and community groups)
$router->add('GET', '/groups/my-groups', 'Nexus\Controllers\GroupController@myGroups');
$router->add('POST', '/groups/store', 'Nexus\Controllers\GroupController@store');
$router->add('POST', '/groups/join', 'Nexus\Controllers\GroupController@join');
$router->add('POST', '/groups/leave', 'Nexus\Controllers\GroupController@leave');
$router->add('POST', '/groups/update', 'Nexus\Controllers\GroupController@update');
$router->add('POST', '/groups/manage-member', 'Nexus\Controllers\GroupController@manageMember');
$router->add('GET', '/groups/{id}/edit', 'Nexus\Controllers\GroupController@edit');
$router->add('GET', '/groups/{id}/post', 'Nexus\Controllers\GroupController@createPost');
$router->add('POST', '/groups/{id}/post', 'Nexus\Controllers\GroupController@storePost');
$router->add('GET', '/groups/{id}', 'Nexus\Controllers\GroupController@show');
$router->add('GET', '/groups/{id}/invite', 'Nexus\Controllers\GroupController@invite');
$router->add('POST', '/groups/{id}/invite', 'Nexus\Controllers\GroupController@sendInvites');
$router->add('GET', '/groups/{id}/discussions/create', 'Nexus\Controllers\GroupController@createDiscussion');
$router->add('POST', '/groups/{id}/discussions/store', 'Nexus\Controllers\GroupController@storeDiscussion');
$router->add('GET', '/groups/{id}/discussions/{discussion_id}', 'Nexus\Controllers\GroupController@showDiscussion');
$router->add('POST', '/groups/{id}/discussions/{discussion_id}/reply', 'Nexus\Controllers\GroupController@replyDiscussion');
$router->add('POST', '/groups/{id}/discussions/{discussion_id}/subscribe', 'Nexus\Controllers\GroupController@toggleSubscription'); // Legacy
$router->add('POST', '/groups/{id}/feedback', 'Nexus\Controllers\GroupController@submitFeedback');
$router->add('GET', '/groups/{id}/feedback', 'Nexus\Controllers\GroupController@viewFeedback');
$router->add('GET', '/groups/{id}/reviews', 'Nexus\Controllers\GroupController@getReviews');
$router->add('POST', '/groups/{id}/reviews', 'Nexus\Controllers\GroupController@submitReview');
$router->add('GET', '/groups/{id}/review/{memberId}', 'Nexus\Controllers\GroupController@showReviewForm');

// Group Analytics (Owner/Admin view)
$router->add('GET', '/groups/{id}/analytics', 'Nexus\Controllers\GroupAnalyticsController@index');
$router->add('GET', '/api/groups/{id}/analytics', 'Nexus\Controllers\GroupAnalyticsController@apiData');

// Group Recommendations (Discovery Engine)
$router->add('GET', '/api/recommendations/groups', 'Nexus\Controllers\Api\GroupRecommendationController@index');
$router->add('POST', '/api/recommendations/track', 'Nexus\Controllers\Api\GroupRecommendationController@track');
$router->add('GET', '/api/recommendations/metrics', 'Nexus\Controllers\Api\GroupRecommendationController@metrics');
$router->add('GET', '/api/recommendations/similar/{id}', 'Nexus\Controllers\Api\GroupRecommendationController@similar');
$router->add('POST', '/api/notifications/settings', 'Nexus\Controllers\UserPreferenceController@updateSettings'); // Smart Settings API
// Consolidated API routes
//$router->add('GET', '/api/notifications', 'Nexus\Controllers\NotificationController@index'); // Handled by CoreApiController
//$router->add('POST', '/api/notifications/read', 'Nexus\Controllers\NotificationController@markRead'); // Handled by CoreApiController
// NOTE: /notifications route handled by NotificationController@manage (line 53)

// --------------------------------------------------------------------------
// 4. VOLUNTEERING
// --------------------------------------------------------------------------
$router->add('GET', '/volunteering', 'Nexus\Controllers\VolunteeringController@index');
$router->add('GET', '/volunteering/dashboard', 'Nexus\Controllers\VolunteeringController@dashboard');
$router->add('GET', '/volunteering/my-applications', 'Nexus\Controllers\VolunteeringController@myApplications');
$router->add('GET', '/volunteering/opportunities/create', 'Nexus\Controllers\VolunteeringController@createOpp');
$router->add('GET', '/volunteering/certificate', 'Nexus\Controllers\VolunteeringController@printCertificate');
$router->add('GET', '/volunteering/organizations', 'Nexus\Controllers\VolunteeringController@organizations');
$router->add('GET', '/volunteering/{id}', 'Nexus\Controllers\VolunteeringController@show');
$router->add('POST', '/volunteering/{id}', 'Nexus\Controllers\VolunteeringController@show'); // AJAX actions (likes/comments)

// Actions
$router->add('POST', '/volunteering/org/store', 'Nexus\Controllers\VolunteeringController@storeOrg');
$router->add('POST', '/volunteering/opp/store', 'Nexus\Controllers\VolunteeringController@storeOpp');
$router->add('POST', '/volunteering/apply', 'Nexus\Controllers\VolunteeringController@apply');
$router->add('POST', '/volunteering/app/update', 'Nexus\Controllers\VolunteeringController@updateApp');
$router->add('POST', '/volunteering/review/store', 'Nexus\Controllers\VolunteeringController@submitReview');
$router->add('POST', '/volunteering/log-hours', 'Nexus\Controllers\VolunteeringController@logHours');
$router->add('POST', '/volunteering/verify-hours', 'Nexus\Controllers\VolunteeringController@verifyHours');

// Organization Profile (public)
$router->add('GET', '/volunteering/organization/{id}', 'Nexus\Controllers\VolunteeringController@showOrg');

// Edit Routes
$router->add('GET', '/volunteering/org/edit/{id}', 'Nexus\Controllers\VolunteeringController@editOrg');
$router->add('POST', '/volunteering/org/update', 'Nexus\Controllers\VolunteeringController@updateOrg');
$router->add('GET', '/volunteering/opp/edit/{id}', 'Nexus\Controllers\VolunteeringController@editOpp');
$router->add('GET', '/volunteering/edit/{id}', 'Nexus\Controllers\VolunteeringController@editOpp'); // Alias for Admin Directory
$router->add('POST', '/volunteering/opp/update', 'Nexus\Controllers\VolunteeringController@updateOpp');

// Shifts
$router->add('POST', '/volunteering/shift/store', 'Nexus\Controllers\VolunteeringController@storeShift');
$router->add('POST', '/volunteering/shift/delete', 'Nexus\Controllers\VolunteeringController@deleteShift');

// Utils
$router->add('GET', '/volunteering/app/ics/{id}', 'Nexus\Controllers\VolunteeringController@downloadIcs');

// --------------------------------------------------------------------------
// 4.5. REVIEWS
// --------------------------------------------------------------------------
$router->add('GET', '/reviews/create/{transactionId}', 'Nexus\Controllers\ReviewController@create');
$router->add('POST', '/reviews/store', 'Nexus\Controllers\ReviewController@store');

// --------------------------------------------------------------------------
// 5. EVENTS
// --------------------------------------------------------------------------
$router->add('GET', '/events', 'Nexus\Controllers\EventController@index');
$router->add('GET', '/events/calendar', 'Nexus\Controllers\EventController@calendar');
$router->add('GET', '/events/create', 'Nexus\Controllers\EventController@create');
$router->add('POST', '/events/store', 'Nexus\Controllers\EventController@store');
$router->add('GET', '/events/{id}', 'Nexus\Controllers\EventController@show');
$router->add('POST', '/events/{id}', 'Nexus\Controllers\EventController@show'); // AJAX actions (likes/comments)
$router->add('GET', '/events/{id}/edit', 'Nexus\Controllers\EventController@edit'); // Standard REST
$router->add('POST', '/events/update/{id}', 'Nexus\Controllers\EventController@update');
$router->add('POST', '/events/rsvp', 'Nexus\Controllers\EventController@rsvp');
$router->add('POST', '/events/invite', 'Nexus\Controllers\EventController@invite');
$router->add('POST', '/events/check-in', 'Nexus\Controllers\EventController@checkIn');

// Specific Event Routes (additional aliases)
$router->add('GET', '/events/edit/{id}', 'Nexus\Controllers\EventController@edit'); // Alias for Admin Directory
$router->add('POST', '/events/{id}/update', 'Nexus\Controllers\EventController@update');
$router->add('GET', '/events/{id}/delete', 'Nexus\Controllers\EventController@destroy'); // Ideally POST/DELETE, but commonly GET link in simple apps
$router->add('GET', '/events/{id}/export', 'Nexus\Controllers\EventController@exportAttendees');



// --------------------------------------------------------------------------
// --------------------------------------------------------------------------
// 6. POLLS
// --------------------------------------------------------------------------
// Static routes MUST come before dynamic {id} routes
$router->add('GET', '/polls', 'Nexus\Controllers\PollController@index');
$router->add('GET', '/polls/create', 'Nexus\Controllers\PollController@create');
$router->add('POST', '/polls/store', 'Nexus\Controllers\PollController@store');
$router->add('POST', '/polls/vote', 'Nexus\Controllers\PollController@vote');
$router->add('GET', '/polls/vote', 'Nexus\Controllers\PollController@index'); // Redirect GET to index

// Dynamic {id} routes - must come AFTER static routes
$router->add('GET', '/polls/{id}', 'Nexus\Controllers\PollController@show');
$router->add('POST', '/polls/{id}', 'Nexus\Controllers\PollController@show'); // AJAX actions (likes/comments)
$router->add('GET', '/polls/{id}/edit', 'Nexus\Controllers\PollController@edit');
$router->add('GET', '/polls/edit/{id}', 'Nexus\Controllers\PollController@edit'); // Alias for Admin Directory
$router->add('POST', '/polls/{id}/vote', 'Nexus\Controllers\PollController@vote');
$router->add('POST', '/polls/{id}/update', 'Nexus\Controllers\PollController@update');
$router->add('GET', '/polls/{id}/delete', 'Nexus\Controllers\PollController@destroy');

// --------------------------------------------------------------------------
// 7. MESSAGING - Consolidated below at 10.5
// --------------------------------------------------------------------------



// --------------------------------------------------------------------------
// 7. GOALS
// --------------------------------------------------------------------------
$router->add('GET', '/goals', 'Nexus\Controllers\GoalController@index');
$router->add('GET', '/goals/create', 'Nexus\Controllers\GoalController@create');
$router->add('POST', '/goals/store', 'Nexus\Controllers\GoalController@store');
$router->add('POST', '/goals/buddy', 'Nexus\Controllers\GoalController@becomeBuddy');

// Specific Goal Routes
$router->add('GET', '/goals/{id}', 'Nexus\Controllers\GoalController@show');
$router->add('GET', '/goals/{id}/edit', 'Nexus\Controllers\GoalController@edit');
$router->add('GET', '/goals/edit/{id}', 'Nexus\Controllers\GoalController@edit'); // Alias for Admin Directory
$router->add('POST', '/goals/{id}/update', 'Nexus\Controllers\GoalController@update');
$router->add('POST', '/goals/{id}/complete', 'Nexus\Controllers\GoalController@complete');
$router->add('GET', '/goals/{id}/delete', 'Nexus\Controllers\GoalController@confirmDelete');
$router->add('POST', '/goals/{id}/delete', 'Nexus\Controllers\GoalController@destroy');


// --------------------------------------------------------------------------
// GDPR CONSENT RE-ACCEPTANCE (User-facing)
// --------------------------------------------------------------------------
$router->add('GET', '/consent-required', 'Nexus\Controllers\ConsentController@required');
$router->add('POST', '/consent/accept', 'Nexus\Controllers\ConsentController@accept');
$router->add('GET', '/consent/decline', 'Nexus\Controllers\ConsentController@decline');

// --------------------------------------------------------------------------
// 8. MEMBERS & PROFILES
// --------------------------------------------------------------------------
$router->add('GET', '/settings', 'Nexus\Controllers\SettingsController@index'); // Route for /settings
$router->add('POST', '/settings/profile', 'Nexus\Controllers\SettingsController@updateProfile'); // NEW: Update Profile
$router->add('POST', '/settings/password', 'Nexus\Controllers\SettingsController@updatePassword'); // Route for password update
$router->add('POST', '/settings/privacy', 'Nexus\Controllers\SettingsController@updatePrivacy'); // Route for privacy update
$router->add('POST', '/settings/notifications', 'Nexus\Controllers\SettingsController@updateNotifications'); // Route for notification settings
$router->add('GET', '/settings/notifications/edit', 'Nexus\Controllers\SettingsController@notificationsEdit'); // CivicOne notification edit form
$router->add('POST', '/settings/consent', 'Nexus\Controllers\SettingsController@updateConsent'); // GDPR consent toggle
$router->add('POST', '/settings/gdpr-request', 'Nexus\Controllers\SettingsController@submitGdprRequest'); // GDPR data request
$router->add('POST', '/settings/federation/update', 'Nexus\Controllers\SettingsController@updateFederation'); // Federation settings
$router->add('POST', '/settings/federation/opt-out', 'Nexus\Controllers\SettingsController@federationOptOut'); // Quick opt-out

// --------------------------------------------------------------------------
// FEDERATED MEMBER DIRECTORY (Multi-Tenant Federation Phase 4)
// --------------------------------------------------------------------------
// Base federation route - Partner Timebanks Hub
$router->add('GET', '/federation', 'Nexus\Controllers\FederationHubController@index');
$router->add('GET', '/federation/activity', 'Nexus\Controllers\FederationHubController@activity');
$router->add('GET', '/federation/activity/api', 'Nexus\Controllers\FederationHubController@activityApi');

// Partner Timebank Profiles
$router->add('GET', '/federation/partners/{id}', 'Nexus\Controllers\FederatedPartnerController@show');

$router->add('GET', '/federation/members', 'Nexus\Controllers\FederatedMemberController@index');
$router->add('GET', '/federation/members/api', 'Nexus\Controllers\FederatedMemberController@api');
$router->add('GET', '/federation/members/skills', 'Nexus\Controllers\FederatedMemberController@skillsApi');
$router->add('GET', '/federation/members/locations', 'Nexus\Controllers\FederatedMemberController@locationsApi');
$router->add('GET', '/federation/members/external/{partnerId}/{memberId}', 'Nexus\Controllers\FederatedMemberController@showExternal');
$router->add('GET', '/federation/members/{id}', 'Nexus\Controllers\FederatedMemberController@show');

// Federated Messaging (Cross-Tenant)
$router->add('GET', '/federation/messages', 'Nexus\Controllers\FederatedMessageController@index');
$router->add('GET', '/federation/messages/compose', 'Nexus\Controllers\FederatedMessageController@compose');
$router->add('GET', '/federation/messages/api', 'Nexus\Controllers\FederatedMessageController@api');
$router->add('GET', '/federation/messages/{id}', 'Nexus\Controllers\FederatedMessageController@thread');
$router->add('POST', '/federation/messages/send', 'Nexus\Controllers\FederatedMessageController@send');
$router->add('POST', '/federation/messages/mark-read', 'Nexus\Controllers\FederatedMessageController@markRead');

// Federated Listings (Cross-Tenant)
$router->add('GET', '/federation/listings', 'Nexus\Controllers\FederatedListingController@index');
$router->add('GET', '/federation/listings/api', 'Nexus\Controllers\FederatedListingController@api');
$router->add('GET', '/federation/listings/external/{partnerId}/{listingId}', 'Nexus\Controllers\FederatedListingController@showExternal');
$router->add('GET', '/federation/listings/{id}', 'Nexus\Controllers\FederatedListingController@show');

// Federated Transactions (Cross-Tenant Hour Exchanges)
$router->add('GET', '/federation/transactions', 'Nexus\Controllers\FederatedTransactionController@index');
$router->add('GET', '/federation/transactions/api', 'Nexus\Controllers\FederatedTransactionController@api');
$router->add('GET', '/federation/transactions/new', 'Nexus\Controllers\FederatedTransactionController@create');
$router->add('GET', '/transactions/new', 'Nexus\Controllers\FederatedTransactionController@create'); // Alias for federated=1 links
$router->add('POST', '/federation/transactions/send', 'Nexus\Controllers\FederatedTransactionController@store');

// Federation Reviews (Post-Transaction Feedback)
$router->add('GET', '/federation/review/{transactionId}', 'Nexus\Controllers\FederationReviewController@show');
$router->add('POST', '/federation/review/{transactionId}', 'Nexus\Controllers\FederationReviewController@store');
$router->add('GET', '/federation/review/{transactionId}/modal', 'Nexus\Controllers\FederationReviewController@modal');
$router->add('GET', '/federation/reviews/pending', 'Nexus\Controllers\FederationReviewController@pending');
$router->add('GET', '/federation/reviews/user/{userId}', 'Nexus\Controllers\FederationReviewController@userReviews');

// Federated Events (Cross-Tenant Event Access)
$router->add('GET', '/federation/events', 'Nexus\Controllers\FederatedEventController@index');
$router->add('GET', '/federation/events/api', 'Nexus\Controllers\FederatedEventController@api');
$router->add('GET', '/federation/events/{id}', 'Nexus\Controllers\FederatedEventController@show');
$router->add('POST', '/federation/events/{id}/register', 'Nexus\Controllers\FederatedEventController@register');

// Federated Groups (Cross-Tenant Group Membership)
$router->add('GET', '/federation/groups', 'Nexus\Controllers\FederatedGroupController@index');
$router->add('GET', '/federation/groups/api', 'Nexus\Controllers\FederatedGroupController@api');
$router->add('GET', '/federation/groups/my', 'Nexus\Controllers\FederatedGroupController@myGroups');
$router->add('GET', '/federation/groups/{id}', 'Nexus\Controllers\FederatedGroupController@show');
$router->add('POST', '/federation/groups/{id}/join', 'Nexus\Controllers\FederatedGroupController@join');
$router->add('POST', '/federation/groups/{id}/leave', 'Nexus\Controllers\FederatedGroupController@leave');

// Federation Help & FAQ
$router->add('GET', '/federation/help', 'Nexus\Controllers\FederationHelpController@index');

// Federation Onboarding Wizard
$router->add('GET', '/federation/onboarding', 'Nexus\Controllers\FederationOnboardingController@index');
$router->add('POST', '/federation/onboarding/save', 'Nexus\Controllers\FederationOnboardingController@save');

// Federation User Dashboard
$router->add('GET', '/federation/dashboard', 'Nexus\Controllers\FederationDashboardController@index');

// Federation User Settings
$router->add('GET', '/federation/settings', 'Nexus\Controllers\FederationSettingsController@index');
$router->add('POST', '/federation/settings/save', 'Nexus\Controllers\FederationSettingsController@save');
$router->add('POST', '/federation/settings/disable', 'Nexus\Controllers\FederationSettingsController@disable');
$router->add('POST', '/federation/settings/enable', 'Nexus\Controllers\FederationSettingsController@enable');

// Federation Offline Page (PWA)
$router->add('GET', '/federation/offline', 'Nexus\Controllers\FederationOfflineController@index');

// Federation Real-time Stream (SSE)
$router->add('GET', '/federation/stream', 'Nexus\Controllers\FederationStreamController@stream');
$router->add('GET', '/federation/stream/info', 'Nexus\Controllers\FederationStreamController@info');
$router->add('POST', '/federation/pusher/auth', 'Nexus\Controllers\FederationStreamController@pusherAuth');

$router->add('GET', '/members', 'Nexus\Controllers\MemberController@index');
$router->add('GET', '/members/{id}', 'Nexus\Controllers\ProfileController@show'); // Member profile by ID or username
$router->add('POST', '/members/{id}', 'Nexus\Controllers\ProfileController@show'); // Allow POST for wall

// --------------------------------------------------------------------------
// LEADERBOARDS & GAMIFICATION
// --------------------------------------------------------------------------
$router->add('GET', '/leaderboard', 'Nexus\Controllers\LeaderboardController@index');
$router->add('GET', '/leaderboards', 'Nexus\Controllers\LeaderboardController@index'); // Alias
$router->add('GET', '/api/leaderboard', 'Nexus\Controllers\LeaderboardController@api');
$router->add('GET', '/api/leaderboard/widget', 'Nexus\Controllers\LeaderboardController@widget');
$router->add('GET', '/api/streaks', 'Nexus\Controllers\LeaderboardController@streaks');

// Achievements Dashboard
$router->add('GET', '/achievements', 'Nexus\Controllers\AchievementsController@index');
$router->add('GET', '/achievements/badges', 'Nexus\Controllers\AchievementsController@badges');
$router->add('GET', '/achievements/challenges', 'Nexus\Controllers\AchievementsController@challenges');
$router->add('GET', '/achievements/collections', 'Nexus\Controllers\AchievementsController@collections');
$router->add('GET', '/achievements/shop', 'Nexus\Controllers\AchievementsController@shop');
$router->add('GET', '/achievements/seasons', 'Nexus\Controllers\AchievementsController@seasons');
$router->add('POST', '/achievements/showcase', 'Nexus\Controllers\AchievementsController@updateShowcase');
$router->add('GET', '/api/achievements', 'Nexus\Controllers\AchievementsController@api');
$router->add('GET', '/api/achievements/progress', 'Nexus\Controllers\AchievementsController@progress');

// Gamification API Endpoints
$router->add('POST', '/api/daily-reward/check', 'Nexus\Controllers\Api\GamificationApiController@checkDailyReward');
$router->add('GET', '/api/daily-reward/status', 'Nexus\Controllers\Api\GamificationApiController@getDailyStatus');
$router->add('GET', '/api/gamification/challenges', 'Nexus\Controllers\Api\GamificationApiController@getChallenges');
$router->add('GET', '/api/gamification/collections', 'Nexus\Controllers\Api\GamificationApiController@getCollections');
$router->add('GET', '/api/gamification/shop', 'Nexus\Controllers\Api\GamificationApiController@getShopItems');
$router->add('POST', '/api/gamification/shop/purchase', 'Nexus\Controllers\Api\GamificationApiController@purchaseItem');
$router->add('GET', '/api/gamification/summary', 'Nexus\Controllers\Api\GamificationApiController@getSummary');
$router->add('POST', '/api/gamification/showcase', 'Nexus\Controllers\Api\GamificationApiController@updateShowcase');
$router->add('GET', '/api/gamification/showcased', 'Nexus\Controllers\Api\GamificationApiController@getShowcasedBadges');
$router->add('GET', '/api/gamification/share', 'Nexus\Controllers\Api\GamificationApiController@shareAchievement');
$router->add('GET', '/api/gamification/seasons', 'Nexus\Controllers\Api\GamificationApiController@getSeasons');
$router->add('GET', '/api/gamification/seasons/current', 'Nexus\Controllers\Api\GamificationApiController@getCurrentSeason');
$router->add('POST', '/api/shop/purchase', 'Nexus\Controllers\Api\GamificationApiController@purchaseItem');

$router->add('GET', '/profile/me', 'Nexus\Controllers\ProfileController@me'); // Specific route first
$router->add('POST', '/profile/me', 'Nexus\Controllers\ProfileController@me'); // Allow POST for wall
$router->add('POST', '/profile/update', 'Nexus\Controllers\ProfileController@update'); // Profile Edit Form (Advanced)
$router->add('GET', '/profile/edit', function () {
    $base = \Nexus\Core\TenantContext::getBasePath();
    header("Location: {$base}/settings?section=profile");
    exit;
});
$router->add('GET', '/profile/{id}', 'Nexus\Controllers\ProfileController@show');
$router->add('POST', '/profile/{id}', 'Nexus\Controllers\ProfileController@show'); // Allow POST for wall
$router->add('GET', '/profile', 'Nexus\Controllers\ProfileController@me');
$router->add('POST', '/profile', 'Nexus\Controllers\ProfileController@me'); // Allow POST for wall

// Connections (legacy PHP views removed — handled by React frontend and API)


// --------------------------------------------------------------------------
// 9. RESOURCES
// --------------------------------------------------------------------------
$router->add('GET', '/resources', 'Nexus\Controllers\ResourceController@index');
$router->add('GET', '/resources/create', 'Nexus\Controllers\ResourceController@create');
$router->add('POST', '/resources/store', 'Nexus\Controllers\ResourceController@store');

// Specific Resource Routes
$router->add('GET', '/resources/{id}/edit', 'Nexus\Controllers\ResourceController@edit');
$router->add('POST', '/resources/{id}/update', 'Nexus\Controllers\ResourceController@update');
$router->add('POST', '/resources/{id}/delete', 'Nexus\Controllers\ResourceController@destroy');
$router->add('GET', '/resources/{id}/download', 'Nexus\Controllers\ResourceController@download');
$router->add('GET', '/resources/{id}/file', 'Nexus\Controllers\ResourceController@file');



// --------------------------------------------------------------------------
// 10.7. SMART MATCHING
// --------------------------------------------------------------------------
$router->add('GET', '/matches', 'Nexus\Controllers\MatchController@index');
$router->add('GET', '/matches/hot', 'Nexus\Controllers\MatchController@hot');
$router->add('GET', '/matches/mutual', 'Nexus\Controllers\MatchController@mutual');
$router->add('GET', '/matches/preferences', 'Nexus\Controllers\MatchController@preferences');
$router->add('POST', '/matches/preferences', 'Nexus\Controllers\MatchController@preferences');
$router->add('GET', '/matches/api', 'Nexus\Controllers\MatchController@api');
$router->add('POST', '/matches/interact', 'Nexus\Controllers\MatchController@interact');
$router->add('GET', '/matches/stats', 'Nexus\Controllers\MatchController@stats');
$router->add('GET', '/matches/debug', 'Nexus\Controllers\MatchController@debug');

// --------------------------------------------------------------------------
// LEGACY ADMIN PANEL (PHP views — being decommissioned)
// React admin panel at /admin/* is the primary admin UI
// --------------------------------------------------------------------------

// --------------------------------------------------------------------------
// 10.8. ADMIN > SMART MATCHING
// --------------------------------------------------------------------------
$router->add('GET', '/admin-legacy/smart-matching', 'Nexus\Controllers\Admin\SmartMatchingController@index');
$router->add('GET', '/admin-legacy/smart-matching/analytics', 'Nexus\Controllers\Admin\SmartMatchingController@analytics');
$router->add('GET', '/admin-legacy/smart-matching/configuration', 'Nexus\Controllers\Admin\SmartMatchingController@configuration');
$router->add('POST', '/admin-legacy/smart-matching/configuration', 'Nexus\Controllers\Admin\SmartMatchingController@configuration');
$router->add('POST', '/admin-legacy/smart-matching/clear-cache', 'Nexus\Controllers\Admin\SmartMatchingController@clearCache');
$router->add('POST', '/admin-legacy/smart-matching/warmup-cache', 'Nexus\Controllers\Admin\SmartMatchingController@warmupCache');
$router->add('POST', '/admin-legacy/smart-matching/run-geocoding', 'Nexus\Controllers\Admin\SmartMatchingController@runGeocoding');
$router->add('GET', '/admin-legacy/smart-matching/api/stats', 'Nexus\Controllers\Admin\SmartMatchingController@apiStats');

// --------------------------------------------------------------------------
// 10.8.1. ADMIN > MATCH APPROVALS (Broker Workflow)
// --------------------------------------------------------------------------
$router->add('GET', '/admin-legacy/match-approvals', 'Nexus\Controllers\Admin\MatchApprovalsController@index');
$router->add('GET', '/admin-legacy/match-approvals/history', 'Nexus\Controllers\Admin\MatchApprovalsController@history');
$router->add('GET', '/admin-legacy/match-approvals/{id}', 'Nexus\Controllers\Admin\MatchApprovalsController@show');
$router->add('POST', '/admin-legacy/match-approvals/approve', 'Nexus\Controllers\Admin\MatchApprovalsController@approve');
$router->add('POST', '/admin-legacy/match-approvals/reject', 'Nexus\Controllers\Admin\MatchApprovalsController@reject');
$router->add('GET', '/admin-legacy/match-approvals/api/stats', 'Nexus\Controllers\Admin\MatchApprovalsController@apiStats');

// --------------------------------------------------------------------------
// 10.8.2. ADMIN > BROKER CONTROLS
// --------------------------------------------------------------------------
$router->add('GET', '/admin-legacy/broker-controls', 'Nexus\Controllers\Admin\BrokerControlsController@index');
$router->add('GET', '/admin-legacy/broker-controls/configuration', 'Nexus\Controllers\Admin\BrokerControlsController@configuration');
$router->add('POST', '/admin-legacy/broker-controls/configuration', 'Nexus\Controllers\Admin\BrokerControlsController@configuration');

// Broker Controls - Exchanges
$router->add('GET', '/admin-legacy/broker-controls/exchanges', 'Nexus\Controllers\Admin\BrokerControlsController@exchanges');
$router->add('GET', '/admin-legacy/broker-controls/exchanges/{id}', 'Nexus\Controllers\Admin\BrokerControlsController@showExchange');
$router->add('POST', '/admin-legacy/broker-controls/exchanges/{id}/approve', 'Nexus\Controllers\Admin\BrokerControlsController@approveExchange');
$router->add('POST', '/admin-legacy/broker-controls/exchanges/{id}/reject', 'Nexus\Controllers\Admin\BrokerControlsController@rejectExchange');

// Broker Controls - Risk Tags
$router->add('GET', '/admin-legacy/broker-controls/risk-tags', 'Nexus\Controllers\Admin\BrokerControlsController@riskTags');
$router->add('GET', '/admin-legacy/broker-controls/risk-tags/{listingId}', 'Nexus\Controllers\Admin\BrokerControlsController@tagListing');
$router->add('POST', '/admin-legacy/broker-controls/risk-tags/{listingId}', 'Nexus\Controllers\Admin\BrokerControlsController@tagListing');
$router->add('POST', '/admin-legacy/broker-controls/risk-tags/{listingId}/remove', 'Nexus\Controllers\Admin\BrokerControlsController@removeTag');

// Broker Controls - Messages
$router->add('GET', '/admin-legacy/broker-controls/messages', 'Nexus\Controllers\Admin\BrokerControlsController@messages');
$router->add('POST', '/admin-legacy/broker-controls/messages/{id}/review', 'Nexus\Controllers\Admin\BrokerControlsController@reviewMessage');
$router->add('POST', '/admin-legacy/broker-controls/messages/{id}/flag', 'Nexus\Controllers\Admin\BrokerControlsController@flagMessage');

// Broker Controls - User Monitoring
$router->add('GET', '/admin-legacy/broker-controls/monitoring', 'Nexus\Controllers\Admin\BrokerControlsController@userMonitoring');
$router->add('POST', '/admin-legacy/broker-controls/monitoring/{userId}', 'Nexus\Controllers\Admin\BrokerControlsController@setMonitoring');

// Broker Controls - Statistics
$router->add('GET', '/admin-legacy/broker-controls/stats', 'Nexus\Controllers\Admin\BrokerControlsController@stats');

// --------------------------------------------------------------------------
// 10.9. ADMIN > SEED GENERATOR
// --------------------------------------------------------------------------
$router->add('GET', '/admin-legacy/seed-generator', 'Nexus\Controllers\Admin\SeedGeneratorController@index');
$router->add('GET', '/admin-legacy/seed-generator/verification', 'Nexus\Controllers\Admin\SeedGeneratorVerificationController@index');
$router->add('POST', '/admin-legacy/seed-generator/generate-production', 'Nexus\Controllers\Admin\SeedGeneratorController@generateProduction');
$router->add('POST', '/admin-legacy/seed-generator/generate-demo', 'Nexus\Controllers\Admin\SeedGeneratorController@generateDemo');
$router->add('GET', '/admin-legacy/seed-generator/preview', 'Nexus\Controllers\Admin\SeedGeneratorController@preview');
$router->add('GET', '/admin-legacy/seed-generator/download', 'Nexus\Controllers\Admin\SeedGeneratorController@download');
$router->add('GET', '/admin-legacy/seed-generator/test', 'Nexus\Controllers\Admin\SeedGeneratorVerificationController@runLiveTest');

// --------------------------------------------------------------------------
// 11. WALLET
// --------------------------------------------------------------------------
$router->add('GET', '/wallet', 'Nexus\Controllers\WalletController@index');
$router->add('POST', '/wallet/transfer', 'Nexus\Controllers\WalletController@transfer');

// User Insights
$router->add('GET', '/wallet/insights', 'Nexus\Controllers\InsightsController@index');
$router->add('GET', '/insights', 'Nexus\Controllers\InsightsController@index'); // Alias
$router->add('GET', '/api/insights', 'Nexus\Controllers\InsightsController@apiInsights');

// --------------------------------------------------------------------------
// 11.5. ORGANIZATION WALLETS
// --------------------------------------------------------------------------
$router->add('GET', '/organizations/{id}/wallet', 'Nexus\Controllers\OrgWalletController@index');
$router->add('POST', '/organizations/{id}/wallet/deposit', 'Nexus\Controllers\OrgWalletController@deposit');
$router->add('POST', '/organizations/{id}/wallet/request', 'Nexus\Controllers\OrgWalletController@requestTransfer');
$router->add('POST', '/organizations/{id}/wallet/approve/{requestId}', 'Nexus\Controllers\OrgWalletController@approve');
$router->add('POST', '/organizations/{id}/wallet/reject/{requestId}', 'Nexus\Controllers\OrgWalletController@reject');
$router->add('POST', '/organizations/{id}/wallet/cancel/{requestId}', 'Nexus\Controllers\OrgWalletController@cancel');
$router->add('POST', '/organizations/{id}/wallet/direct-transfer', 'Nexus\Controllers\OrgWalletController@directTransfer');
$router->add('GET', '/organizations/{id}/wallet/requests', 'Nexus\Controllers\OrgWalletController@requests');
$router->add('GET', '/organizations/{id}/members', 'Nexus\Controllers\OrgWalletController@members');
$router->add('POST', '/organizations/{id}/members/invite', 'Nexus\Controllers\OrgWalletController@inviteMember');
$router->add('POST', '/organizations/{id}/members/approve', 'Nexus\Controllers\OrgWalletController@approveMember');
$router->add('POST', '/organizations/{id}/members/reject', 'Nexus\Controllers\OrgWalletController@rejectMember');
$router->add('POST', '/organizations/{id}/members/role', 'Nexus\Controllers\OrgWalletController@updateMemberRole');
$router->add('POST', '/organizations/{id}/members/remove', 'Nexus\Controllers\OrgWalletController@removeMember');
$router->add('POST', '/organizations/{id}/members/request', 'Nexus\Controllers\OrgWalletController@requestMembership');
$router->add('POST', '/organizations/{id}/members/transfer-ownership', 'Nexus\Controllers\OrgWalletController@transferOwnership');
$router->add('GET', '/api/organizations/{id}/members', 'Nexus\Controllers\OrgWalletController@apiMembers');
$router->add('GET', '/api/organizations/{id}/wallet/balance', 'Nexus\Controllers\OrgWalletController@apiBalance');

// Export endpoints
$router->add('GET', '/organizations/{id}/wallet/export', 'Nexus\Controllers\OrgWalletController@exportTransactions');
$router->add('GET', '/organizations/{id}/wallet/requests/export', 'Nexus\Controllers\OrgWalletController@exportRequests');
$router->add('GET', '/organizations/{id}/members/export', 'Nexus\Controllers\OrgWalletController@exportMembers');

// Bulk operations
$router->add('POST', '/organizations/{id}/wallet/bulk-approve', 'Nexus\Controllers\OrgWalletController@bulkApprove');
$router->add('POST', '/organizations/{id}/wallet/bulk-reject', 'Nexus\Controllers\OrgWalletController@bulkReject');

// Audit log
$router->add('GET', '/organizations/{id}/audit-log', 'Nexus\Controllers\OrgWalletController@auditLog');
$router->add('GET', '/organizations/{id}/audit-log/export', 'Nexus\Controllers\OrgWalletController@exportAuditLog');

// --------------------------------------------------------------------------
// 10. FEED (Redirects to home - legacy support)
// --------------------------------------------------------------------------
$router->add('GET', '/feed', function() {
    header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/');
    exit;
});
$router->add('GET', '/post/{id}', 'Nexus\Controllers\FeedController@show');
$router->add('POST', '/feed/store', 'Nexus\Controllers\FeedController@store');
$router->add('POST', '/api/feed/hide', 'Nexus\Controllers\FeedController@hidePost');
$router->add('POST', '/api/feed/mute', 'Nexus\Controllers\FeedController@muteUser');
$router->add('POST', '/api/feed/report', 'Nexus\Controllers\FeedController@reportPost');

// --------------------------------------------------------------------------
// 10.5. MESSAGES
// --------------------------------------------------------------------------
$router->add('GET', '/messages', 'Nexus\Controllers\MessageController@index');
$router->add('GET', '/messages/new', 'Nexus\Controllers\MessageController@newMessage'); // New message / user search
$router->add('GET', '/messages/create', 'Nexus\Controllers\MessageController@create');
$router->add('GET', '/messages/compose', 'Nexus\Controllers\MessageController@create'); // Alias for create
$router->add('POST', '/messages/store', 'Nexus\Controllers\MessageController@store');
$router->add('POST', '/api/messages/voice', 'Nexus\Controllers\Api\VoiceMessageController@store'); // Voice message upload
$router->add('POST', '/api/messages/send', 'Nexus\Controllers\Api\CoreApiController@sendMessage'); // Send message via AJAX
$router->add('POST', '/api/messages/typing', 'Nexus\Controllers\Api\CoreApiController@typing'); // Typing indicator
$router->add('GET', '/api/messages/poll', 'Nexus\Controllers\Api\CoreApiController@pollMessages'); // Real-time polling fallback
$router->add('GET', '/api/messages/unread-count', 'Nexus\Controllers\Api\CoreApiController@unreadMessagesCount'); // Unread message count
$router->add('POST', '/api/messages/delete', 'Nexus\Controllers\MessageController@deleteMessage'); // Delete single message
$router->add('POST', '/api/messages/delete-conversation', 'Nexus\Controllers\MessageController@deleteConversation'); // Delete entire conversation
$router->add('POST', '/api/messages/reaction', 'Nexus\Controllers\MessageController@toggleReaction'); // Add/remove reaction
$router->add('GET', '/api/messages/reactions-batch', 'Nexus\Controllers\MessageController@getReactionsBatch'); // Get reactions for multiple messages
$router->add('GET', '/messages/thread/{id}', 'Nexus\Controllers\MessageController@show'); // Thread view (alternate URL)
$router->add('GET', '/messages/{id}', 'Nexus\Controllers\MessageController@show');
// NOTE: Reply handled via /messages/store with receiver_id

// --------------------------------------------------------------------------
// LEGACY ADMIN PANEL (PHP views — being decommissioned)
// React admin panel at /admin/* is the primary admin UI
// --------------------------------------------------------------------------

// --------------------------------------------------------------------------
// 12. ADMIN DASHBOARD
// --------------------------------------------------------------------------
$router->add('GET', '/admin-legacy', 'Nexus\Controllers\AdminController@index');
$router->add('GET', '/admin-legacy/activity-log', 'Nexus\Controllers\AdminController@activityLogs');
$router->add('GET', '/admin-legacy/group-locations', 'Nexus\Controllers\AdminController@groupLocations');
$router->add('POST', '/admin-legacy/group-locations', 'Nexus\Controllers\AdminController@groupLocations');
$router->add('GET', '/admin-legacy/geocode-groups', 'Nexus\Controllers\AdminController@geocodeGroups');
$router->add('GET', '/admin-legacy/smart-match-users', 'Nexus\Controllers\AdminController@smartMatchUsers');
$router->add('GET', '/admin-legacy/smart-match-monitoring', 'Nexus\Controllers\AdminController@smartMatchMonitoring');
// Removed: /admin/test-smart-match (debug endpoint)

// WebP Image Converter
$router->add('GET', '/admin-legacy/webp-converter', 'Nexus\Controllers\AdminController@webpConverter');
$router->add('POST', '/admin-legacy/webp-converter/convert', 'Nexus\Controllers\AdminController@webpConvertBatch');

// Group Ranking Management
$router->add('GET', '/admin-legacy/group-ranking', 'Nexus\Controllers\AdminController@groupRanking');
$router->add('POST', '/admin-legacy/group-ranking/update', 'Nexus\Controllers\AdminController@updateFeaturedGroups');
$router->add('POST', '/admin-legacy/group-ranking/toggle', 'Nexus\Controllers\AdminController@toggleFeaturedGroup');
// Removed: /admin/test-ranking (debug endpoint)

// Cron Endpoints
$router->add('GET', '/admin-legacy/cron/update-featured-groups', 'Nexus\Controllers\AdminController@cronUpdateFeaturedGroups');

// Group Types Management
$router->add('GET', '/admin-legacy/group-types', 'Nexus\Controllers\AdminController@groupTypes');
$router->add('POST', '/admin-legacy/group-types', 'Nexus\Controllers\AdminController@groupTypes');
$router->add('GET', '/admin-legacy/group-types/create', 'Nexus\Controllers\AdminController@groupTypeForm');
$router->add('GET', '/admin-legacy/group-types/edit/{id}', 'Nexus\Controllers\AdminController@groupTypeForm');
$router->add('POST', '/admin-legacy/group-types/edit/{id}', 'Nexus\Controllers\AdminController@groupTypeForm');

// User Management - MOVED TO Nexus\Controllers\Admin\UserController
// See lines 252+ 

// Listing Management

// Listing Management
$router->add('POST', '/admin-legacy/listings/delete', 'Nexus\Controllers\AdminController@deleteListing');

// Settings (User Hub) - Defined in Public Pages section (Line 284)
$router->add('GET', '/admin-legacy/settings', 'Nexus\Controllers\AdminController@settings');
$router->add('POST', '/admin-legacy/settings/update', 'Nexus\Controllers\AdminController@saveSettings');
$router->add('POST', '/admin-legacy/settings/save-tenant', 'Nexus\Controllers\AdminController@saveTenantSettings');
$router->add('POST', '/admin-legacy/settings/test-gmail', 'Nexus\Controllers\AdminController@testGmailConnection');
$router->add('POST', '/admin-legacy/settings/regenerate-css', 'Nexus\Controllers\AdminController@regenerateMinifiedCSS');

// Image Optimization Settings
$router->add('GET', '/admin-legacy/image-settings', 'Nexus\Controllers\AdminController@imageSettings');
$router->add('POST', '/admin-legacy/image-settings/save', 'Nexus\Controllers\AdminController@saveImageSettings');

// Tenant Admin Federation Dashboard
$router->add('GET', '/admin-legacy/federation/dashboard', 'Nexus\Controllers\FederationAdminController@index');
$router->add('POST', '/admin-legacy/federation/dashboard/toggle', 'Nexus\Controllers\FederationAdminController@toggleFederation');
$router->add('POST', '/admin-legacy/federation/dashboard/settings', 'Nexus\Controllers\FederationAdminController@updateSettings');

// Tenant Admin Federation Settings
$router->add('GET', '/admin-legacy/federation', 'Nexus\Controllers\Admin\FederationSettingsController@index');
$router->add('POST', '/admin-legacy/federation/update-feature', 'Nexus\Controllers\Admin\FederationSettingsController@updateFeature');
$router->add('GET', '/admin-legacy/federation/partnerships', 'Nexus\Controllers\Admin\FederationSettingsController@partnerships');
$router->add('POST', '/admin-legacy/federation/request-partnership', 'Nexus\Controllers\Admin\FederationSettingsController@requestPartnership');
$router->add('POST', '/admin-legacy/federation/approve-partnership', 'Nexus\Controllers\Admin\FederationSettingsController@approvePartnership');
$router->add('POST', '/admin-legacy/federation/reject-partnership', 'Nexus\Controllers\Admin\FederationSettingsController@rejectPartnership');
$router->add('POST', '/admin-legacy/federation/update-partnership-permissions', 'Nexus\Controllers\Admin\FederationSettingsController@updatePartnershipPermissions');
$router->add('POST', '/admin-legacy/federation/terminate-partnership', 'Nexus\Controllers\Admin\FederationSettingsController@terminatePartnership');
$router->add('POST', '/admin-legacy/federation/counter-propose', 'Nexus\Controllers\Admin\FederationSettingsController@counterPropose');
$router->add('POST', '/admin-legacy/federation/accept-counter-proposal', 'Nexus\Controllers\Admin\FederationSettingsController@acceptCounterProposal');
$router->add('POST', '/admin-legacy/federation/withdraw-request', 'Nexus\Controllers\Admin\FederationSettingsController@withdrawRequest');

// Federation Directory
$router->add('GET', '/admin-legacy/federation/directory', 'Nexus\Controllers\Admin\FederationDirectoryController@index');
$router->add('GET', '/admin-legacy/federation/directory/api', 'Nexus\Controllers\Admin\FederationDirectoryController@api');
$router->add('GET', '/admin-legacy/federation/directory/profile', 'Nexus\Controllers\Admin\FederationDirectoryController@profile');
$router->add('POST', '/admin-legacy/federation/directory/update-profile', 'Nexus\Controllers\Admin\FederationDirectoryController@updateProfile');
$router->add('POST', '/admin-legacy/federation/directory/request-partnership', 'Nexus\Controllers\Admin\FederationDirectoryController@requestPartnership');
$router->add('GET', '/admin-legacy/federation/directory/{id}', 'Nexus\Controllers\Admin\FederationDirectoryController@show');

// Federation Analytics
$router->add('GET', '/admin-legacy/federation/analytics', 'Nexus\Controllers\Admin\FederationAnalyticsController@index');
$router->add('GET', '/admin-legacy/federation/analytics/api', 'Nexus\Controllers\Admin\FederationAnalyticsController@api');
$router->add('GET', '/admin-legacy/federation/analytics/export', 'Nexus\Controllers\Admin\FederationAnalyticsController@export');

// Federation API Keys Management
$router->add('GET', '/admin-legacy/federation/api-keys', 'Nexus\Controllers\Admin\FederationApiKeysController@index');
$router->add('GET', '/admin-legacy/federation/api-keys/create', 'Nexus\Controllers\Admin\FederationApiKeysController@create');
$router->add('POST', '/admin-legacy/federation/api-keys/store', 'Nexus\Controllers\Admin\FederationApiKeysController@store');
$router->add('GET', '/admin-legacy/federation/api-keys/{id}', 'Nexus\Controllers\Admin\FederationApiKeysController@show');
$router->add('POST', '/admin-legacy/federation/api-keys/{id}/suspend', 'Nexus\Controllers\Admin\FederationApiKeysController@suspend');
$router->add('POST', '/admin-legacy/federation/api-keys/{id}/activate', 'Nexus\Controllers\Admin\FederationApiKeysController@activate');
$router->add('POST', '/admin-legacy/federation/api-keys/{id}/revoke', 'Nexus\Controllers\Admin\FederationApiKeysController@revoke');
$router->add('POST', '/admin-legacy/federation/api-keys/{id}/regenerate', 'Nexus\Controllers\Admin\FederationApiKeysController@regenerate');

// Federation Data Import/Export
$router->add('GET', '/admin-legacy/federation/data', 'Nexus\Controllers\Admin\FederationExportController@index');
$router->add('GET', '/admin-legacy/federation/export/users', 'Nexus\Controllers\Admin\FederationExportController@exportUsers');
$router->add('GET', '/admin-legacy/federation/export/partnerships', 'Nexus\Controllers\Admin\FederationExportController@exportPartnerships');
$router->add('GET', '/admin-legacy/federation/export/transactions', 'Nexus\Controllers\Admin\FederationExportController@exportTransactions');
$router->add('GET', '/admin-legacy/federation/export/audit', 'Nexus\Controllers\Admin\FederationExportController@exportAudit');
$router->add('GET', '/admin-legacy/federation/export/all', 'Nexus\Controllers\Admin\FederationExportController@exportAll');
$router->add('POST', '/admin-legacy/federation/import/users', 'Nexus\Controllers\Admin\FederationImportController@importUsers');
$router->add('GET', '/admin-legacy/federation/import/template', 'Nexus\Controllers\Admin\FederationImportController@downloadTemplate');

// External Federation Partners (connections to servers outside this installation)
$router->add('GET', '/admin-legacy/federation/external-partners', 'Nexus\Controllers\Admin\FederationExternalPartnersController@index');
$router->add('GET', '/admin-legacy/federation/external-partners/create', 'Nexus\Controllers\Admin\FederationExternalPartnersController@create');
$router->add('POST', '/admin-legacy/federation/external-partners/store', 'Nexus\Controllers\Admin\FederationExternalPartnersController@store');
$router->add('GET', '/admin-legacy/federation/external-partners/{id}', 'Nexus\Controllers\Admin\FederationExternalPartnersController@show');
$router->add('POST', '/admin-legacy/federation/external-partners/{id}/update', 'Nexus\Controllers\Admin\FederationExternalPartnersController@update');
$router->add('POST', '/admin-legacy/federation/external-partners/{id}/test', 'Nexus\Controllers\Admin\FederationExternalPartnersController@test');
$router->add('POST', '/admin-legacy/federation/external-partners/{id}/suspend', 'Nexus\Controllers\Admin\FederationExternalPartnersController@suspend');
$router->add('POST', '/admin-legacy/federation/external-partners/{id}/activate', 'Nexus\Controllers\Admin\FederationExternalPartnersController@activate');
$router->add('POST', '/admin-legacy/federation/external-partners/{id}/delete', 'Nexus\Controllers\Admin\FederationExternalPartnersController@delete');

// Native App Management (FCM Push Notifications)
$router->add('GET', '/admin-legacy/native-app', 'Nexus\Controllers\AdminController@nativeApp');
$router->add('POST', '/admin-legacy/native-app/test-push', 'Nexus\Controllers\AdminController@sendTestPush');

// Feed Algorithm (EdgeRank) Settings
$router->add('GET', '/admin-legacy/feed-algorithm', 'Nexus\Controllers\AdminController@feedAlgorithm');
$router->add('POST', '/admin-legacy/feed-algorithm/save', 'Nexus\Controllers\AdminController@saveFeedAlgorithm');

// --------------------------------------------------------------------------
// DELIVERABILITY TRACKING MODULE
// --------------------------------------------------------------------------

// Dashboard & List Views
$router->add('GET', '/admin-legacy/deliverability', 'Nexus\Controllers\AdminController@deliverabilityDashboard');
$router->add('GET', '/admin-legacy/deliverability/list', 'Nexus\Controllers\AdminController@deliverablesList');
$router->add('GET', '/admin-legacy/deliverability/analytics', 'Nexus\Controllers\AdminController@deliverabilityAnalytics');

// CRUD Operations
$router->add('GET', '/admin-legacy/deliverability/create', 'Nexus\Controllers\AdminController@deliverableCreate');
$router->add('POST', '/admin-legacy/deliverability/store', 'Nexus\Controllers\AdminController@deliverableStore');
$router->add('GET', '/admin-legacy/deliverability/view/{id}', 'Nexus\Controllers\AdminController@deliverableView');
$router->add('GET', '/admin-legacy/deliverability/edit/{id}', 'Nexus\Controllers\AdminController@deliverableEdit');
$router->add('POST', '/admin-legacy/deliverability/update/{id}', 'Nexus\Controllers\AdminController@deliverableUpdate');
$router->add('POST', '/admin-legacy/deliverability/delete/{id}', 'Nexus\Controllers\AdminController@deliverableDelete');

// AJAX Endpoints
$router->add('POST', '/admin-legacy/deliverability/ajax/update-status', 'Nexus\Controllers\AdminController@deliverableUpdateStatus');
$router->add('POST', '/admin-legacy/deliverability/ajax/complete-milestone', 'Nexus\Controllers\AdminController@milestoneComplete');
$router->add('POST', '/admin-legacy/deliverability/ajax/add-comment', 'Nexus\Controllers\AdminController@deliverableAddComment');

// --------------------------------------------------------------------------
// LAYOUT SYSTEM COMPLETELY REMOVED - All page layout routes obliterated
// --------------------------------------------------------------------------

// Unified Algorithm Settings (MatchRank for Listings, CommunityRank for Members)
$router->add('GET', '/admin-legacy/algorithm-settings', 'Nexus\Controllers\AdminController@algorithmSettings');
$router->add('POST', '/admin-legacy/algorithm-settings/save', 'Nexus\Controllers\AdminController@saveAlgorithmSettings');

// Admin Live Search API (for command palette)
$router->add('GET', '/admin-legacy/api/search', 'Nexus\Controllers\AdminController@liveSearch');

// --------------------------------------------------------------------------
// 12.5. ADMIN > CATEGORIES & ATTRIBUTES
// --------------------------------------------------------------------------
$router->add('GET', '/admin-legacy/categories', 'Nexus\Controllers\Admin\CategoryController@index');
$router->add('GET', '/admin-legacy/categories/create', 'Nexus\Controllers\Admin\CategoryController@create');
$router->add('POST', '/admin-legacy/categories/store', 'Nexus\Controllers\Admin\CategoryController@store');

// Volunteering Admin
$router->add('GET', '/admin-legacy/volunteering', 'Nexus\Controllers\Admin\VolunteeringController@index');
$router->add('GET', '/admin-legacy/volunteering/approvals', 'Nexus\Controllers\Admin\VolunteeringController@approvals');
$router->add('GET', '/admin-legacy/volunteering/organizations', 'Nexus\Controllers\Admin\VolunteeringController@organizations');
$router->add('POST', '/admin-legacy/volunteering/approve', 'Nexus\Controllers\Admin\VolunteeringController@approve');
$router->add('POST', '/admin-legacy/volunteering/decline', 'Nexus\Controllers\Admin\VolunteeringController@decline');
$router->add('POST', '/admin-legacy/volunteering/delete', 'Nexus\Controllers\Admin\VolunteeringController@deleteOrg');
$router->add('GET', '/admin-legacy/categories/edit', function () {
    header('Location: /admin-legacy/categories');
    exit;
}); // Fallback
$router->add('GET', '/admin-legacy/categories/edit/{id}', 'Nexus\Controllers\Admin\CategoryController@edit');
$router->add('POST', '/admin-legacy/categories/update', 'Nexus\Controllers\Admin\CategoryController@update'); // Often forms POST to generic update
$router->add('POST', '/admin-legacy/categories/delete', 'Nexus\Controllers\Admin\CategoryController@delete'); // POST to delete

$router->add('GET', '/admin-legacy/attributes', 'Nexus\Controllers\Admin\AttributeController@index');
$router->add('GET', '/admin-legacy/attributes/create', 'Nexus\Controllers\Admin\AttributeController@create');
$router->add('POST', '/admin-legacy/attributes/store', 'Nexus\Controllers\Admin\AttributeController@store');
$router->add('GET', '/admin-legacy/attributes/edit', function () {
    header('Location: /admin-legacy/attributes');
    exit;
}); // Fallback
$router->add('GET', '/admin-legacy/attributes/edit/{id}', 'Nexus\Controllers\Admin\AttributeController@edit');
$router->add('POST', '/admin-legacy/attributes/update', 'Nexus\Controllers\Admin\AttributeController@update');
$router->add('POST', '/admin-legacy/attributes/delete', 'Nexus\Controllers\Admin\AttributeController@delete');

// Admin Pages
$router->add('GET', '/admin-legacy/pages', 'Nexus\Controllers\Admin\PageController@index');
$router->add('GET', '/admin-legacy/pages/create', 'Nexus\Controllers\Admin\PageController@create');
$router->add('GET', '/admin-legacy/pages/builder/{id}', 'Nexus\Controllers\Admin\PageController@builder');
$router->add('GET', '/admin-legacy/pages/preview/{id}', 'Nexus\Controllers\Admin\PageController@preview');
$router->add('GET', '/admin-legacy/pages/versions/{id}', 'Nexus\Controllers\Admin\PageController@versions');
$router->add('GET', '/admin-legacy/pages/duplicate/{id}', 'Nexus\Controllers\Admin\PageController@duplicate');
$router->add('GET', '/admin-legacy/pages/version-content/{id}', 'Nexus\Controllers\Admin\PageController@versionContent');
$router->add('POST', '/admin-legacy/pages/save', 'Nexus\Controllers\Admin\PageController@save');
$router->add('POST', '/admin-legacy/pages/restore-version', 'Nexus\Controllers\Admin\PageController@restoreVersion');
$router->add('POST', '/admin-legacy/pages/reorder', 'Nexus\Controllers\Admin\PageController@reorder');
$router->add('POST', '/admin-legacy/pages/delete', 'Nexus\Controllers\Admin\PageController@delete');

// Page Builder V2 API
$router->add('POST', '/admin-legacy/api/pages/{id}/blocks', 'Nexus\Controllers\Admin\PageController@saveBlocks');
$router->add('GET', '/admin-legacy/api/pages/{id}/blocks', 'Nexus\Controllers\Admin\PageController@getBlocks');
$router->add('POST', '/admin-legacy/api/blocks/preview', 'Nexus\Controllers\Admin\PageController@previewBlock');
$router->add('POST', '/admin-legacy/api/pages/{id}/settings', 'Nexus\Controllers\Admin\PageController@saveSettings');

// Admin Legal Documents (Version-Controlled Terms/Privacy/etc.)
$router->add('GET', '/admin-legacy/legal-documents', 'Nexus\Controllers\Admin\LegalDocumentsController@index');
$router->add('GET', '/admin-legacy/legal-documents/create', 'Nexus\Controllers\Admin\LegalDocumentsController@create');
$router->add('POST', '/admin-legacy/legal-documents', 'Nexus\Controllers\Admin\LegalDocumentsController@store');
$router->add('GET', '/admin-legacy/legal-documents/compliance', 'Nexus\Controllers\Admin\LegalDocumentsController@compliance');
$router->add('GET', '/admin-legacy/legal-documents/{id}', 'Nexus\Controllers\Admin\LegalDocumentsController@show');
$router->add('GET', '/admin-legacy/legal-documents/{id}/edit', 'Nexus\Controllers\Admin\LegalDocumentsController@edit');
$router->add('POST', '/admin-legacy/legal-documents/{id}', 'Nexus\Controllers\Admin\LegalDocumentsController@update');
$router->add('GET', '/admin-legacy/legal-documents/{id}/versions/create', 'Nexus\Controllers\Admin\LegalDocumentsController@createVersion');
$router->add('POST', '/admin-legacy/legal-documents/{id}/versions', 'Nexus\Controllers\Admin\LegalDocumentsController@storeVersion');
$router->add('GET', '/admin-legacy/legal-documents/{id}/versions/{versionId}', 'Nexus\Controllers\Admin\LegalDocumentsController@showVersion');
$router->add('GET', '/admin-legacy/legal-documents/{id}/versions/{versionId}/edit', 'Nexus\Controllers\Admin\LegalDocumentsController@editVersion');
$router->add('POST', '/admin-legacy/legal-documents/{id}/versions/{versionId}', 'Nexus\Controllers\Admin\LegalDocumentsController@updateVersion');
$router->add('POST', '/admin-legacy/legal-documents/{id}/versions/{versionId}/publish', 'Nexus\Controllers\Admin\LegalDocumentsController@publishVersion');
$router->add('POST', '/admin-legacy/legal-documents/{id}/versions/{versionId}/delete', 'Nexus\Controllers\Admin\LegalDocumentsController@deleteVersion');
$router->add('POST', '/admin-legacy/legal-documents/{id}/versions/{versionId}/notify', 'Nexus\Controllers\Admin\LegalDocumentsController@notifyUsers');
$router->add('GET', '/admin-legacy/legal-documents/{id}/versions/{versionId}/acceptances', 'Nexus\Controllers\Admin\LegalDocumentsController@acceptances');
$router->add('GET', '/admin-legacy/legal-documents/{id}/compare', 'Nexus\Controllers\Admin\LegalDocumentsController@compareVersions');
$router->add('GET', '/admin-legacy/legal-documents/{id}/export', 'Nexus\Controllers\Admin\LegalDocumentsController@exportAcceptances');

// Admin Menus (Menu Manager)
$router->add('GET', '/admin-legacy/menus', 'Nexus\Controllers\Admin\MenuController@index');
$router->add('GET', '/admin-legacy/menus/create', 'Nexus\Controllers\Admin\MenuController@create');
$router->add('POST', '/admin-legacy/menus/create', 'Nexus\Controllers\Admin\MenuController@create');
$router->add('GET', '/admin-legacy/menus/builder/{id}', 'Nexus\Controllers\Admin\MenuController@builder');
$router->add('POST', '/admin-legacy/menus/update/{id}', 'Nexus\Controllers\Admin\MenuController@update');
$router->add('POST', '/admin-legacy/menus/toggle/{id}', 'Nexus\Controllers\Admin\MenuController@toggleActive');
$router->add('POST', '/admin-legacy/menus/delete/{id}', 'Nexus\Controllers\Admin\MenuController@delete');
$router->add('POST', '/admin-legacy/menus/item/add', 'Nexus\Controllers\Admin\MenuController@addItem');
$router->add('GET', '/admin-legacy/menus/item/{id}', 'Nexus\Controllers\Admin\MenuController@getItem');
$router->add('POST', '/admin-legacy/menus/item/update/{id}', 'Nexus\Controllers\Admin\MenuController@updateItem');
$router->add('POST', '/admin-legacy/menus/item/delete/{id}', 'Nexus\Controllers\Admin\MenuController@deleteItem');
$router->add('POST', '/admin-legacy/menus/items/reorder', 'Nexus\Controllers\Admin\MenuController@reorder');
$router->add('POST', '/admin-legacy/menus/cache/clear', 'Nexus\Controllers\Admin\MenuController@clearCache');
$router->add('POST', '/admin-legacy/menus/bulk', 'Nexus\Controllers\Admin\MenuController@bulk');

// Admin Plans (Subscription Manager)
$router->add('GET', '/admin-legacy/plans', 'Nexus\Controllers\Admin\PlanController@index');
$router->add('GET', '/admin-legacy/plans/create', 'Nexus\Controllers\Admin\PlanController@create');
$router->add('POST', '/admin-legacy/plans/create', 'Nexus\Controllers\Admin\PlanController@create');
$router->add('GET', '/admin-legacy/plans/edit/{id}', 'Nexus\Controllers\Admin\PlanController@edit');
$router->add('POST', '/admin-legacy/plans/edit/{id}', 'Nexus\Controllers\Admin\PlanController@edit');
$router->add('POST', '/admin-legacy/plans/delete/{id}', 'Nexus\Controllers\Admin\PlanController@delete');
$router->add('GET', '/admin-legacy/plans/subscriptions', 'Nexus\Controllers\Admin\PlanController@subscriptions');
$router->add('POST', '/admin-legacy/plans/assign', 'Nexus\Controllers\Admin\PlanController@assignPlan');
$router->add('GET', '/admin-legacy/plans/comparison', 'Nexus\Controllers\Admin\PlanController@comparison');

// Admin News (Blog)
$router->add('GET', '/admin-legacy/news', 'Nexus\Controllers\Admin\BlogController@index');
$router->add('GET', '/admin-legacy/news/create', 'Nexus\Controllers\Admin\BlogController@create');
$router->add('GET', '/admin-legacy/news/edit/{id}', 'Nexus\Controllers\Admin\BlogController@edit');
$router->add('GET', '/admin-legacy/news/builder/{id}', 'Nexus\Controllers\Admin\BlogController@builder');
$router->add('POST', '/admin-legacy/news/save-builder', 'Nexus\Controllers\Admin\BlogController@saveBuilder');
$router->add('POST', '/admin-legacy/news/update', 'Nexus\Controllers\Admin\BlogController@update');
$router->add('GET', '/admin-legacy/news/delete/{id}', 'Nexus\Controllers\Admin\BlogController@delete');

// Legacy Aliases (admin/blog)
$router->add('GET', '/admin-legacy/blog', 'Nexus\Controllers\Admin\BlogController@index');
$router->add('GET', '/admin-legacy/blog/create', 'Nexus\Controllers\Admin\BlogController@create');
$router->add('GET', '/admin-legacy/blog/edit/{id}', 'Nexus\Controllers\Admin\BlogController@edit');
$router->add('GET', '/admin-legacy/blog/builder/{id}', 'Nexus\Controllers\Admin\BlogController@builder');
$router->add('POST', '/admin-legacy/blog/save-builder', 'Nexus\Controllers\Admin\BlogController@saveBuilder');
$router->add('POST', '/admin-legacy/blog/update/{id}', 'Nexus\Controllers\Admin\BlogController@update'); // Note: Added {id} to match form if needed, or generic
$router->add('POST', '/admin-legacy/blog/store', 'Nexus\Controllers\Admin\BlogController@store');
$router->add('POST', '/admin-legacy/blog/delete', 'Nexus\Controllers\Admin\BlogController@delete');

// Admin Blog Restore
$router->add('GET', '/admin-legacy/blog-restore', 'Nexus\Controllers\Admin\BlogRestoreController@index');
$router->add('GET', '/admin-legacy/blog-restore/diagnostic', 'Nexus\Controllers\Admin\BlogRestoreController@diagnostic');
$router->add('POST', '/admin-legacy/blog-restore/upload', 'Nexus\Controllers\Admin\BlogRestoreController@upload');
$router->add('POST', '/admin-legacy/blog-restore/import', 'Nexus\Controllers\Admin\BlogRestoreController@import');
$router->add('GET', '/admin-legacy/blog-restore/export', 'Nexus\Controllers\Admin\BlogRestoreController@downloadExport');

// Admin Nexus Score Analytics
$router->add('GET', '/admin-legacy/nexus-score/analytics', 'Nexus\Controllers\NexusScoreController@adminAnalytics');

// Admin Users
$router->add('GET', '/admin-legacy/users', 'Nexus\Controllers\Admin\UserController@index');
$router->add('GET', '/admin-legacy/users/create', 'Nexus\Controllers\Admin\UserController@create');
$router->add('POST', '/admin-legacy/users/store', 'Nexus\Controllers\Admin\UserController@store');
$router->add('GET', '/admin-legacy/users/edit', function () {
    header('Location: /admin-legacy/users');
    exit;
}); // Fallback
$router->add('GET', '/admin-legacy/users/edit/{id}', 'Nexus\Controllers\Admin\UserController@edit');
$router->add('GET', '/admin-legacy/users/{id}/edit', 'Nexus\Controllers\Admin\UserController@edit'); // Standard REST Alias
$router->add('GET', '/admin-legacy/users/{id}/permissions', 'Nexus\Controllers\Admin\UserController@permissions');
$router->add('POST', '/admin-legacy/users/update', 'Nexus\Controllers\Admin\UserController@update');
$router->add('POST', '/admin-legacy/users/delete', 'Nexus\Controllers\Admin\UserController@delete');
$router->add('POST', '/admin-legacy/users/suspend', 'Nexus\Controllers\Admin\UserController@suspend');
$router->add('POST', '/admin-legacy/users/ban', 'Nexus\Controllers\Admin\UserController@ban');
$router->add('POST', '/admin-legacy/users/reactivate', 'Nexus\Controllers\Admin\UserController@reactivate');
$router->add('POST', '/admin-legacy/users/revoke-super-admin', 'Nexus\Controllers\Admin\UserController@revokeSuperAdmin');
$router->add('POST', '/admin-legacy/users/{id}/reset-2fa', 'Nexus\Controllers\Admin\UserController@reset2fa');
$router->add('POST', '/admin-legacy/approve-user', 'Nexus\Controllers\Admin\UserController@approve');
$router->add('POST', '/admin-legacy/users/badges/add', 'Nexus\Controllers\Admin\UserController@addBadge');
$router->add('POST', '/admin-legacy/users/badges/remove', 'Nexus\Controllers\Admin\UserController@removeBadge');
$router->add('POST', '/admin-legacy/users/badges/recheck', 'Nexus\Controllers\Admin\UserController@recheckBadges');
$router->add('POST', '/admin-legacy/users/badges/bulk-award', 'Nexus\Controllers\Admin\UserController@bulkAwardBadge');
$router->add('POST', '/admin-legacy/users/badges/recheck-all', 'Nexus\Controllers\Admin\UserController@recheckAllBadges');

// Admin Impersonation
$router->add('POST', '/admin-legacy/impersonate', 'Nexus\Controllers\AuthController@impersonate');
$router->add('GET', '/admin-legacy/stop-impersonating', 'Nexus\Controllers\AuthController@stopImpersonating');
$router->add('POST', '/admin-legacy/stop-impersonating', 'Nexus\Controllers\AuthController@stopImpersonating');

// Admin Groups
$router->add('GET', '/admin-legacy/groups', 'Nexus\Controllers\Admin\GroupAdminController@index');
$router->add('GET', '/admin-legacy/groups/analytics', 'Nexus\Controllers\Admin\GroupAdminController@analytics');
$router->add('GET', '/admin-legacy/groups/recommendations', 'Nexus\Controllers\Admin\GroupAdminController@recommendations');
$router->add('GET', '/admin-legacy/groups/view', 'Nexus\Controllers\Admin\GroupAdminController@view');
$router->add('GET', '/admin-legacy/groups/settings', 'Nexus\Controllers\Admin\GroupAdminController@settings');
$router->add('POST', '/admin-legacy/groups/settings', 'Nexus\Controllers\Admin\GroupAdminController@saveSettings');
$router->add('GET', '/admin-legacy/groups/policies', 'Nexus\Controllers\Admin\GroupAdminController@policies');
$router->add('POST', '/admin-legacy/groups/policies', 'Nexus\Controllers\Admin\GroupAdminController@savePolicies');
$router->add('GET', '/admin-legacy/groups/moderation', 'Nexus\Controllers\Admin\GroupAdminController@moderation');
$router->add('POST', '/admin-legacy/groups/moderate-flag', 'Nexus\Controllers\Admin\GroupAdminController@moderateFlag');
$router->add('GET', '/admin-legacy/groups/approvals', 'Nexus\Controllers\Admin\GroupAdminController@approvals');
$router->add('POST', '/admin-legacy/groups/process-approval', 'Nexus\Controllers\Admin\GroupAdminController@processApproval');
$router->add('POST', '/admin-legacy/groups/manage-members', 'Nexus\Controllers\Admin\GroupAdminController@manageMembers');
$router->add('POST', '/admin-legacy/groups/batch-operations', 'Nexus\Controllers\Admin\GroupAdminController@batchOperations');
$router->add('GET', '/admin-legacy/groups/export', 'Nexus\Controllers\Admin\GroupAdminController@export');
$router->add('POST', '/admin-legacy/groups/toggle-featured', 'Nexus\Controllers\Admin\GroupAdminController@toggleFeatured');
$router->add('POST', '/admin-legacy/groups/delete', 'Nexus\Controllers\Admin\GroupAdminController@delete');

// Admin Matching Diagnostic
$router->add('GET', '/admin-legacy/matching-diagnostic', 'Nexus\Controllers\Admin\MatchingDiagnosticController@index');

// Admin Gamification
$router->add('GET', '/admin-legacy/gamification', 'Nexus\Controllers\Admin\GamificationController@index');
$router->add('POST', '/admin-legacy/gamification/recheck-all', 'Nexus\Controllers\Admin\GamificationController@recheckAll');
$router->add('POST', '/admin-legacy/gamification/bulk-award', 'Nexus\Controllers\Admin\GamificationController@bulkAward');
$router->add('POST', '/admin-legacy/gamification/award-all', 'Nexus\Controllers\Admin\GamificationController@awardToAll');
$router->add('POST', '/admin-legacy/gamification/reset-xp', 'Nexus\Controllers\Admin\GamificationController@resetXp');
$router->add('POST', '/admin-legacy/gamification/clear-badges', 'Nexus\Controllers\Admin\GamificationController@clearBadges');

// Admin AI Settings
$router->add('GET', '/admin-legacy/ai-settings', 'Nexus\Controllers\Admin\AiSettingsController@index');
$router->add('POST', '/admin-legacy/ai-settings/save', 'Nexus\Controllers\Admin\AiSettingsController@save');
$router->add('POST', '/admin-legacy/ai-settings/test', 'Nexus\Controllers\Admin\AiSettingsController@testProvider');
$router->add('POST', '/admin-legacy/ai-settings/initialize', 'Nexus\Controllers\Admin\AiSettingsController@initialize');

// Admin Custom Badges
$router->add('GET', '/admin-legacy/custom-badges', 'Nexus\Controllers\Admin\CustomBadgeController@index');
$router->add('GET', '/admin-legacy/custom-badges/create', 'Nexus\Controllers\Admin\CustomBadgeController@create');
$router->add('POST', '/admin-legacy/custom-badges/store', 'Nexus\Controllers\Admin\CustomBadgeController@store');
$router->add('GET', '/admin-legacy/custom-badges/edit/{id}', 'Nexus\Controllers\Admin\CustomBadgeController@edit');
$router->add('POST', '/admin-legacy/custom-badges/update', 'Nexus\Controllers\Admin\CustomBadgeController@update');
$router->add('POST', '/admin-legacy/custom-badges/delete', 'Nexus\Controllers\Admin\CustomBadgeController@delete');
$router->add('POST', '/admin-legacy/custom-badges/award', 'Nexus\Controllers\Admin\CustomBadgeController@award');
$router->add('POST', '/admin-legacy/custom-badges/revoke', 'Nexus\Controllers\Admin\CustomBadgeController@revoke');
$router->add('GET', '/admin-legacy/custom-badges/awardees', 'Nexus\Controllers\Admin\CustomBadgeController@getAwardees');

// Admin Achievement Analytics
$router->add('GET', '/admin-legacy/gamification/analytics', 'Nexus\Controllers\Admin\GamificationController@analytics');

// Admin Timebanking Analytics & Abuse Detection
$router->add('GET', '/admin-legacy/timebanking', 'Nexus\Controllers\Admin\TimebankingController@index');
$router->add('GET', '/admin-legacy/timebanking/alerts', 'Nexus\Controllers\Admin\TimebankingController@alerts');
$router->add('GET', '/admin-legacy/timebanking/alert/{id}', 'Nexus\Controllers\Admin\TimebankingController@viewAlert');
$router->add('POST', '/admin-legacy/timebanking/alert/{id}/status', 'Nexus\Controllers\Admin\TimebankingController@updateAlertStatus');
$router->add('POST', '/admin-legacy/timebanking/run-detection', 'Nexus\Controllers\Admin\TimebankingController@runDetection');
$router->add('GET', '/admin-legacy/timebanking/user-report/{id}', 'Nexus\Controllers\Admin\TimebankingController@userReport');
$router->add('GET', '/admin-legacy/timebanking/user-report', 'Nexus\Controllers\Admin\TimebankingController@userReport');
$router->add('POST', '/admin-legacy/timebanking/adjust-balance', 'Nexus\Controllers\Admin\TimebankingController@adjustBalance');
$router->add('GET', '/admin-legacy/timebanking/org-wallets', 'Nexus\Controllers\Admin\TimebankingController@orgWallets');
$router->add('POST', '/admin-legacy/timebanking/org-wallets/initialize', 'Nexus\Controllers\Admin\TimebankingController@initializeOrgWallet');
$router->add('POST', '/admin-legacy/timebanking/org-wallets/initialize-all', 'Nexus\Controllers\Admin\TimebankingController@initializeAllOrgWallets');
$router->add('GET', '/admin-legacy/timebanking/org-members/{id}', 'Nexus\Controllers\Admin\TimebankingController@orgMembers');
$router->add('POST', '/admin-legacy/timebanking/org-members/add', 'Nexus\Controllers\Admin\TimebankingController@addOrgMember');
$router->add('POST', '/admin-legacy/timebanking/org-members/update-role', 'Nexus\Controllers\Admin\TimebankingController@updateOrgMemberRole');
$router->add('POST', '/admin-legacy/timebanking/org-members/remove', 'Nexus\Controllers\Admin\TimebankingController@removeOrgMember');
$router->add('GET', '/admin-legacy/timebanking/create-org', 'Nexus\Controllers\Admin\TimebankingController@createOrgForm');
$router->add('POST', '/admin-legacy/timebanking/create-org', 'Nexus\Controllers\Admin\TimebankingController@createOrg');
$router->add('GET', '/api/admin/users/search', 'Nexus\Controllers\Admin\TimebankingController@userSearchApi');

// Admin Campaigns
$router->add('GET', '/admin-legacy/gamification/campaigns', 'Nexus\Controllers\Admin\GamificationController@campaigns');
$router->add('GET', '/admin-legacy/gamification/campaigns/create', 'Nexus\Controllers\Admin\GamificationController@createCampaign');
$router->add('GET', '/admin-legacy/gamification/campaigns/edit/{id}', 'Nexus\Controllers\Admin\GamificationController@editCampaign');
$router->add('POST', '/admin-legacy/gamification/campaigns/save', 'Nexus\Controllers\Admin\GamificationController@saveCampaign');
$router->add('POST', '/admin-legacy/gamification/campaigns/activate', 'Nexus\Controllers\Admin\GamificationController@activateCampaign');
$router->add('POST', '/admin-legacy/gamification/campaigns/pause', 'Nexus\Controllers\Admin\GamificationController@pauseCampaign');
$router->add('POST', '/admin-legacy/gamification/campaigns/delete', 'Nexus\Controllers\Admin\GamificationController@deleteCampaign');
$router->add('POST', '/admin-legacy/gamification/campaigns/run', 'Nexus\Controllers\Admin\GamificationController@runCampaign');
$router->add('POST', '/admin-legacy/gamification/campaigns/preview-audience', 'Nexus\Controllers\Admin\GamificationController@previewAudience');

// --------------------------------------------------------------------------
// 12.9. ADMIN > CRON JOB MANAGER
// --------------------------------------------------------------------------
$router->add('GET', '/admin-legacy/cron-jobs', 'Nexus\Controllers\Admin\CronJobController@index');
$router->add('POST', '/admin-legacy/cron-jobs/run/{id}', 'Nexus\Controllers\Admin\CronJobController@run');
$router->add('POST', '/admin-legacy/cron-jobs/toggle/{id}', 'Nexus\Controllers\Admin\CronJobController@toggle');
$router->add('GET', '/admin-legacy/cron-jobs/logs', 'Nexus\Controllers\Admin\CronJobController@logs');
$router->add('GET', '/admin-legacy/cron-jobs/setup', 'Nexus\Controllers\Admin\CronJobController@setup');
$router->add('GET', '/admin-legacy/cron-jobs/settings', 'Nexus\Controllers\Admin\CronJobController@settings');
$router->add('POST', '/admin-legacy/cron-jobs/settings', 'Nexus\Controllers\Admin\CronJobController@saveSettings');
$router->add('POST', '/admin-legacy/cron-jobs/clear-logs', 'Nexus\Controllers\Admin\CronJobController@clearLogs');
$router->add('GET', '/admin-legacy/cron-jobs/api/stats', 'Nexus\Controllers\Admin\CronJobController@apiStats');

// Admin Listings
$router->add('GET', '/admin-legacy/listings', 'Nexus\Controllers\Admin\ListingController@index');
$router->add('POST', '/admin-legacy/listings/delete/{id}', 'Nexus\Controllers\Admin\ListingController@delete');
$router->add('POST', '/admin-legacy/listings/approve/{id}', 'Nexus\Controllers\Admin\ListingController@approve');

// Admin SEO
$router->add('GET', '/admin-legacy/seo', 'Nexus\Controllers\Admin\SeoController@index');
$router->add('POST', '/admin-legacy/seo/store', 'Nexus\Controllers\Admin\SeoController@store');
$router->add('GET', '/admin-legacy/seo/audit', 'Nexus\Controllers\Admin\SeoController@audit');
$router->add('GET', '/admin-legacy/seo/bulk/{type}', 'Nexus\Controllers\Admin\SeoController@bulkEdit');
$router->add('POST', '/admin-legacy/seo/bulk/save', 'Nexus\Controllers\Admin\SeoController@bulkSave');
$router->add('GET', '/admin-legacy/seo/redirects', 'Nexus\Controllers\Admin\SeoController@redirects');
$router->add('POST', '/admin-legacy/seo/redirects/store', 'Nexus\Controllers\Admin\SeoController@storeRedirect');
$router->add('POST', '/admin-legacy/seo/redirects/delete', 'Nexus\Controllers\Admin\SeoController@deleteRedirect');
$router->add('GET', '/admin-legacy/seo/organization', 'Nexus\Controllers\Admin\SeoController@organization');
$router->add('POST', '/admin-legacy/seo/organization/save', 'Nexus\Controllers\Admin\SeoController@saveOrganization');
$router->add('POST', '/admin-legacy/seo/ping-sitemaps', 'Nexus\Controllers\Admin\SeoController@pingSitemaps');

// 404 Error Tracking
$router->add('GET', '/admin-legacy/404-errors', 'Nexus\Controllers\Admin\Error404Controller@index');
$router->add('GET', '/admin-legacy/404-errors/api/list', 'Nexus\Controllers\Admin\Error404Controller@apiList');
$router->add('GET', '/admin-legacy/404-errors/api/top', 'Nexus\Controllers\Admin\Error404Controller@topErrors');
$router->add('GET', '/admin-legacy/404-errors/api/stats', 'Nexus\Controllers\Admin\Error404Controller@stats');
$router->add('POST', '/admin-legacy/404-errors/mark-resolved', 'Nexus\Controllers\Admin\Error404Controller@markResolved');
$router->add('POST', '/admin-legacy/404-errors/mark-unresolved', 'Nexus\Controllers\Admin\Error404Controller@markUnresolved');
$router->add('POST', '/admin-legacy/404-errors/delete', 'Nexus\Controllers\Admin\Error404Controller@delete');
$router->add('GET', '/admin-legacy/404-errors/search', 'Nexus\Controllers\Admin\Error404Controller@search');
$router->add('POST', '/admin-legacy/404-errors/create-redirect', 'Nexus\Controllers\Admin\Error404Controller@createRedirect');
$router->add('POST', '/admin-legacy/404-errors/bulk-redirect', 'Nexus\Controllers\Admin\Error404Controller@bulkRedirect');
$router->add('POST', '/admin-legacy/404-errors/clean-old', 'Nexus\Controllers\Admin\Error404Controller@cleanOld');

// --------------------------------------------------------------------------
// 12.6. ADMIN > NEWSLETTERS
// --------------------------------------------------------------------------
$router->add('GET', '/admin-legacy/newsletters', 'Nexus\Controllers\Admin\NewsletterController@index');
$router->add('GET', '/admin-legacy/newsletters/create', 'Nexus\Controllers\Admin\NewsletterController@create');
$router->add('POST', '/admin-legacy/newsletters/store', 'Nexus\Controllers\Admin\NewsletterController@store');
$router->add('GET', '/admin-legacy/newsletters/edit/{id}', 'Nexus\Controllers\Admin\NewsletterController@edit');
$router->add('POST', '/admin-legacy/newsletters/update/{id}', 'Nexus\Controllers\Admin\NewsletterController@update');
$router->add('GET', '/admin-legacy/newsletters/preview/{id}', 'Nexus\Controllers\Admin\NewsletterController@preview');
$router->add('POST', '/admin-legacy/newsletters/send/{id}', 'Nexus\Controllers\Admin\NewsletterController@send');
$router->add('GET', '/admin-legacy/newsletters/send-direct/{id}', 'Nexus\Controllers\Admin\NewsletterController@sendDirect');
$router->add('POST', '/admin-legacy/newsletters/send-test/{id}', 'Nexus\Controllers\Admin\NewsletterController@sendTest');
$router->add('POST', '/admin-legacy/newsletters/delete', 'Nexus\Controllers\Admin\NewsletterController@delete');
$router->add('GET', '/admin-legacy/newsletters/duplicate/{id}', 'Nexus\Controllers\Admin\NewsletterController@duplicate');
$router->add('GET', '/admin-legacy/newsletters/stats/{id}', 'Nexus\Controllers\Admin\NewsletterController@stats');
$router->add('GET', '/admin-legacy/newsletters/activity/{id}', 'Nexus\Controllers\Admin\NewsletterController@activity');
$router->add('GET', '/admin-legacy/newsletters/analytics', 'Nexus\Controllers\Admin\NewsletterController@analytics');
$router->add('POST', '/admin-legacy/newsletters/select-winner/{id}', 'Nexus\Controllers\Admin\NewsletterController@selectWinner');

// AJAX Endpoints for Live Count & Preview
$router->add('POST', '/admin-legacy/newsletters/get-recipient-count', 'Nexus\Controllers\Admin\NewsletterController@getRecipientCount');
$router->add('POST', '/admin-legacy/newsletters/preview-recipients', 'Nexus\Controllers\Admin\NewsletterController@previewRecipients');

// Admin Subscriber Management
$router->add('GET', '/admin-legacy/newsletters/subscribers', 'Nexus\Controllers\Admin\NewsletterController@subscribers');
$router->add('POST', '/admin-legacy/newsletters/subscribers/add', 'Nexus\Controllers\Admin\NewsletterController@addSubscriber');
$router->add('POST', '/admin-legacy/newsletters/subscribers/delete', 'Nexus\Controllers\Admin\NewsletterController@deleteSubscriber');
$router->add('POST', '/admin-legacy/newsletters/subscribers/sync', 'Nexus\Controllers\Admin\NewsletterController@syncMembers');
$router->add('GET', '/admin-legacy/newsletters/subscribers/export', 'Nexus\Controllers\Admin\NewsletterController@exportSubscribers');
$router->add('POST', '/admin-legacy/newsletters/subscribers/import', 'Nexus\Controllers\Admin\NewsletterController@importSubscribers');

// Segment Management
$router->add('GET', '/admin-legacy/newsletters/segments', 'Nexus\Controllers\Admin\NewsletterController@segments');
$router->add('GET', '/admin-legacy/newsletters/segments/create', 'Nexus\Controllers\Admin\NewsletterController@createSegment');
$router->add('POST', '/admin-legacy/newsletters/segments/store', 'Nexus\Controllers\Admin\NewsletterController@storeSegment');
$router->add('GET', '/admin-legacy/newsletters/segments/edit/{id}', 'Nexus\Controllers\Admin\NewsletterController@editSegment');
$router->add('POST', '/admin-legacy/newsletters/segments/update/{id}', 'Nexus\Controllers\Admin\NewsletterController@updateSegment');
$router->add('POST', '/admin-legacy/newsletters/segments/delete', 'Nexus\Controllers\Admin\NewsletterController@deleteSegment');
$router->add('POST', '/admin-legacy/newsletters/segments/preview', 'Nexus\Controllers\Admin\NewsletterController@previewSegment');
$router->add('GET', '/admin-legacy/newsletters/segments/suggestions', 'Nexus\Controllers\Admin\NewsletterController@getSmartSuggestions');
$router->add('POST', '/admin-legacy/newsletters/segments/from-suggestion', 'Nexus\Controllers\Admin\NewsletterController@createFromSuggestion');

// Template Management
$router->add('GET', '/admin-legacy/newsletters/templates', 'Nexus\Controllers\Admin\NewsletterController@templates');
$router->add('GET', '/admin-legacy/newsletters/templates/create', 'Nexus\Controllers\Admin\NewsletterController@createTemplate');
$router->add('POST', '/admin-legacy/newsletters/templates/store', 'Nexus\Controllers\Admin\NewsletterController@storeTemplate');
$router->add('GET', '/admin-legacy/newsletters/templates/edit/{id}', 'Nexus\Controllers\Admin\NewsletterController@editTemplate');
$router->add('POST', '/admin-legacy/newsletters/templates/update/{id}', 'Nexus\Controllers\Admin\NewsletterController@updateTemplate');
$router->add('POST', '/admin-legacy/newsletters/templates/delete', 'Nexus\Controllers\Admin\NewsletterController@deleteTemplate');
$router->add('GET', '/admin-legacy/newsletters/templates/duplicate/{id}', 'Nexus\Controllers\Admin\NewsletterController@duplicateTemplate');
$router->add('GET', '/admin-legacy/newsletters/templates/preview/{id}', 'Nexus\Controllers\Admin\NewsletterController@previewTemplate');
$router->add('POST', '/admin-legacy/newsletters/save-as-template', 'Nexus\Controllers\Admin\NewsletterController@saveAsTemplate');
$router->add('GET', '/admin-legacy/newsletters/get-templates', 'Nexus\Controllers\Admin\NewsletterController@getTemplates');
$router->add('GET', '/admin-legacy/newsletters/load-template/{id}', 'Nexus\Controllers\Admin\NewsletterController@loadTemplate');

// Bounce Management
$router->add('GET', '/admin-legacy/newsletters/bounces', 'Nexus\Controllers\Admin\NewsletterController@bounces');
$router->add('POST', '/admin-legacy/newsletters/unsuppress', 'Nexus\Controllers\Admin\NewsletterController@unsuppress');
$router->add('POST', '/admin-legacy/newsletters/suppress', 'Nexus\Controllers\Admin\NewsletterController@suppress');

// Resend to Non-Openers
$router->add('GET', '/admin-legacy/newsletters/resend/{id}', 'Nexus\Controllers\Admin\NewsletterController@resendForm');
$router->add('POST', '/admin-legacy/newsletters/resend/{id}', 'Nexus\Controllers\Admin\NewsletterController@resend');
$router->add('GET', '/admin-legacy/newsletters/resend-info/{id}', 'Nexus\Controllers\Admin\NewsletterController@getResendInfo');

// Send Time Optimization
$router->add('GET', '/admin-legacy/newsletters/send-time', 'Nexus\Controllers\Admin\NewsletterController@sendTimeOptimization');
$router->add('GET', '/admin-legacy/newsletters/send-time-recommendations', 'Nexus\Controllers\Admin\NewsletterController@getSendTimeRecommendations');
$router->add('GET', '/admin-legacy/newsletters/send-time-heatmap', 'Nexus\Controllers\Admin\NewsletterController@getSendTimeHeatmap');

// Email Client Preview
$router->add('GET', '/admin-legacy/newsletters/client-preview/{id}', 'Nexus\Controllers\Admin\NewsletterController@getEmailClientPreview');

// Diagnostics & Repair
$router->add('GET', '/admin-legacy/newsletters/diagnostics', 'Nexus\Controllers\Admin\NewsletterController@diagnostics');
$router->add('POST', '/admin-legacy/newsletters/repair', 'Nexus\Controllers\Admin\NewsletterController@repair');

// --------------------------------------------------------------------------
// 12.7. PUBLIC > NEWSLETTER SUBSCRIPTION
// --------------------------------------------------------------------------
$router->add('GET', '/newsletter/subscribe', 'Nexus\Controllers\NewsletterSubscriptionController@showForm');
$router->add('POST', '/newsletter/subscribe', 'Nexus\Controllers\NewsletterSubscriptionController@subscribe');
$router->add('GET', '/newsletter/confirm', 'Nexus\Controllers\NewsletterSubscriptionController@confirm');
$router->add('GET', '/newsletter/unsubscribe', 'Nexus\Controllers\NewsletterSubscriptionController@showUnsubscribe');
$router->add('POST', '/newsletter/unsubscribe', 'Nexus\Controllers\NewsletterSubscriptionController@unsubscribe');
$router->add('GET', '/newsletter/unsubscribe/confirm', 'Nexus\Controllers\NewsletterSubscriptionController@oneClickUnsubscribe');
$router->add('POST', '/newsletter/unsubscribe/confirm', 'Nexus\Controllers\NewsletterSubscriptionController@oneClickUnsubscribe');

// --------------------------------------------------------------------------
// 12.8. PUBLIC > NEWSLETTER ANALYTICS TRACKING
// --------------------------------------------------------------------------
$router->add('GET', '/newsletter/track/open/{newsletterId}/{trackingToken}', 'Nexus\Controllers\NewsletterTrackingController@trackOpen');
$router->add('GET', '/newsletter/track/click/{newsletterId}/{linkId}/{trackingToken}', 'Nexus\Controllers\NewsletterTrackingController@trackClick');

// --------------------------------------------------------------------------
// 12.95. ADMIN > ENTERPRISE FEATURES (GDPR, Monitoring, Config)
// --------------------------------------------------------------------------
$router->add('GET', '/admin-legacy/enterprise', 'Nexus\Controllers\Admin\Enterprise\EnterpriseDashboardController@dashboard');

// API Test Runner
$router->add('GET', '/admin-legacy/tests', 'Nexus\Controllers\Admin\TestRunnerController@index');
$router->add('POST', '/admin-legacy/tests/run', 'Nexus\Controllers\Admin\TestRunnerController@runTests');
$router->add('GET', '/admin-legacy/tests/view', 'Nexus\Controllers\Admin\TestRunnerController@viewRun');

// GDPR Requests
$router->add('GET', '/admin-legacy/enterprise/gdpr', 'Nexus\Controllers\Admin\Enterprise\GdprRequestController@dashboard');
$router->add('GET', '/admin-legacy/enterprise/gdpr/requests', 'Nexus\Controllers\Admin\Enterprise\GdprRequestController@index');
$router->add('GET', '/admin-legacy/enterprise/gdpr/requests/new', 'Nexus\Controllers\Admin\Enterprise\GdprRequestController@create');
$router->add('GET', '/admin-legacy/enterprise/gdpr/requests/create', 'Nexus\Controllers\Admin\Enterprise\GdprRequestController@create');
$router->add('POST', '/admin-legacy/enterprise/gdpr/requests', 'Nexus\Controllers\Admin\Enterprise\GdprRequestController@store');
$router->add('GET', '/admin-legacy/enterprise/gdpr/requests/{id}', 'Nexus\Controllers\Admin\Enterprise\GdprRequestController@show');
$router->add('POST', '/admin-legacy/enterprise/gdpr/requests/{id}/process', 'Nexus\Controllers\Admin\Enterprise\GdprRequestController@process');
$router->add('POST', '/admin-legacy/enterprise/gdpr/requests/{id}/complete', 'Nexus\Controllers\Admin\Enterprise\GdprRequestController@complete');
$router->add('POST', '/admin-legacy/enterprise/gdpr/requests/{id}/reject', 'Nexus\Controllers\Admin\Enterprise\GdprRequestController@reject');
$router->add('POST', '/admin-legacy/enterprise/gdpr/requests/{id}/assign', 'Nexus\Controllers\Admin\Enterprise\GdprRequestController@assign');
$router->add('POST', '/admin-legacy/enterprise/gdpr/requests/{id}/notes', 'Nexus\Controllers\Admin\Enterprise\GdprRequestController@addNote');
$router->add('POST', '/admin-legacy/enterprise/gdpr/requests/{id}/generate-export', 'Nexus\Controllers\Admin\Enterprise\GdprRequestController@generateExport');
$router->add('POST', '/admin-legacy/enterprise/gdpr/requests/bulk-process', 'Nexus\Controllers\Admin\Enterprise\GdprRequestController@bulkProcess');

// GDPR Consents
$router->add('GET', '/admin-legacy/enterprise/gdpr/consents', 'Nexus\Controllers\Admin\Enterprise\GdprConsentController@index');
$router->add('POST', '/admin-legacy/enterprise/gdpr/consents/types', 'Nexus\Controllers\Admin\Enterprise\GdprConsentController@storeType');
$router->add('POST', '/admin-legacy/enterprise/gdpr/consents/backfill', 'Nexus\Controllers\Admin\Enterprise\GdprConsentController@backfill');
$router->add('GET', '/admin-legacy/enterprise/gdpr/consents/tenant-versions', 'Nexus\Controllers\Admin\Enterprise\GdprConsentController@getTenantVersions');
$router->add('POST', '/admin-legacy/enterprise/gdpr/consents/tenant-version', 'Nexus\Controllers\Admin\Enterprise\GdprConsentController@updateTenantVersion');
$router->add('DELETE', '/admin-legacy/enterprise/gdpr/consents/tenant-version/{slug}', 'Nexus\Controllers\Admin\Enterprise\GdprConsentController@removeTenantVersion');
$router->add('GET', '/admin-legacy/enterprise/gdpr/consents/{id}', 'Nexus\Controllers\Admin\Enterprise\GdprConsentController@show');
$router->add('GET', '/admin-legacy/enterprise/gdpr/consents/export', 'Nexus\Controllers\Admin\Enterprise\GdprConsentController@export');

// GDPR Breaches
$router->add('GET', '/admin-legacy/enterprise/gdpr/breaches', 'Nexus\Controllers\Admin\Enterprise\GdprBreachController@index');
$router->add('GET', '/admin-legacy/enterprise/gdpr/breaches/report', 'Nexus\Controllers\Admin\Enterprise\GdprBreachController@create');
$router->add('POST', '/admin-legacy/enterprise/gdpr/breaches', 'Nexus\Controllers\Admin\Enterprise\GdprBreachController@store');
$router->add('GET', '/admin-legacy/enterprise/gdpr/breaches/{id}', 'Nexus\Controllers\Admin\Enterprise\GdprBreachController@show');
$router->add('POST', '/admin-legacy/enterprise/gdpr/breaches/{id}/escalate', 'Nexus\Controllers\Admin\Enterprise\GdprBreachController@escalate');

// GDPR Audit
$router->add('GET', '/admin-legacy/enterprise/gdpr/audit', 'Nexus\Controllers\Admin\Enterprise\GdprAuditController@index');
$router->add('GET', '/admin-legacy/enterprise/gdpr/audit/export', 'Nexus\Controllers\Admin\Enterprise\GdprAuditController@export');
$router->add('POST', '/admin-legacy/enterprise/gdpr/export-report', 'Nexus\Controllers\Admin\Enterprise\GdprAuditController@complianceReport');

// Monitoring & APM
$router->add('GET', '/admin-legacy/enterprise/monitoring', 'Nexus\Controllers\Admin\Enterprise\MonitoringController@dashboard');
$router->add('GET', '/admin-legacy/enterprise/monitoring/health', 'Nexus\Controllers\Admin\Enterprise\MonitoringController@healthCheck');
$router->add('GET', '/admin-legacy/enterprise/monitoring/requirements', 'Nexus\Controllers\Admin\Enterprise\MonitoringController@requirements');
$router->add('GET', '/admin-legacy/enterprise/monitoring/logs', 'Nexus\Controllers\Admin\Enterprise\MonitoringController@logs');
$router->add('GET', '/admin-legacy/enterprise/monitoring/logs/download', 'Nexus\Controllers\Admin\Enterprise\MonitoringController@logsDownload');
$router->add('POST', '/admin-legacy/enterprise/monitoring/logs/clear', 'Nexus\Controllers\Admin\Enterprise\MonitoringController@logsClear');
$router->add('GET', '/admin-legacy/enterprise/monitoring/logs/{filename}', 'Nexus\Controllers\Admin\Enterprise\MonitoringController@logView');

// Real-Time Updates API (keep in monitoring for now)
$router->add('GET', '/admin-legacy/api/realtime', 'Nexus\Controllers\Admin\Enterprise\MonitoringController@realtimeStream');
$router->add('GET', '/admin-legacy/api/realtime/poll', 'Nexus\Controllers\Admin\Enterprise\MonitoringController@realtimePoll');

// Configuration
$router->add('GET', '/admin-legacy/enterprise/config', 'Nexus\Controllers\Admin\Enterprise\ConfigController@dashboard');
$router->add('POST', '/admin-legacy/enterprise/config/settings/{group}/{key}', 'Nexus\Controllers\Admin\Enterprise\ConfigController@updateSetting');
$router->add('GET', '/admin-legacy/enterprise/config/export', 'Nexus\Controllers\Admin\Enterprise\ConfigController@export');
$router->add('POST', '/admin-legacy/enterprise/config/cache/clear', 'Nexus\Controllers\Admin\Enterprise\ConfigController@clearCache');
$router->add('GET', '/admin-legacy/enterprise/config/validate', 'Nexus\Controllers\Admin\Enterprise\ConfigController@validate');
$router->add('PATCH', '/admin-legacy/enterprise/config/features/{key}', 'Nexus\Controllers\Admin\Enterprise\ConfigController@toggleFeature');
$router->add('POST', '/admin-legacy/enterprise/config/features/reset', 'Nexus\Controllers\Admin\Enterprise\ConfigController@resetFeatures');

// Secrets & Vault
$router->add('GET', '/admin-legacy/enterprise/config/secrets', 'Nexus\Controllers\Admin\Enterprise\SecretsController@index');
$router->add('POST', '/admin-legacy/enterprise/config/secrets', 'Nexus\Controllers\Admin\Enterprise\SecretsController@store');
$router->add('POST', '/admin-legacy/enterprise/config/secrets/{key}/value', 'Nexus\Controllers\Admin\Enterprise\SecretsController@view');
$router->add('POST', '/admin-legacy/enterprise/config/secrets/{key}/rotate', 'Nexus\Controllers\Admin\Enterprise\SecretsController@rotate');
$router->add('DELETE', '/admin-legacy/enterprise/config/secrets/{key}', 'Nexus\Controllers\Admin\Enterprise\SecretsController@delete');
$router->add('GET', '/admin-legacy/enterprise/config/vault/test', 'Nexus\Controllers\Admin\Enterprise\SecretsController@testVault');

// Roles & Permissions Management
$router->add('GET', '/admin-legacy/enterprise/roles', 'Nexus\Controllers\Admin\RolesController@index');
$router->add('GET', '/admin-legacy/enterprise/permissions', 'Nexus\Controllers\Admin\RolesController@permissions');
$router->add('GET', '/admin-legacy/enterprise/roles/create', 'Nexus\Controllers\Admin\RolesController@create');
$router->add('POST', '/admin-legacy/enterprise/roles', 'Nexus\Controllers\Admin\RolesController@store');
$router->add('GET', '/admin-legacy/enterprise/audit/permissions', 'Nexus\Controllers\Admin\RolesController@auditLog');
$router->add('GET', '/admin-legacy/enterprise/roles/{id}', 'Nexus\Controllers\Admin\RolesController@show');
$router->add('GET', '/admin-legacy/enterprise/roles/{id}/edit', 'Nexus\Controllers\Admin\RolesController@edit');
$router->add('PATCH', '/admin-legacy/enterprise/roles/{id}', 'Nexus\Controllers\Admin\RolesController@update');
$router->add('PUT', '/admin-legacy/enterprise/roles/{id}', 'Nexus\Controllers\Admin\RolesController@update');
$router->add('DELETE', '/admin-legacy/enterprise/roles/{id}', 'Nexus\Controllers\Admin\RolesController@destroy');
$router->add('POST', '/admin-legacy/enterprise/roles/{id}/users/{userId}', 'Nexus\Controllers\Admin\RolesController@assignToUser');
$router->add('DELETE', '/admin-legacy/enterprise/roles/{id}/users/{userId}', 'Nexus\Controllers\Admin\RolesController@revokeFromUser');

// Permission API (REST endpoints for AJAX/frontend)
$router->add('GET', '/admin-legacy/api/permissions/check', 'Nexus\Controllers\Admin\PermissionApiController@checkPermission');
$router->add('GET', '/admin-legacy/api/permissions', 'Nexus\Controllers\Admin\PermissionApiController@getAllPermissions');
$router->add('GET', '/admin-legacy/api/roles', 'Nexus\Controllers\Admin\PermissionApiController@getAllRoles');
$router->add('GET', '/admin-legacy/api/roles/{roleId}/permissions', 'Nexus\Controllers\Admin\PermissionApiController@getRolePermissions');
$router->add('GET', '/admin-legacy/api/users/{userId}/permissions', 'Nexus\Controllers\Admin\PermissionApiController@getUserPermissions');
$router->add('GET', '/admin-legacy/api/users/{userId}/roles', 'Nexus\Controllers\Admin\PermissionApiController@getUserRoles');
$router->add('GET', '/admin-legacy/api/users/{userId}/effective-permissions', 'Nexus\Controllers\Admin\PermissionApiController@getUserEffectivePermissions');
$router->add('POST', '/admin-legacy/api/users/{userId}/roles', 'Nexus\Controllers\Admin\PermissionApiController@assignRoleToUser');
$router->add('DELETE', '/admin-legacy/api/users/{userId}/roles/{roleId}', 'Nexus\Controllers\Admin\PermissionApiController@revokeRoleFromUser');
$router->add('POST', '/admin-legacy/api/users/{userId}/permissions', 'Nexus\Controllers\Admin\PermissionApiController@grantPermissionToUser');
$router->add('DELETE', '/admin-legacy/api/users/{userId}/permissions/{permissionId}', 'Nexus\Controllers\Admin\PermissionApiController@revokePermissionFromUser');
$router->add('GET', '/admin-legacy/api/audit/permissions', 'Nexus\Controllers\Admin\PermissionApiController@getAuditLog');
$router->add('GET', '/admin-legacy/api/stats/permissions', 'Nexus\Controllers\Admin\PermissionApiController@getPermissionStats');

// User-facing Privacy Settings (GDPR self-service)
$router->add('GET', '/settings/privacy', 'Nexus\Controllers\SettingsController@privacy');
$router->add('POST', '/api/gdpr/consent', 'Nexus\Controllers\Api\GdprApiController@updateConsent');
$router->add('POST', '/api/gdpr/request', 'Nexus\Controllers\Api\GdprApiController@createRequest');
$router->add('POST', '/api/gdpr/delete-account', 'Nexus\Controllers\Api\GdprApiController@deleteAccount');

// --------------------------------------------------------------------------
// 13. LEGACY SUPER ADMIN (DEPRECATED - Use /super-admin/* routes at top of file)
// --------------------------------------------------------------------------
// NOTE: The old MasterController routes have been replaced by the new
// hierarchical Super Admin Panel. Routes are defined at the TOP of this file.
// See: Nexus\Controllers\SuperAdmin\* controllers
//
// Legacy routes kept for backwards compatibility (will redirect):
// $router->add('GET', '/super-admin', 'Nexus\Controllers\MasterController@index');
// These are now handled by SuperAdmin\DashboardController, TenantController, UserController

// --------------------------------------------------------------------------
// 14. CRON JOBS
// --------------------------------------------------------------------------
// Notification Digests
$router->add('GET', '/cron/daily-digest', 'Nexus\Controllers\CronController@dailyDigest');
$router->add('GET', '/cron/weekly-digest', 'Nexus\Controllers\CronController@weeklyDigest');
$router->add('GET', '/cron/process-queue', 'Nexus\Controllers\CronController@runInstantQueue');

// Smart Matching Digests
$router->add('GET', '/cron/match-digest-daily', 'Nexus\Controllers\CronController@matchDigestDaily');
$router->add('GET', '/cron/match-digest-weekly', 'Nexus\Controllers\CronController@matchDigestWeekly');
$router->add('GET', '/cron/notify-hot-matches', 'Nexus\Controllers\CronController@notifyHotMatches');

// Geocoding
$router->add('GET', '/cron/geocode-batch', 'Nexus\Controllers\CronController@geocodeBatch');

// Federation
$router->add('GET', '/cron/federation-weekly-digest', 'Nexus\Controllers\CronController@federationWeeklyDigest');

// Newsletter Processing
$router->add('GET', '/cron/process-newsletters', 'Nexus\Controllers\CronController@processNewsletters');
$router->add('GET', '/cron/process-recurring', 'Nexus\Controllers\CronController@processRecurring');
$router->add('GET', '/cron/process-newsletter-queue', 'Nexus\Controllers\CronController@processNewsletterQueue');

// Maintenance
$router->add('GET', '/cron/cleanup', 'Nexus\Controllers\CronController@cleanup');

// Master Cron (runs all tasks based on schedule)
$router->add('GET', '/cron/run-all', 'Nexus\Controllers\CronController@runAll');

// --------------------------------------------------------------------------
// Notification API routes consolidated above

// ============================================
// API V2 - COMMUNITY ANALYTICS (Admin)
// ============================================
$router->add('GET', '/api/v2/admin/community-analytics', 'Nexus\Controllers\Api\AdminCommunityAnalyticsApiController@index');
$router->add('GET', '/api/v2/admin/community-analytics/export', 'Nexus\Controllers\Api\AdminCommunityAnalyticsApiController@export');
$router->add('GET', '/api/v2/admin/community-analytics/geography', 'Nexus\Controllers\Api\AdminCommunityAnalyticsApiController@geography');

// ============================================
// API V2 - IMPACT REPORTING (Admin)
// ============================================
$router->add('GET', '/api/v2/admin/impact-report', 'Nexus\Controllers\Api\AdminImpactReportApiController@index');
$router->add('PUT', '/api/v2/admin/impact-report/config', 'Nexus\Controllers\Api\AdminImpactReportApiController@updateConfig');

// ============================================
// API V2 - ONBOARDING (Authenticated users)
// ============================================
$router->add('GET', '/api/v2/onboarding/status', 'Nexus\Controllers\Api\OnboardingApiController@status');
$router->add('GET', '/api/v2/onboarding/categories', 'Nexus\Controllers\Api\OnboardingApiController@categories');
$router->add('POST', '/api/v2/onboarding/interests', 'Nexus\Controllers\Api\OnboardingApiController@saveInterests');
$router->add('POST', '/api/v2/onboarding/skills', 'Nexus\Controllers\Api\OnboardingApiController@saveSkills');
$router->add('POST', '/api/v2/onboarding/complete', 'Nexus\Controllers\Api\OnboardingApiController@complete');

// ============================================
// API V2 - GROUP EXCHANGES (Authenticated users)
// ============================================
$router->add('GET', '/api/v2/group-exchanges', 'Nexus\Controllers\Api\GroupExchangesApiController@index');
$router->add('POST', '/api/v2/group-exchanges', 'Nexus\Controllers\Api\GroupExchangesApiController@store');
$router->add('GET', '/api/v2/group-exchanges/{id}', 'Nexus\Controllers\Api\GroupExchangesApiController@show');
$router->add('PUT', '/api/v2/group-exchanges/{id}', 'Nexus\Controllers\Api\GroupExchangesApiController@update');
$router->add('DELETE', '/api/v2/group-exchanges/{id}', 'Nexus\Controllers\Api\GroupExchangesApiController@destroy');
$router->add('POST', '/api/v2/group-exchanges/{id}/participants', 'Nexus\Controllers\Api\GroupExchangesApiController@addParticipant');
$router->add('DELETE', '/api/v2/group-exchanges/{id}/participants/{userId}', 'Nexus\Controllers\Api\GroupExchangesApiController@removeParticipant');
$router->add('POST', '/api/v2/group-exchanges/{id}/confirm', 'Nexus\Controllers\Api\GroupExchangesApiController@confirm');
$router->add('POST', '/api/v2/group-exchanges/{id}/complete', 'Nexus\Controllers\Api\GroupExchangesApiController@complete');

// DISPATCH
// --------------------------------------------------------------------------
$router->dispatch();
