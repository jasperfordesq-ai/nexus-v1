<?php

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
// ADMIN UTILITIES
// --------------------------------------------------------------------------
$router->add('GET', '/test-permissions', function() {
    require __DIR__ . '/test-permissions.php';
    exit;
});

$router->add('GET', '/check-database-status', function() {
    require __DIR__ . '/check-database-status.php';
    exit;
});

$router->add('GET', '/test-permission-features', function() {
    require __DIR__ . '/test-permission-features.php';
    exit;
});

$router->add('GET', '/diagnose-members-issues', function() {
    require __DIR__ . '/diagnose-members-issues.php';
    exit;
});

$router->add('GET', '/check-session', function() {
    require __DIR__ . '/check-session.php';
    exit;
});

$router->add('GET', '/view-error-log', function() {
    require __DIR__ . '/view-error-log.php';
    exit;
});

$router->add('GET', '/debug-members-load', function() {
    require __DIR__ . '/debug-members-load.php';
    exit;
});

$router->add('GET', '/test-members-comprehensive', function() {
    require __DIR__ . '/test-members-comprehensive.php';
    exit;
});

$router->add('GET', '/test-profile-routing', function() {
    require __DIR__ . '/test-profile-routing.php';
    exit;
});

$router->add('GET', '/test-index', function() {
    require __DIR__ . '/test-index.php';
    exit;
});

$router->add('GET', '/install-permissions', function() {
    require __DIR__ . '/install-permissions.php';
    exit;
});

$router->add('POST', '/install-permissions', function() {
    require __DIR__ . '/install-permissions.php';
    exit;
});

$router->add('GET', '/run-menu-migration', function() {
    require __DIR__ . '/run-menu-migration.php';
    exit;
});

$router->add('GET', '/test-simple-route', function() {
    require __DIR__ . '/../test-simple-route.php';
    exit;
});

$router->add('GET', '/test-ajax-endpoint', function() {
    require __DIR__ . '/../test-ajax-endpoint.php';
    exit;
});

$router->add('GET', '/run-migration-017', function() {
    require __DIR__ . '/run-migration-017.php';
    exit;
});

$router->add('GET', '/run-migration-019', function() {
    require __DIR__ . '/../scripts/run_migration_019.php';
    exit;
});

$router->add('GET', '/run-migration-020', function() {
    require __DIR__ . '/../scripts/run_migration_020.php';
    exit;
});

$router->add('GET', '/run-migration-reactions', function() {
    require __DIR__ . '/../scripts/migrations/create_message_reactions_table.php';
    exit;
});

$router->add('GET', '/check-tables', function() {
    require __DIR__ . '/check-tables.php';
    exit;
});

$router->add('GET', '/check-menu', function() {
    require __DIR__ . '/check-menu.php';
    exit;
});

$router->add('GET', '/test-match-preferences', function() {
    require __DIR__ . '/test-match-preferences.php';
    exit;
});

$router->add('GET', '/create-cron-log', function() {
    require __DIR__ . '/create-cron-log.php';
    exit;
});

$router->add('GET', '/debug-matching', function() {
    require __DIR__ . '/debug-matching.php';
    exit;
});

$router->add('GET', '/fix-enum', function() {
    require __DIR__ . '/fix-enum.php';
    exit;
});

$router->add('GET', '/geocode-users', function() {
    require __DIR__ . '/geocode_users.php';
    exit;
});

$router->add('GET', '/sync-listing-locations', function() {
    require __DIR__ . '/../scripts/sync_listing_locations.php';
    exit;
});

$router->add('GET', '/debug-listings', function() {
    require __DIR__ . '/../scripts/debug_listings.php';
    exit;
});

$router->add('GET', '/debug-tenant-data', function() {
    require __DIR__ . '/debug-tenant-data.php';
    exit;
});

// Session/Auth Debug (God only)
$router->add('GET', '/debug-session', function() {
    if (empty($_SESSION['is_god'])) {
        http_response_code(403);
        die('God access required');
    }

    header('Content-Type: text/html; charset=utf-8');
    echo "<h2>Session Data</h2><pre>";
    echo "user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
    echo "user_name: " . ($_SESSION['user_name'] ?? 'NOT SET') . "\n";
    echo "user_email: " . ($_SESSION['user_email'] ?? 'NOT SET') . "\n";
    echo "user_role: " . ($_SESSION['user_role'] ?? 'NOT SET') . "\n";
    echo "is_admin: " . ($_SESSION['is_admin'] ?? 'NOT SET') . "\n";
    echo "is_super_admin: " . ($_SESSION['is_super_admin'] ?? 'NOT SET') . "\n";
    echo "is_god: " . ($_SESSION['is_god'] ?? 'NOT SET') . "\n";
    echo "tenant_id: " . ($_SESSION['tenant_id'] ?? 'NOT SET') . "\n";
    echo "</pre>";

    if (isset($_SESSION['user_id'])) {
        echo "<h2>Database User Data</h2><pre>";
        $user = \Nexus\Core\Database::query(
            "SELECT id, name, email, role, is_super_admin, is_god, is_tenant_super_admin, tenant_id, status FROM users WHERE id = ?",
            [$_SESSION['user_id']]
        )->fetch();
        print_r($user);
        echo "</pre>";

        echo "<h2>AdminAuth Status</h2><pre>";
        echo "AdminAuth::isGod(): " . (\Nexus\Core\AdminAuth::isGod() ? 'true' : 'false') . "\n";
        echo "AdminAuth::isSuperAdmin(): " . (\Nexus\Core\AdminAuth::isSuperAdmin() ? 'true' : 'false') . "\n";
        echo "AdminAuth::isAdmin(): " . (\Nexus\Core\AdminAuth::isAdmin() ? 'true' : 'false') . "\n";
        echo "AdminAuth::getPrivilegeLevel(): " . \Nexus\Core\AdminAuth::getPrivilegeLevel() . "\n";
        echo "</pre>";
    }
    exit;
});

$router->add('GET', '/test-tenant-resolve', function() {
    require __DIR__ . '/test-tenant-resolve.php';
    exit;
});

$router->add('GET', '/fix-tenant-domain', function() {
    require __DIR__ . '/fix-tenant-domain.php';
    exit;
});

$router->add('GET', '/test-feed-api', function() {
    require __DIR__ . '/test-feed-api.php';
    exit;
});

$router->add('GET', '/test-blog-posts', function() {
    require __DIR__ . '/test-blog-posts.php';
    exit;
});

$router->add('GET', '/check-posts-status', function() {
    require __DIR__ . '/check-posts-status.php';
    exit;
});

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
// API ROUTES (MOBILE APP)
// --------------------------------------------------------------------------
// Polls
$router->add('GET', '/api/polls', 'Nexus\Controllers\Api\PollApiController@index');
$router->add('POST', '/api/polls/vote', 'Nexus\Controllers\Api\PollApiController@vote');

// Goals
$router->add('GET', '/api/goals', 'Nexus\Controllers\Api\GoalApiController@index');
$router->add('POST', '/api/goals/update', 'Nexus\Controllers\Api\GoalApiController@updateProgress'); // Method is updateProgress
$router->add('POST', '/api/goals/offer-buddy', 'Nexus\Controllers\Api\GoalApiController@offerBuddy'); // Offer to be a goal buddy

// Volunteering
$router->add('GET', '/api/vol_opportunities', 'Nexus\Controllers\Api\VolunteeringApiController@index');

// Events
$router->add('GET', '/api/events', 'Nexus\Controllers\Api\EventApiController@index');
$router->add('POST', '/api/events/rsvp', 'Nexus\Controllers\Api\EventApiController@rsvp');

// Wallet
$router->add('GET', '/api/wallet/balance', 'Nexus\Controllers\Api\WalletApiController@balance');

// Layout Switching API (session-based, no URL pollution)
$router->add('POST', '/api/layout-switch', 'Nexus\Controllers\Api\LayoutApiController@switch');
$router->add('GET', '/api/layout-switch', 'Nexus\Controllers\Api\LayoutApiController@current');

// Nexus Score API
$router->add('GET', '/api/nexus-score', 'Nexus\Controllers\NexusScoreController@apiGetScore');
$router->add('POST', '/api/nexus-score/recalculate', 'Nexus\Controllers\NexusScoreController@apiRecalculateScores');
$router->add('GET', '/api/wallet/transactions', 'Nexus\Controllers\Api\WalletApiController@transactions');
$router->add('GET', '/api/wallet/pending-count', 'Nexus\Controllers\Api\WalletApiController@pendingCount'); // Badge updates
$router->add('POST', '/api/wallet/transfer', 'Nexus\Controllers\Api\WalletApiController@transfer');
$router->add('POST', '/api/wallet/delete', 'Nexus\Controllers\Api\WalletApiController@delete');
$router->add('POST', '/api/wallet/user-search', 'Nexus\Controllers\Api\WalletApiController@userSearch'); // User autocomplete


// Core (Directory, Feed, etc.)
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

$router->add('POST', '/api/listings/delete', 'Nexus\Controllers\ListingController@delete'); // Listings Delete API

// ============================================
// MASTER PLATFORM SOCIAL MEDIA MODULE API
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

// Token-based Auth API (for mobile apps - more reliable than session cookies)
$router->add('POST', '/api/auth/login', 'Nexus\Controllers\Api\AuthController@login');
$router->add('POST', '/api/auth/logout', 'Nexus\Controllers\Api\AuthController@logout');
$router->add('POST', '/api/auth/refresh-token', 'Nexus\Controllers\Api\AuthController@refreshToken');
$router->add('POST', '/api/auth/validate-token', 'Nexus\Controllers\Api\AuthController@validateToken');
$router->add('GET', '/api/auth/validate-token', 'Nexus\Controllers\Api\AuthController@validateToken');

// Pusher Realtime API (WebSocket authentication)
$router->add('POST', '/api/pusher/auth', 'Nexus\Controllers\Api\PusherAuthController@auth');
$router->add('GET', '/api/pusher/config', 'Nexus\Controllers\Api\PusherAuthController@config');
$router->add('GET', '/api/pusher/debug', 'Nexus\Controllers\Api\PusherAuthController@debug');

// WebAuthn Biometric Auth API (Primary endpoints - used by nexus-native.js)
$router->add('POST', '/api/webauthn/register-challenge', 'Nexus\Controllers\Api\WebAuthnApiController@registerChallenge');
$router->add('POST', '/api/webauthn/register-verify', 'Nexus\Controllers\Api\WebAuthnApiController@registerVerify');
$router->add('POST', '/api/webauthn/auth-challenge', 'Nexus\Controllers\Api\WebAuthnApiController@authChallenge');
$router->add('POST', '/api/webauthn/auth-verify', 'Nexus\Controllers\Api\WebAuthnApiController@authVerify');
$router->add('POST', '/api/webauthn/remove', 'Nexus\Controllers\Api\WebAuthnApiController@remove');
$router->add('GET', '/api/webauthn/remove', 'Nexus\Controllers\Api\WebAuthnApiController@removeAll'); // Clear all credentials via GET
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
$router->add('POST', '/api/ai/chat', 'Nexus\Controllers\Api\AiApiController@chat');
$router->add('POST', '/api/ai/chat/stream', 'Nexus\Controllers\Api\AiApiController@streamChat');
$router->add('GET', '/api/ai/conversations', 'Nexus\Controllers\Api\AiApiController@listConversations');
$router->add('GET', '/api/ai/conversations/([0-9]+)', 'Nexus\Controllers\Api\AiApiController@getConversation');
$router->add('POST', '/api/ai/conversations', 'Nexus\Controllers\Api\AiApiController@createConversation');
$router->add('DELETE', '/api/ai/conversations/([0-9]+)', 'Nexus\Controllers\Api\AiApiController@deleteConversation');
$router->add('GET', '/api/ai/providers', 'Nexus\Controllers\Api\AiApiController@getProviders');
$router->add('GET', '/api/ai/limits', 'Nexus\Controllers\Api\AiApiController@getLimits');
$router->add('POST', '/api/ai/test-provider', 'Nexus\Controllers\Api\AiApiController@testProvider');
$router->add('POST', '/api/ai/generate/listing', 'Nexus\Controllers\Api\AiApiController@generateListing');
$router->add('POST', '/api/ai/generate/event', 'Nexus\Controllers\Api\AiApiController@generateEvent');
$router->add('POST', '/api/ai/generate/message', 'Nexus\Controllers\Api\AiApiController@generateMessage');
$router->add('POST', '/api/ai/generate/bio', 'Nexus\Controllers\Api\AiApiController@generateBio');
$router->add('POST', '/api/ai/generate/newsletter', 'Nexus\Controllers\Api\AiApiController@generateNewsletter');
$router->add('POST', '/api/ai/generate/blog', 'Nexus\Controllers\Api\AiApiController@generateBlog');
$router->add('POST', '/api/ai/generate/page', 'Nexus\Controllers\Api\AiApiController@generatePage');

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
$router->add('POST', '/dashboard/switch_layout', 'Nexus\Controllers\HomeController@switchLayout');
$router->add('GET', '/dashboard/switch_layout', 'Nexus\Controllers\HomeController@switchLayout'); // Support GET link

// NEXUS SCORE SYSTEM
$router->add('GET', '/nexus-score', 'Nexus\Controllers\NexusScoreController@dashboard');
$router->add('GET', '/nexus-score/report', 'Nexus\Controllers\NexusScoreController@impactReport');
$router->add('GET', '/nexus-score/leaderboard', 'Nexus\Controllers\NexusScoreController@leaderboard');
$router->add('GET', '/profile/{id}/score', 'Nexus\Controllers\NexusScoreController@publicProfile');

// PUBLIC SECTOR DEMO OVERRIDES
$tenantSlug = TenantContext::get()['slug'] ?? '';
if ($tenantSlug === 'public-sector-demo') {
    $router->add('GET', '/', 'Nexus\Controllers\DemoController@home');
    $router->add('GET', '/home', 'Nexus\Controllers\DemoController@home');
    $router->add('GET', '/compliance', 'Nexus\Controllers\DemoController@compliance');
    $router->add('GET', '/hse-case-study', 'Nexus\Controllers\DemoController@hseCaseStudy');
    $router->add('GET', '/council-case-study', 'Nexus\Controllers\DemoController@councilCaseStudy');
    $router->add('GET', '/technical-specs', 'Nexus\Controllers\DemoController@technicalSpecs');
} else {
    // Normal Homepage
    $router->add('GET', '/', 'Nexus\Controllers\HomeController@index');
    $router->add('POST', '/', 'Nexus\Controllers\HomeController@index'); // Support Feed Post
    $router->add('GET', '/home', 'Nexus\Controllers\HomeController@index'); // Alias
    $router->add('POST', '/home', 'Nexus\Controllers\HomeController@index'); // Support Feed Post
}

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

// Search
$router->add('GET', '/search', 'Nexus\Controllers\SearchController@index');

// Static Content Pages
$router->add('GET', '/about', 'Nexus\Controllers\PageController@about');
$router->add('GET', '/contact', 'Nexus\Controllers\PageController@contact');
$router->add('POST', '/contact/submit', 'Nexus\Controllers\ContactController@submit');
$router->add('POST', '/contact/send', 'Nexus\Controllers\ContactController@submit');
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
$router->add('GET', '/terms', 'Nexus\Controllers\PageController@terms');
$router->add('GET', '/privacy', 'Nexus\Controllers\PageController@privacy');
$router->add('GET', '/accessibility', 'Nexus\Controllers\PageController@accessibility');
$router->add('GET', '/sitemap.xml', 'Nexus\Controllers\SitemapController@index');
$router->add('GET', '/robots.txt', 'Nexus\Controllers\RobotsController@index');

$router->add('GET', '/mobile-download', function () {
    \Nexus\Core\View::render('pages/mobile-download');
});

// Legal
$router->add('GET', '/legal', 'Nexus\Controllers\PageController@legal');
$router->add('GET', '/legal/volunteer-license', function () {
    \Nexus\Core\View::render('legal/volunteer-license');
});

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
foreach ([null, 'modern', 'civicone'] as $layout) {
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

// Specific Event Routes
$router->add('GET', '/events/{id}', 'Nexus\Controllers\EventController@show');
$router->add('GET', '/events/{id}/edit', 'Nexus\Controllers\EventController@edit');
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
// 8. MEMBERS & PROFILES
// --------------------------------------------------------------------------
$router->add('GET', '/settings', 'Nexus\Controllers\SettingsController@index'); // Route for /settings
$router->add('POST', '/settings/profile', 'Nexus\Controllers\SettingsController@updateProfile'); // NEW: Update Profile
$router->add('POST', '/settings/password', 'Nexus\Controllers\SettingsController@updatePassword'); // Route for password update
$router->add('POST', '/settings/privacy', 'Nexus\Controllers\SettingsController@updatePrivacy'); // Route for privacy update
$router->add('POST', '/settings/notifications', 'Nexus\Controllers\SettingsController@updateNotifications'); // Route for notification settings
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
$router->add('GET', '/federation/members/{id}', 'Nexus\Controllers\FederatedMemberController@show');

// Federated Messaging (Cross-Tenant)
$router->add('GET', '/federation/messages', 'Nexus\Controllers\FederatedMessageController@index');
$router->add('GET', '/federation/messages/api', 'Nexus\Controllers\FederatedMessageController@api');
$router->add('GET', '/federation/messages/{id}', 'Nexus\Controllers\FederatedMessageController@thread');
$router->add('POST', '/federation/messages/send', 'Nexus\Controllers\FederatedMessageController@send');
$router->add('POST', '/federation/messages/mark-read', 'Nexus\Controllers\FederatedMessageController@markRead');

// Federated Listings (Cross-Tenant)
$router->add('GET', '/federation/listings', 'Nexus\Controllers\FederatedListingController@index');
$router->add('GET', '/federation/listings/api', 'Nexus\Controllers\FederatedListingController@api');
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
    header("Location: {$base}/settings");
    exit;
});
$router->add('GET', '/profile/{id}', 'Nexus\Controllers\ProfileController@show');
$router->add('POST', '/profile/{id}', 'Nexus\Controllers\ProfileController@show'); // Allow POST for wall
$router->add('GET', '/profile', 'Nexus\Controllers\ProfileController@me');
$router->add('POST', '/profile', 'Nexus\Controllers\ProfileController@me'); // Allow POST for wall

// Connections
$router->add('GET', '/connections', function () {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
        exit;
    }
    $userId = $_SESSION['user_id'];
    $pending = \Nexus\Models\Connection::getPending($userId);
    $friends = \Nexus\Models\Connection::getFriends($userId);
    require __DIR__ . '/../views/modern/connections/index.php';
});
$router->add('GET', '/connections/add', function () {
    require __DIR__ . '/../views/connections/add.php';
});
$router->add('POST', '/connections/add', function () {
    require __DIR__ . '/../views/connections/add.php';
});
$router->add('POST', '/connections/accept', function () {
    require __DIR__ . '/../views/connections/accept.php';
});


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
// 10.8. ADMIN > SMART MATCHING
// --------------------------------------------------------------------------
$router->add('GET', '/admin/smart-matching', 'Nexus\Controllers\Admin\SmartMatchingController@index');
$router->add('GET', '/admin/smart-matching/analytics', 'Nexus\Controllers\Admin\SmartMatchingController@analytics');
$router->add('GET', '/admin/smart-matching/configuration', 'Nexus\Controllers\Admin\SmartMatchingController@configuration');
$router->add('POST', '/admin/smart-matching/configuration', 'Nexus\Controllers\Admin\SmartMatchingController@configuration');
$router->add('POST', '/admin/smart-matching/clear-cache', 'Nexus\Controllers\Admin\SmartMatchingController@clearCache');
$router->add('POST', '/admin/smart-matching/warmup-cache', 'Nexus\Controllers\Admin\SmartMatchingController@warmupCache');
$router->add('POST', '/admin/smart-matching/run-geocoding', 'Nexus\Controllers\Admin\SmartMatchingController@runGeocoding');
$router->add('GET', '/admin/smart-matching/api/stats', 'Nexus\Controllers\Admin\SmartMatchingController@apiStats');

// --------------------------------------------------------------------------
// 10.9. ADMIN > SEED GENERATOR
// --------------------------------------------------------------------------
$router->add('GET', '/admin/seed-generator', 'Nexus\Controllers\Admin\SeedGeneratorController@index');
$router->add('GET', '/admin/seed-generator/verification', 'Nexus\Controllers\Admin\SeedGeneratorVerificationController@index');
$router->add('POST', '/admin/seed-generator/generate-production', 'Nexus\Controllers\Admin\SeedGeneratorController@generateProduction');
$router->add('POST', '/admin/seed-generator/generate-demo', 'Nexus\Controllers\Admin\SeedGeneratorController@generateDemo');
$router->add('GET', '/admin/seed-generator/preview', 'Nexus\Controllers\Admin\SeedGeneratorController@preview');
$router->add('GET', '/admin/seed-generator/download', 'Nexus\Controllers\Admin\SeedGeneratorController@download');
$router->add('GET', '/admin/seed-generator/test', 'Nexus\Controllers\Admin\SeedGeneratorVerificationController@runLiveTest');

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
// 12. ADMIN DASHBOARD
// --------------------------------------------------------------------------
$router->add('GET', '/admin', 'Nexus\Controllers\AdminController@index');
$router->add('GET', '/admin/activity-log', 'Nexus\Controllers\AdminController@activityLogs');
$router->add('GET', '/admin/group-locations', 'Nexus\Controllers\AdminController@groupLocations');
$router->add('POST', '/admin/group-locations', 'Nexus\Controllers\AdminController@groupLocations');
$router->add('GET', '/admin/geocode-groups', 'Nexus\Controllers\AdminController@geocodeGroups');
$router->add('GET', '/admin/smart-match-users', 'Nexus\Controllers\AdminController@smartMatchUsers');
$router->add('GET', '/admin/smart-match-monitoring', 'Nexus\Controllers\AdminController@smartMatchMonitoring');
$router->add('GET', '/admin/test-smart-match', 'Nexus\Controllers\AdminController@testSmartMatch');

// WebP Image Converter
$router->add('GET', '/admin/webp-converter', 'Nexus\Controllers\AdminController@webpConverter');
$router->add('POST', '/admin/webp-converter/convert', 'Nexus\Controllers\AdminController@webpConvertBatch');

// Group Ranking Management
$router->add('GET', '/admin/group-ranking', 'Nexus\Controllers\AdminController@groupRanking');
$router->add('POST', '/admin/group-ranking/update', 'Nexus\Controllers\AdminController@updateFeaturedGroups');
$router->add('POST', '/admin/group-ranking/toggle', 'Nexus\Controllers\AdminController@toggleFeaturedGroup');
$router->add('GET', '/admin/test-ranking', 'Nexus\Controllers\AdminController@testRanking');

// Cron Endpoints
$router->add('GET', '/admin/cron/update-featured-groups', 'Nexus\Controllers\AdminController@cronUpdateFeaturedGroups');

// Group Types Management
$router->add('GET', '/admin/group-types', 'Nexus\Controllers\AdminController@groupTypes');
$router->add('POST', '/admin/group-types', 'Nexus\Controllers\AdminController@groupTypes');
$router->add('GET', '/admin/group-types/create', 'Nexus\Controllers\AdminController@groupTypeForm');
$router->add('GET', '/admin/group-types/edit/{id}', 'Nexus\Controllers\AdminController@groupTypeForm');
$router->add('POST', '/admin/group-types/edit/{id}', 'Nexus\Controllers\AdminController@groupTypeForm');

// User Management - MOVED TO Nexus\Controllers\Admin\UserController
// See lines 252+ 

// Listing Management

// Listing Management
$router->add('POST', '/admin/listings/delete', 'Nexus\Controllers\AdminController@deleteListing');

// Settings (User Hub) - Defined in Public Pages section (Line 284)
$router->add('GET', '/admin/settings', 'Nexus\Controllers\AdminController@settings');
$router->add('POST', '/admin/settings/update', 'Nexus\Controllers\AdminController@saveSettings');
$router->add('POST', '/admin/settings/save-tenant', 'Nexus\Controllers\AdminController@saveTenantSettings');
$router->add('POST', '/admin/settings/test-gmail', 'Nexus\Controllers\AdminController@testGmailConnection');

// Image Optimization Settings
$router->add('GET', '/admin/image-settings', 'Nexus\Controllers\AdminController@imageSettings');
$router->add('POST', '/admin/image-settings/save', 'Nexus\Controllers\AdminController@saveImageSettings');

// Tenant Admin Federation Dashboard
$router->add('GET', '/admin/federation/dashboard', 'Nexus\Controllers\FederationAdminController@index');
$router->add('POST', '/admin/federation/dashboard/toggle', 'Nexus\Controllers\FederationAdminController@toggleFederation');
$router->add('POST', '/admin/federation/dashboard/settings', 'Nexus\Controllers\FederationAdminController@updateSettings');

// Tenant Admin Federation Settings
$router->add('GET', '/admin/federation', 'Nexus\Controllers\Admin\FederationSettingsController@index');
$router->add('POST', '/admin/federation/update-feature', 'Nexus\Controllers\Admin\FederationSettingsController@updateFeature');
$router->add('GET', '/admin/federation/partnerships', 'Nexus\Controllers\Admin\FederationSettingsController@partnerships');
$router->add('POST', '/admin/federation/request-partnership', 'Nexus\Controllers\Admin\FederationSettingsController@requestPartnership');
$router->add('POST', '/admin/federation/approve-partnership', 'Nexus\Controllers\Admin\FederationSettingsController@approvePartnership');
$router->add('POST', '/admin/federation/reject-partnership', 'Nexus\Controllers\Admin\FederationSettingsController@rejectPartnership');
$router->add('POST', '/admin/federation/update-partnership-permissions', 'Nexus\Controllers\Admin\FederationSettingsController@updatePartnershipPermissions');
$router->add('POST', '/admin/federation/terminate-partnership', 'Nexus\Controllers\Admin\FederationSettingsController@terminatePartnership');
$router->add('POST', '/admin/federation/counter-propose', 'Nexus\Controllers\Admin\FederationSettingsController@counterPropose');
$router->add('POST', '/admin/federation/accept-counter-proposal', 'Nexus\Controllers\Admin\FederationSettingsController@acceptCounterProposal');
$router->add('POST', '/admin/federation/withdraw-request', 'Nexus\Controllers\Admin\FederationSettingsController@withdrawRequest');

// Federation Directory
$router->add('GET', '/admin/federation/directory', 'Nexus\Controllers\Admin\FederationDirectoryController@index');
$router->add('GET', '/admin/federation/directory/api', 'Nexus\Controllers\Admin\FederationDirectoryController@api');
$router->add('GET', '/admin/federation/directory/profile', 'Nexus\Controllers\Admin\FederationDirectoryController@profile');
$router->add('POST', '/admin/federation/directory/update-profile', 'Nexus\Controllers\Admin\FederationDirectoryController@updateProfile');
$router->add('POST', '/admin/federation/directory/request-partnership', 'Nexus\Controllers\Admin\FederationDirectoryController@requestPartnership');
$router->add('GET', '/admin/federation/directory/{id}', 'Nexus\Controllers\Admin\FederationDirectoryController@show');

// Federation Analytics
$router->add('GET', '/admin/federation/analytics', 'Nexus\Controllers\Admin\FederationAnalyticsController@index');
$router->add('GET', '/admin/federation/analytics/api', 'Nexus\Controllers\Admin\FederationAnalyticsController@api');
$router->add('GET', '/admin/federation/analytics/export', 'Nexus\Controllers\Admin\FederationAnalyticsController@export');

// Federation API Keys Management
$router->add('GET', '/admin/federation/api-keys', 'Nexus\Controllers\Admin\FederationApiKeysController@index');
$router->add('GET', '/admin/federation/api-keys/create', 'Nexus\Controllers\Admin\FederationApiKeysController@create');
$router->add('POST', '/admin/federation/api-keys/store', 'Nexus\Controllers\Admin\FederationApiKeysController@store');
$router->add('GET', '/admin/federation/api-keys/{id}', 'Nexus\Controllers\Admin\FederationApiKeysController@show');
$router->add('POST', '/admin/federation/api-keys/{id}/suspend', 'Nexus\Controllers\Admin\FederationApiKeysController@suspend');
$router->add('POST', '/admin/federation/api-keys/{id}/activate', 'Nexus\Controllers\Admin\FederationApiKeysController@activate');
$router->add('POST', '/admin/federation/api-keys/{id}/revoke', 'Nexus\Controllers\Admin\FederationApiKeysController@revoke');
$router->add('POST', '/admin/federation/api-keys/{id}/regenerate', 'Nexus\Controllers\Admin\FederationApiKeysController@regenerate');

// Federation Data Import/Export
$router->add('GET', '/admin/federation/data', 'Nexus\Controllers\Admin\FederationExportController@index');
$router->add('GET', '/admin/federation/export/users', 'Nexus\Controllers\Admin\FederationExportController@exportUsers');
$router->add('GET', '/admin/federation/export/partnerships', 'Nexus\Controllers\Admin\FederationExportController@exportPartnerships');
$router->add('GET', '/admin/federation/export/transactions', 'Nexus\Controllers\Admin\FederationExportController@exportTransactions');
$router->add('GET', '/admin/federation/export/audit', 'Nexus\Controllers\Admin\FederationExportController@exportAudit');
$router->add('GET', '/admin/federation/export/all', 'Nexus\Controllers\Admin\FederationExportController@exportAll');
$router->add('POST', '/admin/federation/import/users', 'Nexus\Controllers\Admin\FederationImportController@importUsers');
$router->add('GET', '/admin/federation/import/template', 'Nexus\Controllers\Admin\FederationImportController@downloadTemplate');

// Native App Management (FCM Push Notifications)
$router->add('GET', '/admin/native-app', 'Nexus\Controllers\AdminController@nativeApp');
$router->add('POST', '/admin/native-app/test-push', 'Nexus\Controllers\AdminController@sendTestPush');

// Feed Algorithm (EdgeRank) Settings
$router->add('GET', '/admin/feed-algorithm', 'Nexus\Controllers\AdminController@feedAlgorithm');
$router->add('POST', '/admin/feed-algorithm/save', 'Nexus\Controllers\AdminController@saveFeedAlgorithm');

// --------------------------------------------------------------------------
// DELIVERABILITY TRACKING MODULE
// --------------------------------------------------------------------------

// Dashboard & List Views
$router->add('GET', '/admin/deliverability', 'Nexus\Controllers\AdminController@deliverabilityDashboard');
$router->add('GET', '/admin/deliverability/list', 'Nexus\Controllers\AdminController@deliverablesList');
$router->add('GET', '/admin/deliverability/analytics', 'Nexus\Controllers\AdminController@deliverabilityAnalytics');

// CRUD Operations
$router->add('GET', '/admin/deliverability/create', 'Nexus\Controllers\AdminController@deliverableCreate');
$router->add('POST', '/admin/deliverability/store', 'Nexus\Controllers\AdminController@deliverableStore');
$router->add('GET', '/admin/deliverability/view/{id}', 'Nexus\Controllers\AdminController@deliverableView');
$router->add('GET', '/admin/deliverability/edit/{id}', 'Nexus\Controllers\AdminController@deliverableEdit');
$router->add('POST', '/admin/deliverability/update/{id}', 'Nexus\Controllers\AdminController@deliverableUpdate');
$router->add('POST', '/admin/deliverability/delete/{id}', 'Nexus\Controllers\AdminController@deliverableDelete');

// AJAX Endpoints
$router->add('POST', '/admin/deliverability/ajax/update-status', 'Nexus\Controllers\AdminController@deliverableUpdateStatus');
$router->add('POST', '/admin/deliverability/ajax/complete-milestone', 'Nexus\Controllers\AdminController@milestoneComplete');
$router->add('POST', '/admin/deliverability/ajax/add-comment', 'Nexus\Controllers\AdminController@deliverableAddComment');

// --------------------------------------------------------------------------
// LAYOUT SYSTEM COMPLETELY REMOVED - All page layout routes obliterated
// --------------------------------------------------------------------------

// Unified Algorithm Settings (MatchRank for Listings, CommunityRank for Members)
$router->add('GET', '/admin/algorithm-settings', 'Nexus\Controllers\AdminController@algorithmSettings');
$router->add('POST', '/admin/algorithm-settings/save', 'Nexus\Controllers\AdminController@saveAlgorithmSettings');

// Admin Live Search API (for command palette)
$router->add('GET', '/admin/api/search', 'Nexus\Controllers\AdminController@liveSearch');

// --------------------------------------------------------------------------
// 12.5. ADMIN > CATEGORIES & ATTRIBUTES
// --------------------------------------------------------------------------
$router->add('GET', '/admin/categories', 'Nexus\Controllers\Admin\CategoryController@index');
$router->add('GET', '/admin/categories/create', 'Nexus\Controllers\Admin\CategoryController@create');
$router->add('POST', '/admin/categories/store', 'Nexus\Controllers\Admin\CategoryController@store');

// Volunteering Admin
$router->add('GET', '/admin/volunteering', 'Nexus\Controllers\Admin\VolunteeringController@index');
$router->add('GET', '/admin/volunteering/approvals', 'Nexus\Controllers\Admin\VolunteeringController@approvals');
$router->add('GET', '/admin/volunteering/organizations', 'Nexus\Controllers\Admin\VolunteeringController@organizations');
$router->add('POST', '/admin/volunteering/approve', 'Nexus\Controllers\Admin\VolunteeringController@approve');
$router->add('POST', '/admin/volunteering/decline', 'Nexus\Controllers\Admin\VolunteeringController@decline');
$router->add('POST', '/admin/volunteering/delete', 'Nexus\Controllers\Admin\VolunteeringController@deleteOrg');
$router->add('GET', '/admin/categories/edit', function () {
    header('Location: /admin/categories');
    exit;
}); // Fallback
$router->add('GET', '/admin/categories/edit/{id}', 'Nexus\Controllers\Admin\CategoryController@edit');
$router->add('POST', '/admin/categories/update', 'Nexus\Controllers\Admin\CategoryController@update'); // Often forms POST to generic update
$router->add('POST', '/admin/categories/delete', 'Nexus\Controllers\Admin\CategoryController@delete'); // POST to delete

$router->add('GET', '/admin/attributes', 'Nexus\Controllers\Admin\AttributeController@index');
$router->add('GET', '/admin/attributes/create', 'Nexus\Controllers\Admin\AttributeController@create');
$router->add('POST', '/admin/attributes/store', 'Nexus\Controllers\Admin\AttributeController@store');
$router->add('GET', '/admin/attributes/edit', function () {
    header('Location: /admin/attributes');
    exit;
}); // Fallback
$router->add('GET', '/admin/attributes/edit/{id}', 'Nexus\Controllers\Admin\AttributeController@edit');
$router->add('POST', '/admin/attributes/update', 'Nexus\Controllers\Admin\AttributeController@update');
$router->add('POST', '/admin/attributes/delete', 'Nexus\Controllers\Admin\AttributeController@delete');

// Admin Pages
$router->add('GET', '/admin/pages', 'Nexus\Controllers\Admin\PageController@index');
$router->add('GET', '/admin/pages/create', 'Nexus\Controllers\Admin\PageController@create');
$router->add('GET', '/admin/pages/builder/{id}', 'Nexus\Controllers\Admin\PageController@builder');
$router->add('GET', '/admin/pages/preview/{id}', 'Nexus\Controllers\Admin\PageController@preview');
$router->add('GET', '/admin/pages/versions/{id}', 'Nexus\Controllers\Admin\PageController@versions');
$router->add('GET', '/admin/pages/duplicate/{id}', 'Nexus\Controllers\Admin\PageController@duplicate');
$router->add('GET', '/admin/pages/version-content/{id}', 'Nexus\Controllers\Admin\PageController@versionContent');
$router->add('POST', '/admin/pages/save', 'Nexus\Controllers\Admin\PageController@save');
$router->add('POST', '/admin/pages/restore-version', 'Nexus\Controllers\Admin\PageController@restoreVersion');
$router->add('POST', '/admin/pages/reorder', 'Nexus\Controllers\Admin\PageController@reorder');
$router->add('POST', '/admin/pages/delete', 'Nexus\Controllers\Admin\PageController@delete');

// Page Builder V2 API
$router->add('POST', '/admin/api/pages/{id}/blocks', 'Nexus\Controllers\Admin\PageController@saveBlocks');
$router->add('GET', '/admin/api/pages/{id}/blocks', 'Nexus\Controllers\Admin\PageController@getBlocks');
$router->add('POST', '/admin/api/blocks/preview', 'Nexus\Controllers\Admin\PageController@previewBlock');
$router->add('POST', '/admin/api/pages/{id}/settings', 'Nexus\Controllers\Admin\PageController@saveSettings');

// Admin Menus (Menu Manager)
$router->add('GET', '/admin/menus', 'Nexus\Controllers\Admin\MenuController@index');
$router->add('GET', '/admin/menus/create', 'Nexus\Controllers\Admin\MenuController@create');
$router->add('POST', '/admin/menus/create', 'Nexus\Controllers\Admin\MenuController@create');
$router->add('GET', '/admin/menus/builder/{id}', 'Nexus\Controllers\Admin\MenuController@builder');
$router->add('POST', '/admin/menus/update/{id}', 'Nexus\Controllers\Admin\MenuController@update');
$router->add('POST', '/admin/menus/toggle/{id}', 'Nexus\Controllers\Admin\MenuController@toggleActive');
$router->add('POST', '/admin/menus/delete/{id}', 'Nexus\Controllers\Admin\MenuController@delete');
$router->add('POST', '/admin/menus/item/add', 'Nexus\Controllers\Admin\MenuController@addItem');
$router->add('GET', '/admin/menus/item/{id}', 'Nexus\Controllers\Admin\MenuController@getItem');
$router->add('POST', '/admin/menus/item/update/{id}', 'Nexus\Controllers\Admin\MenuController@updateItem');
$router->add('POST', '/admin/menus/item/delete/{id}', 'Nexus\Controllers\Admin\MenuController@deleteItem');
$router->add('POST', '/admin/menus/reorder', 'Nexus\Controllers\Admin\MenuController@reorder');

// Admin Plans (Subscription Manager)
$router->add('GET', '/admin/plans', 'Nexus\Controllers\Admin\PlanController@index');
$router->add('GET', '/admin/plans/create', 'Nexus\Controllers\Admin\PlanController@create');
$router->add('POST', '/admin/plans/create', 'Nexus\Controllers\Admin\PlanController@create');
$router->add('GET', '/admin/plans/edit/{id}', 'Nexus\Controllers\Admin\PlanController@edit');
$router->add('POST', '/admin/plans/edit/{id}', 'Nexus\Controllers\Admin\PlanController@edit');
$router->add('POST', '/admin/plans/delete/{id}', 'Nexus\Controllers\Admin\PlanController@delete');
$router->add('GET', '/admin/plans/subscriptions', 'Nexus\Controllers\Admin\PlanController@subscriptions');
$router->add('POST', '/admin/plans/assign', 'Nexus\Controllers\Admin\PlanController@assignPlan');
$router->add('GET', '/admin/plans/comparison', 'Nexus\Controllers\Admin\PlanController@comparison');

// Admin News (Blog)
$router->add('GET', '/admin/news', 'Nexus\Controllers\Admin\BlogController@index');
$router->add('GET', '/admin/news/create', 'Nexus\Controllers\Admin\BlogController@create');
$router->add('GET', '/admin/news/edit/{id}', 'Nexus\Controllers\Admin\BlogController@edit');
$router->add('GET', '/admin/news/builder/{id}', 'Nexus\Controllers\Admin\BlogController@builder');
$router->add('POST', '/admin/news/save-builder', 'Nexus\Controllers\Admin\BlogController@saveBuilder');
$router->add('POST', '/admin/news/update', 'Nexus\Controllers\Admin\BlogController@update');
$router->add('GET', '/admin/news/delete/{id}', 'Nexus\Controllers\Admin\BlogController@delete');

// Legacy Aliases (admin/blog)
$router->add('GET', '/admin/blog', 'Nexus\Controllers\Admin\BlogController@index');
$router->add('GET', '/admin/blog/create', 'Nexus\Controllers\Admin\BlogController@create');
$router->add('GET', '/admin/blog/edit/{id}', 'Nexus\Controllers\Admin\BlogController@edit');
$router->add('GET', '/admin/blog/builder/{id}', 'Nexus\Controllers\Admin\BlogController@builder');
$router->add('POST', '/admin/blog/save-builder', 'Nexus\Controllers\Admin\BlogController@saveBuilder');
$router->add('POST', '/admin/blog/update/{id}', 'Nexus\Controllers\Admin\BlogController@update'); // Note: Added {id} to match form if needed, or generic
$router->add('POST', '/admin/blog/store', 'Nexus\Controllers\Admin\BlogController@store');
$router->add('POST', '/admin/blog/delete', 'Nexus\Controllers\Admin\BlogController@delete');

// Admin Blog Restore
$router->add('GET', '/admin/blog-restore', 'Nexus\Controllers\Admin\BlogRestoreController@index');
$router->add('GET', '/admin/blog-restore/diagnostic', 'Nexus\Controllers\Admin\BlogRestoreController@diagnostic');
$router->add('POST', '/admin/blog-restore/upload', 'Nexus\Controllers\Admin\BlogRestoreController@upload');
$router->add('POST', '/admin/blog-restore/import', 'Nexus\Controllers\Admin\BlogRestoreController@import');
$router->add('GET', '/admin/blog-restore/export', 'Nexus\Controllers\Admin\BlogRestoreController@downloadExport');

// Admin Nexus Score Analytics
$router->add('GET', '/admin/nexus-score/analytics', 'Nexus\Controllers\NexusScoreController@adminAnalytics');

// Admin Users
$router->add('GET', '/admin/users', 'Nexus\Controllers\Admin\UserController@index');
$router->add('GET', '/admin/users/create', 'Nexus\Controllers\Admin\UserController@create');
$router->add('POST', '/admin/users/store', 'Nexus\Controllers\Admin\UserController@store');
$router->add('GET', '/admin/users/edit', function () {
    header('Location: /admin/users');
    exit;
}); // Fallback
$router->add('GET', '/admin/users/edit/{id}', 'Nexus\Controllers\Admin\UserController@edit');
$router->add('GET', '/admin/users/{id}/edit', 'Nexus\Controllers\Admin\UserController@edit'); // Standard REST Alias
$router->add('GET', '/admin/users/{id}/permissions', 'Nexus\Controllers\Admin\UserController@permissions');
$router->add('POST', '/admin/users/update', 'Nexus\Controllers\Admin\UserController@update');
$router->add('POST', '/admin/users/delete', 'Nexus\Controllers\Admin\UserController@delete');
$router->add('POST', '/admin/users/suspend', 'Nexus\Controllers\Admin\UserController@suspend');
$router->add('POST', '/admin/users/ban', 'Nexus\Controllers\Admin\UserController@ban');
$router->add('POST', '/admin/users/reactivate', 'Nexus\Controllers\Admin\UserController@reactivate');
$router->add('POST', '/admin/users/revoke-super-admin', 'Nexus\Controllers\Admin\UserController@revokeSuperAdmin');
$router->add('POST', '/admin/approve-user', 'Nexus\Controllers\Admin\UserController@approve');
$router->add('POST', '/admin/users/badges/add', 'Nexus\Controllers\Admin\UserController@addBadge');
$router->add('POST', '/admin/users/badges/remove', 'Nexus\Controllers\Admin\UserController@removeBadge');
$router->add('POST', '/admin/users/badges/recheck', 'Nexus\Controllers\Admin\UserController@recheckBadges');
$router->add('POST', '/admin/users/badges/bulk-award', 'Nexus\Controllers\Admin\UserController@bulkAwardBadge');
$router->add('POST', '/admin/users/badges/recheck-all', 'Nexus\Controllers\Admin\UserController@recheckAllBadges');

// Admin Impersonation
$router->add('POST', '/admin/impersonate', 'Nexus\Controllers\AuthController@impersonate');
$router->add('GET', '/admin/stop-impersonating', 'Nexus\Controllers\AuthController@stopImpersonating');
$router->add('POST', '/admin/stop-impersonating', 'Nexus\Controllers\AuthController@stopImpersonating');

// Admin Groups
$router->add('GET', '/admin/groups', 'Nexus\Controllers\Admin\GroupAdminController@index');
$router->add('GET', '/admin/groups/analytics', 'Nexus\Controllers\Admin\GroupAdminController@analytics');
$router->add('GET', '/admin/groups/recommendations', 'Nexus\Controllers\Admin\GroupAdminController@recommendations');
$router->add('GET', '/admin/groups/view', 'Nexus\Controllers\Admin\GroupAdminController@view');
$router->add('GET', '/admin/groups/settings', 'Nexus\Controllers\Admin\GroupAdminController@settings');
$router->add('POST', '/admin/groups/settings', 'Nexus\Controllers\Admin\GroupAdminController@saveSettings');
$router->add('GET', '/admin/groups/policies', 'Nexus\Controllers\Admin\GroupAdminController@policies');
$router->add('POST', '/admin/groups/policies', 'Nexus\Controllers\Admin\GroupAdminController@savePolicies');
$router->add('GET', '/admin/groups/moderation', 'Nexus\Controllers\Admin\GroupAdminController@moderation');
$router->add('POST', '/admin/groups/moderate-flag', 'Nexus\Controllers\Admin\GroupAdminController@moderateFlag');
$router->add('GET', '/admin/groups/approvals', 'Nexus\Controllers\Admin\GroupAdminController@approvals');
$router->add('POST', '/admin/groups/process-approval', 'Nexus\Controllers\Admin\GroupAdminController@processApproval');
$router->add('POST', '/admin/groups/manage-members', 'Nexus\Controllers\Admin\GroupAdminController@manageMembers');
$router->add('POST', '/admin/groups/batch-operations', 'Nexus\Controllers\Admin\GroupAdminController@batchOperations');
$router->add('GET', '/admin/groups/export', 'Nexus\Controllers\Admin\GroupAdminController@export');
$router->add('POST', '/admin/groups/toggle-featured', 'Nexus\Controllers\Admin\GroupAdminController@toggleFeatured');
$router->add('POST', '/admin/groups/delete', 'Nexus\Controllers\Admin\GroupAdminController@delete');

// Admin Matching Diagnostic
$router->add('GET', '/admin/matching-diagnostic', 'Nexus\Controllers\Admin\MatchingDiagnosticController@index');

// Admin Gamification
$router->add('GET', '/admin/gamification', 'Nexus\Controllers\Admin\GamificationController@index');
$router->add('POST', '/admin/gamification/recheck-all', 'Nexus\Controllers\Admin\GamificationController@recheckAll');
$router->add('POST', '/admin/gamification/bulk-award', 'Nexus\Controllers\Admin\GamificationController@bulkAward');
$router->add('POST', '/admin/gamification/award-all', 'Nexus\Controllers\Admin\GamificationController@awardToAll');
$router->add('POST', '/admin/gamification/reset-xp', 'Nexus\Controllers\Admin\GamificationController@resetXp');
$router->add('POST', '/admin/gamification/clear-badges', 'Nexus\Controllers\Admin\GamificationController@clearBadges');

// Admin AI Settings
$router->add('GET', '/admin/ai-settings', 'Nexus\Controllers\Admin\AiSettingsController@index');
$router->add('POST', '/admin/ai-settings/save', 'Nexus\Controllers\Admin\AiSettingsController@save');
$router->add('POST', '/admin/ai-settings/test', 'Nexus\Controllers\Admin\AiSettingsController@testProvider');
$router->add('POST', '/admin/ai-settings/initialize', 'Nexus\Controllers\Admin\AiSettingsController@initialize');

// Admin Custom Badges
$router->add('GET', '/admin/custom-badges', 'Nexus\Controllers\Admin\CustomBadgeController@index');
$router->add('GET', '/admin/custom-badges/create', 'Nexus\Controllers\Admin\CustomBadgeController@create');
$router->add('POST', '/admin/custom-badges/store', 'Nexus\Controllers\Admin\CustomBadgeController@store');
$router->add('GET', '/admin/custom-badges/edit/{id}', 'Nexus\Controllers\Admin\CustomBadgeController@edit');
$router->add('POST', '/admin/custom-badges/update', 'Nexus\Controllers\Admin\CustomBadgeController@update');
$router->add('POST', '/admin/custom-badges/delete', 'Nexus\Controllers\Admin\CustomBadgeController@delete');
$router->add('POST', '/admin/custom-badges/award', 'Nexus\Controllers\Admin\CustomBadgeController@award');
$router->add('POST', '/admin/custom-badges/revoke', 'Nexus\Controllers\Admin\CustomBadgeController@revoke');
$router->add('GET', '/admin/custom-badges/awardees', 'Nexus\Controllers\Admin\CustomBadgeController@getAwardees');

// Admin Achievement Analytics
$router->add('GET', '/admin/gamification/analytics', 'Nexus\Controllers\Admin\GamificationController@analytics');

// Admin Timebanking Analytics & Abuse Detection
$router->add('GET', '/admin/timebanking', 'Nexus\Controllers\Admin\TimebankingController@index');
$router->add('GET', '/admin/timebanking/alerts', 'Nexus\Controllers\Admin\TimebankingController@alerts');
$router->add('GET', '/admin/timebanking/alert/{id}', 'Nexus\Controllers\Admin\TimebankingController@viewAlert');
$router->add('POST', '/admin/timebanking/alert/{id}/status', 'Nexus\Controllers\Admin\TimebankingController@updateAlertStatus');
$router->add('POST', '/admin/timebanking/run-detection', 'Nexus\Controllers\Admin\TimebankingController@runDetection');
$router->add('GET', '/admin/timebanking/user-report/{id}', 'Nexus\Controllers\Admin\TimebankingController@userReport');
$router->add('GET', '/admin/timebanking/user-report', 'Nexus\Controllers\Admin\TimebankingController@userReport');
$router->add('POST', '/admin/timebanking/adjust-balance', 'Nexus\Controllers\Admin\TimebankingController@adjustBalance');
$router->add('GET', '/admin/timebanking/org-wallets', 'Nexus\Controllers\Admin\TimebankingController@orgWallets');
$router->add('POST', '/admin/timebanking/org-wallets/initialize', 'Nexus\Controllers\Admin\TimebankingController@initializeOrgWallet');
$router->add('POST', '/admin/timebanking/org-wallets/initialize-all', 'Nexus\Controllers\Admin\TimebankingController@initializeAllOrgWallets');
$router->add('GET', '/admin/timebanking/org-members/{id}', 'Nexus\Controllers\Admin\TimebankingController@orgMembers');
$router->add('POST', '/admin/timebanking/org-members/add', 'Nexus\Controllers\Admin\TimebankingController@addOrgMember');
$router->add('POST', '/admin/timebanking/org-members/update-role', 'Nexus\Controllers\Admin\TimebankingController@updateOrgMemberRole');
$router->add('POST', '/admin/timebanking/org-members/remove', 'Nexus\Controllers\Admin\TimebankingController@removeOrgMember');
$router->add('GET', '/admin/timebanking/create-org', 'Nexus\Controllers\Admin\TimebankingController@createOrgForm');
$router->add('POST', '/admin/timebanking/create-org', 'Nexus\Controllers\Admin\TimebankingController@createOrg');
$router->add('GET', '/api/admin/users/search', 'Nexus\Controllers\Admin\TimebankingController@userSearchApi');

// Admin Campaigns
$router->add('GET', '/admin/gamification/campaigns', 'Nexus\Controllers\Admin\GamificationController@campaigns');
$router->add('GET', '/admin/gamification/campaigns/create', 'Nexus\Controllers\Admin\GamificationController@createCampaign');
$router->add('GET', '/admin/gamification/campaigns/edit/{id}', 'Nexus\Controllers\Admin\GamificationController@editCampaign');
$router->add('POST', '/admin/gamification/campaigns/save', 'Nexus\Controllers\Admin\GamificationController@saveCampaign');
$router->add('POST', '/admin/gamification/campaigns/activate', 'Nexus\Controllers\Admin\GamificationController@activateCampaign');
$router->add('POST', '/admin/gamification/campaigns/pause', 'Nexus\Controllers\Admin\GamificationController@pauseCampaign');
$router->add('POST', '/admin/gamification/campaigns/delete', 'Nexus\Controllers\Admin\GamificationController@deleteCampaign');
$router->add('POST', '/admin/gamification/campaigns/run', 'Nexus\Controllers\Admin\GamificationController@runCampaign');
$router->add('POST', '/admin/gamification/campaigns/preview-audience', 'Nexus\Controllers\Admin\GamificationController@previewAudience');

// --------------------------------------------------------------------------
// 12.9. ADMIN > CRON JOB MANAGER
// --------------------------------------------------------------------------
$router->add('GET', '/admin/cron-jobs', 'Nexus\Controllers\Admin\CronJobController@index');
$router->add('POST', '/admin/cron-jobs/run/{id}', 'Nexus\Controllers\Admin\CronJobController@run');
$router->add('POST', '/admin/cron-jobs/toggle/{id}', 'Nexus\Controllers\Admin\CronJobController@toggle');
$router->add('GET', '/admin/cron-jobs/logs', 'Nexus\Controllers\Admin\CronJobController@logs');
$router->add('GET', '/admin/cron-jobs/setup', 'Nexus\Controllers\Admin\CronJobController@setup');
$router->add('GET', '/admin/cron-jobs/settings', 'Nexus\Controllers\Admin\CronJobController@settings');
$router->add('POST', '/admin/cron-jobs/settings', 'Nexus\Controllers\Admin\CronJobController@saveSettings');
$router->add('POST', '/admin/cron-jobs/clear-logs', 'Nexus\Controllers\Admin\CronJobController@clearLogs');
$router->add('GET', '/admin/cron-jobs/api/stats', 'Nexus\Controllers\Admin\CronJobController@apiStats');

// Admin Listings
$router->add('GET', '/admin/listings', 'Nexus\Controllers\Admin\ListingController@index');
$router->add('POST', '/admin/listings/delete/{id}', 'Nexus\Controllers\Admin\ListingController@delete');

// Admin SEO
$router->add('GET', '/admin/seo', 'Nexus\Controllers\Admin\SeoController@index');
$router->add('POST', '/admin/seo/store', 'Nexus\Controllers\Admin\SeoController@store');
$router->add('GET', '/admin/seo/audit', 'Nexus\Controllers\Admin\SeoController@audit');
$router->add('GET', '/admin/seo/bulk/{type}', 'Nexus\Controllers\Admin\SeoController@bulkEdit');
$router->add('POST', '/admin/seo/bulk/save', 'Nexus\Controllers\Admin\SeoController@bulkSave');
$router->add('GET', '/admin/seo/redirects', 'Nexus\Controllers\Admin\SeoController@redirects');
$router->add('POST', '/admin/seo/redirects/store', 'Nexus\Controllers\Admin\SeoController@storeRedirect');
$router->add('POST', '/admin/seo/redirects/delete', 'Nexus\Controllers\Admin\SeoController@deleteRedirect');
$router->add('GET', '/admin/seo/organization', 'Nexus\Controllers\Admin\SeoController@organization');
$router->add('POST', '/admin/seo/organization/save', 'Nexus\Controllers\Admin\SeoController@saveOrganization');
$router->add('POST', '/admin/seo/ping-sitemaps', 'Nexus\Controllers\Admin\SeoController@pingSitemaps');

// 404 Error Tracking
$router->add('GET', '/admin/404-errors', 'Nexus\Controllers\Admin\Error404Controller@index');
$router->add('GET', '/admin/404-errors/api/list', 'Nexus\Controllers\Admin\Error404Controller@apiList');
$router->add('GET', '/admin/404-errors/api/top', 'Nexus\Controllers\Admin\Error404Controller@topErrors');
$router->add('GET', '/admin/404-errors/api/stats', 'Nexus\Controllers\Admin\Error404Controller@stats');
$router->add('POST', '/admin/404-errors/mark-resolved', 'Nexus\Controllers\Admin\Error404Controller@markResolved');
$router->add('POST', '/admin/404-errors/mark-unresolved', 'Nexus\Controllers\Admin\Error404Controller@markUnresolved');
$router->add('POST', '/admin/404-errors/delete', 'Nexus\Controllers\Admin\Error404Controller@delete');
$router->add('GET', '/admin/404-errors/search', 'Nexus\Controllers\Admin\Error404Controller@search');
$router->add('POST', '/admin/404-errors/create-redirect', 'Nexus\Controllers\Admin\Error404Controller@createRedirect');
$router->add('POST', '/admin/404-errors/bulk-redirect', 'Nexus\Controllers\Admin\Error404Controller@bulkRedirect');
$router->add('POST', '/admin/404-errors/clean-old', 'Nexus\Controllers\Admin\Error404Controller@cleanOld');

// --------------------------------------------------------------------------
// 12.6. ADMIN > NEWSLETTERS
// --------------------------------------------------------------------------
$router->add('GET', '/admin/newsletters', 'Nexus\Controllers\Admin\NewsletterController@index');
$router->add('GET', '/admin/newsletters/create', 'Nexus\Controllers\Admin\NewsletterController@create');
$router->add('POST', '/admin/newsletters/store', 'Nexus\Controllers\Admin\NewsletterController@store');
$router->add('GET', '/admin/newsletters/edit/{id}', 'Nexus\Controllers\Admin\NewsletterController@edit');
$router->add('POST', '/admin/newsletters/update/{id}', 'Nexus\Controllers\Admin\NewsletterController@update');
$router->add('GET', '/admin/newsletters/preview/{id}', 'Nexus\Controllers\Admin\NewsletterController@preview');
$router->add('POST', '/admin/newsletters/send/{id}', 'Nexus\Controllers\Admin\NewsletterController@send');
$router->add('GET', '/admin/newsletters/send-direct/{id}', 'Nexus\Controllers\Admin\NewsletterController@sendDirect');
$router->add('POST', '/admin/newsletters/send-test/{id}', 'Nexus\Controllers\Admin\NewsletterController@sendTest');
$router->add('POST', '/admin/newsletters/delete', 'Nexus\Controllers\Admin\NewsletterController@delete');
$router->add('GET', '/admin/newsletters/duplicate/{id}', 'Nexus\Controllers\Admin\NewsletterController@duplicate');
$router->add('GET', '/admin/newsletters/stats/{id}', 'Nexus\Controllers\Admin\NewsletterController@stats');
$router->add('GET', '/admin/newsletters/analytics', 'Nexus\Controllers\Admin\NewsletterController@analytics');
$router->add('POST', '/admin/newsletters/select-winner/{id}', 'Nexus\Controllers\Admin\NewsletterController@selectWinner');

// AJAX Endpoints for Live Count & Preview
$router->add('POST', '/admin/newsletters/get-recipient-count', 'Nexus\Controllers\Admin\NewsletterController@getRecipientCount');
$router->add('POST', '/admin/newsletters/preview-recipients', 'Nexus\Controllers\Admin\NewsletterController@previewRecipients');

// Admin Subscriber Management
$router->add('GET', '/admin/newsletters/subscribers', 'Nexus\Controllers\Admin\NewsletterController@subscribers');
$router->add('POST', '/admin/newsletters/subscribers/add', 'Nexus\Controllers\Admin\NewsletterController@addSubscriber');
$router->add('POST', '/admin/newsletters/subscribers/delete', 'Nexus\Controllers\Admin\NewsletterController@deleteSubscriber');
$router->add('POST', '/admin/newsletters/subscribers/sync', 'Nexus\Controllers\Admin\NewsletterController@syncMembers');
$router->add('GET', '/admin/newsletters/subscribers/export', 'Nexus\Controllers\Admin\NewsletterController@exportSubscribers');
$router->add('POST', '/admin/newsletters/subscribers/import', 'Nexus\Controllers\Admin\NewsletterController@importSubscribers');

// Segment Management
$router->add('GET', '/admin/newsletters/segments', 'Nexus\Controllers\Admin\NewsletterController@segments');
$router->add('GET', '/admin/newsletters/segments/create', 'Nexus\Controllers\Admin\NewsletterController@createSegment');
$router->add('POST', '/admin/newsletters/segments/store', 'Nexus\Controllers\Admin\NewsletterController@storeSegment');
$router->add('GET', '/admin/newsletters/segments/edit/{id}', 'Nexus\Controllers\Admin\NewsletterController@editSegment');
$router->add('POST', '/admin/newsletters/segments/update/{id}', 'Nexus\Controllers\Admin\NewsletterController@updateSegment');
$router->add('POST', '/admin/newsletters/segments/delete', 'Nexus\Controllers\Admin\NewsletterController@deleteSegment');
$router->add('POST', '/admin/newsletters/segments/preview', 'Nexus\Controllers\Admin\NewsletterController@previewSegment');
$router->add('GET', '/admin/newsletters/segments/suggestions', 'Nexus\Controllers\Admin\NewsletterController@getSmartSuggestions');
$router->add('POST', '/admin/newsletters/segments/from-suggestion', 'Nexus\Controllers\Admin\NewsletterController@createFromSuggestion');

// Template Management
$router->add('GET', '/admin/newsletters/templates', 'Nexus\Controllers\Admin\NewsletterController@templates');
$router->add('GET', '/admin/newsletters/templates/create', 'Nexus\Controllers\Admin\NewsletterController@createTemplate');
$router->add('POST', '/admin/newsletters/templates/store', 'Nexus\Controllers\Admin\NewsletterController@storeTemplate');
$router->add('GET', '/admin/newsletters/templates/edit/{id}', 'Nexus\Controllers\Admin\NewsletterController@editTemplate');
$router->add('POST', '/admin/newsletters/templates/update/{id}', 'Nexus\Controllers\Admin\NewsletterController@updateTemplate');
$router->add('POST', '/admin/newsletters/templates/delete', 'Nexus\Controllers\Admin\NewsletterController@deleteTemplate');
$router->add('GET', '/admin/newsletters/templates/duplicate/{id}', 'Nexus\Controllers\Admin\NewsletterController@duplicateTemplate');
$router->add('GET', '/admin/newsletters/templates/preview/{id}', 'Nexus\Controllers\Admin\NewsletterController@previewTemplate');
$router->add('POST', '/admin/newsletters/save-as-template', 'Nexus\Controllers\Admin\NewsletterController@saveAsTemplate');
$router->add('GET', '/admin/newsletters/get-templates', 'Nexus\Controllers\Admin\NewsletterController@getTemplates');
$router->add('GET', '/admin/newsletters/load-template/{id}', 'Nexus\Controllers\Admin\NewsletterController@loadTemplate');

// Bounce Management
$router->add('GET', '/admin/newsletters/bounces', 'Nexus\Controllers\Admin\NewsletterController@bounces');
$router->add('POST', '/admin/newsletters/unsuppress', 'Nexus\Controllers\Admin\NewsletterController@unsuppress');
$router->add('POST', '/admin/newsletters/suppress', 'Nexus\Controllers\Admin\NewsletterController@suppress');

// Resend to Non-Openers
$router->add('GET', '/admin/newsletters/resend/{id}', 'Nexus\Controllers\Admin\NewsletterController@resendForm');
$router->add('POST', '/admin/newsletters/resend/{id}', 'Nexus\Controllers\Admin\NewsletterController@resend');
$router->add('GET', '/admin/newsletters/resend-info/{id}', 'Nexus\Controllers\Admin\NewsletterController@getResendInfo');

// Send Time Optimization
$router->add('GET', '/admin/newsletters/send-time', 'Nexus\Controllers\Admin\NewsletterController@sendTimeOptimization');
$router->add('GET', '/admin/newsletters/send-time-recommendations', 'Nexus\Controllers\Admin\NewsletterController@getSendTimeRecommendations');
$router->add('GET', '/admin/newsletters/send-time-heatmap', 'Nexus\Controllers\Admin\NewsletterController@getSendTimeHeatmap');

// Email Client Preview
$router->add('GET', '/admin/newsletters/client-preview/{id}', 'Nexus\Controllers\Admin\NewsletterController@getEmailClientPreview');

// Diagnostics & Repair
$router->add('GET', '/admin/newsletters/diagnostics', 'Nexus\Controllers\Admin\NewsletterController@diagnostics');
$router->add('POST', '/admin/newsletters/repair', 'Nexus\Controllers\Admin\NewsletterController@repair');

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
$router->add('GET', '/admin/enterprise', 'Nexus\Controllers\Admin\EnterpriseController@dashboard');

// API Test Runner
$router->add('GET', '/admin/tests', 'Nexus\Controllers\Admin\TestRunnerController@index');
$router->add('POST', '/admin/tests/run', 'Nexus\Controllers\Admin\TestRunnerController@runTests');
$router->add('GET', '/admin/tests/view', 'Nexus\Controllers\Admin\TestRunnerController@viewRun');

// GDPR Compliance
$router->add('GET', '/admin/enterprise/gdpr', 'Nexus\Controllers\Admin\EnterpriseController@gdprDashboard');
$router->add('GET', '/admin/enterprise/gdpr/requests', 'Nexus\Controllers\Admin\EnterpriseController@gdprRequests');
$router->add('GET', '/admin/enterprise/gdpr/requests/new', 'Nexus\Controllers\Admin\EnterpriseController@gdprRequestCreate');
$router->add('GET', '/admin/enterprise/gdpr/requests/create', 'Nexus\Controllers\Admin\EnterpriseController@gdprRequestCreate');
$router->add('POST', '/admin/enterprise/gdpr/requests', 'Nexus\Controllers\Admin\EnterpriseController@gdprRequestStore');
$router->add('GET', '/admin/enterprise/gdpr/requests/{id}', 'Nexus\Controllers\Admin\EnterpriseController@gdprRequestView');
$router->add('POST', '/admin/enterprise/gdpr/requests/{id}/process', 'Nexus\Controllers\Admin\EnterpriseController@gdprRequestProcess');
$router->add('POST', '/admin/enterprise/gdpr/requests/{id}/complete', 'Nexus\Controllers\Admin\EnterpriseController@gdprRequestComplete');
$router->add('POST', '/admin/enterprise/gdpr/requests/{id}/reject', 'Nexus\Controllers\Admin\EnterpriseController@gdprRequestReject');
$router->add('POST', '/admin/enterprise/gdpr/requests/{id}/assign', 'Nexus\Controllers\Admin\EnterpriseController@gdprRequestAssign');
$router->add('POST', '/admin/enterprise/gdpr/requests/{id}/notes', 'Nexus\Controllers\Admin\EnterpriseController@gdprRequestAddNote');
$router->add('POST', '/admin/enterprise/gdpr/requests/{id}/generate-export', 'Nexus\Controllers\Admin\EnterpriseController@gdprGenerateExport');
$router->add('POST', '/admin/enterprise/gdpr/requests/bulk-process', 'Nexus\Controllers\Admin\EnterpriseController@gdprBulkProcess');
$router->add('GET', '/admin/enterprise/gdpr/consents', 'Nexus\Controllers\Admin\EnterpriseController@gdprConsents');
$router->add('POST', '/admin/enterprise/gdpr/consents/types', 'Nexus\Controllers\Admin\EnterpriseController@gdprConsentTypeStore');
$router->add('GET', '/admin/enterprise/gdpr/consents/{id}', 'Nexus\Controllers\Admin\EnterpriseController@gdprConsentDetail');
$router->add('GET', '/admin/enterprise/gdpr/consents/export', 'Nexus\Controllers\Admin\EnterpriseController@gdprConsentsExport');
$router->add('GET', '/admin/enterprise/gdpr/breaches', 'Nexus\Controllers\Admin\EnterpriseController@gdprBreaches');
$router->add('GET', '/admin/enterprise/gdpr/breaches/report', 'Nexus\Controllers\Admin\EnterpriseController@gdprBreachReportForm');
$router->add('POST', '/admin/enterprise/gdpr/breaches', 'Nexus\Controllers\Admin\EnterpriseController@gdprBreachReport');
$router->add('GET', '/admin/enterprise/gdpr/breaches/{id}', 'Nexus\Controllers\Admin\EnterpriseController@gdprBreachView');
$router->add('POST', '/admin/enterprise/gdpr/breaches/{id}/escalate', 'Nexus\Controllers\Admin\EnterpriseController@gdprBreachEscalate');
$router->add('GET', '/admin/enterprise/gdpr/audit', 'Nexus\Controllers\Admin\EnterpriseController@gdprAuditLog');
$router->add('GET', '/admin/enterprise/gdpr/audit/export', 'Nexus\Controllers\Admin\EnterpriseController@gdprAuditExport');
$router->add('POST', '/admin/enterprise/gdpr/export-report', 'Nexus\Controllers\Admin\EnterpriseController@gdprExportReport');

// Monitoring & APM
$router->add('GET', '/admin/enterprise/monitoring', 'Nexus\Controllers\Admin\EnterpriseController@monitoring');
$router->add('GET', '/admin/enterprise/monitoring/health', 'Nexus\Controllers\Admin\EnterpriseController@healthCheck');
$router->add('GET', '/admin/enterprise/monitoring/requirements', 'Nexus\Controllers\Admin\EnterpriseController@requirements');
$router->add('GET', '/admin/enterprise/monitoring/logs', 'Nexus\Controllers\Admin\EnterpriseController@logs');
$router->add('GET', '/admin/enterprise/monitoring/logs/download', 'Nexus\Controllers\Admin\EnterpriseController@logsDownload');
$router->add('POST', '/admin/enterprise/monitoring/logs/clear', 'Nexus\Controllers\Admin\EnterpriseController@logsClear');
$router->add('GET', '/admin/enterprise/monitoring/logs/{filename}', 'Nexus\Controllers\Admin\EnterpriseController@logView');

// Real-Time Updates API
$router->add('GET', '/admin/api/realtime', 'Nexus\Controllers\Admin\EnterpriseController@realtimeStream');
$router->add('GET', '/admin/api/realtime/poll', 'Nexus\Controllers\Admin\EnterpriseController@realtimePoll');

// Configuration & Secrets
$router->add('GET', '/admin/enterprise/config', 'Nexus\Controllers\Admin\EnterpriseController@config');
$router->add('POST', '/admin/enterprise/config/settings/{group}/{key}', 'Nexus\Controllers\Admin\EnterpriseController@configSettingUpdate');
$router->add('GET', '/admin/enterprise/config/export', 'Nexus\Controllers\Admin\EnterpriseController@configExport');
$router->add('POST', '/admin/enterprise/config/cache/clear', 'Nexus\Controllers\Admin\EnterpriseController@configCacheClear');
$router->add('GET', '/admin/enterprise/config/validate', 'Nexus\Controllers\Admin\EnterpriseController@configValidate');
$router->add('PATCH', '/admin/enterprise/config/features/{key}', 'Nexus\Controllers\Admin\EnterpriseController@featureFlagToggle');
$router->add('POST', '/admin/enterprise/config/features/reset', 'Nexus\Controllers\Admin\EnterpriseController@featureFlagsReset');
$router->add('GET', '/admin/enterprise/config/secrets', 'Nexus\Controllers\Admin\EnterpriseController@secrets');
$router->add('POST', '/admin/enterprise/config/secrets', 'Nexus\Controllers\Admin\EnterpriseController@secretStore');
$router->add('POST', '/admin/enterprise/config/secrets/{key}/value', 'Nexus\Controllers\Admin\EnterpriseController@secretView');
$router->add('POST', '/admin/enterprise/config/secrets/{key}/rotate', 'Nexus\Controllers\Admin\EnterpriseController@secretRotate');
$router->add('DELETE', '/admin/enterprise/config/secrets/{key}', 'Nexus\Controllers\Admin\EnterpriseController@secretDelete');
$router->add('GET', '/admin/enterprise/config/vault/test', 'Nexus\Controllers\Admin\EnterpriseController@vaultTest');

// Roles & Permissions Management
$router->add('GET', '/admin/enterprise/roles', 'Nexus\Controllers\Admin\RolesController@index');
$router->add('GET', '/admin/enterprise/permissions', 'Nexus\Controllers\Admin\RolesController@permissions');
$router->add('GET', '/admin/enterprise/roles/create', 'Nexus\Controllers\Admin\RolesController@create');
$router->add('POST', '/admin/enterprise/roles', 'Nexus\Controllers\Admin\RolesController@store');
$router->add('GET', '/admin/enterprise/audit/permissions', 'Nexus\Controllers\Admin\RolesController@auditLog');
$router->add('GET', '/admin/enterprise/roles/{id}', 'Nexus\Controllers\Admin\RolesController@show');
$router->add('GET', '/admin/enterprise/roles/{id}/edit', 'Nexus\Controllers\Admin\RolesController@edit');
$router->add('PATCH', '/admin/enterprise/roles/{id}', 'Nexus\Controllers\Admin\RolesController@update');
$router->add('PUT', '/admin/enterprise/roles/{id}', 'Nexus\Controllers\Admin\RolesController@update');
$router->add('DELETE', '/admin/enterprise/roles/{id}', 'Nexus\Controllers\Admin\RolesController@destroy');
$router->add('POST', '/admin/enterprise/roles/{id}/users/{userId}', 'Nexus\Controllers\Admin\RolesController@assignToUser');
$router->add('DELETE', '/admin/enterprise/roles/{id}/users/{userId}', 'Nexus\Controllers\Admin\RolesController@revokeFromUser');

// Permission API (REST endpoints for AJAX/frontend)
$router->add('GET', '/admin/api/permissions/check', 'Nexus\Controllers\Admin\PermissionApiController@checkPermission');
$router->add('GET', '/admin/api/permissions', 'Nexus\Controllers\Admin\PermissionApiController@getAllPermissions');
$router->add('GET', '/admin/api/roles', 'Nexus\Controllers\Admin\PermissionApiController@getAllRoles');
$router->add('GET', '/admin/api/roles/{roleId}/permissions', 'Nexus\Controllers\Admin\PermissionApiController@getRolePermissions');
$router->add('GET', '/admin/api/users/{userId}/permissions', 'Nexus\Controllers\Admin\PermissionApiController@getUserPermissions');
$router->add('GET', '/admin/api/users/{userId}/roles', 'Nexus\Controllers\Admin\PermissionApiController@getUserRoles');
$router->add('GET', '/admin/api/users/{userId}/effective-permissions', 'Nexus\Controllers\Admin\PermissionApiController@getUserEffectivePermissions');
$router->add('POST', '/admin/api/users/{userId}/roles', 'Nexus\Controllers\Admin\PermissionApiController@assignRoleToUser');
$router->add('DELETE', '/admin/api/users/{userId}/roles/{roleId}', 'Nexus\Controllers\Admin\PermissionApiController@revokeRoleFromUser');
$router->add('POST', '/admin/api/users/{userId}/permissions', 'Nexus\Controllers\Admin\PermissionApiController@grantPermissionToUser');
$router->add('DELETE', '/admin/api/users/{userId}/permissions/{permissionId}', 'Nexus\Controllers\Admin\PermissionApiController@revokePermissionFromUser');
$router->add('GET', '/admin/api/audit/permissions', 'Nexus\Controllers\Admin\PermissionApiController@getAuditLog');
$router->add('GET', '/admin/api/stats/permissions', 'Nexus\Controllers\Admin\PermissionApiController@getPermissionStats');

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

// DISPATCH
// --------------------------------------------------------------------------
$router->dispatch();
