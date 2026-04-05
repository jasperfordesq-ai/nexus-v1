<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Support\Facades\Route;

// Global route pattern: {id} parameters must be numeric
Route::pattern('id', '[0-9]+');

/*
|--------------------------------------------------------------------------
| Laravel API Routes
|--------------------------------------------------------------------------
|
| Routes registered here are served by Laravel's router.
| Any route NOT matched here falls through to the legacy Router::dispatch().
|
| Migration progress: routes are being migrated incrementally from
| httpdocs/routes/*.php to this file.
|
*/

// ==========================================================================
// Phase 5 wiring: Delegation controllers (App\Http\Controllers\Api\*)
// replace legacy Nexus controllers where method signatures match exactly.
// Each delegation controller wraps the legacy controller via ob_start(),
// so responses are identical. Routes NOT yet swapped have a mismatch in
// method names between the delegation controller and the route definition.
// ==========================================================================

// Health check -- confirms Laravel routing is operational
// NOTE: Do NOT expose framework version or tenant_id — this endpoint is public.
Route::get('/laravel/health', function () {
    return response()->json([
        'status' => 'ok',
    ]);
});

// ============================================
// MIGRATED ROUTES — Tenant Bootstrap
// Source: httpdocs/routes/tenant-bootstrap.php
// ============================================
Route::get('/v2/tenant/bootstrap', [\App\Http\Controllers\Api\TenantBootstrapController::class, 'bootstrap']);
Route::get('/v2/tenants', [\App\Http\Controllers\Api\TenantBootstrapController::class, 'list']);
Route::get('/v2/platform/stats', [\App\Http\Controllers\Api\TenantBootstrapController::class, 'platformStats']);
Route::get('/v2/config/algorithms', [\App\Http\Controllers\Api\AdminConfigController::class, 'getAlgorithmInfo']);

// ============================================

// PUBLIC ROUTES — Job Feed (RSS/XML and JSON for aggregator syndication)
// No auth required, tenant-scoped via subdomain/header (Agent D)
// ============================================
Route::get('/v2/jobs/feed.xml', [\App\Http\Controllers\Api\JobFeedController::class, 'rssFeed']);
Route::get('/v2/jobs/feed.json', [\App\Http\Controllers\Api\JobFeedController::class, 'jsonFeed']);

// ============================================
// PUBLIC ROUTES — SEO metadata (no auth required)
// React frontend fetches per-page metadata for <head> tags
// ============================================
Route::get('/v2/seo/metadata/{slug}', [\App\Http\Controllers\Api\SeoController::class, 'metadata'])
    ->where('slug', '.*');
Route::get('/v2/seo/redirects', [\App\Http\Controllers\Api\SeoController::class, 'redirects']);

// ============================================
// PUBLIC ROUTES — Explore / Discover
// Supports both authenticated (personalized) and anonymous (global) access
// ============================================
Route::get('/v2/explore', [\App\Http\Controllers\Api\ExploreController::class, 'index']);
Route::get('/v2/explore/for-you', [\App\Http\Controllers\Api\ExploreController::class, 'forYou']);
Route::get('/v2/explore/trending', [\App\Http\Controllers\Api\ExploreController::class, 'trending']);
Route::get('/v2/explore/popular-listings', [\App\Http\Controllers\Api\ExploreController::class, 'popularListings']);
Route::get('/v2/explore/category/{slug}', [\App\Http\Controllers\Api\ExploreController::class, 'category']);

// ============================================
// Categories — public read-only (used by listings, explore, search pages)
// ============================================
Route::get('/v2/categories', function (\Illuminate\Http\Request $request) {
    $type = $request->query('type', 'listing');
    $allowed = ['listing', 'event', 'volunteering', 'resource'];
    if (!in_array($type, $allowed, true)) {
        $type = 'listing';
    }
    $categories = \App\Models\Category::where('type', $type)
        ->where('tenant_id', \App\Core\TenantContext::getId())
        ->orderBy('name')
        ->get();
    return response()->json(['data' => $categories]);
});

// ============================================
// Public group routes (optional auth — viewer_membership populated when Bearer token present)
// These are outside the auth:sanctum group so Sanctum middleware doesn't interfere.
// ============================================
Route::get('/v2/groups', [\App\Http\Controllers\Api\GroupsController::class, 'index']);
Route::get('/v2/groups/{id}', [\App\Http\Controllers\Api\GroupsController::class, 'show']);
Route::get('/v2/groups/{id}/members', [\App\Http\Controllers\Api\GroupsController::class, 'members']);

// ============================================
// Authenticated routes — Sanctum token authentication required
// Controllers also enforce auth via $this->requireAuth() as a fallback
// ============================================
Route::middleware('auth:sanctum')->group(function () {

// Explore — authenticated actions (tracking, dismissals, experiments)
Route::post('/v2/explore/track', [\App\Http\Controllers\Api\ExploreController::class, 'track']);
Route::post('/v2/explore/dismiss', [\App\Http\Controllers\Api\ExploreController::class, 'dismiss']);
Route::get('/v2/explore/experiments', [\App\Http\Controllers\Api\ExploreController::class, 'experiments']);
Route::get('/v2/explore/analytics', [\App\Http\Controllers\Api\ExploreController::class, 'analytics']);

// MIGRATED ROUTES — Exchanges
// Source: httpdocs/routes/exchanges.php
// ============================================
Route::get('/v2/exchanges/config', [\App\Http\Controllers\Api\ExchangesController::class, 'config']);
Route::get('/v2/exchanges/check', [\App\Http\Controllers\Api\ExchangesController::class, 'check']);
Route::get('/v2/exchanges', [\App\Http\Controllers\Api\ExchangesController::class, 'index']);
Route::post('/v2/exchanges', [\App\Http\Controllers\Api\ExchangesController::class, 'store']);
Route::get('/v2/exchanges/{id}', [\App\Http\Controllers\Api\ExchangesController::class, 'show']);
Route::post('/v2/exchanges/{id}/accept', [\App\Http\Controllers\Api\ExchangesController::class, 'accept']);
Route::post('/v2/exchanges/{id}/decline', [\App\Http\Controllers\Api\ExchangesController::class, 'decline']);
Route::post('/v2/exchanges/{id}/start', [\App\Http\Controllers\Api\ExchangesController::class, 'start']);
Route::post('/v2/exchanges/{id}/complete', [\App\Http\Controllers\Api\ExchangesController::class, 'complete']);
Route::post('/v2/exchanges/{id}/confirm', [\App\Http\Controllers\Api\ExchangesController::class, 'confirm']);
Route::delete('/v2/exchanges/{id}', [\App\Http\Controllers\Api\ExchangesController::class, 'cancel']);

// ============================================
// Presence — Real-time online/offline status
// ============================================
Route::post('/v2/presence/heartbeat', [\App\Http\Controllers\Api\PresenceController::class, 'heartbeat']);
Route::get('/v2/presence/users', [\App\Http\Controllers\Api\PresenceController::class, 'users']);
Route::put('/v2/presence/status', [\App\Http\Controllers\Api\PresenceController::class, 'setStatus']);
Route::put('/v2/presence/privacy', [\App\Http\Controllers\Api\PresenceController::class, 'setPrivacy']);
Route::get('/v2/presence/online-count', [\App\Http\Controllers\Api\PresenceController::class, 'onlineCount']);

// ============================================
// MIGRATED ROUTES — Events
// Source: httpdocs/routes/events.php
// ============================================
Route::get('/v2/events', [\App\Http\Controllers\Api\EventsController::class, 'index'])->withoutMiddleware('auth:sanctum');
Route::get('/v2/events/nearby', [\App\Http\Controllers\Api\EventsController::class, 'nearby'])->withoutMiddleware('auth:sanctum');
Route::post('/v2/events', [\App\Http\Controllers\Api\EventsController::class, 'store']);
Route::get('/v2/events/{id}', [\App\Http\Controllers\Api\EventsController::class, 'show'])->withoutMiddleware('auth:sanctum');
Route::put('/v2/events/{id}', [\App\Http\Controllers\Api\EventsController::class, 'update']);
Route::delete('/v2/events/{id}', [\App\Http\Controllers\Api\EventsController::class, 'destroy']);
Route::post('/v2/events/{id}/rsvp', [\App\Http\Controllers\Api\EventsController::class, 'rsvp']);
Route::delete('/v2/events/{id}/rsvp', [\App\Http\Controllers\Api\EventsController::class, 'removeRsvp']);
Route::get('/v2/events/{id}/attendees', [\App\Http\Controllers\Api\EventsController::class, 'attendees'])->withoutMiddleware('auth:sanctum');
Route::post('/v2/events/{id}/attendees/{attendeeId}/check-in', [\App\Http\Controllers\Api\EventsController::class, 'checkIn']);
Route::post('/v2/events/{id}/cancel', [\App\Http\Controllers\Api\EventsController::class, 'cancel']);
Route::post('/v2/events/{id}/waitlist', [\App\Http\Controllers\Api\EventsController::class, 'waitlist']);
Route::delete('/v2/events/{id}/waitlist', [\App\Http\Controllers\Api\EventsController::class, 'leaveWaitlist']);
Route::post('/v2/events/{id}/image', [\App\Http\Controllers\Api\EventsController::class, 'uploadImage']);

// ============================================
// MIGRATED ROUTES — Listings (controller routes only)
// Source: httpdocs/routes/listings.php
// NOTE: Categories endpoint moved to public routes (above auth:sanctum group)
// ============================================
// FUTURE: When ready to use new Laravel controllers, replace:
//   [\Nexus\Controllers\Api\ListingsApiController::class, 'index']
// with:
//   [App\Http\Controllers\Api\ListingsController::class, 'index']
//
// The new ListingsController uses constructor DI (App\Services\ListingService),
// returns JsonResponse from every method, and handles validation via
// Laravel's ValidationException. See ListingsController.php for the
// reference implementation pattern to follow for all other controllers.
Route::get('/v2/listings', [\App\Http\Controllers\Api\ListingsController::class, 'index'])->withoutMiddleware('auth:sanctum');
Route::get('/v2/listings/nearby', [\App\Http\Controllers\Api\ListingsController::class, 'nearby'])->withoutMiddleware('auth:sanctum');
Route::get('/v2/listings/saved', [\App\Http\Controllers\Api\ListingsController::class, 'getSavedListings']);
Route::get('/v2/listings/featured', [\App\Http\Controllers\Api\ListingsController::class, 'featured'])->withoutMiddleware('auth:sanctum');
Route::get('/v2/listings/tags/popular', [\App\Http\Controllers\Api\ListingsController::class, 'popularTags'])->withoutMiddleware('auth:sanctum');
Route::get('/v2/listings/tags/autocomplete', [\App\Http\Controllers\Api\ListingsController::class, 'autocompleteTags'])->withoutMiddleware('auth:sanctum');
Route::post('/v2/listings', [\App\Http\Controllers\Api\ListingsController::class, 'store']);
Route::post('/v2/listings/generate-description', [\App\Http\Controllers\Api\ListingsController::class, 'generateDescription']);
Route::get('/v2/listings/{id}', [\App\Http\Controllers\Api\ListingsController::class, 'show'])->withoutMiddleware('auth:sanctum');
Route::put('/v2/listings/{id}', [\App\Http\Controllers\Api\ListingsController::class, 'update']);
Route::delete('/v2/listings/{id}', [\App\Http\Controllers\Api\ListingsController::class, 'destroy']);
Route::post('/v2/listings/{id}/save', [\App\Http\Controllers\Api\ListingsController::class, 'saveListing']);
Route::delete('/v2/listings/{id}/save', [\App\Http\Controllers\Api\ListingsController::class, 'unsaveListing']);
Route::post('/v2/listings/{id}/image', [\App\Http\Controllers\Api\ListingsController::class, 'uploadImage']);
Route::delete('/v2/listings/{id}/image', [\App\Http\Controllers\Api\ListingsController::class, 'deleteImage']);
Route::post('/v2/listings/{id}/images', [\App\Http\Controllers\Api\ListingsController::class, 'uploadImages']);
Route::delete('/v2/listings/{id}/images/{imageId}', [\App\Http\Controllers\Api\ListingsController::class, 'deleteListingImage']);
Route::put('/v2/listings/{id}/images/reorder', [\App\Http\Controllers\Api\ListingsController::class, 'reorderImages']);
Route::post('/v2/listings/{id}/renew', [\App\Http\Controllers\Api\ListingsController::class, 'renew']);
Route::get('/v2/listings/{id}/analytics', [\App\Http\Controllers\Api\ListingsController::class, 'analytics']);
Route::put('/v2/listings/{id}/tags', [\App\Http\Controllers\Api\ListingsController::class, 'setSkillTags']);
Route::post('/v2/listings/{id}/report', [\App\Http\Controllers\Api\ListingsController::class, 'report']);

// ============================================
// MIGRATED ROUTES — Messages
// Source: httpdocs/routes/messages.php
// ============================================
Route::get('/v2/messages', [\App\Http\Controllers\Api\MessagesController::class, 'conversations']);
Route::get('/v2/messages/unread-count', [\App\Http\Controllers\Api\MessagesController::class, 'unreadCount']);
Route::get('/v2/messages/restriction-status', [\App\Http\Controllers\Api\MessagesController::class, 'restrictionStatus']);
Route::post('/v2/messages', [\App\Http\Controllers\Api\MessagesController::class, 'send']);
Route::post('/v2/messages/typing', [\App\Http\Controllers\Api\MessagesController::class, 'typing']);
Route::post('/v2/messages/upload-voice', [\App\Http\Controllers\Api\MessagesController::class, 'uploadVoice']);
Route::post('/v2/messages/voice', [\App\Http\Controllers\Api\MessagesController::class, 'sendVoice']);
Route::delete('/v2/messages/conversations/{id}', [\App\Http\Controllers\Api\MessagesController::class, 'archiveConversation']);
Route::get('/v2/messages/{id}', [\App\Http\Controllers\Api\MessagesController::class, 'show']);
Route::put('/v2/messages/{id}/read', [\App\Http\Controllers\Api\MessagesController::class, 'markRead']);
Route::post('/v2/messages/{id}/reactions', [\App\Http\Controllers\Api\MessagesController::class, 'toggleReaction']);
Route::post('/v2/messages/{id}/translate', [\App\Http\Controllers\Api\MessagesController::class, 'translateTranscript']);
Route::put('/v2/messages/{id}', [\App\Http\Controllers\Api\MessagesController::class, 'update']);
Route::delete('/v2/messages/{id}', [\App\Http\Controllers\Api\MessagesController::class, 'deleteMessage']);
Route::delete('/v2/conversations/{id}', [\App\Http\Controllers\Api\MessagesController::class, 'archive']);
Route::post('/v2/messages/conversations/{id}/restore', [\App\Http\Controllers\Api\MessagesController::class, 'restoreConversation']);

// ============================================
// MIGRATED ROUTES — Groups & Connections
// Source: httpdocs/routes/groups.php
Route::get('/v2/connections/status/me', function () {
    return response()->json(['errors' => [['code' => 'invalid_user', 'message' => 'Cannot check connection status with yourself']]], 422);
}); // Guard: reject literal "me" before {userId} param
// ============================================
// NOTE: GET /v2/groups, /v2/groups/{id}, /v2/groups/{id}/members are registered
// as public routes ABOVE this auth group (with optional auth in the controller).
Route::post('/v2/groups', [\App\Http\Controllers\Api\GroupsController::class, 'store']);
Route::get('/v2/groups/recommendations', [\App\Http\Controllers\Api\GroupRecommendController::class, 'index']);
Route::post('/v2/groups/recommendations/track', [\App\Http\Controllers\Api\GroupRecommendController::class, 'track']);
Route::get('/v2/groups/recommendations/metrics', [\App\Http\Controllers\Api\GroupRecommendController::class, 'metrics']);
Route::put('/v2/groups/{id}', [\App\Http\Controllers\Api\GroupsController::class, 'update']);
Route::delete('/v2/groups/{id}', [\App\Http\Controllers\Api\GroupsController::class, 'destroy']);
Route::get('/v2/groups/{id}/similar', [\App\Http\Controllers\Api\GroupRecommendController::class, 'similar']);
Route::post('/v2/groups/{id}/join', [\App\Http\Controllers\Api\GroupsController::class, 'join']);
Route::delete('/v2/groups/{id}/membership', [\App\Http\Controllers\Api\GroupsController::class, 'leave']);
Route::put('/v2/groups/{id}/members/{userId}', [\App\Http\Controllers\Api\GroupsController::class, 'updateMember']);
Route::delete('/v2/groups/{id}/members/{userId}', [\App\Http\Controllers\Api\GroupsController::class, 'removeMember']);
Route::get('/v2/groups/{id}/requests', [\App\Http\Controllers\Api\GroupsController::class, 'pendingRequests']);
Route::post('/v2/groups/{id}/requests/{userId}', [\App\Http\Controllers\Api\GroupsController::class, 'handleRequest']);
Route::get('/v2/groups/{id}/discussions', [\App\Http\Controllers\Api\GroupsController::class, 'discussions']);
Route::post('/v2/groups/{id}/discussions', [\App\Http\Controllers\Api\GroupsController::class, 'createDiscussion']);
Route::get('/v2/groups/{id}/discussions/{discussionId}', [\App\Http\Controllers\Api\GroupsController::class, 'discussionMessages']);
Route::post('/v2/groups/{id}/discussions/{discussionId}/messages', [\App\Http\Controllers\Api\GroupsController::class, 'postToDiscussion']);
Route::post('/v2/groups/{id}/image', [\App\Http\Controllers\Api\GroupsController::class, 'uploadImage']);
Route::get('/v2/groups/{id}/announcements', [\App\Http\Controllers\Api\GroupsController::class, 'announcements']);
Route::post('/v2/groups/{id}/announcements', [\App\Http\Controllers\Api\GroupsController::class, 'createAnnouncement']);
Route::put('/v2/groups/{id}/announcements/{announcementId}', [\App\Http\Controllers\Api\GroupsController::class, 'updateAnnouncement']);
Route::delete('/v2/groups/{id}/announcements/{announcementId}', [\App\Http\Controllers\Api\GroupsController::class, 'deleteAnnouncement']);
Route::get('/v2/groups/{id}/files', [\App\Http\Controllers\Api\GroupFilesController::class, 'index']);
Route::post('/v2/groups/{id}/files', [\App\Http\Controllers\Api\GroupFilesController::class, 'store']);
Route::get('/v2/groups/{id}/files/folders', [\App\Http\Controllers\Api\GroupFilesController::class, 'folders']);
Route::get('/v2/groups/{id}/files/stats', [\App\Http\Controllers\Api\GroupFilesController::class, 'stats']);
Route::get('/v2/groups/{id}/files/{fileId}/download', [\App\Http\Controllers\Api\GroupFilesController::class, 'download']);
Route::delete('/v2/groups/{id}/files/{fileId}', [\App\Http\Controllers\Api\GroupFilesController::class, 'destroy']);
Route::get('/v2/groups/{id}/analytics', [\App\Http\Controllers\Api\GroupAnalyticsController::class, 'dashboard']);
Route::get('/v2/groups/{id}/analytics/growth', [\App\Http\Controllers\Api\GroupAnalyticsController::class, 'growth']);
Route::get('/v2/groups/{id}/analytics/engagement', [\App\Http\Controllers\Api\GroupAnalyticsController::class, 'engagement']);
Route::get('/v2/groups/{id}/analytics/contributors', [\App\Http\Controllers\Api\GroupAnalyticsController::class, 'contributors']);
Route::get('/v2/groups/{id}/analytics/retention', [\App\Http\Controllers\Api\GroupAnalyticsController::class, 'retention']);
Route::get('/v2/groups/{id}/analytics/comparative', [\App\Http\Controllers\Api\GroupAnalyticsController::class, 'comparative']);
Route::get('/v2/groups/{id}/analytics/export/members', [\App\Http\Controllers\Api\GroupAnalyticsController::class, 'exportMembers']);
Route::get('/v2/groups/{id}/analytics/export/activity', [\App\Http\Controllers\Api\GroupAnalyticsController::class, 'exportActivity']);
Route::get('/v2/groups/{id}/invites', [\App\Http\Controllers\Api\GroupInviteController::class, 'index']);
Route::post('/v2/groups/{id}/invites/link', [\App\Http\Controllers\Api\GroupInviteController::class, 'createLink']);
Route::post('/v2/groups/{id}/invites/email', [\App\Http\Controllers\Api\GroupInviteController::class, 'sendEmails']);
Route::delete('/v2/groups/{id}/invites/{inviteId}', [\App\Http\Controllers\Api\GroupInviteController::class, 'revoke']);
Route::post('/v2/groups/invite/{token}/accept', [\App\Http\Controllers\Api\GroupInviteController::class, 'accept']);
Route::get('/v2/groups/{id}/tags', [\App\Http\Controllers\Api\GroupTagController::class, 'index']);
Route::put('/v2/groups/{id}/tags', [\App\Http\Controllers\Api\GroupTagController::class, 'update']);
Route::get('/v2/group-tags', [\App\Http\Controllers\Api\GroupTagController::class, 'allTags']);
Route::get('/v2/group-tags/popular', [\App\Http\Controllers\Api\GroupTagController::class, 'popular']);
Route::get('/v2/group-tags/suggest', [\App\Http\Controllers\Api\GroupTagController::class, 'suggest']);
Route::get('/v2/groups/{id}/questions', [\App\Http\Controllers\Api\GroupQAController::class, 'index']);
Route::post('/v2/groups/{id}/questions', [\App\Http\Controllers\Api\GroupQAController::class, 'ask']);
Route::get('/v2/groups/{id}/questions/{questionId}', [\App\Http\Controllers\Api\GroupQAController::class, 'show']);
Route::post('/v2/groups/{id}/questions/{questionId}/answers', [\App\Http\Controllers\Api\GroupQAController::class, 'answer']);
Route::post('/v2/groups/{id}/answers/{answerId}/accept', [\App\Http\Controllers\Api\GroupQAController::class, 'accept']);
Route::post('/v2/groups/{id}/qa/vote', [\App\Http\Controllers\Api\GroupQAController::class, 'vote']);
Route::get('/v2/groups/{id}/wiki', [\App\Http\Controllers\Api\GroupWikiController::class, 'index']);
Route::post('/v2/groups/{id}/wiki', [\App\Http\Controllers\Api\GroupWikiController::class, 'create']);
Route::get('/v2/groups/{id}/wiki/{slug}', [\App\Http\Controllers\Api\GroupWikiController::class, 'show']);
Route::put('/v2/groups/{id}/wiki/{pageId}', [\App\Http\Controllers\Api\GroupWikiController::class, 'update']);
Route::delete('/v2/groups/{id}/wiki/{pageId}', [\App\Http\Controllers\Api\GroupWikiController::class, 'destroy']);
Route::get('/v2/groups/{id}/wiki/{pageId}/revisions', [\App\Http\Controllers\Api\GroupWikiController::class, 'revisions']);
Route::get('/v2/groups/{id}/media', [\App\Http\Controllers\Api\GroupMediaController::class, 'index']);
Route::post('/v2/groups/{id}/media', [\App\Http\Controllers\Api\GroupMediaController::class, 'upload']);
Route::delete('/v2/groups/{id}/media/{mediaId}', [\App\Http\Controllers\Api\GroupMediaController::class, 'destroy']);
Route::get('/v2/groups/{id}/webhooks', [\App\Http\Controllers\Api\GroupWebhookController::class, 'index']);
Route::post('/v2/groups/{id}/webhooks', [\App\Http\Controllers\Api\GroupWebhookController::class, 'store']);
Route::delete('/v2/groups/{id}/webhooks/{webhookId}', [\App\Http\Controllers\Api\GroupWebhookController::class, 'destroy']);
Route::put('/v2/groups/{id}/webhooks/{webhookId}/toggle', [\App\Http\Controllers\Api\GroupWebhookController::class, 'toggle']);
Route::get('/v2/groups/{id}/welcome', [\App\Http\Controllers\Api\GroupWelcomeController::class, 'getConfig']);
Route::put('/v2/groups/{id}/welcome', [\App\Http\Controllers\Api\GroupWelcomeController::class, 'setConfig']);
Route::get('/v2/group-templates', [\App\Http\Controllers\Api\GroupTemplateController::class, 'index']);
Route::get('/v2/groups/{id}/custom-fields', [\App\Http\Controllers\Api\GroupCustomFieldController::class, 'getValues']);
Route::put('/v2/groups/{id}/custom-fields', [\App\Http\Controllers\Api\GroupCustomFieldController::class, 'setValues']);
Route::get('/v2/groups/{id}/export', [\App\Http\Controllers\Api\GroupDataExportController::class, 'exportAll']);
Route::get('/v2/groups/{id}/challenges', [\App\Http\Controllers\Api\GroupChallengeController::class, 'index']);
Route::post('/v2/groups/{id}/challenges', [\App\Http\Controllers\Api\GroupChallengeController::class, 'store']);
Route::delete('/v2/groups/{id}/challenges/{challengeId}', [\App\Http\Controllers\Api\GroupChallengeController::class, 'destroy']);
Route::get('/v2/groups/{id}/scheduled-posts', [\App\Http\Controllers\Api\GroupScheduledPostController::class, 'index']);
Route::post('/v2/groups/{id}/scheduled-posts', [\App\Http\Controllers\Api\GroupScheduledPostController::class, 'store']);
Route::delete('/v2/groups/{id}/scheduled-posts/{postId}', [\App\Http\Controllers\Api\GroupScheduledPostController::class, 'cancel']);
Route::get('/v2/groups/{id}/notification-prefs', [\App\Http\Controllers\Api\GroupNotificationPrefController::class, 'get']);
Route::put('/v2/groups/{id}/notification-prefs', [\App\Http\Controllers\Api\GroupNotificationPrefController::class, 'set']);
Route::get('/v2/group-collections', [\App\Http\Controllers\Api\GroupCollectionController::class, 'index']);
Route::get('/v2/group-collections/{id}', [\App\Http\Controllers\Api\GroupCollectionController::class, 'show']);
Route::get('/v2/groups/{id}/mentions/suggest', [\App\Http\Controllers\Api\GroupMentionController::class, 'suggestions']);
Route::get('/v2/connections', [\App\Http\Controllers\Api\ConnectionsController::class, 'index']);
Route::get('/v2/connections/pending', [\App\Http\Controllers\Api\ConnectionsController::class, 'pendingCounts']);
Route::get('/v2/connections/status/{userId}', [\App\Http\Controllers\Api\ConnectionsController::class, 'status']);
Route::post('/v2/connections/request', [\App\Http\Controllers\Api\ConnectionsController::class, 'request']);
Route::post('/v2/connections/{id}/accept', [\App\Http\Controllers\Api\ConnectionsController::class, 'accept']);
Route::delete('/v2/connections/{id}', [\App\Http\Controllers\Api\ConnectionsController::class, 'destroy']);

// ============================================
// MIGRATED ROUTES — Users (controller routes only)
// Source: httpdocs/routes/users.php
Route::get('/v2/users', [\App\Http\Controllers\Api\UsersController::class, 'index']); // Member directory
// ============================================
Route::get('/v2/me/stats', [\App\Http\Controllers\Api\UsersController::class, 'stats']);
Route::get('/v2/users/me', [\App\Http\Controllers\Api\UsersController::class, 'me']);
Route::put('/v2/users/me', [\App\Http\Controllers\Api\UsersController::class, 'update']);
Route::get('/v2/users/me/preferences', [\App\Http\Controllers\Api\UsersController::class, 'getPreferences']);
Route::put('/v2/users/me/preferences', [\App\Http\Controllers\Api\UsersController::class, 'updatePreferences']);
Route::put('/v2/users/me/theme', [\App\Http\Controllers\Api\UsersController::class, 'updateTheme']);
Route::put('/v2/users/me/theme-preferences', [\App\Http\Controllers\Api\UsersController::class, 'updateThemePreferences']);
Route::put('/v2/users/me/language', [\App\Http\Controllers\Api\UsersController::class, 'updateLanguage']);
Route::post('/v2/users/me/avatar', [\App\Http\Controllers\Api\UsersController::class, 'updateAvatar']);
Route::post('/v2/users/me/password', [\App\Http\Controllers\Api\UsersController::class, 'updatePassword']);
Route::delete('/v2/users/me', [\App\Http\Controllers\Api\UsersController::class, 'deleteAccount']);
Route::get('/v2/users/me/listings', [\App\Http\Controllers\Api\UsersController::class, 'myListings']);
Route::get('/v2/users/me/notifications', [\App\Http\Controllers\Api\UsersController::class, 'notificationPreferences']);
Route::put('/v2/users/me/notifications', [\App\Http\Controllers\Api\UsersController::class, 'updateNotificationPreferences']);
Route::get('/v2/users/me/consent', [\App\Http\Controllers\Api\UsersController::class, 'getConsent']);
Route::put('/v2/users/me/consent', [\App\Http\Controllers\Api\UsersController::class, 'updateConsent']);
Route::post('/v2/users/me/gdpr-request', [\App\Http\Controllers\Api\UsersController::class, 'createGdprRequest']);
Route::put('/v2/users/me/resume-visibility', [\App\Http\Controllers\Api\JobVacanciesController::class, 'updateResumeVisibility']);
Route::get('/v2/users/me/sessions', [\App\Http\Controllers\Api\UsersController::class, 'sessions']);
Route::get('/v2/users/me/match-preferences', [\App\Http\Controllers\Api\MatchPreferencesController::class, 'show']);
Route::put('/v2/users/me/match-preferences', [\App\Http\Controllers\Api\MatchPreferencesController::class, 'update']);
Route::get('/v2/users/me/insurance', [\App\Http\Controllers\Api\UserInsuranceController::class, 'list']);
Route::post('/v2/users/me/insurance', [\App\Http\Controllers\Api\UserInsuranceController::class, 'upload']);
Route::get('/v2/users/{id}', [\App\Http\Controllers\Api\UsersController::class, 'show']);
Route::get('/v2/users/{id}/listings', [\App\Http\Controllers\Api\UsersController::class, 'listings']);
Route::get('/v2/members/nearby', [\App\Http\Controllers\Api\UsersController::class, 'nearby']);
// Skills (categories + search are public; remaining skill routes require auth)
Route::get('/v2/skills/categories', [\App\Http\Controllers\Api\SkillTaxonomyController::class, 'getCategories'])->withoutMiddleware('auth:sanctum');
Route::get('/v2/skills/search', [\App\Http\Controllers\Api\SkillTaxonomyController::class, 'search'])->withoutMiddleware('auth:sanctum');
Route::get('/v2/skills/members', [\App\Http\Controllers\Api\SkillTaxonomyController::class, 'getMembersWithSkill']);
Route::get('/v2/skills/categories/{id}', [\App\Http\Controllers\Api\SkillTaxonomyController::class, 'getCategoryById']);
Route::post('/v2/skills/categories', [\App\Http\Controllers\Api\SkillTaxonomyController::class, 'createCategory'])->middleware('admin');
Route::put('/v2/skills/categories/{id}', [\App\Http\Controllers\Api\SkillTaxonomyController::class, 'updateCategory'])->middleware('admin');
Route::delete('/v2/skills/categories/{id}', [\App\Http\Controllers\Api\SkillTaxonomyController::class, 'deleteCategory'])->middleware('admin');
Route::get('/v2/users/me/skills', [\App\Http\Controllers\Api\SkillTaxonomyController::class, 'getMySkills']);
Route::post('/v2/users/me/skills', [\App\Http\Controllers\Api\SkillTaxonomyController::class, 'addSkill']);
Route::put('/v2/users/me/skills/{id}', [\App\Http\Controllers\Api\SkillTaxonomyController::class, 'updateSkill']);
Route::delete('/v2/users/me/skills/{id}', [\App\Http\Controllers\Api\SkillTaxonomyController::class, 'removeSkill']);
Route::get('/v2/users/{id}/skills', [\App\Http\Controllers\Api\SkillTaxonomyController::class, 'getUserSkills']);
// Availability
Route::get('/v2/users/me/availability', [\App\Http\Controllers\Api\MemberAvailabilityController::class, 'getMyAvailability']);
Route::put('/v2/users/me/availability', [\App\Http\Controllers\Api\MemberAvailabilityController::class, 'setBulkAvailability']);
Route::put('/v2/users/me/availability/{day}', [\App\Http\Controllers\Api\MemberAvailabilityController::class, 'setDayAvailability']);
Route::post('/v2/users/me/availability/date', [\App\Http\Controllers\Api\MemberAvailabilityController::class, 'addSpecificDate']);
Route::delete('/v2/users/me/availability/{id}', [\App\Http\Controllers\Api\MemberAvailabilityController::class, 'deleteSlot']);
Route::get('/v2/users/{id}/availability', [\App\Http\Controllers\Api\MemberAvailabilityController::class, 'getUserAvailability']);
Route::get('/v2/members/availability/compatible', [\App\Http\Controllers\Api\MemberAvailabilityController::class, 'findCompatibleTimes']);
Route::get('/v2/members/availability/available', [\App\Http\Controllers\Api\MemberAvailabilityController::class, 'getAvailableMembers']);
// Endorsements
Route::post('/v2/members/{id}/endorse', [\App\Http\Controllers\Api\EndorsementController::class, 'endorse']);
Route::delete('/v2/members/{id}/endorse', [\App\Http\Controllers\Api\EndorsementController::class, 'removeEndorsement']);
Route::get('/v2/members/{id}/endorsements', [\App\Http\Controllers\Api\EndorsementController::class, 'getEndorsements']);
Route::get('/v2/members/top-endorsed', [\App\Http\Controllers\Api\EndorsementController::class, 'getTopEndorsed']);
// Peer Endorsements (verification badge system)
Route::post('/v2/members/{id}/peer-endorse', [\App\Http\Controllers\Api\PeerEndorsementController::class, 'endorse']);
// Activity Dashboard
Route::get('/v2/users/me/activity/dashboard', [\App\Http\Controllers\Api\MemberActivityController::class, 'getDashboard']);
Route::get('/v2/users/me/activity/timeline', [\App\Http\Controllers\Api\MemberActivityController::class, 'getTimeline']);
Route::get('/v2/users/me/activity/hours', [\App\Http\Controllers\Api\MemberActivityController::class, 'getHours']);
Route::get('/v2/users/me/activity/monthly', [\App\Http\Controllers\Api\MemberActivityController::class, 'getMonthlyHours']);
Route::get('/v2/users/{id}/activity/dashboard', [\App\Http\Controllers\Api\MemberActivityController::class, 'getPublicDashboard']);
// Verification Badges
Route::get('/v2/users/{id}/verification-badges', [\App\Http\Controllers\Api\MemberVerificationBadgeController::class, 'getUserBadges']);
// NOTE: Admin badge management routes moved to admin middleware group below
// Federation (user-facing — NOT admin-only)
Route::get('/v2/federation/status', [\App\Http\Controllers\Api\FederationV2Controller::class, 'status']);
Route::post('/v2/federation/opt-in', [\App\Http\Controllers\Api\FederationV2Controller::class, 'optIn']);
Route::post('/v2/federation/setup', [\App\Http\Controllers\Api\FederationV2Controller::class, 'setup']);
Route::post('/v2/federation/opt-out', [\App\Http\Controllers\Api\FederationV2Controller::class, 'optOut']);
Route::get('/v2/federation/partners', [\App\Http\Controllers\Api\FederationV2Controller::class, 'partners']);
Route::get('/v2/federation/activity', [\App\Http\Controllers\Api\FederationV2Controller::class, 'activity']);
Route::get('/v2/federation/events', [\App\Http\Controllers\Api\FederationV2Controller::class, 'events']);
Route::get('/v2/federation/listings', [\App\Http\Controllers\Api\FederationV2Controller::class, 'listings']);
Route::get('/v2/federation/members', [\App\Http\Controllers\Api\FederationV2Controller::class, 'members']);
Route::get('/v2/federation/members/{id}', [\App\Http\Controllers\Api\FederationV2Controller::class, 'member']);
Route::get('/v2/federation/messages', [\App\Http\Controllers\Api\FederationV2Controller::class, 'messages']);
Route::post('/v2/federation/messages', [\App\Http\Controllers\Api\FederationV2Controller::class, 'sendMessage']);
Route::post('/v2/federation/messages/{id}/mark-read', [\App\Http\Controllers\Api\FederationV2Controller::class, 'markMessageRead']);
Route::get('/v2/federation/settings', [\App\Http\Controllers\Api\FederationV2Controller::class, 'getSettings']);
Route::put('/v2/federation/settings', [\App\Http\Controllers\Api\FederationV2Controller::class, 'updateSettings']);
Route::get('/v2/federation/connections', [\App\Http\Controllers\Api\FederationV2Controller::class, 'connections']);
Route::post('/v2/federation/connections', [\App\Http\Controllers\Api\FederationV2Controller::class, 'sendConnectionRequest']);
Route::post('/v2/federation/connections/{id}/accept', [\App\Http\Controllers\Api\FederationV2Controller::class, 'acceptConnection']);
Route::post('/v2/federation/connections/{id}/reject', [\App\Http\Controllers\Api\FederationV2Controller::class, 'rejectConnection']);
Route::delete('/v2/federation/connections/{id}', [\App\Http\Controllers\Api\FederationV2Controller::class, 'removeConnection']);
Route::get('/v2/federation/connections/status/{userId}/{tenantId}', [\App\Http\Controllers\Api\FederationV2Controller::class, 'connectionStatus']);
// Sub-Accounts
Route::get('/v2/users/me/sub-accounts', [\App\Http\Controllers\Api\SubAccountController::class, 'getChildAccounts']);
Route::get('/v2/users/me/parent-accounts', [\App\Http\Controllers\Api\SubAccountController::class, 'getParentAccounts']);
Route::post('/v2/users/me/sub-accounts', [\App\Http\Controllers\Api\SubAccountController::class, 'requestRelationship']);
Route::put('/v2/users/me/sub-accounts/{id}/approve', [\App\Http\Controllers\Api\SubAccountController::class, 'approveRelationship']);
Route::put('/v2/users/me/sub-accounts/{id}/permissions', [\App\Http\Controllers\Api\SubAccountController::class, 'updatePermissions']);
Route::delete('/v2/users/me/sub-accounts/{id}', [\App\Http\Controllers\Api\SubAccountController::class, 'revokeRelationship']);
Route::get('/v2/users/me/sub-accounts/{childId}/activity', [\App\Http\Controllers\Api\SubAccountController::class, 'getChildActivity']);

// ============================================
// MIGRATED ROUTES — Social (Wallet, Feed, Notifications, Reviews, Search, Polls)
// Source: httpdocs/routes/social.php
// Realtime config (was closure in legacy router, now a controller)
Route::get('/v2/realtime/config', [\App\Http\Controllers\Api\RealtimeController::class, 'config']);
// ============================================
// Wallet
Route::get('/v2/wallet/balance', [\App\Http\Controllers\Api\WalletController::class, 'balance']);
Route::get('/v2/wallet/transactions', [\App\Http\Controllers\Api\WalletController::class, 'transactions']);
Route::get('/v2/wallet/transactions/{id}', [\App\Http\Controllers\Api\WalletController::class, 'showTransaction']);
Route::post('/v2/wallet/transfer', [\App\Http\Controllers\Api\WalletController::class, 'transfer']);
Route::delete('/v2/wallet/transactions/{id}', [\App\Http\Controllers\Api\WalletController::class, 'destroyTransaction']);
Route::get('/v2/wallet/user-search', [\App\Http\Controllers\Api\WalletController::class, 'userSearch']);
Route::get('/v2/wallet/pending-count', [\App\Http\Controllers\Api\WalletController::class, 'pendingCount']);
// Feed
Route::get('/v2/feed', [\App\Http\Controllers\Api\SocialController::class, 'feedV2']);
Route::get('/v2/feed/posts/{id}', [\App\Http\Controllers\Api\SocialController::class, 'showPost']);
Route::post('/v2/feed/posts', [\App\Http\Controllers\Api\SocialController::class, 'createPostV2']);
Route::post('/v2/feed/like', [\App\Http\Controllers\Api\SocialController::class, 'likeV2']);
Route::post('/v2/feed/polls', [\App\Http\Controllers\Api\SocialController::class, 'createPollV2']);
Route::get('/v2/feed/polls/{id}', [\App\Http\Controllers\Api\SocialController::class, 'getPollV2']);
Route::post('/v2/feed/polls/{id}/vote', [\App\Http\Controllers\Api\SocialController::class, 'votePollV2']);
Route::get('/v2/feed/posts/scheduled', [\App\Http\Controllers\Api\SocialController::class, 'scheduledPosts']);
Route::post('/v2/feed/posts/{id}/hide', [\App\Http\Controllers\Api\SocialController::class, 'hidePostV2']);
Route::post('/v2/feed/posts/{id}/report', [\App\Http\Controllers\Api\SocialController::class, 'reportPostV2']);
Route::delete('/v2/feed/posts/{id}', [\App\Http\Controllers\Api\SocialController::class, 'deletePostV2']);
Route::post('/v2/feed/posts/{id}/delete', [\App\Http\Controllers\Api\SocialController::class, 'deletePostV2']); // deprecated: use DELETE /v2/feed/posts/{id}
Route::post('/v2/feed/users/{id}/mute', [\App\Http\Controllers\Api\SocialController::class, 'muteUserV2']);
Route::post('/v2/feed/posts/{id}/impression', [\App\Http\Controllers\Api\SocialController::class, 'recordImpression']);
Route::post('/v2/feed/posts/{id}/click', [\App\Http\Controllers\Api\SocialController::class, 'recordClick']);
// Feed Sidebar
Route::get('/v2/community/stats', [\App\Http\Controllers\Api\FeedSidebarController::class, 'communityStats']);
Route::get('/v2/members/suggested', [\App\Http\Controllers\Api\FeedSidebarController::class, 'suggestedMembers']);
Route::get('/v2/feed/sidebar', [\App\Http\Controllers\Api\FeedSidebarController::class, 'sidebar']);
// Feed Sharing & Hashtags
Route::post('/v2/feed/posts/{id}/share', [\App\Http\Controllers\Api\FeedSocialController::class, 'sharePost']);
Route::delete('/v2/feed/posts/{id}/share', [\App\Http\Controllers\Api\FeedSocialController::class, 'unsharePost']);
Route::get('/v2/feed/posts/{id}/sharers', [\App\Http\Controllers\Api\FeedSocialController::class, 'getSharers']);
Route::get('/v2/feed/hashtags/trending', [\App\Http\Controllers\Api\FeedSocialController::class, 'getTrendingHashtags']);
Route::get('/v2/feed/hashtags/search', [\App\Http\Controllers\Api\FeedSocialController::class, 'searchHashtags']);
Route::get('/v2/feed/hashtags/{tag}', [\App\Http\Controllers\Api\FeedSocialController::class, 'getHashtagPosts']);
// Reactions (emoji reactions on posts and comments)
Route::post('/v2/posts/{id}/reactions', [\App\Http\Controllers\Api\ReactionController::class, 'togglePostReaction']);
Route::get('/v2/posts/{id}/reactions', [\App\Http\Controllers\Api\ReactionController::class, 'getPostReactions']);
Route::get('/v2/posts/{id}/reactions/{type}/users', [\App\Http\Controllers\Api\ReactionController::class, 'getPostReactors']);
Route::post('/v2/comments/{id}/reactions', [\App\Http\Controllers\Api\ReactionController::class, 'toggleCommentReaction']);
Route::get('/v2/comments/{id}/reactions', [\App\Http\Controllers\Api\ReactionController::class, 'getCommentReactions']);
// Link Previews
Route::get('/v2/link-preview', [\App\Http\Controllers\Api\LinkPreviewController::class, 'show']);
Route::post('/v2/link-preview', [\App\Http\Controllers\Api\LinkPreviewController::class, 'fetch']);
// Post Media (carousel / multi-image)
Route::post('/v2/posts/{id}/media', [\App\Http\Controllers\Api\PostMediaController::class, 'uploadMedia']);
Route::put('/v2/posts/{id}/media/reorder', [\App\Http\Controllers\Api\PostMediaController::class, 'reorderMedia']);
Route::delete('/v2/posts/media/{mediaId}', [\App\Http\Controllers\Api\PostMediaController::class, 'removeMedia']);
Route::put('/v2/posts/media/{mediaId}/alt', [\App\Http\Controllers\Api\PostMediaController::class, 'updateAltText']);
// Notifications
Route::get('/v2/notifications', [\App\Http\Controllers\Api\NotificationsController::class, 'index']);
Route::get('/v2/notifications/counts', [\App\Http\Controllers\Api\NotificationsController::class, 'counts']);
Route::post('/v2/notifications/read-all', [\App\Http\Controllers\Api\NotificationsController::class, 'markAllRead']);
Route::delete('/v2/notifications', [\App\Http\Controllers\Api\NotificationsController::class, 'destroyAll']);
Route::get('/v2/notifications/{id}', [\App\Http\Controllers\Api\NotificationsController::class, 'show']);
Route::post('/v2/notifications/{id}/read', [\App\Http\Controllers\Api\NotificationsController::class, 'markRead']);
Route::delete('/v2/notifications/{id}', [\App\Http\Controllers\Api\NotificationsController::class, 'destroy']);
// Reviews
Route::get('/v2/reviews/pending', [\App\Http\Controllers\Api\ReviewsController::class, 'pending']);
Route::get('/v2/reviews/user/{userId}', [\App\Http\Controllers\Api\ReviewsController::class, 'userReviews']);
Route::get('/v2/users/{userId}/reviews', [\App\Http\Controllers\Api\ReviewsController::class, 'userReviews']);
Route::get('/v2/reviews/user/{userId}/stats', [\App\Http\Controllers\Api\ReviewsController::class, 'userStats']);
Route::get('/v2/reviews/user/{userId}/trust', [\App\Http\Controllers\Api\ReviewsController::class, 'userTrust']);
Route::get('/v2/reviews/{id}', [\App\Http\Controllers\Api\ReviewsController::class, 'show']);
Route::post('/v2/reviews', [\App\Http\Controllers\Api\ReviewsController::class, 'store']);
Route::delete('/v2/reviews/{id}', [\App\Http\Controllers\Api\ReviewsController::class, 'destroy']);
// Search
Route::get('/v2/search', [\App\Http\Controllers\Api\SearchController::class, 'index']);
Route::get('/v2/search/suggestions', [\App\Http\Controllers\Api\SearchController::class, 'suggestions']);
Route::get('/v2/search/saved', [\App\Http\Controllers\Api\SearchController::class, 'savedSearches']);
Route::post('/v2/search/saved', [\App\Http\Controllers\Api\SearchController::class, 'saveSearch']);
Route::delete('/v2/search/saved/{id}', [\App\Http\Controllers\Api\SearchController::class, 'deleteSavedSearch']);
Route::post('/v2/search/saved/{id}/run', [\App\Http\Controllers\Api\SearchController::class, 'runSavedSearch']);
Route::get('/v2/search/trending', [\App\Http\Controllers\Api\SearchController::class, 'trending']);
// Metrics
Route::post('/v2/metrics', [\App\Http\Controllers\Api\MetricsController::class, 'store']);
Route::get('/v2/metrics/summary', [\App\Http\Controllers\Api\MetricsController::class, 'summary']);
// Polls
Route::get('/v2/polls', [\App\Http\Controllers\Api\PollsController::class, 'index']);
Route::post('/v2/polls', [\App\Http\Controllers\Api\PollsController::class, 'store']);
Route::get('/v2/polls/categories', [\App\Http\Controllers\Api\PollsController::class, 'categories']);
Route::get('/v2/polls/{id}', [\App\Http\Controllers\Api\PollsController::class, 'show']);
Route::put('/v2/polls/{id}', [\App\Http\Controllers\Api\PollsController::class, 'update']);
Route::delete('/v2/polls/{id}', [\App\Http\Controllers\Api\PollsController::class, 'destroy']);
Route::post('/v2/polls/{id}/vote', [\App\Http\Controllers\Api\PollsController::class, 'vote']);
Route::post('/v2/polls/{id}/rank', [\App\Http\Controllers\Api\PollsController::class, 'rank']);
Route::get('/v2/polls/{id}/ranked-results', [\App\Http\Controllers\Api\PollsController::class, 'rankedResults']);
Route::get('/v2/polls/{id}/export', [\App\Http\Controllers\Api\PollsController::class, 'export']);

// ============================================
// MIGRATED ROUTES — Content (Jobs, Ideation, Goals, Gamification, Volunteering, Comments, Blog, Help, Pages, Resources, KB)
// Source: httpdocs/routes/content.php
// ============================================
Route::get('/v2/jobs', [\App\Http\Controllers\Api\JobVacanciesController::class, 'index'])->withoutMiddleware('auth:sanctum');
Route::post('/v2/jobs', [\App\Http\Controllers\Api\JobVacanciesController::class, 'store']);
Route::get('/v2/jobs/recommended', [\App\Http\Controllers\Api\JobVacanciesController::class, 'recommended']);
Route::get('/v2/jobs/applications/{id}/cv', [\App\Http\Controllers\Api\JobVacanciesController::class, 'downloadCv']);
Route::get('/v2/jobs/saved', [\App\Http\Controllers\Api\JobVacanciesController::class, 'savedJobs']);
Route::get('/v2/jobs/my-applications', [\App\Http\Controllers\Api\JobVacanciesController::class, 'myApplications']);
Route::get('/v2/jobs/my-postings', [\App\Http\Controllers\Api\JobVacanciesController::class, 'myPostings']);
// AI description generator + duplicate detection (Agent A)
Route::post('/v2/jobs/generate-description', [\App\Http\Controllers\Api\JobVacanciesController::class, 'generateDescription']);
Route::post('/v2/jobs/check-duplicate', [\App\Http\Controllers\Api\JobVacanciesController::class, 'checkDuplicate']);
// Talent search (Agent C)
Route::get('/v2/jobs/talent-search', [\App\Http\Controllers\Api\JobVacanciesController::class, 'talentSearch']);
Route::get('/v2/jobs/talent-search/{id}', [\App\Http\Controllers\Api\JobVacanciesController::class, 'talentProfile']);
Route::get('/v2/jobs/alerts', [\App\Http\Controllers\Api\JobVacanciesController::class, 'listAlerts']);
Route::post('/v2/jobs/alerts', [\App\Http\Controllers\Api\JobVacanciesController::class, 'createAlert']);
Route::delete('/v2/jobs/alerts/{id}', [\App\Http\Controllers\Api\JobVacanciesController::class, 'deleteAlert']);
Route::put('/v2/jobs/alerts/{id}/unsubscribe', [\App\Http\Controllers\Api\JobVacanciesController::class, 'unsubscribeAlert']);
Route::put('/v2/jobs/alerts/{id}/resubscribe', [\App\Http\Controllers\Api\JobVacanciesController::class, 'resubscribeAlert']);
// Saved profile — static literal routes BEFORE {id} wildcard
Route::get('/v2/jobs/saved-profile', [\App\Http\Controllers\Api\JobVacanciesController::class, 'getSavedProfile']);
Route::put('/v2/jobs/saved-profile', [\App\Http\Controllers\Api\JobVacanciesController::class, 'saveSavedProfile']);
// Job templates
Route::get('/v2/jobs/templates', [\App\Http\Controllers\Api\JobVacanciesController::class, 'listTemplates']);
Route::post('/v2/jobs/templates', [\App\Http\Controllers\Api\JobVacanciesController::class, 'createTemplate']);
Route::get('/v2/jobs/templates/{id}', [\App\Http\Controllers\Api\JobVacanciesController::class, 'getTemplate']);
Route::delete('/v2/jobs/templates/{id}', [\App\Http\Controllers\Api\JobVacanciesController::class, 'deleteTemplate']);
// Salary benchmark lookup
Route::get('/v2/jobs/salary-benchmark', [\App\Http\Controllers\Api\JobVacanciesController::class, 'salaryBenchmark']);
// GDPR — static literal routes before {id} wildcard
Route::get('/v2/jobs/gdpr-export', [\App\Http\Controllers\Api\JobVacanciesController::class, 'gdprExport']);
Route::delete('/v2/jobs/gdpr-erase-me', [\App\Http\Controllers\Api\JobVacanciesController::class, 'gdprErase']);
// Pipeline rules
Route::get('/v2/jobs/{id}/pipeline-rules', [\App\Http\Controllers\Api\JobVacanciesController::class, 'listPipelineRules']);
Route::post('/v2/jobs/{id}/pipeline-rules', [\App\Http\Controllers\Api\JobVacanciesController::class, 'createPipelineRule']);
Route::delete('/v2/jobs/pipeline-rules/{id}', [\App\Http\Controllers\Api\JobVacanciesController::class, 'deletePipelineRule']);
Route::post('/v2/jobs/{id}/pipeline-rules/run', [\App\Http\Controllers\Api\JobVacanciesController::class, 'runPipelineRules']);
// Bulk application actions
Route::post('/v2/jobs/{id}/applications/bulk-status', [\App\Http\Controllers\Api\JobVacanciesController::class, 'bulkUpdateApplicationStatus']);
// Static literal routes MUST come before {id} wildcard to avoid mismatching
Route::get('/v2/jobs/my-interviews', [\App\Http\Controllers\Api\JobVacanciesController::class, 'myInterviews']);
Route::get('/v2/jobs/my-offers', [\App\Http\Controllers\Api\JobVacanciesController::class, 'myOffers']);
Route::get('/v2/jobs/{id}', [\App\Http\Controllers\Api\JobVacanciesController::class, 'show'])->withoutMiddleware('auth:sanctum');
Route::put('/v2/jobs/{id}', [\App\Http\Controllers\Api\JobVacanciesController::class, 'update']);
Route::delete('/v2/jobs/{id}', [\App\Http\Controllers\Api\JobVacanciesController::class, 'destroy']);
Route::post('/v2/jobs/{id}/apply', [\App\Http\Controllers\Api\JobVacanciesController::class, 'apply']);
Route::post('/v2/jobs/{id}/save', [\App\Http\Controllers\Api\JobVacanciesController::class, 'saveJob']);
Route::delete('/v2/jobs/{id}/save', [\App\Http\Controllers\Api\JobVacanciesController::class, 'unsaveJob']);
Route::get('/v2/jobs/{id}/match', [\App\Http\Controllers\Api\JobVacanciesController::class, 'matchPercentage']);
Route::get('/v2/jobs/{id}/qualified', [\App\Http\Controllers\Api\JobVacanciesController::class, 'qualificationAssessment']);
Route::get('/v2/jobs/{id}/applications', [\App\Http\Controllers\Api\JobVacanciesController::class, 'applications']);
Route::get('/v2/jobs/{id}/applications/export-csv', [\App\Http\Controllers\Api\JobVacanciesController::class, 'exportApplicationsCsv']);
Route::get('/v2/jobs/{id}/analytics', [\App\Http\Controllers\Api\JobVacanciesController::class, 'analytics']);
Route::post('/v2/jobs/{id}/renew', [\App\Http\Controllers\Api\JobVacanciesController::class, 'renewJob']);
Route::post('/v2/jobs/{id}/feature', [\App\Http\Controllers\Api\JobVacanciesController::class, 'featureJob']);
Route::delete('/v2/jobs/{id}/feature', [\App\Http\Controllers\Api\JobVacanciesController::class, 'unfeatureJob']);
Route::put('/v2/jobs/applications/{id}', [\App\Http\Controllers\Api\JobVacanciesController::class, 'updateApplication']);
Route::get('/v2/jobs/applications/{id}/history', [\App\Http\Controllers\Api\JobVacanciesController::class, 'applicationHistory']);
// Interviews
Route::post('/v2/jobs/applications/{id}/interview', [\App\Http\Controllers\Api\JobVacanciesController::class, 'proposeInterview']);
Route::put('/v2/jobs/interviews/{id}/accept', [\App\Http\Controllers\Api\JobVacanciesController::class, 'acceptInterview']);
Route::put('/v2/jobs/interviews/{id}/decline', [\App\Http\Controllers\Api\JobVacanciesController::class, 'declineInterview']);
Route::delete('/v2/jobs/interviews/{id}', [\App\Http\Controllers\Api\JobVacanciesController::class, 'cancelInterview']);
Route::get('/v2/jobs/{id}/interviews', [\App\Http\Controllers\Api\JobVacanciesController::class, 'getInterviews']);
// Offers
Route::post('/v2/jobs/applications/{id}/offer', [\App\Http\Controllers\Api\JobVacanciesController::class, 'createOffer']);
Route::put('/v2/jobs/offers/{id}/accept', [\App\Http\Controllers\Api\JobVacanciesController::class, 'acceptOffer']);
Route::put('/v2/jobs/offers/{id}/reject', [\App\Http\Controllers\Api\JobVacanciesController::class, 'rejectOffer']);
Route::delete('/v2/jobs/offers/{id}', [\App\Http\Controllers\Api\JobVacanciesController::class, 'withdrawOffer']);
Route::get('/v2/jobs/applications/{id}/offer', [\App\Http\Controllers\Api\JobVacanciesController::class, 'getApplicationOffer']);
// AI CV parsing
Route::get('/v2/jobs/applications/{id}/parse-cv', [\App\Http\Controllers\Api\JobVacanciesController::class, 'parseResumeCv']);
// Referrals
Route::post('/v2/jobs/{id}/referral', [\App\Http\Controllers\Api\JobVacanciesController::class, 'getOrCreateReferral']);
Route::get('/v2/jobs/{id}/referral-stats', [\App\Http\Controllers\Api\JobVacanciesController::class, 'referralStats']);
// Scorecards
Route::put('/v2/jobs/applications/{id}/scorecard', [\App\Http\Controllers\Api\JobVacanciesController::class, 'upsertScorecard']);
Route::get('/v2/jobs/applications/{id}/scorecards', [\App\Http\Controllers\Api\JobVacanciesController::class, 'getScorecards']);
// Hiring team
Route::get('/v2/jobs/{id}/team', [\App\Http\Controllers\Api\JobVacanciesController::class, 'getTeam']);
Route::post('/v2/jobs/{id}/team', [\App\Http\Controllers\Api\JobVacanciesController::class, 'addTeamMember']);
Route::delete('/v2/jobs/{id}/team/{userId}', [\App\Http\Controllers\Api\JobVacanciesController::class, 'removeTeamMember']);
// Interview self-scheduling slots (Agent E)
Route::get('/v2/jobs/{id}/interview-slots', [\App\Http\Controllers\Api\JobVacanciesController::class, 'listInterviewSlots']);
Route::post('/v2/jobs/{id}/interview-slots', [\App\Http\Controllers\Api\JobVacanciesController::class, 'createInterviewSlots']);
Route::post('/v2/jobs/{id}/interview-slots/bulk', [\App\Http\Controllers\Api\JobVacanciesController::class, 'bulkCreateInterviewSlots']);
Route::post('/v2/jobs/interview-slots/{slotId}/book', [\App\Http\Controllers\Api\JobVacanciesController::class, 'bookInterviewSlot']);
Route::delete('/v2/jobs/interview-slots/{slotId}/book', [\App\Http\Controllers\Api\JobVacanciesController::class, 'cancelInterviewSlotBooking']);
Route::delete('/v2/jobs/interview-slots/{slotId}', [\App\Http\Controllers\Api\JobVacanciesController::class, 'deleteInterviewSlot']);
Route::get('/v2/ideation-challenges', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'index'])->withoutMiddleware('auth:sanctum');
Route::post('/v2/ideation-challenges', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'store']);
Route::get('/v2/ideation-ideas/{id}', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'showIdea']);
Route::put('/v2/ideation-ideas/{id}', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'updateIdea']);
Route::put('/v2/ideation-ideas/{id}/draft', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'updateDraft']);
Route::delete('/v2/ideation-ideas/{id}', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'deleteIdea']);
Route::post('/v2/ideation-ideas/{id}/vote', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'voteIdea']);
Route::put('/v2/ideation-ideas/{id}/status', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'updateIdeaStatus']);
Route::get('/v2/ideation-ideas/{id}/comments', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'comments']);
Route::post('/v2/ideation-ideas/{id}/comments', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'addComment']);
Route::delete('/v2/ideation-comments/{id}', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'deleteComment']);
Route::get('/v2/ideation-challenges/{id}', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'show'])->withoutMiddleware('auth:sanctum');
Route::put('/v2/ideation-challenges/{id}', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'update']);
Route::delete('/v2/ideation-challenges/{id}', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'destroy']);
Route::put('/v2/ideation-challenges/{id}/status', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'updateStatus']);
Route::get('/v2/ideation-challenges/{id}/ideas/drafts', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'ideaDrafts']);
Route::get('/v2/ideation-challenges/{id}/ideas', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'ideas']);
Route::post('/v2/ideation-challenges/{id}/ideas', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'submitIdea']);
Route::post('/v2/ideation-challenges/{id}/favorite', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'toggleFavorite']);
Route::post('/v2/ideation-challenges/{id}/duplicate', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'duplicate']);
Route::post('/v2/ideation-ideas/{id}/convert-to-group', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'convertToGroup']);
Route::get('/v2/goals', [\App\Http\Controllers\Api\GoalsController::class, 'index']);
Route::post('/v2/goals', [\App\Http\Controllers\Api\GoalsController::class, 'store']);
Route::get('/v2/goals/discover', [\App\Http\Controllers\Api\GoalsController::class, 'discover']);
Route::get('/v2/goals/mentoring', [\App\Http\Controllers\Api\GoalsController::class, 'mentoring']);
Route::get('/v2/goals/templates', [\App\Http\Controllers\Api\GoalsController::class, 'templates']);
Route::get('/v2/goals/templates/categories', [\App\Http\Controllers\Api\GoalsController::class, 'templateCategories']);
Route::post('/v2/goals/templates', [\App\Http\Controllers\Api\GoalsController::class, 'createTemplate']);
Route::post('/v2/goals/from-template/{templateId}', [\App\Http\Controllers\Api\GoalsController::class, 'createFromTemplate']);
Route::get('/v2/goals/{id}', [\App\Http\Controllers\Api\GoalsController::class, 'show']);
Route::put('/v2/goals/{id}', [\App\Http\Controllers\Api\GoalsController::class, 'update']);
Route::delete('/v2/goals/{id}', [\App\Http\Controllers\Api\GoalsController::class, 'destroy']);
Route::post('/v2/goals/{id}/progress', [\App\Http\Controllers\Api\GoalsController::class, 'progress']);
Route::post('/v2/goals/{id}/buddy', [\App\Http\Controllers\Api\GoalsController::class, 'buddy']);
Route::post('/v2/goals/{id}/complete', [\App\Http\Controllers\Api\GoalsController::class, 'complete']);
Route::get('/v2/goals/{id}/checkins', [\App\Http\Controllers\Api\GoalsController::class, 'listCheckins']);
Route::post('/v2/goals/{id}/checkins', [\App\Http\Controllers\Api\GoalsController::class, 'createCheckin']);
Route::get('/v2/goals/{id}/history', [\App\Http\Controllers\Api\GoalsController::class, 'history']);
Route::get('/v2/goals/{id}/history/summary', [\App\Http\Controllers\Api\GoalsController::class, 'historySummary']);
Route::get('/v2/goals/{id}/reminder', [\App\Http\Controllers\Api\GoalsController::class, 'getReminder']);
Route::put('/v2/goals/{id}/reminder', [\App\Http\Controllers\Api\GoalsController::class, 'setReminder']);
Route::delete('/v2/goals/{id}/reminder', [\App\Http\Controllers\Api\GoalsController::class, 'deleteReminder']);
Route::get('/v2/gamification/profile', [\App\Http\Controllers\Api\GamificationV2Controller::class, 'profile']);
Route::get('/v2/gamification/badges', [\App\Http\Controllers\Api\GamificationV2Controller::class, 'badges']);
Route::get('/v2/gamification/badges/{key}', [\App\Http\Controllers\Api\GamificationV2Controller::class, 'showBadge']);
Route::get('/v2/gamification/leaderboard', [\App\Http\Controllers\Api\GamificationV2Controller::class, 'leaderboard']);
Route::get('/v2/gamification/challenges', [\App\Http\Controllers\Api\GamificationV2Controller::class, 'challenges']);
Route::get('/v2/gamification/collections', [\App\Http\Controllers\Api\GamificationV2Controller::class, 'collections']);
Route::get('/v2/gamification/daily-reward', [\App\Http\Controllers\Api\GamificationV2Controller::class, 'dailyRewardStatus']);
Route::post('/v2/gamification/daily-reward', [\App\Http\Controllers\Api\GamificationV2Controller::class, 'claimDailyReward']);
Route::get('/v2/gamification/shop', [\App\Http\Controllers\Api\GamificationV2Controller::class, 'shop']);
Route::post('/v2/gamification/shop/purchase', [\App\Http\Controllers\Api\GamificationV2Controller::class, 'purchase']);
Route::put('/v2/gamification/showcase', [\App\Http\Controllers\Api\GamificationV2Controller::class, 'updateShowcase']);
Route::get('/v2/gamification/seasons', [\App\Http\Controllers\Api\GamificationV2Controller::class, 'seasons']);
Route::get('/v2/gamification/seasons/current', [\App\Http\Controllers\Api\GamificationV2Controller::class, 'currentSeason']);
Route::post('/v2/gamification/challenges/{id}/claim', [\App\Http\Controllers\Api\GamificationV2Controller::class, 'claimChallenge']);
Route::get('/v2/gamification/nexus-score', [\App\Http\Controllers\Api\GamificationV2Controller::class, 'nexusScore']);
Route::get('/v2/gamification/community-dashboard', [\App\Http\Controllers\Api\GamificationV2Controller::class, 'communityDashboard']);
Route::get('/v2/gamification/personal-journey', [\App\Http\Controllers\Api\GamificationV2Controller::class, 'personalJourney']);
Route::get('/v2/gamification/member-spotlight', [\App\Http\Controllers\Api\GamificationV2Controller::class, 'memberSpotlight']);
Route::get('/v2/gamification/engagement-history', [\App\Http\Controllers\Api\GamificationV2Controller::class, 'engagementHistory']);
Route::get('/v2/volunteering/opportunities', [\App\Http\Controllers\Api\VolunteerController::class, 'opportunities'])->withoutMiddleware('auth:sanctum');
Route::post('/v2/volunteering/opportunities', [\App\Http\Controllers\Api\VolunteerController::class, 'createOpportunity']);
Route::get('/v2/volunteering/opportunities/{id}', [\App\Http\Controllers\Api\VolunteerController::class, 'showOpportunity'])->withoutMiddleware('auth:sanctum');
Route::put('/v2/volunteering/opportunities/{id}', [\App\Http\Controllers\Api\VolunteerController::class, 'updateOpportunity']);
Route::delete('/v2/volunteering/opportunities/{id}', [\App\Http\Controllers\Api\VolunteerController::class, 'deleteOpportunity']);
Route::get('/v2/volunteering/opportunities/{id}/shifts', [\App\Http\Controllers\Api\VolunteerController::class, 'shifts']);
Route::get('/v2/volunteering/opportunities/{id}/applications', [\App\Http\Controllers\Api\VolunteerController::class, 'opportunityApplications']);
Route::post('/v2/volunteering/opportunities/{id}/apply', [\App\Http\Controllers\Api\VolunteerController::class, 'apply']);
Route::get('/v2/volunteering/applications', [\App\Http\Controllers\Api\VolunteerController::class, 'myApplications']);
Route::put('/v2/volunteering/applications/{id}', [\App\Http\Controllers\Api\VolunteerController::class, 'handleApplication']);
Route::delete('/v2/volunteering/applications/{id}', [\App\Http\Controllers\Api\VolunteerController::class, 'withdrawApplication']);
Route::get('/v2/volunteering/shifts', [\App\Http\Controllers\Api\VolunteerController::class, 'myShifts']);
Route::post('/v2/volunteering/shifts/{id}/signup', [\App\Http\Controllers\Api\VolunteerController::class, 'signUp']);
Route::delete('/v2/volunteering/shifts/{id}/signup', [\App\Http\Controllers\Api\VolunteerController::class, 'cancelSignup']);
Route::get('/v2/volunteering/hours', [\App\Http\Controllers\Api\VolunteerController::class, 'myHours']);
Route::post('/v2/volunteering/hours', [\App\Http\Controllers\Api\VolunteerController::class, 'logHours']);
Route::get('/v2/volunteering/hours/summary', [\App\Http\Controllers\Api\VolunteerController::class, 'hoursSummary']);
Route::get('/v2/volunteering/hours/pending-review', [\App\Http\Controllers\Api\VolunteerController::class, 'pendingHoursReview']);
Route::put('/v2/volunteering/hours/{id}/verify', [\App\Http\Controllers\Api\VolunteerController::class, 'verifyHours']);
Route::get('/v2/volunteering/my-organisations', [\App\Http\Controllers\Api\VolunteerController::class, 'myOrganisations']);
Route::get('/v2/volunteering/organisations', [\App\Http\Controllers\Api\VolunteerController::class, 'organisations'])->withoutMiddleware('auth:sanctum');
Route::post('/v2/volunteering/organisations', [\App\Http\Controllers\Api\VolunteerController::class, 'createOrganisation']);
Route::get('/v2/volunteering/organisations/{id}', [\App\Http\Controllers\Api\VolunteerController::class, 'showOrganisation'])->withoutMiddleware('auth:sanctum');
Route::post('/v2/volunteering/reviews', [\App\Http\Controllers\Api\VolunteerController::class, 'createReview']);
Route::get('/v2/volunteering/reviews/{type}/{id}', [\App\Http\Controllers\Api\VolunteerController::class, 'getReviews']);
Route::get('/v2/comments', [\App\Http\Controllers\Api\CommentsController::class, 'index']);
Route::post('/v2/comments', [\App\Http\Controllers\Api\CommentsController::class, 'store']);
Route::put('/v2/comments/{id}', [\App\Http\Controllers\Api\CommentsController::class, 'update']);
Route::delete('/v2/comments/{id}', [\App\Http\Controllers\Api\CommentsController::class, 'destroy']);
// Note: POST /v2/comments/{id}/reactions is handled by ReactionController (line ~354)
Route::get('/v2/mentions/search', [\App\Http\Controllers\Api\MentionController::class, 'search']);
Route::get('/v2/mentions/me', [\App\Http\Controllers\Api\MentionController::class, 'myMentions']);
Route::get('/v2/blog', [\App\Http\Controllers\Api\BlogPublicController::class, 'index'])->withoutMiddleware('auth:sanctum');
Route::get('/v2/blog/categories', [\App\Http\Controllers\Api\BlogPublicController::class, 'categories'])->withoutMiddleware('auth:sanctum');
Route::get('/v2/blog/{slug}', [\App\Http\Controllers\Api\BlogPublicController::class, 'show'])->withoutMiddleware('auth:sanctum');
Route::get('/v2/help/faqs', [\App\Http\Controllers\Api\HelpController::class, 'getFaqs'])->withoutMiddleware('auth:sanctum');
Route::get('/v2/pages/{slug}', [\App\Http\Controllers\Api\PagesPublicController::class, 'show'])->withoutMiddleware('auth:sanctum');
Route::get('/v2/resources', [\App\Http\Controllers\Api\ResourcePublicController::class, 'index'])->withoutMiddleware('auth:sanctum');
Route::get('/v2/resources/categories', [\App\Http\Controllers\Api\ResourcePublicController::class, 'categories']);
Route::get('/v2/resources/categories/tree', [\App\Http\Controllers\Api\ResourceCategoryController::class, 'tree']);
Route::post('/v2/resources/categories', [\App\Http\Controllers\Api\ResourceCategoryController::class, 'store'])->middleware('admin');
Route::put('/v2/resources/categories/{id}', [\App\Http\Controllers\Api\ResourceCategoryController::class, 'update'])->middleware('admin');
Route::delete('/v2/resources/categories/{id}', [\App\Http\Controllers\Api\ResourceCategoryController::class, 'destroy'])->middleware('admin');
Route::put('/v2/resources/reorder', [\App\Http\Controllers\Api\ResourceCategoryController::class, 'reorder'])->middleware('admin');
Route::post('/v2/resources', [\App\Http\Controllers\Api\ResourcePublicController::class, 'store']);
Route::get('/v2/resources/{id}/download', [\App\Http\Controllers\Api\ResourcePublicController::class, 'download']);
Route::delete('/v2/resources/{id}', [\App\Http\Controllers\Api\ResourcePublicController::class, 'destroy']);
Route::get('/v2/kb', [\App\Http\Controllers\Api\KnowledgeBaseController::class, 'index'])->withoutMiddleware('auth:sanctum');
Route::get('/v2/kb/search', [\App\Http\Controllers\Api\KnowledgeBaseController::class, 'search'])->withoutMiddleware('auth:sanctum');
Route::post('/v2/kb', [\App\Http\Controllers\Api\KnowledgeBaseController::class, 'store']);
Route::get('/v2/kb/slug/{slug}', [\App\Http\Controllers\Api\KnowledgeBaseController::class, 'showBySlug'])->withoutMiddleware('auth:sanctum');
Route::get('/v2/kb/{id}', [\App\Http\Controllers\Api\KnowledgeBaseController::class, 'show'])->withoutMiddleware('auth:sanctum');
Route::put('/v2/kb/{id}', [\App\Http\Controllers\Api\KnowledgeBaseController::class, 'update']);
Route::delete('/v2/kb/{id}', [\App\Http\Controllers\Api\KnowledgeBaseController::class, 'destroy']);
Route::post('/v2/kb/{id}/feedback', [\App\Http\Controllers\Api\KnowledgeBaseController::class, 'feedback']);
Route::post('/v2/kb/{id}/attachments', [\App\Http\Controllers\Api\KnowledgeBaseController::class, 'uploadAttachment']);
Route::get('/v2/kb/{id}/attachments/{attachmentId}/download', [\App\Http\Controllers\Api\KnowledgeBaseController::class, 'downloadAttachment'])->withoutMiddleware('auth:sanctum');
Route::delete('/v2/kb/{id}/attachments/{attachmentId}', [\App\Http\Controllers\Api\KnowledgeBaseController::class, 'deleteAttachment']);

// ============================================
// STORIES — 24-hour disappearing content
// ============================================
Route::get('/v2/stories', [\App\Http\Controllers\Api\StoryController::class, 'index']);
Route::get('/v2/stories/user/{userId}', [\App\Http\Controllers\Api\StoryController::class, 'userStories']);
Route::post('/v2/stories', [\App\Http\Controllers\Api\StoryController::class, 'store']);
Route::post('/v2/stories/{id}/view', [\App\Http\Controllers\Api\StoryController::class, 'view']);
Route::get('/v2/stories/{id}/viewers', [\App\Http\Controllers\Api\StoryController::class, 'viewers']);
Route::post('/v2/stories/{id}/react', [\App\Http\Controllers\Api\StoryController::class, 'react']);
Route::delete('/v2/stories/{id}', [\App\Http\Controllers\Api\StoryController::class, 'destroy']);
Route::post('/v2/stories/{id}/poll/vote', [\App\Http\Controllers\Api\StoryController::class, 'pollVote']);
Route::post('/v2/stories/{id}/reply', [\App\Http\Controllers\Api\StoryController::class, 'reply']);
Route::get('/v2/stories/highlights/{userId}', [\App\Http\Controllers\Api\StoryController::class, 'highlights']);
Route::get('/v2/stories/highlights/{id}/stories', [\App\Http\Controllers\Api\StoryController::class, 'highlightStories']);
Route::post('/v2/stories/highlights', [\App\Http\Controllers\Api\StoryController::class, 'createHighlight']);
Route::post('/v2/stories/highlights/{id}/items', [\App\Http\Controllers\Api\StoryController::class, 'addHighlightItem']);
Route::delete('/v2/stories/highlights/{id}', [\App\Http\Controllers\Api\StoryController::class, 'deleteHighlight']);
Route::put('/v2/stories/highlights/reorder', [\App\Http\Controllers\Api\StoryController::class, 'reorderHighlights']);
Route::put('/v2/stories/highlights/{id}', [\App\Http\Controllers\Api\StoryController::class, 'updateHighlight']);
Route::delete('/v2/stories/highlights/{id}/items/{storyId}', [\App\Http\Controllers\Api\StoryController::class, 'removeHighlightItem']);
Route::get('/v2/stories/archive', [\App\Http\Controllers\Api\StoryController::class, 'archive']);
Route::get('/v2/stories/close-friends', [\App\Http\Controllers\Api\StoryController::class, 'closeFriends']);
Route::post('/v2/stories/close-friends', [\App\Http\Controllers\Api\StoryController::class, 'addCloseFriend']);
Route::delete('/v2/stories/close-friends/{friendId}', [\App\Http\Controllers\Api\StoryController::class, 'removeCloseFriend']);
Route::post('/v2/stories/{id}/analytics', [\App\Http\Controllers\Api\StoryController::class, 'trackAnalytics']);
Route::get('/v2/stories/{id}/analytics', [\App\Http\Controllers\Api\StoryController::class, 'getAnalytics']);
Route::post('/v2/stories/{id}/stickers', [\App\Http\Controllers\Api\StoryController::class, 'saveStickers']);

// ============================================

// Stripe donation payment routes
Route::post('/v2/donations/payment-intent', [\App\Http\Controllers\Api\DonationPaymentController::class, 'createPaymentIntent']);
Route::get('/v2/donations/{id}/receipt', [\App\Http\Controllers\Api\DonationPaymentController::class, 'getDonationReceipt']);

}); // End Route::middleware('auth:sanctum')

// ============================================
// Admin routes — Sanctum auth + admin middleware
// Controllers also enforce auth via $this->requireAdmin() as a fallback
// ============================================
Route::middleware(['auth:sanctum', 'admin'])->group(function () {

// MIGRATED ROUTES — Admin API (Dashboard, Users, Listings, Config, Cache, Jobs, Federation, CRM, Super Admin)
// Source: httpdocs/routes/admin-api.php
// ============================================
// Admin verification badge management (distinct path to avoid collision with gamification badges at line 539)
Route::post('/v2/admin/users/{id}/verification-badges', [\App\Http\Controllers\Api\MemberVerificationBadgeController::class, 'grantBadge']);
Route::delete('/v2/admin/users/{id}/verification-badges/{type}', [\App\Http\Controllers\Api\MemberVerificationBadgeController::class, 'revokeBadge']);
Route::get('/v2/admin/users/{id}/verification-badges', [\App\Http\Controllers\Api\MemberVerificationBadgeController::class, 'getAdminBadgeList']);

Route::get('/v2/admin/dashboard/stats', [\App\Http\Controllers\Api\AdminDashboardController::class, 'stats']);
Route::get('/v2/admin/dashboard/trends', [\App\Http\Controllers\Api\AdminDashboardController::class, 'trends']);
Route::get('/v2/admin/dashboard/activity', [\App\Http\Controllers\Api\AdminDashboardController::class, 'activity']);
Route::get('/v2/admin/users', [\App\Http\Controllers\Api\AdminUsersController::class, 'index']);
Route::post('/v2/admin/users', [\App\Http\Controllers\Api\AdminUsersController::class, 'store']);
Route::post('/v2/admin/users/import', [\App\Http\Controllers\Api\AdminUsersController::class, 'import']);
Route::get('/v2/admin/users/import/template', [\App\Http\Controllers\Api\AdminUsersController::class, 'importTemplate']);
Route::get('/v2/admin/users/{id}', [\App\Http\Controllers\Api\AdminUsersController::class, 'show']);
Route::put('/v2/admin/users/{id}', [\App\Http\Controllers\Api\AdminUsersController::class, 'update']);
Route::delete('/v2/admin/users/{id}', [\App\Http\Controllers\Api\AdminUsersController::class, 'destroy']);
Route::post('/v2/admin/users/{id}/approve', [\App\Http\Controllers\Api\AdminUsersController::class, 'approve']);
Route::post('/v2/admin/users/{id}/suspend', [\App\Http\Controllers\Api\AdminUsersController::class, 'suspend']);
Route::post('/v2/admin/users/{id}/ban', [\App\Http\Controllers\Api\AdminUsersController::class, 'ban']);
Route::post('/v2/admin/users/{id}/reactivate', [\App\Http\Controllers\Api\AdminUsersController::class, 'reactivate']);
Route::post('/v2/admin/users/{id}/reset-2fa', [\App\Http\Controllers\Api\AdminUsersController::class, 'reset2fa']);
Route::post('/v2/admin/users/badges/recheck-all', [\App\Http\Controllers\Api\AdminGamificationController::class, 'recheckAll']);
Route::post('/v2/admin/users/{id}/badges', [\App\Http\Controllers\Api\AdminUsersController::class, 'addBadge']);
Route::delete('/v2/admin/users/{id}/badges/{badgeId}', [\App\Http\Controllers\Api\AdminUsersController::class, 'removeBadge']);
// impersonate, super-admin promotion — moved to super-admin middleware group (see below)
Route::post('/v2/admin/users/{id}/badges/recheck', [\App\Http\Controllers\Api\AdminUsersController::class, 'recheckBadges']);
Route::get('/v2/admin/users/{id}/consents', [\App\Http\Controllers\Api\AdminUsersController::class, 'getConsents']);
Route::post('/v2/admin/users/{id}/password', [\App\Http\Controllers\Api\AdminUsersController::class, 'setPassword']);
Route::post('/v2/admin/users/{id}/send-password-reset', [\App\Http\Controllers\Api\AdminUsersController::class, 'sendPasswordReset']);
Route::post('/v2/admin/users/{id}/send-welcome-email', [\App\Http\Controllers\Api\AdminUsersController::class, 'sendWelcomeEmail']);
Route::get('/v2/admin/listings', [\App\Http\Controllers\Api\AdminListingsController::class, 'index']);
Route::get('/v2/admin/listings/featured', [\App\Http\Controllers\Api\ListingsController::class, 'featured']);
Route::get('/v2/admin/listings/moderation-queue', [\App\Http\Controllers\Api\AdminListingsController::class, 'moderationQueue']);
Route::get('/v2/admin/listings/moderation-stats', [\App\Http\Controllers\Api\AdminListingsController::class, 'moderationStats']);
Route::get('/v2/admin/listings/{id}', [\App\Http\Controllers\Api\AdminListingsController::class, 'show']);
Route::post('/v2/admin/listings/{id}/approve', [\App\Http\Controllers\Api\AdminListingsController::class, 'approve']);
Route::delete('/v2/admin/listings/{id}', [\App\Http\Controllers\Api\AdminListingsController::class, 'destroy']);
Route::get('/v2/admin/categories', [\App\Http\Controllers\Api\AdminCategoriesController::class, 'index']);
Route::post('/v2/admin/categories', [\App\Http\Controllers\Api\AdminCategoriesController::class, 'store']);
Route::put('/v2/admin/categories/{id}', [\App\Http\Controllers\Api\AdminCategoriesController::class, 'update']);
Route::delete('/v2/admin/categories/{id}', [\App\Http\Controllers\Api\AdminCategoriesController::class, 'destroy']);
Route::get('/v2/admin/attributes', [\App\Http\Controllers\Api\AdminCategoriesController::class, 'listAttributes']);
Route::post('/v2/admin/attributes', [\App\Http\Controllers\Api\AdminCategoriesController::class, 'storeAttribute']);
Route::put('/v2/admin/attributes/{id}', [\App\Http\Controllers\Api\AdminCategoriesController::class, 'updateAttribute']);
Route::delete('/v2/admin/attributes/{id}', [\App\Http\Controllers\Api\AdminCategoriesController::class, 'destroyAttribute']);
Route::get('/v2/admin/config', [\App\Http\Controllers\Api\AdminConfigController::class, 'getConfig']);
Route::put('/v2/admin/config/features', [\App\Http\Controllers\Api\AdminConfigController::class, 'updateFeature']);
Route::put('/v2/admin/config/modules', [\App\Http\Controllers\Api\AdminConfigController::class, 'updateModule']);
Route::get('/v2/admin/cache/stats', [\App\Http\Controllers\Api\AdminConfigController::class, 'cacheStats']);
Route::post('/v2/admin/cache/clear', [\App\Http\Controllers\Api\AdminConfigController::class, 'clearCache']);
Route::get('/v2/admin/background-jobs', [\App\Http\Controllers\Api\AdminConfigController::class, 'getJobs']);
Route::post('/v2/admin/background-jobs/{id}/run', [\App\Http\Controllers\Api\AdminConfigController::class, 'runJob']);
Route::get('/v2/admin/settings', [\App\Http\Controllers\Api\AdminConfigController::class, 'getSettings']);
Route::put('/v2/admin/settings', [\App\Http\Controllers\Api\AdminConfigController::class, 'updateSettings']);
Route::get('/v2/admin/config/registration-policy', [\App\Http\Controllers\Api\RegistrationPolicyController::class, 'getPolicy']);
Route::put('/v2/admin/config/registration-policy', [\App\Http\Controllers\Api\RegistrationPolicyController::class, 'updatePolicy']);
Route::get('/v2/admin/identity/providers', [\App\Http\Controllers\Api\RegistrationPolicyController::class, 'listProviders']);
Route::get('/v2/admin/identity/sessions', [\App\Http\Controllers\Api\RegistrationPolicyController::class, 'listSessions']);
Route::get('/v2/admin/identity/audit-log', [\App\Http\Controllers\Api\RegistrationPolicyController::class, 'getAuditLog']);
Route::post('/v2/admin/identity/sessions/{id}/approve', [\App\Http\Controllers\Api\RegistrationPolicyController::class, 'adminApproveVerification']);
Route::post('/v2/admin/identity/sessions/{id}/reject', [\App\Http\Controllers\Api\RegistrationPolicyController::class, 'adminRejectVerification']);
Route::get('/v2/admin/identity/provider-health', [\App\Http\Controllers\Api\IdentityProviderHealthController::class, 'getProviderHealth']);
Route::get('/v2/admin/identity/provider-credentials', [\App\Http\Controllers\Api\RegistrationPolicyController::class, 'listProviderCredentials']);
Route::put('/v2/admin/identity/provider-credentials/{slug}', [\App\Http\Controllers\Api\RegistrationPolicyController::class, 'saveProviderCredentials']);
Route::delete('/v2/admin/identity/provider-credentials/{slug}', [\App\Http\Controllers\Api\RegistrationPolicyController::class, 'deleteProviderCredentials']);
Route::get('/v2/admin/invite-codes', [\App\Http\Controllers\Api\RegistrationPolicyController::class, 'listInviteCodes']);
Route::post('/v2/admin/invite-codes', [\App\Http\Controllers\Api\RegistrationPolicyController::class, 'generateInviteCodes']);
Route::delete('/v2/admin/invite-codes/{id}', [\App\Http\Controllers\Api\RegistrationPolicyController::class, 'deactivateInviteCode']);
Route::get('/v2/admin/config/groups', [\App\Http\Controllers\Api\AdminConfigController::class, 'getGroupConfig']);
Route::put('/v2/admin/config/groups', [\App\Http\Controllers\Api\AdminConfigController::class, 'updateGroupConfig']);
Route::put('/v2/admin/config/groups/bulk', [\App\Http\Controllers\Api\AdminConfigController::class, 'updateGroupConfigBulk']);
Route::get('/v2/admin/config/listings', [\App\Http\Controllers\Api\AdminConfigController::class, 'getListingConfig']);
Route::put('/v2/admin/config/listings', [\App\Http\Controllers\Api\AdminConfigController::class, 'updateListingConfig']);
Route::put('/v2/admin/config/listings/bulk', [\App\Http\Controllers\Api\AdminConfigController::class, 'updateListingConfigBulk']);
Route::get('/v2/admin/config/volunteering', [\App\Http\Controllers\Api\AdminConfigController::class, 'getVolunteeringConfig']);
Route::put('/v2/admin/config/volunteering/bulk', [\App\Http\Controllers\Api\AdminConfigController::class, 'updateVolunteeringConfigBulk']);
Route::get('/v2/admin/config/jobs', [\App\Http\Controllers\Api\AdminConfigController::class, 'getJobConfig']);
Route::put('/v2/admin/config/jobs/bulk', [\App\Http\Controllers\Api\AdminConfigController::class, 'updateJobConfigBulk']);
Route::get('/v2/admin/config/ai', [\App\Http\Controllers\Api\AdminConfigController::class, 'getAiConfig']);
Route::put('/v2/admin/config/ai', [\App\Http\Controllers\Api\AdminConfigController::class, 'updateAiConfig']);
Route::get('/v2/admin/config/feed-algorithm', [\App\Http\Controllers\Api\AdminConfigController::class, 'getFeedAlgorithmConfig']);
Route::put('/v2/admin/config/feed-algorithm', [\App\Http\Controllers\Api\AdminConfigController::class, 'updateFeedAlgorithmConfig']);
Route::get('/v2/admin/config/algorithms', [\App\Http\Controllers\Api\AdminConfigController::class, 'getAlgorithmConfig']);
Route::put('/v2/admin/config/algorithm/{area}', [\App\Http\Controllers\Api\AdminConfigController::class, 'updateAlgorithmConfig']);
Route::get('/v2/admin/config/algorithm-health', [\App\Http\Controllers\Api\AdminConfigController::class, 'getAlgorithmHealth']);
Route::get('/v2/admin/config/images', [\App\Http\Controllers\Api\AdminConfigController::class, 'getImageConfig']);
Route::put('/v2/admin/config/images', [\App\Http\Controllers\Api\AdminConfigController::class, 'updateImageConfig']);
Route::get('/v2/admin/config/seo', [\App\Http\Controllers\Api\AdminConfigController::class, 'getSeoConfig']);
Route::put('/v2/admin/config/seo', [\App\Http\Controllers\Api\AdminConfigController::class, 'updateSeoConfig']);
Route::get('/v2/admin/config/sitemap-stats', [\App\Http\Controllers\Api\AdminConfigController::class, 'getSitemapStats']);
Route::post('/v2/admin/config/sitemap-clear-cache', [\App\Http\Controllers\Api\AdminConfigController::class, 'clearSitemapCache']);
Route::get('/v2/admin/config/languages', [\App\Http\Controllers\Api\AdminConfigController::class, 'getLanguageConfig']);
Route::put('/v2/admin/config/languages', [\App\Http\Controllers\Api\AdminConfigController::class, 'updateLanguageConfig']);
Route::get('/v2/admin/config/native-app', [\App\Http\Controllers\Api\AdminConfigController::class, 'getNativeAppConfig']);
Route::put('/v2/admin/config/native-app', [\App\Http\Controllers\Api\AdminConfigController::class, 'updateNativeAppConfig']);
// ── Admin: Landing Page Configuration ──────────────────────────────────────
Route::get('/v2/admin/config/landing-page', [\App\Http\Controllers\Api\AdminConfigController::class, 'getLandingPageConfig']);
Route::put('/v2/admin/config/landing-page', [\App\Http\Controllers\Api\AdminConfigController::class, 'updateLandingPageConfig']);
// ── Admin: Onboarding Module Configuration ─────────────────────────────────
Route::get('/v2/admin/config/onboarding', [\App\Http\Controllers\Api\AdminOnboardingConfigController::class, 'getConfig']);
Route::put('/v2/admin/config/onboarding', [\App\Http\Controllers\Api\AdminOnboardingConfigController::class, 'updateConfig']);
Route::get('/v2/admin/config/onboarding/presets', [\App\Http\Controllers\Api\AdminOnboardingConfigController::class, 'getPresets']);
Route::post('/v2/admin/config/onboarding/apply-preset', [\App\Http\Controllers\Api\AdminOnboardingConfigController::class, 'applyPreset']);

// ── Admin: Safeguarding Options CRUD ────────────────────────────────────────
Route::get('/v2/admin/safeguarding/options', [\App\Http\Controllers\Api\AdminSafeguardingOptionsController::class, 'index']);
Route::post('/v2/admin/safeguarding/options', [\App\Http\Controllers\Api\AdminSafeguardingOptionsController::class, 'store']);
Route::put('/v2/admin/safeguarding/options/reorder', [\App\Http\Controllers\Api\AdminSafeguardingOptionsController::class, 'reorder']);
Route::put('/v2/admin/safeguarding/options/{id}', [\App\Http\Controllers\Api\AdminSafeguardingOptionsController::class, 'update']);
Route::delete('/v2/admin/safeguarding/options/{id}', [\App\Http\Controllers\Api\AdminSafeguardingOptionsController::class, 'destroy']);

Route::get('/v2/admin/system/cron-jobs', [\App\Http\Controllers\Api\AdminConfigController::class, 'getCronJobs']);
Route::post('/v2/admin/system/cron-jobs/{id}/run', [\App\Http\Controllers\Api\AdminConfigController::class, 'runCronJob']);
Route::get('/v2/admin/system/cron-jobs/logs', [\App\Http\Controllers\Api\AdminCronController::class, 'getLogs']);
Route::get('/v2/admin/system/cron-jobs/logs/{id}', [\App\Http\Controllers\Api\AdminCronController::class, 'getLogDetail']);
Route::delete('/v2/admin/system/cron-jobs/logs', [\App\Http\Controllers\Api\AdminCronController::class, 'clearLogs']);
Route::get('/v2/admin/system/cron-jobs/settings', [\App\Http\Controllers\Api\AdminCronController::class, 'getGlobalSettings']);
Route::put('/v2/admin/system/cron-jobs/settings', [\App\Http\Controllers\Api\AdminCronController::class, 'updateGlobalSettings']);
Route::get('/v2/admin/system/cron-jobs/health', [\App\Http\Controllers\Api\AdminCronController::class, 'getHealthMetrics']);
Route::get('/v2/admin/system/cron-jobs/{jobId}/settings', [\App\Http\Controllers\Api\AdminCronController::class, 'getJobSettings']);
Route::put('/v2/admin/system/cron-jobs/{jobId}/settings', [\App\Http\Controllers\Api\AdminCronController::class, 'updateJobSettings']);
Route::get('/v2/admin/system/activity-log', [\App\Http\Controllers\Api\AdminDashboardController::class, 'activity']);
Route::get('/v2/admin/email/status', [\App\Http\Controllers\Api\AdminEmailController::class, 'status']);
Route::post('/v2/admin/email/test', [\App\Http\Controllers\Api\AdminEmailController::class, 'test']);
Route::post('/v2/admin/email/test-gmail', [\App\Http\Controllers\Api\AdminEmailController::class, 'testGmail']);
Route::get('/v2/admin/email/config', [\App\Http\Controllers\Api\AdminEmailController::class, 'getConfig']);
Route::put('/v2/admin/email/config', [\App\Http\Controllers\Api\AdminEmailController::class, 'updateConfig']);
Route::post('/v2/admin/email/test-provider', [\App\Http\Controllers\Api\AdminEmailController::class, 'testProvider']);
Route::get('/v2/admin/matching/config', [\App\Http\Controllers\Api\AdminMatchingController::class, 'getConfig']);
Route::put('/v2/admin/matching/config', [\App\Http\Controllers\Api\AdminMatchingController::class, 'updateConfig']);
Route::post('/v2/admin/matching/cache/clear', [\App\Http\Controllers\Api\AdminMatchingController::class, 'clearCache']);
Route::get('/v2/admin/matching/stats', [\App\Http\Controllers\Api\AdminMatchingController::class, 'getStats']);
Route::get('/v2/admin/matching/approvals', [\App\Http\Controllers\Api\AdminMatchingController::class, 'index']);
Route::get('/v2/admin/matching/approvals/stats', [\App\Http\Controllers\Api\AdminMatchingController::class, 'approvalStats']);
Route::get('/v2/admin/matching/approvals/{id}', [\App\Http\Controllers\Api\AdminMatchingController::class, 'show']);
Route::post('/v2/admin/matching/approvals/{id}/approve', [\App\Http\Controllers\Api\AdminMatchingController::class, 'approve']);
Route::post('/v2/admin/matching/approvals/{id}/reject', [\App\Http\Controllers\Api\AdminMatchingController::class, 'reject']);
Route::get('/v2/admin/help/faqs', [\App\Http\Controllers\Api\HelpController::class, 'adminGetFaqs']);
Route::post('/v2/admin/help/faqs', [\App\Http\Controllers\Api\HelpController::class, 'adminCreateFaq']);
Route::put('/v2/admin/help/faqs/{id}', [\App\Http\Controllers\Api\HelpController::class, 'adminUpdateFaq']);
Route::delete('/v2/admin/help/faqs/{id}', [\App\Http\Controllers\Api\HelpController::class, 'adminDeleteFaq']);
Route::get('/v2/admin/blog', [\App\Http\Controllers\Api\AdminBlogController::class, 'index']);
Route::post('/v2/admin/blog', [\App\Http\Controllers\Api\AdminBlogController::class, 'store']);
Route::get('/v2/admin/blog/{id}', [\App\Http\Controllers\Api\AdminBlogController::class, 'show']);
Route::put('/v2/admin/blog/{id}', [\App\Http\Controllers\Api\AdminBlogController::class, 'update']);
Route::delete('/v2/admin/blog/{id}', [\App\Http\Controllers\Api\AdminBlogController::class, 'destroy']);
Route::post('/v2/admin/blog/{id}/toggle-status', [\App\Http\Controllers\Api\AdminBlogController::class, 'toggleStatus']);
Route::get('/v2/admin/feed/posts', [\App\Http\Controllers\Api\AdminFeedController::class, 'index']);
Route::get('/v2/admin/feed/posts/{id}', [\App\Http\Controllers\Api\AdminFeedController::class, 'show']);
Route::post('/v2/admin/feed/posts/{id}/hide', [\App\Http\Controllers\Api\AdminFeedController::class, 'hide']);
Route::delete('/v2/admin/feed/posts/{id}', [\App\Http\Controllers\Api\AdminFeedController::class, 'destroy']);
Route::get('/v2/admin/feed/stats', [\App\Http\Controllers\Api\AdminFeedController::class, 'stats']);
Route::get('/v2/admin/comments', [\App\Http\Controllers\Api\AdminCommentsController::class, 'index']);
Route::get('/v2/admin/comments/{id}', [\App\Http\Controllers\Api\AdminCommentsController::class, 'show']);
Route::post('/v2/admin/comments/{id}/hide', [\App\Http\Controllers\Api\AdminCommentsController::class, 'hide']);
Route::delete('/v2/admin/comments/{id}', [\App\Http\Controllers\Api\AdminCommentsController::class, 'destroy']);
Route::get('/v2/admin/reviews', [\App\Http\Controllers\Api\AdminReviewsController::class, 'index']);
Route::get('/v2/admin/reviews/{id}', [\App\Http\Controllers\Api\AdminReviewsController::class, 'show']);
Route::post('/v2/admin/reviews/{id}/flag', [\App\Http\Controllers\Api\AdminReviewsController::class, 'flag']);
Route::post('/v2/admin/reviews/{id}/hide', [\App\Http\Controllers\Api\AdminReviewsController::class, 'hide']);
Route::delete('/v2/admin/reviews/{id}', [\App\Http\Controllers\Api\AdminReviewsController::class, 'destroy']);
Route::get('/v2/admin/reports', [\App\Http\Controllers\Api\AdminReportsController::class, 'index']);
Route::get('/v2/admin/reports/stats', [\App\Http\Controllers\Api\AdminReportsController::class, 'stats']);
Route::get('/v2/admin/reports/social-value', [\App\Http\Controllers\Api\AdminAnalyticsReportsController::class, 'socialValue']);
Route::put('/v2/admin/reports/social-value/config', [\App\Http\Controllers\Api\AdminAnalyticsReportsController::class, 'updateSocialValueConfig']);
Route::get('/v2/admin/reports/members', [\App\Http\Controllers\Api\AdminAnalyticsReportsController::class, 'memberReports']);
Route::get('/v2/admin/reports/hours', [\App\Http\Controllers\Api\AdminAnalyticsReportsController::class, 'hoursReports']);
Route::get('/v2/admin/reports/export-types', [\App\Http\Controllers\Api\AdminAnalyticsReportsController::class, 'exportTypes']);
Route::get('/v2/admin/reports/{type}/export', [\App\Http\Controllers\Api\AdminAnalyticsReportsController::class, 'exportReport']);
Route::get('/v2/admin/reports/{id}', [\App\Http\Controllers\Api\AdminReportsController::class, 'show']);
Route::post('/v2/admin/reports/{id}/resolve', [\App\Http\Controllers\Api\AdminReportsController::class, 'resolve']);
Route::post('/v2/admin/reports/{id}/dismiss', [\App\Http\Controllers\Api\AdminReportsController::class, 'dismiss']);
Route::get('/v2/admin/gamification/stats', [\App\Http\Controllers\Api\AdminGamificationController::class, 'stats']);
Route::get('/v2/admin/gamification/badges', [\App\Http\Controllers\Api\AdminGamificationController::class, 'badges']);
Route::post('/v2/admin/gamification/badges', [\App\Http\Controllers\Api\AdminGamificationController::class, 'createBadge']);
Route::delete('/v2/admin/gamification/badges/{id}', [\App\Http\Controllers\Api\AdminGamificationController::class, 'deleteBadge']);
Route::get('/v2/admin/gamification/campaigns', [\App\Http\Controllers\Api\AdminGamificationController::class, 'campaigns']);
Route::post('/v2/admin/gamification/campaigns', [\App\Http\Controllers\Api\AdminGamificationController::class, 'createCampaign']);
Route::put('/v2/admin/gamification/campaigns/{id}', [\App\Http\Controllers\Api\AdminGamificationController::class, 'updateCampaign']);
Route::delete('/v2/admin/gamification/campaigns/{id}', [\App\Http\Controllers\Api\AdminGamificationController::class, 'deleteCampaign']);
Route::post('/v2/admin/gamification/recheck-all', [\App\Http\Controllers\Api\AdminGamificationController::class, 'recheckAll']);
Route::post('/v2/admin/gamification/bulk-award', [\App\Http\Controllers\Api\AdminGamificationController::class, 'bulkAward']);
Route::get('/v2/admin/gamification/badge-config', [\App\Http\Controllers\Api\AdminGamificationController::class, 'getBadgeConfig']);
Route::put('/v2/admin/gamification/badge-config/{badgeKey}', [\App\Http\Controllers\Api\AdminGamificationController::class, 'updateBadgeConfig']);
Route::post('/v2/admin/gamification/badge-config/{badgeKey}/reset', [\App\Http\Controllers\Api\AdminGamificationController::class, 'resetBadgeConfig']);
Route::get('/v2/admin/groups', [\App\Http\Controllers\Api\AdminGroupsController::class, 'index']);
Route::get('/v2/admin/groups/analytics', [\App\Http\Controllers\Api\AdminGroupsController::class, 'analytics']);
Route::get('/v2/admin/groups/approvals', [\App\Http\Controllers\Api\AdminGroupsController::class, 'approvals']);
Route::post('/v2/admin/groups/approvals/{id}/approve', [\App\Http\Controllers\Api\AdminGroupsController::class, 'approveMember']);
Route::post('/v2/admin/groups/approvals/{id}/reject', [\App\Http\Controllers\Api\AdminGroupsController::class, 'rejectMember']);
Route::get('/v2/admin/groups/moderation', [\App\Http\Controllers\Api\AdminGroupsController::class, 'moderation']);
Route::get('/v2/admin/groups/types', [\App\Http\Controllers\Api\AdminGroupsController::class, 'getGroupTypes']);
Route::post('/v2/admin/groups/types', [\App\Http\Controllers\Api\AdminGroupsController::class, 'createGroupType']);
Route::put('/v2/admin/groups/types/{id}', [\App\Http\Controllers\Api\AdminGroupsController::class, 'updateGroupType']);
Route::delete('/v2/admin/groups/types/{id}', [\App\Http\Controllers\Api\AdminGroupsController::class, 'deleteGroupType']);
Route::get('/v2/admin/groups/types/{id}/policies', [\App\Http\Controllers\Api\AdminGroupsController::class, 'getPolicies']);
Route::put('/v2/admin/groups/types/{id}/policies', [\App\Http\Controllers\Api\AdminGroupsController::class, 'setPolicy']);
Route::post('/v2/admin/groups/batch-geocode', [\App\Http\Controllers\Api\AdminGroupsController::class, 'batchGeocode']);
Route::get('/v2/admin/groups/recommendations', [\App\Http\Controllers\Api\AdminGroupsController::class, 'getRecommendationData']);
Route::get('/v2/admin/groups/featured', [\App\Http\Controllers\Api\AdminGroupsController::class, 'getFeaturedGroups']);
Route::post('/v2/admin/groups/featured/update', [\App\Http\Controllers\Api\AdminGroupsController::class, 'updateFeaturedGroups']);
Route::put('/v2/admin/groups/{id}/status', [\App\Http\Controllers\Api\AdminGroupsController::class, 'updateStatus']);
Route::delete('/v2/admin/groups/{id}', [\App\Http\Controllers\Api\AdminGroupsController::class, 'deleteGroup']);
Route::get('/v2/admin/groups/{id}', [\App\Http\Controllers\Api\AdminGroupsController::class, 'getGroup']);
Route::put('/v2/admin/groups/{id}', [\App\Http\Controllers\Api\AdminGroupsController::class, 'updateGroup']);
Route::put('/v2/admin/groups/{id}/toggle-featured', [\App\Http\Controllers\Api\AdminGroupsController::class, 'toggleFeatured']);
Route::post('/v2/admin/groups/{id}/geocode', [\App\Http\Controllers\Api\AdminGroupsController::class, 'geocodeGroup']);
Route::get('/v2/admin/groups/{groupId}/members', [\App\Http\Controllers\Api\AdminGroupsController::class, 'getMembers']);
Route::post('/v2/admin/groups/{groupId}/members/{userId}/promote', [\App\Http\Controllers\Api\AdminGroupsController::class, 'promoteMember']);
Route::post('/v2/admin/groups/{groupId}/members/{userId}/demote', [\App\Http\Controllers\Api\AdminGroupsController::class, 'demoteMember']);
Route::delete('/v2/admin/groups/{groupId}/members/{userId}', [\App\Http\Controllers\Api\AdminGroupsController::class, 'kickMember']);
Route::post('/v2/admin/groups/bulk-archive', [\App\Http\Controllers\Api\AdminGroupsController::class, 'bulkArchive']);
Route::post('/v2/admin/groups/bulk-unarchive', [\App\Http\Controllers\Api\AdminGroupsController::class, 'bulkUnarchive']);
Route::post('/v2/admin/groups/{id}/archive', [\App\Http\Controllers\Api\AdminGroupsController::class, 'archiveGroup']);
Route::post('/v2/admin/groups/{id}/unarchive', [\App\Http\Controllers\Api\AdminGroupsController::class, 'unarchiveGroup']);
Route::post('/v2/admin/groups/{id}/transfer-ownership', [\App\Http\Controllers\Api\AdminGroupsController::class, 'transferOwnership']);
Route::post('/v2/admin/groups/{id}/merge', [\App\Http\Controllers\Api\AdminGroupsController::class, 'mergeGroup']);
Route::post('/v2/admin/groups/{id}/clone', [\App\Http\Controllers\Api\AdminGroupsController::class, 'cloneGroup']);
Route::get('/v2/admin/groups/{id}/audit-log', [\App\Http\Controllers\Api\AdminGroupsController::class, 'auditLog']);
Route::get('/v2/admin/group-tags', [\App\Http\Controllers\Api\AdminGroupsController::class, 'listTags']);
Route::post('/v2/admin/group-tags', [\App\Http\Controllers\Api\AdminGroupsController::class, 'createTag']);
Route::delete('/v2/admin/group-tags/{tagId}', [\App\Http\Controllers\Api\AdminGroupsController::class, 'deleteTag']);
Route::get('/v2/admin/group-collections', [\App\Http\Controllers\Api\AdminGroupsController::class, 'listCollections']);
Route::post('/v2/admin/group-collections', [\App\Http\Controllers\Api\AdminGroupsController::class, 'createCollection']);
Route::put('/v2/admin/group-collections/{id}', [\App\Http\Controllers\Api\AdminGroupsController::class, 'updateCollection']);
Route::delete('/v2/admin/group-collections/{id}', [\App\Http\Controllers\Api\AdminGroupsController::class, 'deleteCollection']);
Route::put('/v2/admin/group-collections/{id}/groups', [\App\Http\Controllers\Api\AdminGroupsController::class, 'setCollectionGroups']);
Route::get('/v2/admin/group-auto-assign-rules', [\App\Http\Controllers\Api\AdminGroupsController::class, 'listAutoAssignRules']);
Route::post('/v2/admin/group-auto-assign-rules', [\App\Http\Controllers\Api\AdminGroupsController::class, 'createAutoAssignRule']);
Route::delete('/v2/admin/group-auto-assign-rules/{id}', [\App\Http\Controllers\Api\AdminGroupsController::class, 'deleteAutoAssignRule']);
Route::get('/v2/admin/timebanking/stats', [\App\Http\Controllers\Api\AdminTimebankingController::class, 'stats']);
Route::get('/v2/admin/timebanking/alerts', [\App\Http\Controllers\Api\AdminTimebankingController::class, 'alerts']);
Route::put('/v2/admin/timebanking/alerts/{id}', [\App\Http\Controllers\Api\AdminTimebankingController::class, 'updateAlert']);
Route::post('/v2/admin/timebanking/adjust-balance', [\App\Http\Controllers\Api\AdminTimebankingController::class, 'adjustBalance']);
Route::get('/v2/admin/timebanking/org-wallets', [\App\Http\Controllers\Api\AdminTimebankingController::class, 'orgWallets']);
Route::get('/v2/admin/timebanking/user-report', [\App\Http\Controllers\Api\AdminTimebankingController::class, 'userReport']);
Route::get('/v2/admin/timebanking/user-statement', [\App\Http\Controllers\Api\AdminTimebankingController::class, 'userStatement']);
Route::get('/v2/admin/wallet/grants', [\App\Http\Controllers\Api\AdminWalletGrantController::class, 'index']);
Route::post('/v2/admin/wallet/grant', [\App\Http\Controllers\Api\AdminWalletGrantController::class, 'store']);
Route::get('/v2/admin/enterprise/dashboard', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'dashboard']);
Route::get('/v2/admin/enterprise/roles', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'roles']);
Route::post('/v2/admin/enterprise/roles', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'createRole']);
Route::get('/v2/admin/enterprise/roles/{id}', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'showRole']);
Route::put('/v2/admin/enterprise/roles/{id}', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'updateRole']);
Route::delete('/v2/admin/enterprise/roles/{id}', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'deleteRole']);
Route::get('/v2/admin/enterprise/permissions', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'permissions']);
Route::get('/v2/admin/enterprise/gdpr/dashboard', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'gdprDashboard']);
Route::get('/v2/admin/enterprise/gdpr/requests', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'gdprRequests']);
Route::put('/v2/admin/enterprise/gdpr/requests/{id}', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'updateGdprRequest']);
Route::get('/v2/admin/enterprise/gdpr/consents', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'gdprConsents']);
Route::get('/v2/admin/enterprise/gdpr/breaches', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'gdprBreaches']);
Route::post('/v2/admin/enterprise/gdpr/breaches', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'createBreach']);
Route::get('/v2/admin/enterprise/gdpr/audit/export', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'gdprAuditExport']);
Route::get('/v2/admin/enterprise/gdpr/audit', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'gdprAudit']);
Route::get('/v2/admin/enterprise/monitoring', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'monitoring']);
Route::get('/v2/admin/enterprise/monitoring/health', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'healthCheck']);
Route::get('/v2/admin/enterprise/monitoring/logs', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'logs']);
Route::get('/v2/admin/enterprise/config', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'config']);
Route::put('/v2/admin/enterprise/config', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'updateConfig']);
Route::post('/v2/admin/enterprise/config/reset', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'resetConfig']);
Route::get('/v2/admin/enterprise/config/secrets', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'secrets']);

// Enterprise GDPR — extended endpoints
Route::get('/v2/admin/enterprise/gdpr/statistics', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'gdprStatistics']);
Route::get('/v2/admin/enterprise/gdpr/trends', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'gdprTrends']);
Route::post('/v2/admin/enterprise/gdpr/requests', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'createGdprRequest']);
Route::get('/v2/admin/enterprise/gdpr/requests/{id}', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'showGdprRequest']);
Route::put('/v2/admin/enterprise/gdpr/requests/{id}/assign', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'assignGdprRequest']);
Route::post('/v2/admin/enterprise/gdpr/requests/{id}/notes', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'addGdprRequestNote']);
Route::post('/v2/admin/enterprise/gdpr/requests/{id}/export', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'generateGdprExport']);

// Enterprise GDPR — consent type management
Route::get('/v2/admin/enterprise/gdpr/consent-types', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'consentTypes']);
Route::post('/v2/admin/enterprise/gdpr/consent-types', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'createConsentType']);
Route::put('/v2/admin/enterprise/gdpr/consent-types/{id}', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'updateConsentType']);
Route::delete('/v2/admin/enterprise/gdpr/consent-types/{id}', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'deleteConsentType']);
Route::get('/v2/admin/enterprise/gdpr/consent-types/{slug}/users', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'consentTypeUsers']);
Route::get('/v2/admin/enterprise/gdpr/consent-types/{slug}/export', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'exportConsentTypeUsers']);

// Enterprise GDPR — breach detail & DPA notification
Route::get('/v2/admin/enterprise/gdpr/breaches/{id}', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'showBreach']);
Route::put('/v2/admin/enterprise/gdpr/breaches/{id}', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'updateBreach']);
Route::post('/v2/admin/enterprise/gdpr/breaches/{id}/notify-dpa', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'notifyDpa']);

// Enterprise monitoring — log files, requirements, health history
Route::get('/v2/admin/enterprise/monitoring/log-files', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'logFiles']);
Route::get('/v2/admin/enterprise/monitoring/log-files/{filename}', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'viewLogFile']);
Route::delete('/v2/admin/enterprise/monitoring/log-files/{filename}', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'clearLogFile']);
Route::get('/v2/admin/enterprise/monitoring/requirements', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'systemRequirements']);
Route::get('/v2/admin/enterprise/monitoring/health-history', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'healthCheckHistory']);

// Enterprise config — feature flags & secrets management
Route::get('/v2/admin/enterprise/config/features', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'featureFlags']);
Route::patch('/v2/admin/enterprise/config/features', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'updateFeatureFlag']);
Route::post('/v2/admin/enterprise/config/secrets/{key}/rotate', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'rotateSecret']);
Route::delete('/v2/admin/enterprise/config/secrets/{key}', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'deleteSecret']);
Route::post('/v2/admin/enterprise/config/secrets/test-vault', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'testVaultConnection']);

Route::get('/v2/admin/legal-documents', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'legalDocs']);
Route::post('/v2/admin/legal-documents', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'createLegalDoc']);
Route::get('/v2/admin/legal-documents/compliance', [\App\Http\Controllers\Api\AdminLegalDocController::class, 'getComplianceStats']);
Route::get('/v2/admin/legal-documents/{id}', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'showLegalDoc']);
Route::put('/v2/admin/legal-documents/{id}', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'updateLegalDoc']);
Route::delete('/v2/admin/legal-documents/{id}', [\App\Http\Controllers\Api\AdminEnterpriseController::class, 'deleteLegalDoc']);
Route::get('/v2/admin/legal-documents/{docId}/versions', [\App\Http\Controllers\Api\AdminLegalDocController::class, 'getVersions']);
Route::get('/v2/admin/legal-documents/{docId}/versions/compare', [\App\Http\Controllers\Api\AdminLegalDocController::class, 'compareVersions']);
Route::post('/v2/admin/legal-documents/{docId}/versions', [\App\Http\Controllers\Api\AdminLegalDocController::class, 'createVersion']);
Route::put('/v2/admin/legal-documents/{docId}/versions/{versionId}', [\App\Http\Controllers\Api\AdminLegalDocController::class, 'updateVersion']);
Route::delete('/v2/admin/legal-documents/{docId}/versions/{versionId}', [\App\Http\Controllers\Api\AdminLegalDocController::class, 'deleteVersion']);
Route::post('/v2/admin/legal-documents/versions/{versionId}/publish', [\App\Http\Controllers\Api\AdminLegalDocController::class, 'publishVersion']);
Route::get('/v2/admin/legal-documents/versions/{versionId}/acceptances', [\App\Http\Controllers\Api\AdminLegalDocController::class, 'getAcceptances']);
Route::get('/v2/admin/legal-documents/{docId}/acceptances/export', [\App\Http\Controllers\Api\AdminLegalDocController::class, 'exportAcceptances']);
Route::post('/v2/admin/legal-documents/{docId}/versions/{versionId}/notify', [\App\Http\Controllers\Api\AdminLegalDocController::class, 'notifyUsers']);
Route::get('/v2/admin/legal-documents/{docId}/versions/{versionId}/pending-count', [\App\Http\Controllers\Api\AdminLegalDocController::class, 'getUsersPendingCount']);
Route::get('/v2/admin/broker/dashboard', [\App\Http\Controllers\Api\AdminBrokerController::class, 'dashboard']);
Route::get('/v2/admin/broker/exchanges', [\App\Http\Controllers\Api\AdminBrokerController::class, 'exchanges']);
Route::post('/v2/admin/broker/exchanges/{id}/approve', [\App\Http\Controllers\Api\AdminBrokerController::class, 'approveExchange']);
Route::post('/v2/admin/broker/exchanges/{id}/reject', [\App\Http\Controllers\Api\AdminBrokerController::class, 'rejectExchange']);
Route::get('/v2/admin/broker/risk-tags', [\App\Http\Controllers\Api\AdminBrokerController::class, 'riskTags']);
Route::get('/v2/admin/broker/messages', [\App\Http\Controllers\Api\AdminBrokerController::class, 'messages']);
Route::get('/v2/admin/broker/messages/unreviewed-count', [\App\Http\Controllers\Api\AdminBrokerController::class, 'unreviewedCount']);
Route::post('/v2/admin/broker/messages/{id}/review', [\App\Http\Controllers\Api\AdminBrokerController::class, 'reviewMessage']);
Route::get('/v2/admin/broker/monitoring', [\App\Http\Controllers\Api\AdminBrokerController::class, 'monitoring']);
Route::post('/v2/admin/broker/messages/{id}/flag', [\App\Http\Controllers\Api\AdminBrokerController::class, 'flagMessage']);
Route::post('/v2/admin/broker/monitoring/{userId}', [\App\Http\Controllers\Api\AdminBrokerController::class, 'setMonitoring']);
Route::post('/v2/admin/broker/risk-tags/{listingId}', [\App\Http\Controllers\Api\AdminBrokerController::class, 'saveRiskTag']);
Route::delete('/v2/admin/broker/risk-tags/{listingId}', [\App\Http\Controllers\Api\AdminBrokerController::class, 'removeRiskTag']);
Route::get('/v2/admin/broker/configuration', [\App\Http\Controllers\Api\AdminBrokerController::class, 'getConfiguration']);
Route::post('/v2/admin/broker/configuration', [\App\Http\Controllers\Api\AdminBrokerController::class, 'saveConfiguration']);
Route::get('/v2/admin/broker/exchanges/{id}', [\App\Http\Controllers\Api\AdminBrokerController::class, 'showExchange']);
Route::get('/v2/admin/broker/messages/{id}', [\App\Http\Controllers\Api\AdminBrokerController::class, 'showMessage']);
Route::post('/v2/admin/broker/messages/{id}/approve', [\App\Http\Controllers\Api\AdminBrokerController::class, 'approveMessage']);
Route::get('/v2/admin/broker/archives', [\App\Http\Controllers\Api\AdminBrokerController::class, 'archives']);
Route::get('/v2/admin/broker/archives/{id}', [\App\Http\Controllers\Api\AdminBrokerController::class, 'showArchive']);
Route::get('/v2/admin/vetting/stats', [\App\Http\Controllers\Api\AdminVettingController::class, 'stats']);
Route::get('/v2/admin/vetting/user/{userId}', [\App\Http\Controllers\Api\AdminVettingController::class, 'getUserRecords']);
Route::get('/v2/admin/vetting', [\App\Http\Controllers\Api\AdminVettingController::class, 'list']);
Route::get('/v2/admin/vetting/{id}', [\App\Http\Controllers\Api\AdminVettingController::class, 'show']);
Route::post('/v2/admin/vetting/bulk', [\App\Http\Controllers\Api\AdminVettingController::class, 'bulk']);
Route::post('/v2/admin/vetting', [\App\Http\Controllers\Api\AdminVettingController::class, 'store']);
Route::put('/v2/admin/vetting/{id}', [\App\Http\Controllers\Api\AdminVettingController::class, 'update']);
Route::post('/v2/admin/vetting/{id}/verify', [\App\Http\Controllers\Api\AdminVettingController::class, 'verify']);
Route::post('/v2/admin/vetting/{id}/reject', [\App\Http\Controllers\Api\AdminVettingController::class, 'reject']);
Route::delete('/v2/admin/vetting/{id}', [\App\Http\Controllers\Api\AdminVettingController::class, 'destroy']);
Route::post('/v2/admin/vetting/{id}/upload', [\App\Http\Controllers\Api\AdminVettingController::class, 'uploadDocument']);
Route::get('/v2/admin/insurance/stats', [\App\Http\Controllers\Api\AdminInsuranceCertificateController::class, 'stats']);
Route::get('/v2/admin/insurance/user/{userId}', [\App\Http\Controllers\Api\AdminInsuranceCertificateController::class, 'getUserCertificates']);
Route::get('/v2/admin/insurance', [\App\Http\Controllers\Api\AdminInsuranceCertificateController::class, 'list']);
Route::get('/v2/admin/insurance/{id}', [\App\Http\Controllers\Api\AdminInsuranceCertificateController::class, 'show']);
Route::post('/v2/admin/insurance', [\App\Http\Controllers\Api\AdminInsuranceCertificateController::class, 'store']);
Route::put('/v2/admin/insurance/{id}', [\App\Http\Controllers\Api\AdminInsuranceCertificateController::class, 'update']);
Route::post('/v2/admin/insurance/{id}/verify', [\App\Http\Controllers\Api\AdminInsuranceCertificateController::class, 'verify']);
Route::post('/v2/admin/insurance/{id}/reject', [\App\Http\Controllers\Api\AdminInsuranceCertificateController::class, 'reject']);
Route::delete('/v2/admin/insurance/{id}', [\App\Http\Controllers\Api\AdminInsuranceCertificateController::class, 'destroy']);
Route::get('/v2/admin/newsletters', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'index']);
Route::post('/v2/admin/newsletters', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'store']);
Route::get('/v2/admin/newsletters/subscribers', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'subscribers']);
Route::post('/v2/admin/newsletters/subscribers', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'addSubscriber']);
Route::post('/v2/admin/newsletters/subscribers/import', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'importSubscribers']);
Route::get('/v2/admin/newsletters/subscribers/export', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'exportSubscribers']);
Route::post('/v2/admin/newsletters/subscribers/sync', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'syncPlatformMembers']);
Route::delete('/v2/admin/newsletters/subscribers/{id}', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'removeSubscriber']);
Route::get('/v2/admin/newsletters/segments', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'segments']);
Route::post('/v2/admin/newsletters/segments', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'storeSegment']);
Route::post('/v2/admin/newsletters/segments/preview', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'previewSegment']);
Route::get('/v2/admin/newsletters/segments/suggestions', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'getSegmentSuggestions']);
Route::get('/v2/admin/newsletters/segments/{id}', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'showSegment']);
Route::put('/v2/admin/newsletters/segments/{id}', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'updateSegment']);
Route::delete('/v2/admin/newsletters/segments/{id}', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'destroySegment']);
Route::get('/v2/admin/newsletters/templates', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'templates']);
Route::post('/v2/admin/newsletters/templates', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'storeTemplate']);
Route::get('/v2/admin/newsletters/templates/{id}', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'showTemplate']);
Route::put('/v2/admin/newsletters/templates/{id}', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'updateTemplate']);
Route::delete('/v2/admin/newsletters/templates/{id}', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'destroyTemplate']);
Route::post('/v2/admin/newsletters/templates/{id}/duplicate', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'duplicateTemplate']);
Route::get('/v2/admin/newsletters/templates/{id}/preview', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'previewTemplate']);
Route::get('/v2/admin/newsletters/analytics', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'analytics']);
Route::get('/v2/admin/newsletters/bounces', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'getBounces']);
Route::get('/v2/admin/newsletters/suppression-list', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'getSuppressionList']);
Route::post('/v2/admin/newsletters/suppression-list/{email}/unsuppress', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'unsuppress']);
Route::post('/v2/admin/newsletters/suppression-list/{email}/suppress', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'suppress']);
Route::get('/v2/admin/newsletters/send-time-optimizer', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'getSendTimeData']);
Route::get('/v2/admin/newsletters/diagnostics', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'getDiagnostics']);
Route::get('/v2/admin/newsletters/bounce-trends', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'getBounceTrends']);
Route::post('/v2/admin/newsletters/recipient-count', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'recipientCount']);
Route::get('/v2/admin/newsletters/{id}', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'show']);
Route::get('/v2/admin/newsletters/{id}/resend-info', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'getResendInfo']);
Route::post('/v2/admin/newsletters/{id}/resend', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'resend']);
Route::post('/v2/admin/newsletters/{id}/send', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'sendNewsletter']);
Route::post('/v2/admin/newsletters/{id}/send-test', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'sendTest']);
Route::post('/v2/admin/newsletters/{id}/duplicate', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'duplicateNewsletter']);
Route::get('/v2/admin/newsletters/{id}/activity', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'activity']);
Route::get('/v2/admin/newsletters/{id}/openers', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'openers']);
Route::get('/v2/admin/newsletters/{id}/clickers', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'clickers']);
Route::get('/v2/admin/newsletters/{id}/non-openers', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'nonOpeners']);
Route::get('/v2/admin/newsletters/{id}/openers-no-click', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'openersNoClick']);
Route::get('/v2/admin/newsletters/{id}/email-clients', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'emailClients']);
Route::get('/v2/admin/newsletters/{id}/stats', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'stats']);
Route::post('/v2/admin/newsletters/{id}/ab-winner', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'selectAbWinner']);
Route::put('/v2/admin/newsletters/{id}', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'update']);
Route::delete('/v2/admin/newsletters/{id}', [\App\Http\Controllers\Api\AdminNewsletterController::class, 'destroy']);
Route::get('/v2/admin/volunteering', [\App\Http\Controllers\Api\AdminVolunteerController::class, 'index']);
Route::get('/v2/admin/volunteering/approvals', [\App\Http\Controllers\Api\AdminVolunteerController::class, 'approvals']);
Route::get('/v2/admin/volunteering/organizations', [\App\Http\Controllers\Api\AdminVolunteerController::class, 'organizations']);
Route::post('/v2/admin/volunteering/approvals/{id}/approve', [\App\Http\Controllers\Api\AdminVolunteerController::class, 'approveApplication']);
Route::post('/v2/admin/volunteering/approvals/{id}/decline', [\App\Http\Controllers\Api\AdminVolunteerController::class, 'declineApplication']);
Route::post('/v2/admin/volunteering/send-shift-reminders', [\App\Http\Controllers\Api\AdminVolunteerController::class, 'sendShiftReminders']);
Route::get('/v2/admin/volunteering/expenses', [\App\Http\Controllers\Api\VolunteerExpenseController::class, 'adminExpenses']);
Route::put('/v2/admin/volunteering/expenses/{id}', [\App\Http\Controllers\Api\VolunteerExpenseController::class, 'reviewExpense']);
Route::get('/v2/admin/volunteering/expenses/export', [\App\Http\Controllers\Api\VolunteerExpenseController::class, 'exportExpenses']);
Route::get('/v2/admin/volunteering/expenses/policies', [\App\Http\Controllers\Api\VolunteerExpenseController::class, 'getExpensePolicies']);
Route::put('/v2/admin/volunteering/expenses/policies', [\App\Http\Controllers\Api\VolunteerExpenseController::class, 'updateExpensePolicy']);
Route::get('/v2/admin/volunteering/guardian-consents', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'adminGuardianConsents']);
Route::get('/v2/admin/volunteering/training', [\App\Http\Controllers\Api\VolunteerWellbeingController::class, 'adminTraining']);
Route::put('/v2/admin/volunteering/training/{id}/verify', [\App\Http\Controllers\Api\VolunteerWellbeingController::class, 'verifyTraining']);
Route::put('/v2/admin/volunteering/training/{id}/reject', [\App\Http\Controllers\Api\VolunteerWellbeingController::class, 'rejectTraining']);
Route::get('/v2/admin/volunteering/incidents', [\App\Http\Controllers\Api\VolunteerWellbeingController::class, 'adminIncidents']);
Route::put('/v2/admin/volunteering/incidents/{id}', [\App\Http\Controllers\Api\VolunteerWellbeingController::class, 'updateIncident']);
Route::put('/v2/admin/volunteering/organizations/{id}/dlp', [\App\Http\Controllers\Api\VolunteerWellbeingController::class, 'assignDlp']);
Route::get('/v2/admin/volunteering/custom-fields', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'adminCustomFields']);
Route::post('/v2/admin/volunteering/custom-fields', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'createCustomField']);
Route::put('/v2/admin/volunteering/custom-fields/{id}', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'updateCustomField']);
Route::delete('/v2/admin/volunteering/custom-fields/{id}', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'deleteCustomField']);
Route::get('/v2/admin/volunteering/reminder-settings', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'getReminderSettings']);
Route::put('/v2/admin/volunteering/reminder-settings', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'updateReminderSettings']);
Route::put('/v2/admin/volunteering/community-projects/{id}/review', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'reviewCommunityProject']);
Route::get('/v2/admin/volunteering/webhooks', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'getWebhooks']);
Route::post('/v2/admin/volunteering/webhooks', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'createWebhook']);
Route::put('/v2/admin/volunteering/webhooks/{id}', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'updateWebhook']);
Route::delete('/v2/admin/volunteering/webhooks/{id}', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'deleteWebhook']);
Route::post('/v2/admin/volunteering/webhooks/{id}/test', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'testWebhook']);
Route::get('/v2/admin/volunteering/webhooks/{id}/logs', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'getWebhookLogs']);
Route::get('/v2/admin/volunteering/giving-days', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'adminGivingDays']);
Route::post('/v2/admin/volunteering/giving-days', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'createGivingDay']);
Route::put('/v2/admin/volunteering/giving-days/{id}', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'updateGivingDay']);
Route::get('/v2/admin/volunteering/donations/export', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'exportDonations']);
Route::post('/v2/admin/donations/{id}/refund', [\App\Http\Controllers\Api\DonationPaymentController::class, 'adminRefund']);
Route::get('/v2/admin/events', [\App\Http\Controllers\Api\AdminEventsController::class, 'index']);
Route::get('/v2/admin/events/{id}', [\App\Http\Controllers\Api\AdminEventsController::class, 'show']);
Route::delete('/v2/admin/events/{id}', [\App\Http\Controllers\Api\AdminEventsController::class, 'destroy']);
Route::post('/v2/admin/events/{id}/cancel', [\App\Http\Controllers\Api\AdminEventsController::class, 'cancel']);
Route::get('/v2/admin/polls', [\App\Http\Controllers\Api\AdminPollsController::class, 'index']);
Route::get('/v2/admin/polls/{id}', [\App\Http\Controllers\Api\AdminPollsController::class, 'show']);
Route::delete('/v2/admin/polls/{id}', [\App\Http\Controllers\Api\AdminPollsController::class, 'destroy']);
Route::get('/v2/admin/goals', [\App\Http\Controllers\Api\AdminGoalsController::class, 'index']);
Route::get('/v2/admin/goals/{id}', [\App\Http\Controllers\Api\AdminGoalsController::class, 'show']);
Route::delete('/v2/admin/goals/{id}', [\App\Http\Controllers\Api\AdminGoalsController::class, 'destroy']);
Route::get('/v2/admin/resources', [\App\Http\Controllers\Api\AdminResourcesController::class, 'index']);
Route::get('/v2/admin/resources/{id}', [\App\Http\Controllers\Api\AdminResourcesController::class, 'show']);
Route::delete('/v2/admin/resources/{id}', [\App\Http\Controllers\Api\AdminResourcesController::class, 'destroy']);
Route::get('/v2/admin/jobs', [\App\Http\Controllers\Api\AdminJobsController::class, 'index']);
// Static literal admin job routes BEFORE {id} wildcard (Agent B + D)
Route::get('/v2/admin/jobs/moderation-queue', [\App\Http\Controllers\Api\AdminJobsController::class, 'moderationQueue']);
Route::get('/v2/admin/jobs/moderation-stats', [\App\Http\Controllers\Api\AdminJobsController::class, 'moderationStats']);
Route::get('/v2/admin/jobs/spam-stats', [\App\Http\Controllers\Api\AdminJobsController::class, 'spamStats']);
Route::get('/v2/admin/jobs/bias-audit', [\App\Http\Controllers\Api\AdminJobsController::class, 'biasAudit']);
Route::put('/v2/admin/jobs/applications/{id}', [\App\Http\Controllers\Api\AdminJobsController::class, 'updateApplicationStatus']);
// Wildcard {id} routes
Route::get('/v2/admin/jobs/{id}', [\App\Http\Controllers\Api\AdminJobsController::class, 'show']);
Route::delete('/v2/admin/jobs/{id}', [\App\Http\Controllers\Api\AdminJobsController::class, 'destroy']);
Route::post('/v2/admin/jobs/{id}/feature', [\App\Http\Controllers\Api\AdminJobsController::class, 'feature']);
Route::post('/v2/admin/jobs/{id}/unfeature', [\App\Http\Controllers\Api\AdminJobsController::class, 'unfeature']);
Route::get('/v2/admin/jobs/{id}/applications', [\App\Http\Controllers\Api\AdminJobsController::class, 'getApplications']);
Route::post('/v2/admin/jobs/{id}/approve', [\App\Http\Controllers\Api\AdminJobsController::class, 'approve']);
Route::post('/v2/admin/jobs/{id}/reject', [\App\Http\Controllers\Api\AdminJobsController::class, 'reject']);
Route::post('/v2/admin/jobs/{id}/flag', [\App\Http\Controllers\Api\AdminJobsController::class, 'flag']);
Route::get('/v2/admin/ideation', [\App\Http\Controllers\Api\AdminIdeationController::class, 'index']);
Route::get('/v2/admin/ideation/{id}', [\App\Http\Controllers\Api\AdminIdeationController::class, 'show']);
Route::delete('/v2/admin/ideation/{id}', [\App\Http\Controllers\Api\AdminIdeationController::class, 'destroy']);
Route::post('/v2/admin/ideation/{id}/status', [\App\Http\Controllers\Api\AdminIdeationController::class, 'updateStatus']);
Route::get('/v2/admin/federation/settings', [\App\Http\Controllers\Api\AdminFederationController::class, 'settings']);
Route::put('/v2/admin/federation/settings', [\App\Http\Controllers\Api\AdminFederationController::class, 'updateSettings']);
Route::get('/v2/admin/federation/partnerships', [\App\Http\Controllers\Api\AdminFederationController::class, 'partnerships']);
Route::post('/v2/admin/federation/partnerships/{id}/approve', [\App\Http\Controllers\Api\AdminFederationController::class, 'approvePartnership']);
Route::post('/v2/admin/federation/partnerships/{id}/reject', [\App\Http\Controllers\Api\AdminFederationController::class, 'rejectPartnership']);
Route::post('/v2/admin/federation/partnerships/{id}/terminate', [\App\Http\Controllers\Api\AdminFederationController::class, 'terminatePartnership']);
Route::post('/v2/admin/federation/partnerships/request', [\App\Http\Controllers\Api\AdminFederationController::class, 'requestPartnership']);
Route::get('/v2/admin/federation/partnerships/{id}', [\App\Http\Controllers\Api\AdminFederationController::class, 'partnershipDetail']);
Route::post('/v2/admin/federation/partnerships/{id}/counter-propose', [\App\Http\Controllers\Api\AdminFederationController::class, 'counterProposePartnership']);
Route::put('/v2/admin/federation/partnerships/{id}/permissions', [\App\Http\Controllers\Api\AdminFederationController::class, 'updatePartnershipPermissions']);
Route::get('/v2/admin/federation/partnerships/{id}/audit-log', [\App\Http\Controllers\Api\AdminFederationController::class, 'partnershipAuditLog']);
Route::get('/v2/admin/federation/partnerships/{id}/stats', [\App\Http\Controllers\Api\AdminFederationController::class, 'partnershipStats']);
Route::get('/v2/admin/federation/directory', [\App\Http\Controllers\Api\AdminFederationController::class, 'directory']);
Route::get('/v2/admin/federation/directory/profile', [\App\Http\Controllers\Api\AdminFederationController::class, 'profile']);
Route::put('/v2/admin/federation/directory/profile', [\App\Http\Controllers\Api\AdminFederationController::class, 'updateProfile']);
Route::get('/v2/admin/federation/topics', [\App\Http\Controllers\Api\AdminFederationController::class, 'topics']);
Route::get('/v2/admin/federation/topics/mine', [\App\Http\Controllers\Api\AdminFederationController::class, 'myTopics']);
Route::put('/v2/admin/federation/topics/mine', [\App\Http\Controllers\Api\AdminFederationController::class, 'updateMyTopics']);
Route::get('/v2/admin/federation/analytics', [\App\Http\Controllers\Api\AdminFederationController::class, 'analytics']);
Route::get('/v2/admin/federation/activity', [\App\Http\Controllers\Api\AdminFederationController::class, 'activityFeed']);
Route::get('/v2/admin/federation/api-keys', [\App\Http\Controllers\Api\AdminFederationController::class, 'apiKeys']);
Route::post('/v2/admin/federation/api-keys', [\App\Http\Controllers\Api\AdminFederationController::class, 'createApiKey']);
Route::post('/v2/admin/federation/api-keys/{id}/revoke', [\App\Http\Controllers\Api\AdminFederationController::class, 'revokeApiKey']);
Route::get('/v2/admin/federation/data', [\App\Http\Controllers\Api\AdminFederationController::class, 'dataManagement']);
Route::get('/v2/admin/federation/export/{type}', [\App\Http\Controllers\Api\AdminFederationController::class, 'exportData']);
Route::get('/v2/admin/federation/neighborhoods', [\App\Http\Controllers\Api\AdminFederationNeighborhoodsController::class, 'index']);
Route::post('/v2/admin/federation/neighborhoods', [\App\Http\Controllers\Api\AdminFederationNeighborhoodsController::class, 'store']);
Route::get('/v2/admin/federation/available-tenants', [\App\Http\Controllers\Api\AdminFederationNeighborhoodsController::class, 'availableTenants']);
Route::delete('/v2/admin/federation/neighborhoods/{id}', [\App\Http\Controllers\Api\AdminFederationNeighborhoodsController::class, 'destroy']);
Route::post('/v2/admin/federation/neighborhoods/{id}/tenants', [\App\Http\Controllers\Api\AdminFederationNeighborhoodsController::class, 'addTenant']);
Route::delete('/v2/admin/federation/neighborhoods/{id}/tenants/{tenantId}', [\App\Http\Controllers\Api\AdminFederationNeighborhoodsController::class, 'removeTenant']);
Route::get('/v2/admin/federation/credit-agreements', [\App\Http\Controllers\Api\AdminFederationCreditAgreementsController::class, 'index']);
Route::post('/v2/admin/federation/credit-agreements', [\App\Http\Controllers\Api\AdminFederationCreditAgreementsController::class, 'store']);
Route::post('/v2/admin/federation/credit-agreements/{id}/{action}', [\App\Http\Controllers\Api\AdminFederationCreditAgreementsController::class, 'action']);
Route::get('/v2/admin/federation/credit-agreements/{id}/transactions', [\App\Http\Controllers\Api\AdminFederationCreditAgreementsController::class, 'transactions']);
Route::get('/v2/admin/federation/credit-balances', [\App\Http\Controllers\Api\AdminFederationCreditAgreementsController::class, 'balances']);
Route::get('/v2/admin/federation/partners', [\App\Http\Controllers\Api\AdminFederationCreditAgreementsController::class, 'partners']);
// External federation partners
Route::get('/v2/admin/federation/external-partners', [\App\Http\Controllers\Api\AdminFederationExternalPartnersController::class, 'index']);
Route::post('/v2/admin/federation/external-partners', [\App\Http\Controllers\Api\AdminFederationExternalPartnersController::class, 'store']);
Route::put('/v2/admin/federation/external-partners/{id}', [\App\Http\Controllers\Api\AdminFederationExternalPartnersController::class, 'update']);
Route::delete('/v2/admin/federation/external-partners/{id}', [\App\Http\Controllers\Api\AdminFederationExternalPartnersController::class, 'destroy']);
Route::post('/v2/admin/federation/external-partners/{id}/health-check', [\App\Http\Controllers\Api\AdminFederationExternalPartnersController::class, 'healthCheck']);
Route::get('/v2/admin/federation/external-partners/{id}/logs', [\App\Http\Controllers\Api\AdminFederationExternalPartnersController::class, 'logs']);
// Federation webhooks
Route::get('/v2/admin/federation/webhooks', [\App\Http\Controllers\Api\AdminFederationWebhooksController::class, 'index']);
Route::post('/v2/admin/federation/webhooks', [\App\Http\Controllers\Api\AdminFederationWebhooksController::class, 'store']);
Route::put('/v2/admin/federation/webhooks/{id}', [\App\Http\Controllers\Api\AdminFederationWebhooksController::class, 'update']);
Route::delete('/v2/admin/federation/webhooks/{id}', [\App\Http\Controllers\Api\AdminFederationWebhooksController::class, 'destroy']);
Route::post('/v2/admin/federation/webhooks/{id}/test', [\App\Http\Controllers\Api\AdminFederationWebhooksController::class, 'test']);
Route::get('/v2/admin/federation/webhooks/{id}/logs', [\App\Http\Controllers\Api\AdminFederationWebhooksController::class, 'logs']);
Route::post('/v2/admin/federation/webhook-logs/{id}/retry', [\App\Http\Controllers\Api\AdminFederationWebhooksController::class, 'retry']);
// NOTE: Federation user routes moved to auth-only group (not admin-only)
Route::get('/v2/admin/pages', [\App\Http\Controllers\Api\AdminContentController::class, 'getPages']);
Route::post('/v2/admin/pages', [\App\Http\Controllers\Api\AdminContentController::class, 'createPage']);
Route::get('/v2/admin/pages/{id}', [\App\Http\Controllers\Api\AdminContentController::class, 'getPage']);
Route::put('/v2/admin/pages/{id}', [\App\Http\Controllers\Api\AdminContentController::class, 'updatePage']);
Route::delete('/v2/admin/pages/{id}', [\App\Http\Controllers\Api\AdminContentController::class, 'deletePage']);
Route::get('/v2/admin/menus', [\App\Http\Controllers\Api\AdminContentController::class, 'getMenus']);
Route::post('/v2/admin/menus', [\App\Http\Controllers\Api\AdminContentController::class, 'createMenu']);
Route::get('/v2/admin/menus/{id}', [\App\Http\Controllers\Api\AdminContentController::class, 'getMenu']);
Route::put('/v2/admin/menus/{id}', [\App\Http\Controllers\Api\AdminContentController::class, 'updateMenu']);
Route::delete('/v2/admin/menus/{id}', [\App\Http\Controllers\Api\AdminContentController::class, 'deleteMenu']);
Route::get('/v2/admin/menus/{id}/items', [\App\Http\Controllers\Api\AdminContentController::class, 'getMenuItems']);
Route::post('/v2/admin/menus/{id}/items', [\App\Http\Controllers\Api\AdminContentController::class, 'createMenuItem']);
Route::post('/v2/admin/menus/{id}/items/reorder', [\App\Http\Controllers\Api\AdminContentController::class, 'reorderMenuItems']);
Route::put('/v2/admin/menu-items/{id}', [\App\Http\Controllers\Api\AdminContentController::class, 'updateMenuItem']);
Route::delete('/v2/admin/menu-items/{id}', [\App\Http\Controllers\Api\AdminContentController::class, 'deleteMenuItem']);
Route::get('/v2/admin/plans', [\App\Http\Controllers\Api\AdminContentController::class, 'getPlans']);
Route::post('/v2/admin/plans', [\App\Http\Controllers\Api\AdminContentController::class, 'createPlan']);
Route::get('/v2/admin/plans/{id}', [\App\Http\Controllers\Api\AdminContentController::class, 'getPlan']);
Route::put('/v2/admin/plans/{id}', [\App\Http\Controllers\Api\AdminContentController::class, 'updatePlan']);
Route::delete('/v2/admin/plans/{id}', [\App\Http\Controllers\Api\AdminContentController::class, 'deletePlan']);
Route::get('/v2/admin/subscriptions', [\App\Http\Controllers\Api\AdminContentController::class, 'getSubscriptions']);
// Billing — Stripe subscription management
Route::get('/v2/admin/billing/subscription', [\App\Http\Controllers\Api\AdminBillingController::class, 'getSubscription']);
Route::post('/v2/admin/billing/checkout', [\App\Http\Controllers\Api\AdminBillingController::class, 'createCheckoutSession']);
Route::post('/v2/admin/billing/portal', [\App\Http\Controllers\Api\AdminBillingController::class, 'createPortalSession']);
Route::get('/v2/admin/billing/invoices', [\App\Http\Controllers\Api\AdminBillingController::class, 'getInvoices']);
Route::get('/v2/admin/tools/redirects', [\App\Http\Controllers\Api\AdminToolsController::class, 'getRedirects']);
Route::post('/v2/admin/tools/redirects', [\App\Http\Controllers\Api\AdminToolsController::class, 'createRedirect']);
Route::delete('/v2/admin/tools/redirects/{id}', [\App\Http\Controllers\Api\AdminToolsController::class, 'deleteRedirect']);
Route::get('/v2/admin/tools/404-errors', [\App\Http\Controllers\Api\AdminToolsController::class, 'get404Errors']);
Route::delete('/v2/admin/tools/404-errors/{id}', [\App\Http\Controllers\Api\AdminToolsController::class, 'delete404Error']);
Route::post('/v2/admin/tools/health-check', [\App\Http\Controllers\Api\AdminToolsController::class, 'runHealthCheck']);
Route::get('/v2/admin/tools/ip-debug', [\App\Http\Controllers\Api\AdminToolsController::class, 'ipDebug']);
Route::get('/v2/admin/tools/webp-stats', [\App\Http\Controllers\Api\AdminToolsController::class, 'getWebpStats']);
Route::post('/v2/admin/tools/webp-convert', [\App\Http\Controllers\Api\AdminToolsController::class, 'runWebpConversion']);
Route::post('/v2/admin/tools/seed', [\App\Http\Controllers\Api\AdminToolsController::class, 'runSeedGenerator']);
Route::get('/v2/admin/tools/blog-backups', [\App\Http\Controllers\Api\AdminToolsController::class, 'getBlogBackups']);
Route::post('/v2/admin/tools/blog-backups/{id}/restore', [\App\Http\Controllers\Api\AdminToolsController::class, 'restoreBlogBackup']);
Route::get('/v2/admin/tools/seo-audit', [\App\Http\Controllers\Api\AdminToolsController::class, 'getSeoAudit']);
Route::post('/v2/admin/tools/seo-audit', [\App\Http\Controllers\Api\AdminToolsController::class, 'runSeoAudit']);
Route::get('/v2/admin/deliverability/dashboard', [\App\Http\Controllers\Api\AdminDeliverabilityController::class, 'getDashboard']);
Route::get('/v2/admin/deliverability/analytics', [\App\Http\Controllers\Api\AdminDeliverabilityController::class, 'getAnalytics']);
Route::get('/v2/admin/deliverability', [\App\Http\Controllers\Api\AdminDeliverabilityController::class, 'getDeliverables']);
Route::post('/v2/admin/deliverability', [\App\Http\Controllers\Api\AdminDeliverabilityController::class, 'createDeliverable']);
Route::get('/v2/admin/deliverability/{id}', [\App\Http\Controllers\Api\AdminDeliverabilityController::class, 'getDeliverable']);
Route::put('/v2/admin/deliverability/{id}', [\App\Http\Controllers\Api\AdminDeliverabilityController::class, 'updateDeliverable']);
Route::delete('/v2/admin/deliverability/{id}', [\App\Http\Controllers\Api\AdminDeliverabilityController::class, 'deleteDeliverable']);
Route::post('/v2/admin/deliverability/{id}/comments', [\App\Http\Controllers\Api\AdminDeliverabilityController::class, 'addComment']);
Route::get('/v2/admin/safeguarding/dashboard', [\App\Http\Controllers\Api\AdminSafeguardingController::class, 'dashboard']);
Route::get('/v2/admin/safeguarding/flagged-messages', [\App\Http\Controllers\Api\AdminSafeguardingController::class, 'flaggedMessages']);
Route::get('/v2/admin/safeguarding/assignments', [\App\Http\Controllers\Api\AdminSafeguardingController::class, 'assignments']);
Route::post('/v2/admin/safeguarding/flagged-messages/{id}/review', [\App\Http\Controllers\Api\AdminSafeguardingController::class, 'reviewMessage']);
Route::post('/v2/admin/safeguarding/assignments', [\App\Http\Controllers\Api\AdminSafeguardingController::class, 'createAssignment']);
Route::delete('/v2/admin/safeguarding/assignments/{id}', [\App\Http\Controllers\Api\AdminSafeguardingController::class, 'deleteAssignment']);
Route::get('/v2/admin/safeguarding/member-preferences', [\App\Http\Controllers\Api\AdminSafeguardingController::class, 'memberPreferences']);

}); // End Route::middleware(['auth:sanctum', 'admin'])

// ============================================
// Super Admin routes — Sanctum auth + super-admin middleware
// ============================================
Route::middleware(['auth:sanctum', 'super-admin'])->group(function () {

Route::get('/v2/admin/super/dashboard', [\App\Http\Controllers\Api\AdminSuperController::class, 'dashboard']);
Route::get('/v2/admin/super/tenants', [\App\Http\Controllers\Api\AdminSuperController::class, 'tenantList']);
Route::get('/v2/admin/super/tenants/hierarchy', [\App\Http\Controllers\Api\AdminSuperController::class, 'tenantHierarchy']);
Route::post('/v2/admin/super/tenants', [\App\Http\Controllers\Api\AdminSuperController::class, 'tenantCreate']);
Route::get('/v2/admin/super/tenants/{id}', [\App\Http\Controllers\Api\AdminSuperController::class, 'tenantShow']);
Route::put('/v2/admin/super/tenants/{id}', [\App\Http\Controllers\Api\AdminSuperController::class, 'tenantUpdate']);
Route::delete('/v2/admin/super/tenants/{id}', [\App\Http\Controllers\Api\AdminSuperController::class, 'tenantDelete']);
Route::post('/v2/admin/super/tenants/{id}/reactivate', [\App\Http\Controllers\Api\AdminSuperController::class, 'tenantReactivate']);
Route::post('/v2/admin/super/tenants/{id}/toggle-hub', [\App\Http\Controllers\Api\AdminSuperController::class, 'tenantToggleHub']);
Route::post('/v2/admin/super/tenants/{id}/move', [\App\Http\Controllers\Api\AdminSuperController::class, 'tenantMove']);
Route::get('/v2/admin/super/users', [\App\Http\Controllers\Api\AdminSuperController::class, 'userList']);
Route::post('/v2/admin/super/users', [\App\Http\Controllers\Api\AdminSuperController::class, 'userCreate']);
Route::get('/v2/admin/super/users/{id}', [\App\Http\Controllers\Api\AdminSuperController::class, 'userShow']);
Route::put('/v2/admin/super/users/{id}', [\App\Http\Controllers\Api\AdminSuperController::class, 'userUpdate']);
Route::post('/v2/admin/super/users/{id}/grant-super-admin', [\App\Http\Controllers\Api\AdminSuperController::class, 'userGrantSuperAdmin']);
Route::post('/v2/admin/super/users/{id}/revoke-super-admin', [\App\Http\Controllers\Api\AdminSuperController::class, 'userRevokeSuperAdmin']);
Route::post('/v2/admin/super/users/{id}/grant-global-super-admin', [\App\Http\Controllers\Api\AdminSuperController::class, 'userGrantGlobalSuperAdmin']);
Route::post('/v2/admin/super/users/{id}/revoke-global-super-admin', [\App\Http\Controllers\Api\AdminSuperController::class, 'userRevokeGlobalSuperAdmin']);
Route::post('/v2/admin/super/users/{id}/move-tenant', [\App\Http\Controllers\Api\AdminSuperController::class, 'userMoveTenant']);
Route::post('/v2/admin/super/users/{id}/move-and-promote', [\App\Http\Controllers\Api\AdminSuperController::class, 'userMoveAndPromote']);
Route::post('/v2/admin/super/bulk/move-users', [\App\Http\Controllers\Api\AdminSuperController::class, 'bulkMoveUsers']);
Route::post('/v2/admin/super/bulk/update-tenants', [\App\Http\Controllers\Api\AdminSuperController::class, 'bulkUpdateTenants']);
Route::get('/v2/admin/super/audit', [\App\Http\Controllers\Api\AdminSuperController::class, 'audit']);
Route::get('/v2/admin/super/federation', [\App\Http\Controllers\Api\AdminSuperController::class, 'federationOverview']);
Route::get('/v2/admin/super/federation/system-controls', [\App\Http\Controllers\Api\AdminSuperController::class, 'federationGetSystemControls']);
Route::put('/v2/admin/super/federation/system-controls', [\App\Http\Controllers\Api\AdminSuperController::class, 'federationUpdateSystemControls']);
Route::post('/v2/admin/super/federation/emergency-lockdown', [\App\Http\Controllers\Api\AdminSuperController::class, 'federationEmergencyLockdown']);
Route::post('/v2/admin/super/federation/lift-lockdown', [\App\Http\Controllers\Api\AdminSuperController::class, 'federationLiftLockdown']);
Route::get('/v2/admin/super/federation/whitelist', [\App\Http\Controllers\Api\AdminSuperController::class, 'federationGetWhitelist']);
Route::post('/v2/admin/super/federation/whitelist', [\App\Http\Controllers\Api\AdminSuperController::class, 'federationAddToWhitelist']);
Route::delete('/v2/admin/super/federation/whitelist/{tenantId}', [\App\Http\Controllers\Api\AdminSuperController::class, 'federationRemoveFromWhitelist']);
Route::get('/v2/admin/super/federation/partnerships', [\App\Http\Controllers\Api\AdminSuperController::class, 'federationPartnerships']);
Route::post('/v2/admin/super/federation/partnerships/{id}/suspend', [\App\Http\Controllers\Api\AdminSuperController::class, 'federationSuspendPartnership']);
Route::post('/v2/admin/super/federation/partnerships/{id}/terminate', [\App\Http\Controllers\Api\AdminSuperController::class, 'federationTerminatePartnership']);
Route::get('/v2/admin/super/federation/tenant/{id}/features', [\App\Http\Controllers\Api\AdminSuperController::class, 'federationGetTenantFeatures']);
Route::put('/v2/admin/super/federation/tenant/{id}/features', [\App\Http\Controllers\Api\AdminSuperController::class, 'federationUpdateTenantFeature']);

// Impersonate and super-admin promotion — requires super-admin (moved from admin group)
Route::post('/v2/admin/users/{id}/impersonate', [\App\Http\Controllers\Api\AdminUsersController::class, 'impersonate']);
Route::put('/v2/admin/users/{id}/super-admin', [\App\Http\Controllers\Api\AdminUsersController::class, 'setSuperAdmin']);
Route::put('/v2/admin/users/{id}/global-super-admin', [\App\Http\Controllers\Api\AdminUsersController::class, 'setGlobalSuperAdmin']);

}); // End Route::middleware(['auth:sanctum', 'super-admin'])

// ============================================
// Admin CRM routes — Sanctum auth + admin middleware
// ============================================
Route::middleware(['auth:sanctum', 'admin'])->group(function () {

Route::get('/v2/admin/crm/dashboard', [\App\Http\Controllers\Api\AdminCrmController::class, 'dashboard']);
Route::get('/v2/admin/crm/funnel', [\App\Http\Controllers\Api\AdminCrmController::class, 'funnel']);
Route::get('/v2/admin/crm/admins', [\App\Http\Controllers\Api\AdminCrmController::class, 'listAdmins']);
Route::get('/v2/admin/crm/notes', [\App\Http\Controllers\Api\AdminCrmController::class, 'listNotes']);
Route::post('/v2/admin/crm/notes', [\App\Http\Controllers\Api\AdminCrmController::class, 'createNote']);
Route::put('/v2/admin/crm/notes/{id}', [\App\Http\Controllers\Api\AdminCrmController::class, 'updateNote']);
Route::delete('/v2/admin/crm/notes/{id}', [\App\Http\Controllers\Api\AdminCrmController::class, 'deleteNote']);
Route::get('/v2/admin/crm/tasks', [\App\Http\Controllers\Api\AdminCrmController::class, 'listTasks']);
Route::post('/v2/admin/crm/tasks', [\App\Http\Controllers\Api\AdminCrmController::class, 'createTask']);
Route::put('/v2/admin/crm/tasks/{id}', [\App\Http\Controllers\Api\AdminCrmController::class, 'updateTask']);
Route::delete('/v2/admin/crm/tasks/{id}', [\App\Http\Controllers\Api\AdminCrmController::class, 'deleteTask']);
Route::get('/v2/admin/crm/tags', [\App\Http\Controllers\Api\AdminCrmController::class, 'listTags']);
Route::post('/v2/admin/crm/tags', [\App\Http\Controllers\Api\AdminCrmController::class, 'addTag']);
Route::delete('/v2/admin/crm/tags/bulk', [\App\Http\Controllers\Api\AdminCrmController::class, 'bulkRemoveTag']);
Route::delete('/v2/admin/crm/tags/{id}', [\App\Http\Controllers\Api\AdminCrmController::class, 'removeTag']);
Route::get('/v2/admin/crm/timeline', [\App\Http\Controllers\Api\AdminCrmController::class, 'timeline']);
Route::get('/v2/admin/crm/export/notes', [\App\Http\Controllers\Api\AdminCrmController::class, 'exportNotes']);
Route::get('/v2/admin/crm/export/tasks', [\App\Http\Controllers\Api\AdminCrmController::class, 'exportTasks']);
Route::get('/v2/admin/crm/export/dashboard', [\App\Http\Controllers\Api\AdminCrmController::class, 'exportDashboard']);

// ============================================

}); // End Route::middleware(['auth:sanctum', 'admin']) — CRM

// ============================================
// Public routes (auth, CSRF, VAPID — no auth required)
// ============================================
Route::get('/push/vapid-key', [\App\Http\Controllers\Api\PushController::class, 'vapidKey']);
Route::get('/push/vapid-public-key', [\App\Http\Controllers\Api\PushController::class, 'vapidKey']);
// Session management — rate-limited to prevent abuse (30 req/min per IP)
Route::middleware('throttle:30,1')->group(function () {
    Route::post('/auth/heartbeat', [\App\Http\Controllers\Api\AuthController::class, 'heartbeat']);
    Route::get('/auth/check-session', [\App\Http\Controllers\Api\AuthController::class, 'checkSession']);
    Route::post('/auth/refresh-session', [\App\Http\Controllers\Api\AuthController::class, 'refreshSession']);
    Route::post('/auth/restore-session', [\App\Http\Controllers\Api\AuthController::class, 'restoreSession']);
});
// Rate-limited auth endpoints (10 requests/minute per IP — brute force protection)
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/auth/login', [\App\Http\Controllers\Api\AuthController::class, 'login']);
    Route::post('/v2/auth/register', [\App\Http\Controllers\Api\RegistrationController::class, 'register']);
    Route::post('/totp/verify', [\App\Http\Controllers\Api\TotpController::class, 'verify']);
    Route::post('/webauthn/auth-challenge', [\App\Http\Controllers\Api\WebAuthnController::class, 'authChallenge']);
    Route::post('/webauthn/auth-verify', [\App\Http\Controllers\Api\WebAuthnController::class, 'authVerify']);
    Route::post('/auth/forgot-password', [\App\Http\Controllers\Api\PasswordResetController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [\App\Http\Controllers\Api\PasswordResetController::class, 'resetPassword']);
    Route::post('/auth/verify-email', [\App\Http\Controllers\Api\EmailVerificationController::class, 'verifyEmail']);
    Route::post('/auth/resend-verification', [\App\Http\Controllers\Api\EmailVerificationController::class, 'resendVerification']);
    Route::post('/auth/resend-verification-by-email', [\App\Http\Controllers\Api\EmailVerificationController::class, 'resendVerificationByEmail']);
});

// Auth utilities — rate-limited to prevent token enumeration/abuse (30 req/min per IP)
Route::middleware('throttle:30,1')->group(function () {
    Route::post('/auth/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout']);
    Route::post('/auth/refresh-token', [\App\Http\Controllers\Api\AuthController::class, 'refreshToken']);
    Route::post('/auth/validate-token', [\App\Http\Controllers\Api\AuthController::class, 'validateToken']);
    Route::get('/auth/validate-token', [\App\Http\Controllers\Api\AuthController::class, 'validateToken']);
});
// CSRF tokens are high-frequency, rate-limit more generously (60 req/min per IP)
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/auth/csrf-token', [\App\Http\Controllers\Api\AuthController::class, 'getCsrfToken']);
    Route::get('/v2/csrf-token', [\App\Http\Controllers\Api\AuthController::class, 'getCsrfToken']);
    Route::get('/csrf-token', [\App\Http\Controllers\Api\AuthController::class, 'getCsrfToken']);
});

// Newsletter unsubscribe/tracking — public (recipients may not be logged in)
Route::post('/v2/newsletter/unsubscribe', [\App\Http\Controllers\Api\NewsletterController::class, 'unsubscribe'])->middleware('throttle:30,1');
Route::get('/v2/newsletter/pixel/{token}', [\App\Http\Controllers\Api\NewsletterController::class, 'trackOpen']);

// ============================================
// Public routes — No auth required
// These were incorrectly inside auth:sanctum but must be accessible
// to unauthenticated visitors (menus, WebAuthn login, password reset, etc.)
// ============================================
Route::get('/menus', [\App\Http\Controllers\Api\MenuController::class, 'index']);
Route::get('/menus/config', [\App\Http\Controllers\Api\MenuController::class, 'config']);
Route::get('/menus/mobile', [\App\Http\Controllers\Api\MenuController::class, 'mobile']);
Route::get('/menus/{slug}', [\App\Http\Controllers\Api\MenuController::class, 'show']);
Route::post('/v2/contact', [\App\Http\Controllers\Api\CoreController::class, 'apiSubmit'])->middleware('throttle:5,1');

// API documentation
Route::get('/docs', [\App\Http\Controllers\Api\OpenApiDocController::class, 'ui']);
Route::get('/docs/openapi.json', [\App\Http\Controllers\Api\OpenApiDocController::class, 'json']);
Route::get('/docs/openapi.yaml', [\App\Http\Controllers\Api\OpenApiDocController::class, 'yaml']);

// ============================================
// Misc/legacy/federation routes — Sanctum auth required
// Controllers also enforce auth internally as a fallback
// ============================================
Route::middleware('auth:sanctum')->group(function () {

// MIGRATED ROUTES — Misc API (Social, Auth, Push, AI, Menus, Wallet Features, Events, Volunteering, Ideation, Matching)
// Source: httpdocs/routes/misc-api.php
// ============================================
// Legacy social routes (deprecated — use V2 GET/POST equivalents above)
Route::get('/social/test', [\App\Http\Controllers\Api\SocialController::class, 'test']);
Route::post('/social/like', [\App\Http\Controllers\Api\SocialController::class, 'like']);
Route::post('/social/likers', [\App\Http\Controllers\Api\SocialController::class, 'likers']); // deprecated: use V2 GET equivalent
Route::post('/social/comments', [\App\Http\Controllers\Api\SocialController::class, 'comments']); // deprecated: use V2 GET equivalent
Route::post('/social/share', [\App\Http\Controllers\Api\SocialController::class, 'share']);
Route::post('/social/delete', [\App\Http\Controllers\Api\SocialController::class, 'delete']);
Route::post('/social/reaction', [\App\Http\Controllers\Api\SocialController::class, 'reaction']);
Route::post('/social/reply', [\App\Http\Controllers\Api\SocialController::class, 'reply']);
Route::post('/social/edit-comment', [\App\Http\Controllers\Api\SocialController::class, 'editComment']);
Route::post('/social/delete-comment', [\App\Http\Controllers\Api\SocialController::class, 'deleteComment']);
Route::post('/social/mention-search', [\App\Http\Controllers\Api\SocialController::class, 'mentionSearch']); // deprecated: use V2 GET equivalent
Route::post('/social/feed', [\App\Http\Controllers\Api\SocialController::class, 'feed']); // deprecated: use GET /v2/feed
Route::post('/social/create-post', [\App\Http\Controllers\Api\SocialController::class, 'createPost']);
Route::post('/upload', [\App\Http\Controllers\Api\UploadController::class, 'store']);
Route::post('/push/subscribe', [\App\Http\Controllers\Api\PushController::class, 'subscribe']);
Route::post('/push/unsubscribe', [\App\Http\Controllers\Api\PushController::class, 'unsubscribe']);
Route::post('/push/send', [\App\Http\Controllers\Api\PushController::class, 'send']);
Route::get('/push/status', [\App\Http\Controllers\Api\PushController::class, 'status']);
Route::post('/push/register-device', [\App\Http\Controllers\Api\PushController::class, 'registerDevice']);
Route::post('/push/unregister-device', [\App\Http\Controllers\Api\PushController::class, 'unregisterDevice']);
Route::post('/auth/revoke', [\App\Http\Controllers\Api\AuthController::class, 'revokeToken']);
Route::post('/auth/revoke-all', [\App\Http\Controllers\Api\AuthController::class, 'revokeAllTokens']);
Route::post('/auth/admin-session', [\App\Http\Controllers\Api\AuthController::class, 'adminSession']);
Route::get('/auth/admin-session', [\App\Http\Controllers\Api\AuthController::class, 'adminSession']);
Route::get('/v2/auth/verification-status', [\App\Http\Controllers\Api\RegistrationPolicyController::class, 'getVerificationStatus']);
Route::post('/v2/auth/start-verification', [\App\Http\Controllers\Api\RegistrationPolicyController::class, 'startVerification'])->middleware('throttle:10,1');
Route::post('/v2/auth/validate-invite', [\App\Http\Controllers\Api\RegistrationPolicyController::class, 'validateInviteCode'])->middleware('throttle:10,1');
Route::get('/v2/auth/registration-info', [\App\Http\Controllers\Api\RegistrationPolicyController::class, 'getRegistrationInfo']);
// NOTE: identity webhook route moved to public webhook section (below auth group)
// NOTE: /docs, /auth/forgot-password, /auth/reset-password, /auth/verify-email,
// /auth/resend-verification, /auth/resend-verification-by-email are public routes (registered above auth group)
// NOTE: /totp/verify moved to public routes section (user has no token during 2FA login)
Route::get('/totp/status', [\App\Http\Controllers\Api\TotpController::class, 'status']);
Route::get('/v2/auth/2fa/status', [\App\Http\Controllers\Api\TwoFactorController::class, 'status']);
Route::post('/v2/auth/2fa/setup', [\App\Http\Controllers\Api\TwoFactorController::class, 'setup']);
Route::post('/v2/auth/2fa/verify', [\App\Http\Controllers\Api\TwoFactorController::class, 'verify']);
Route::post('/v2/auth/2fa/disable', [\App\Http\Controllers\Api\TwoFactorController::class, 'disable']);
Route::post('/app/check-version', [\App\Http\Controllers\Api\AppController::class, 'checkVersion'])->withoutMiddleware('auth:sanctum');
Route::get('/app/version', [\App\Http\Controllers\Api\AppController::class, 'version'])->withoutMiddleware('auth:sanctum');
Route::post('/app/log', [\App\Http\Controllers\Api\AppController::class, 'log'])->withoutMiddleware('auth:sanctum')->middleware('throttle:10,1');
Route::post('/pusher/auth', [\App\Http\Controllers\Api\PusherController::class, 'auth']);
Route::get('/pusher/auth', [\App\Http\Controllers\Api\PusherController::class, 'auth']);
Route::get('/pusher/config', [\App\Http\Controllers\Api\PusherController::class, 'config']);
Route::post('/webauthn/register-challenge', [\App\Http\Controllers\Api\WebAuthnController::class, 'registerChallenge']);
Route::post('/webauthn/register-verify', [\App\Http\Controllers\Api\WebAuthnController::class, 'registerVerify']);
// NOTE: POST /webauthn/auth-challenge, /webauthn/auth-verify, /webauthn/login/options, /webauthn/login/verify
// are public routes (registered above auth group) — they're pre-login flows
Route::post('/webauthn/remove', [\App\Http\Controllers\Api\WebAuthnController::class, 'remove']);
Route::post('/webauthn/rename', [\App\Http\Controllers\Api\WebAuthnController::class, 'rename']);
Route::post('/webauthn/remove-all', [\App\Http\Controllers\Api\WebAuthnController::class, 'removeAll']);
Route::get('/webauthn/credentials', [\App\Http\Controllers\Api\WebAuthnController::class, 'credentials']);
Route::get('/webauthn/status', [\App\Http\Controllers\Api\WebAuthnController::class, 'status']);
Route::post('/ai/chat', [\App\Http\Controllers\Api\AiChatController::class, 'chat']);
Route::post('/ai/chat/stream', [\App\Http\Controllers\Api\AiChatController::class, 'streamChat']);
Route::get('/ai/conversations', [\App\Http\Controllers\Api\AiChatController::class, 'listConversations']);
Route::get('/ai/conversations/{id}', [\App\Http\Controllers\Api\AiChatController::class, 'getConversation']);
Route::post('/ai/conversations', [\App\Http\Controllers\Api\AiChatController::class, 'createConversation']);
Route::delete('/ai/conversations/{id}', [\App\Http\Controllers\Api\AiChatController::class, 'deleteConversation']);
Route::get('/ai/providers', [\App\Http\Controllers\Api\AiChatController::class, 'getProviders']);
Route::get('/ai/limits', [\App\Http\Controllers\Api\AiChatController::class, 'getLimits']);
Route::post('/ai/test-provider', [\App\Http\Controllers\Api\AiChatController::class, 'testProvider']);
Route::post('/ai/generate/listing', [\App\Http\Controllers\Api\AiChatController::class, 'generateListing']);
Route::post('/ai/generate/event', [\App\Http\Controllers\Api\AiChatController::class, 'generateEvent']);
Route::post('/ai/generate/message', [\App\Http\Controllers\Api\AiChatController::class, 'generateMessage']);
Route::post('/ai/generate/bio', [\App\Http\Controllers\Api\AiChatController::class, 'generateBio']);
Route::post('/ai/generate/newsletter', [\App\Http\Controllers\Api\AiChatController::class, 'generateNewsletter']);
Route::post('/ai/generate/blog', [\App\Http\Controllers\Api\AiChatController::class, 'generateBlog']);
Route::post('/ai/generate/page', [\App\Http\Controllers\Api\AiChatController::class, 'generatePage']);
Route::post('/menus/clear-cache', [\App\Http\Controllers\Api\MenuController::class, 'clearCache']);
// NOTE: GET /menus, /menus/config, /menus/mobile, /menus/{slug} are public routes (registered above auth group)
// NOTE: POST /v2/contact is a public route (registered above auth group)
Route::post('/help/feedback', [\App\Http\Controllers\Api\HelpController::class, 'feedback']);
Route::get('/groups/{id}/analytics', [\App\Http\Controllers\Api\AdminGroupsController::class, 'apiData'])->middleware(['auth:sanctum', 'admin']);
Route::get('/recommendations/groups', [\App\Http\Controllers\Api\GroupRecommendController::class, 'index']);
Route::post('/recommendations/track', [\App\Http\Controllers\Api\GroupRecommendController::class, 'track']);
Route::get('/recommendations/metrics', [\App\Http\Controllers\Api\GroupRecommendController::class, 'metrics']);
Route::get('/recommendations/similar/{id}', [\App\Http\Controllers\Api\GroupRecommendController::class, 'similar']);
Route::post('/notifications/settings', [\App\Http\Controllers\Api\UsersController::class, 'updateSettings']);
Route::get('/leaderboard', [\App\Http\Controllers\Api\GamificationController::class, 'api']);
Route::get('/leaderboard/widget', [\App\Http\Controllers\Api\GamificationController::class, 'widget']);
Route::get('/streaks', [\App\Http\Controllers\Api\GamificationController::class, 'streaks']);
Route::get('/achievements', [\App\Http\Controllers\Api\GamificationController::class, 'api']);
Route::get('/achievements/progress', [\App\Http\Controllers\Api\GamificationController::class, 'progress']);
Route::post('/daily-reward/check', [\App\Http\Controllers\Api\GamificationController::class, 'checkDailyReward']);
Route::get('/daily-reward/status', [\App\Http\Controllers\Api\GamificationController::class, 'getDailyStatus']);
Route::get('/gamification/challenges', [\App\Http\Controllers\Api\GamificationController::class, 'getChallenges']);
Route::get('/gamification/collections', [\App\Http\Controllers\Api\GamificationController::class, 'getCollections']);
Route::get('/gamification/shop', [\App\Http\Controllers\Api\GamificationController::class, 'getShopItems']);
Route::post('/gamification/shop/purchase', [\App\Http\Controllers\Api\GamificationController::class, 'purchaseItem']);
Route::get('/gamification/summary', [\App\Http\Controllers\Api\GamificationController::class, 'getSummary']);
Route::post('/gamification/showcase', [\App\Http\Controllers\Api\GamificationController::class, 'updateShowcase']);
Route::get('/gamification/showcased', [\App\Http\Controllers\Api\GamificationController::class, 'getShowcasedBadges']);
Route::get('/gamification/share', [\App\Http\Controllers\Api\GamificationController::class, 'shareAchievement']);
Route::get('/gamification/seasons', [\App\Http\Controllers\Api\GamificationController::class, 'getSeasons']);
Route::get('/gamification/seasons/current', [\App\Http\Controllers\Api\GamificationController::class, 'getCurrentSeason']);
Route::post('/shop/purchase', [\App\Http\Controllers\Api\GamificationController::class, 'purchaseItem']);
Route::get('/insights', [\App\Http\Controllers\Api\AdminDashboardController::class, 'apiInsights'])->middleware(['auth:sanctum', 'admin']);
Route::get('/organizations/{id}/members', [\App\Http\Controllers\Api\OrgWalletController::class, 'apiMembers']);
Route::get('/organizations/{id}/wallet/balance', [\App\Http\Controllers\Api\OrgWalletController::class, 'apiBalance']);
Route::post('/feed/hide', [\App\Http\Controllers\Api\FeedController::class, 'hidePost']);
Route::post('/feed/mute', [\App\Http\Controllers\Api\FeedController::class, 'muteUser']);
Route::post('/feed/report', [\App\Http\Controllers\Api\FeedController::class, 'reportPost']);
Route::post('/messages/voice', [\App\Http\Controllers\Api\VoiceMessageController::class, 'store']);
// Legacy message routes removed — all clients use /v2/messages (MessagesController)
Route::post('/messages/delete', [\App\Http\Controllers\Api\MessagesController::class, 'deleteMessage']);
Route::post('/messages/delete-conversation', [\App\Http\Controllers\Api\MessagesController::class, 'deleteConversation']);
Route::post('/messages/reaction', [\App\Http\Controllers\Api\MessagesController::class, 'toggleReaction']);
Route::get('/messages/reactions-batch', [\App\Http\Controllers\Api\MessagesController::class, 'getReactionsBatch']);
Route::get('/admin/users/search', [\App\Http\Controllers\Api\AdminTimebankingController::class, 'userSearchApi'])->middleware(['auth:sanctum', 'admin']);
// Newsletter unsubscribe/tracking — moved to public routes (no auth required)
// See public section below line 1244
Route::post('/gdpr/consent', [\App\Http\Controllers\Api\GdprController::class, 'updateConsent']);
Route::post('/gdpr/request', [\App\Http\Controllers\Api\GdprController::class, 'createRequest']);
Route::post('/gdpr/delete-account', [\App\Http\Controllers\Api\GdprController::class, 'deleteAccount']);
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/v2/admin/community-analytics', [\App\Http\Controllers\Api\AdminCommunityAnalyticsController::class, 'index']);
    Route::get('/v2/admin/community-analytics/export', [\App\Http\Controllers\Api\AdminCommunityAnalyticsController::class, 'export']);
    Route::get('/v2/admin/community-analytics/geography', [\App\Http\Controllers\Api\AdminCommunityAnalyticsController::class, 'geography']);
    Route::get('/v2/admin/impact-report', [\App\Http\Controllers\Api\AdminImpactReportController::class, 'index']);
    Route::put('/v2/admin/impact-report/config', [\App\Http\Controllers\Api\AdminImpactReportController::class, 'updateConfig']);
});
// ── Onboarding — Sanctum auth required ───────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/v2/onboarding/status', [\App\Http\Controllers\Api\OnboardingController::class, 'status']);
    Route::get('/v2/onboarding/config', [\App\Http\Controllers\Api\OnboardingController::class, 'getConfig']);
    Route::get('/v2/onboarding/categories', [\App\Http\Controllers\Api\OnboardingController::class, 'categories']);
    Route::get('/v2/onboarding/safeguarding-options', [\App\Http\Controllers\Api\OnboardingController::class, 'safeguardingOptions']);
    Route::post('/v2/onboarding/safeguarding', [\App\Http\Controllers\Api\OnboardingController::class, 'saveSafeguarding'])->middleware('throttle:5,1');
    Route::post('/v2/onboarding/complete', [\App\Http\Controllers\Api\OnboardingController::class, 'complete'])->middleware('throttle:5,1');
});
Route::get('/v2/group-exchanges', [\App\Http\Controllers\Api\GroupExchangeController::class, 'index']);
Route::post('/v2/group-exchanges', [\App\Http\Controllers\Api\GroupExchangeController::class, 'store']);
Route::get('/v2/group-exchanges/{id}', [\App\Http\Controllers\Api\GroupExchangeController::class, 'show']);
Route::put('/v2/group-exchanges/{id}', [\App\Http\Controllers\Api\GroupExchangeController::class, 'update']);
Route::delete('/v2/group-exchanges/{id}', [\App\Http\Controllers\Api\GroupExchangeController::class, 'destroy']);
Route::post('/v2/group-exchanges/{id}/participants', [\App\Http\Controllers\Api\GroupExchangeController::class, 'addParticipant']);
Route::delete('/v2/group-exchanges/{id}/participants/{userId}', [\App\Http\Controllers\Api\GroupExchangeController::class, 'removeParticipant']);
Route::post('/v2/group-exchanges/{id}/confirm', [\App\Http\Controllers\Api\GroupExchangeController::class, 'confirm']);
Route::post('/v2/group-exchanges/{id}/complete', [\App\Http\Controllers\Api\GroupExchangeController::class, 'complete']);
Route::get('/v2/wallet/statement', [\App\Http\Controllers\Api\WalletFeaturesController::class, 'statement']);
Route::get('/v2/wallet/categories', [\App\Http\Controllers\Api\WalletFeaturesController::class, 'listCategories']);
Route::post('/v2/wallet/categories', [\App\Http\Controllers\Api\WalletFeaturesController::class, 'createCategory']);
Route::put('/v2/wallet/categories/{id}', [\App\Http\Controllers\Api\WalletFeaturesController::class, 'updateCategory']);
Route::delete('/v2/wallet/categories/{id}', [\App\Http\Controllers\Api\WalletFeaturesController::class, 'deleteCategory']);
Route::get('/v2/wallet/community-fund', [\App\Http\Controllers\Api\WalletFeaturesController::class, 'communityFundBalance']);
Route::get('/v2/wallet/community-fund/transactions', [\App\Http\Controllers\Api\WalletFeaturesController::class, 'communityFundTransactions']);
Route::post('/v2/wallet/community-fund/deposit', [\App\Http\Controllers\Api\WalletFeaturesController::class, 'communityFundDeposit']);
Route::post('/v2/wallet/community-fund/withdraw', [\App\Http\Controllers\Api\WalletFeaturesController::class, 'communityFundWithdraw']);
Route::post('/v2/wallet/community-fund/donate', [\App\Http\Controllers\Api\WalletFeaturesController::class, 'communityFundDonate']);
Route::post('/v2/wallet/donate', [\App\Http\Controllers\Api\WalletFeaturesController::class, 'donate']);
Route::get('/v2/wallet/donations', [\App\Http\Controllers\Api\WalletFeaturesController::class, 'donationHistory']);
Route::get('/v2/wallet/starting-balance', [\App\Http\Controllers\Api\WalletFeaturesController::class, 'getStartingBalance']);
Route::put('/v2/wallet/starting-balance', [\App\Http\Controllers\Api\WalletFeaturesController::class, 'setStartingBalance']);
Route::post('/v2/exchanges/{id}/rate', [\App\Http\Controllers\Api\WalletFeaturesController::class, 'rateExchange']);
Route::get('/v2/exchanges/{id}/ratings', [\App\Http\Controllers\Api\WalletFeaturesController::class, 'exchangeRatings']);
Route::get('/v2/users/{id}/rating', [\App\Http\Controllers\Api\WalletFeaturesController::class, 'userRating']);
Route::post('/v2/events/recurring', [\App\Http\Controllers\Api\EventsController::class, 'createRecurring']);
Route::get('/v2/events/series', [\App\Http\Controllers\Api\EventsController::class, 'listSeries']);
Route::post('/v2/events/series', [\App\Http\Controllers\Api\EventsController::class, 'createSeries']);
Route::get('/v2/events/series/{seriesId}', [\App\Http\Controllers\Api\EventsController::class, 'showSeries']);
Route::put('/v2/events/{id}/recurring', [\App\Http\Controllers\Api\EventsController::class, 'updateRecurring']);
// cancel duplicate removed — registered at line 86
Route::get('/v2/events/{id}/waitlist', [\App\Http\Controllers\Api\EventsController::class, 'waitlist']);
// POST/DELETE waitlist duplicates removed — registered at lines 87-88
Route::get('/v2/events/{id}/reminders', [\App\Http\Controllers\Api\EventsController::class, 'getReminders']);
Route::put('/v2/events/{id}/reminders', [\App\Http\Controllers\Api\EventsController::class, 'updateReminders']);
Route::get('/v2/events/{id}/attendance', [\App\Http\Controllers\Api\EventsController::class, 'getAttendance']);
Route::post('/v2/events/{id}/attendance', [\App\Http\Controllers\Api\EventsController::class, 'markAttendance']);
Route::post('/v2/events/{id}/attendance/bulk', [\App\Http\Controllers\Api\EventsController::class, 'bulkMarkAttendance']);
Route::post('/v2/events/{id}/series', [\App\Http\Controllers\Api\EventsController::class, 'linkToSeries']);
Route::get('/v2/volunteering/recommended-shifts', [\App\Http\Controllers\Api\VolunteerController::class, 'recommendedShifts']);
Route::get('/v2/volunteering/certificates', [\App\Http\Controllers\Api\VolunteerCertificateController::class, 'myCertificates']);
Route::post('/v2/volunteering/certificates', [\App\Http\Controllers\Api\VolunteerCertificateController::class, 'generateCertificate']);
Route::get('/v2/volunteering/certificates/verify/{code}', [\App\Http\Controllers\Api\VolunteerCertificateController::class, 'verifyCertificate'])->withoutMiddleware('auth:sanctum');
Route::get('/v2/volunteering/certificates/{code}/html', [\App\Http\Controllers\Api\VolunteerCertificateController::class, 'certificateHtml'])->withoutMiddleware('auth:sanctum');
Route::get('/v2/volunteering/credentials', [\App\Http\Controllers\Api\VolunteerCertificateController::class, 'myCredentials']);
Route::post('/v2/volunteering/credentials', [\App\Http\Controllers\Api\VolunteerCertificateController::class, 'uploadCredential']);
Route::delete('/v2/volunteering/credentials/{id}', [\App\Http\Controllers\Api\VolunteerCertificateController::class, 'deleteCredential']);
Route::get('/v2/volunteering/emergency-alerts', [\App\Http\Controllers\Api\VolunteerWellbeingController::class, 'myEmergencyAlerts']);
Route::post('/v2/volunteering/emergency-alerts', [\App\Http\Controllers\Api\VolunteerWellbeingController::class, 'createEmergencyAlert']);
Route::put('/v2/volunteering/emergency-alerts/{id}', [\App\Http\Controllers\Api\VolunteerWellbeingController::class, 'respondToEmergencyAlert']);
Route::delete('/v2/volunteering/emergency-alerts/{id}', [\App\Http\Controllers\Api\VolunteerWellbeingController::class, 'cancelEmergencyAlert']);
Route::get('/v2/volunteering/wellbeing', [\App\Http\Controllers\Api\VolunteerWellbeingController::class, 'wellbeingDashboard']);
Route::post('/v2/volunteering/wellbeing/checkin', [\App\Http\Controllers\Api\VolunteerWellbeingController::class, 'wellbeingCheckin']);
Route::get('/v2/volunteering/wellbeing/my-status', [\App\Http\Controllers\Api\VolunteerWellbeingController::class, 'myWellbeingStatus']);
Route::get('/v2/volunteering/swaps', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'getSwapRequests']);
Route::post('/v2/volunteering/swaps', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'requestSwap']);
Route::put('/v2/volunteering/swaps/{id}', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'respondToSwap']);
Route::delete('/v2/volunteering/swaps/{id}', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'cancelSwap']);
Route::get('/v2/volunteering/admin/swaps', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'adminPendingSwaps']);
Route::put('/v2/volunteering/admin/swaps/{id}', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'adminDecideSwap']);
Route::get('/v2/volunteering/my-waitlists', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'myWaitlists']);
Route::post('/v2/volunteering/shifts/{id}/waitlist', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'joinWaitlist']);
Route::delete('/v2/volunteering/shifts/{id}/waitlist', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'leaveWaitlist']);
Route::post('/v2/volunteering/shifts/{id}/waitlist/promote', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'promoteFromWaitlist']);
Route::get('/v2/volunteering/group-reservations', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'myGroupReservations']);
Route::post('/v2/volunteering/shifts/{id}/group-reserve', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'groupReserve']);
Route::post('/v2/volunteering/group-reservations/{id}/members', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'addGroupMember']);
Route::delete('/v2/volunteering/group-reservations/{id}/members/{userId}', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'removeGroupMember']);
Route::delete('/v2/volunteering/group-reservations/{id}', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'cancelGroupReservation']);
Route::get('/v2/volunteering/shifts/{id}/checkin', [\App\Http\Controllers\Api\VolunteerCheckInController::class, 'getCheckIn']);
Route::post('/v2/volunteering/checkin/verify/{token}', [\App\Http\Controllers\Api\VolunteerCheckInController::class, 'verifyCheckIn']);
Route::post('/v2/volunteering/checkin/checkout/{token}', [\App\Http\Controllers\Api\VolunteerCheckInController::class, 'checkOut']);
Route::get('/v2/volunteering/shifts/{id}/checkins', [\App\Http\Controllers\Api\VolunteerCheckInController::class, 'shiftCheckIns']);
Route::get('/v2/volunteering/opportunities/{id}/recurring-patterns', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'recurringPatterns']);
Route::post('/v2/volunteering/opportunities/{id}/recurring-patterns', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'createRecurringPattern']);
Route::put('/v2/volunteering/recurring-patterns/{id}', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'updateRecurringPattern']);
Route::delete('/v2/volunteering/recurring-patterns/{id}', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'deleteRecurringPattern']);
Route::get('/v2/volunteering/expenses', [\App\Http\Controllers\Api\VolunteerExpenseController::class, 'myExpenses']);
Route::post('/v2/volunteering/expenses', [\App\Http\Controllers\Api\VolunteerExpenseController::class, 'submitExpense']);
Route::get('/v2/volunteering/expenses/{id}', [\App\Http\Controllers\Api\VolunteerExpenseController::class, 'getExpense']);
Route::get('/v2/volunteering/guardian-consents', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'myGuardianConsents']);
Route::post('/v2/volunteering/guardian-consents', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'requestGuardianConsent']);
Route::get('/v2/volunteering/guardian-consents/verify/{token}', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'verifyGuardianConsent'])->withoutMiddleware('auth:sanctum');
Route::delete('/v2/volunteering/guardian-consents/{id}', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'withdrawGuardianConsent']);
Route::get('/v2/volunteering/training', [\App\Http\Controllers\Api\VolunteerWellbeingController::class, 'myTraining']);
Route::post('/v2/volunteering/training', [\App\Http\Controllers\Api\VolunteerWellbeingController::class, 'recordTraining']);
Route::post('/v2/volunteering/incidents', [\App\Http\Controllers\Api\VolunteerWellbeingController::class, 'reportIncident']);
Route::get('/v2/volunteering/incidents', [\App\Http\Controllers\Api\VolunteerWellbeingController::class, 'getIncidents']);
Route::get('/v2/volunteering/incidents/{id}', [\App\Http\Controllers\Api\VolunteerWellbeingController::class, 'getIncident']);
Route::get('/v2/volunteering/custom-fields', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'getCustomFields']);
Route::get('/v2/volunteering/accessibility-needs', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'myAccessibilityNeeds']);
Route::put('/v2/volunteering/accessibility-needs', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'updateAccessibilityNeeds']);
Route::get('/v2/volunteering/community-projects', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'getCommunityProjects']);
Route::post('/v2/volunteering/community-projects', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'proposeCommunityProject']);
Route::get('/v2/volunteering/community-projects/{id}', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'getCommunityProject']);
Route::put('/v2/volunteering/community-projects/{id}', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'updateCommunityProject']);
Route::post('/v2/volunteering/community-projects/{id}/support', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'supportCommunityProject']);
Route::delete('/v2/volunteering/community-projects/{id}/support', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'unsupportCommunityProject']);
Route::get('/v2/volunteering/donations', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'getDonations']);
Route::post('/v2/volunteering/donations', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'createDonation']);
Route::get('/v2/volunteering/giving-days', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'getGivingDays']);
Route::get('/v2/volunteering/giving-days/{id}/stats', [\App\Http\Controllers\Api\VolunteerCommunityController::class, 'getGivingDayStats']);
Route::get('/v2/ideation-categories', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'listCategories']);
Route::post('/v2/ideation-categories', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'createCategory'])->middleware('admin');
Route::put('/v2/ideation-categories/{id}', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'updateCategory'])->middleware('admin');
Route::delete('/v2/ideation-categories/{id}', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'deleteCategory'])->middleware('admin');
Route::get('/v2/ideation-tags/popular', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'popularTags']);
Route::get('/v2/ideation-tags', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'listTags']);
Route::post('/v2/ideation-tags', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'createTag']);
Route::delete('/v2/ideation-tags/{id}', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'deleteTag']);
Route::get('/v2/ideation-ideas/{id}/media', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'listIdeaMedia']);
Route::post('/v2/ideation-ideas/{id}/media', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'addIdeaMedia']);
Route::delete('/v2/ideation-media/{id}', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'deleteIdeaMedia']);
Route::get('/v2/ideation-campaigns', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'listCampaigns']);
Route::get('/v2/ideation-campaigns/{id}', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'showCampaign']);
Route::post('/v2/ideation-campaigns', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'createCampaign']);
Route::put('/v2/ideation-campaigns/{id}', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'updateCampaign']);
Route::delete('/v2/ideation-campaigns/{id}', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'deleteCampaign']);
Route::post('/v2/ideation-campaigns/{id}/challenges', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'linkChallengeToCampaign']);
Route::delete('/v2/ideation-campaigns/{id}/challenges/{challengeId}', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'unlinkChallengeFromCampaign']);
Route::get('/v2/ideation-templates', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'listTemplates']);
Route::get('/v2/ideation-templates/{id}', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'showTemplate']);
Route::post('/v2/ideation-templates', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'createTemplate']);
Route::put('/v2/ideation-templates/{id}', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'updateTemplate']);
Route::delete('/v2/ideation-templates/{id}', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'deleteTemplate']);
Route::get('/v2/ideation-templates/{id}/data', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'getTemplateData']);
Route::get('/v2/ideation-challenges/{id}/outcome', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'getOutcome']);
Route::put('/v2/ideation-challenges/{id}/outcome', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'upsertOutcome']);
Route::get('/v2/ideation-outcomes/dashboard', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'outcomesDashboard']);
Route::get('/v2/ideation-challenges/{id}/team-links', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'getTeamLinks']);
Route::get('/v2/groups/{id}/chatrooms', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'listChatrooms']);
Route::post('/v2/groups/{id}/chatrooms', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'createChatroom']);
Route::delete('/v2/group-chatrooms/{id}', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'deleteChatroom']);
Route::get('/v2/group-chatrooms/{id}/messages', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'chatroomMessages']);
Route::post('/v2/group-chatrooms/{id}/messages', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'postChatroomMessage']);
Route::delete('/v2/group-chatroom-messages/{id}', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'deleteChatroomMessage']);
Route::post('/v2/groups/{groupId}/chatrooms/{chatroomId}/pin/{messageId}', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'pinChatroomMessage']);
Route::delete('/v2/groups/{groupId}/chatrooms/{chatroomId}/pin/{messageId}', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'unpinChatroomMessage']);
Route::get('/v2/groups/{groupId}/chatrooms/{chatroomId}/pinned', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'pinnedChatroomMessages']);
Route::get('/v2/groups/{id}/tasks', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'listTasks']);
Route::post('/v2/groups/{id}/tasks', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'createTask']);
Route::get('/v2/team-tasks/{id}', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'showTask']);
Route::put('/v2/team-tasks/{id}', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'updateTask']);
Route::delete('/v2/team-tasks/{id}', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'deleteTask']);
Route::get('/v2/groups/{id}/task-stats', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'taskStats']);
Route::get('/v2/groups/{id}/documents', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'listDocuments']);
Route::post('/v2/groups/{id}/documents', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'uploadDocument']);
Route::delete('/v2/team-documents/{id}', [\App\Http\Controllers\Api\IdeationChallengesController::class, 'deleteDocument']);
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/v2/admin/members/inactive', [\App\Http\Controllers\Api\AdminAnalyticsReportsController::class, 'inactiveMembers']);
    Route::post('/v2/admin/members/inactive/detect', [\App\Http\Controllers\Api\AdminAnalyticsReportsController::class, 'detectInactive']);
    Route::post('/v2/admin/members/inactive/notify', [\App\Http\Controllers\Api\AdminAnalyticsReportsController::class, 'markInactiveNotified']);
    Route::get('/v2/admin/moderation/queue', [\App\Http\Controllers\Api\AdminAnalyticsReportsController::class, 'moderationQueue']);
    Route::post('/v2/admin/moderation/{id}/review', [\App\Http\Controllers\Api\AdminAnalyticsReportsController::class, 'moderationReview']);
    Route::get('/v2/admin/moderation/stats', [\App\Http\Controllers\Api\AdminAnalyticsReportsController::class, 'moderationStats']);
    Route::get('/v2/admin/moderation/settings', [\App\Http\Controllers\Api\AdminAnalyticsReportsController::class, 'moderationSettings']);
    Route::put('/v2/admin/moderation/settings', [\App\Http\Controllers\Api\AdminAnalyticsReportsController::class, 'updateModerationSettings']);
});
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('/v2/admin/listings/{id}/feature', [\App\Http\Controllers\Api\AdminListingsController::class, 'feature']);
    Route::delete('/v2/admin/listings/{id}/feature', [\App\Http\Controllers\Api\AdminListingsController::class, 'unfeature']);
    Route::post('/v2/admin/listings/{id}/reject', [\App\Http\Controllers\Api\AdminListingsController::class, 'reject']);
});
// search saved/trending duplicates removed — registered at lines 345-349
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/v2/admin/search/analytics', [\App\Http\Controllers\Api\AdminListingsController::class, 'searchAnalytics']);
    Route::get('/v2/admin/search/trending', [\App\Http\Controllers\Api\AdminListingsController::class, 'searchTrending']);
    Route::get('/v2/admin/search/zero-results', [\App\Http\Controllers\Api\AdminListingsController::class, 'searchZeroResults']);
});
// Matching routes — auth:sanctum required (controller uses $this->requireAuth())
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/v2/matches/all', [\App\Http\Controllers\Api\MatchingController::class, 'allMatches']);
    Route::post('/v2/matches/{id}/dismiss', [\App\Http\Controllers\Api\MatchingController::class, 'dismiss']);
});

// ============================================
// MIGRATED ROUTES — Legacy API (Polls, Goals, Events, Wallet, Cookie Consent, Legal, Nexus Score, Notifications, Listings)
// Source: httpdocs/routes/legacy-api.php
// ============================================
Route::get('/polls', [\App\Http\Controllers\Api\PollsController::class, 'index']);
Route::post('/polls/vote', [\App\Http\Controllers\Api\PollsController::class, 'vote']);
Route::get('/goals', [\App\Http\Controllers\Api\GoalsController::class, 'index']);
Route::post('/goals/update', [\App\Http\Controllers\Api\GoalsController::class, 'updateProgress']);
Route::post('/goals/offer-buddy', [\App\Http\Controllers\Api\GoalsController::class, 'offerBuddy']);
Route::get('/vol_opportunities', [\App\Http\Controllers\Api\VolunteerController::class, 'index']);
Route::get('/events', [\App\Http\Controllers\Api\EventsController::class, 'index']);
Route::post('/events/rsvp', [\App\Http\Controllers\Api\EventsController::class, 'rsvp']);
Route::get('/wallet/balance', [\App\Http\Controllers\Api\WalletController::class, 'balance']);
Route::get('/cookie-consent', [\App\Http\Controllers\Api\CookieConsentController::class, 'show']);
Route::post('/cookie-consent', [\App\Http\Controllers\Api\CookieConsentController::class, 'store']);
Route::get('/cookie-consent/inventory', [\App\Http\Controllers\Api\CookieConsentController::class, 'inventory']);
Route::get('/cookie-consent/check/{category}', [\App\Http\Controllers\Api\CookieConsentController::class, 'check']);
Route::put('/cookie-consent/{id}', [\App\Http\Controllers\Api\CookieConsentController::class, 'update']);
Route::delete('/cookie-consent/{id}', [\App\Http\Controllers\Api\CookieConsentController::class, 'withdraw']);
// Legal document routes are registered OUTSIDE this auth group (see below)
// because GET /v2/legal/{type} must be public — the React useLegalDocument
// hook fetches custom legal docs without authentication (skipAuth: true).
// Keeping them here caused a recurring regression: the API returned 401,
// the hook silently fell back, and tenants lost their custom policies.
Route::get('/nexus-score', [\App\Http\Controllers\Api\GamificationController::class, 'apiGetScore']);
Route::post('/nexus-score/recalculate', [\App\Http\Controllers\Api\GamificationController::class, 'apiRecalculateScores']);
Route::get('/wallet/transactions', [\App\Http\Controllers\Api\WalletController::class, 'transactions']);
Route::get('/wallet/pending-count', [\App\Http\Controllers\Api\WalletController::class, 'pendingCount']);
Route::post('/wallet/transfer', [\App\Http\Controllers\Api\WalletController::class, 'transfer']);
Route::post('/wallet/delete', [\App\Http\Controllers\Api\WalletController::class, 'delete']);
Route::post('/wallet/user-search', [\App\Http\Controllers\Api\WalletController::class, 'userSearch']);
Route::get('/members', [\App\Http\Controllers\Api\CoreController::class, 'members']);
Route::get('/listings', [\App\Http\Controllers\Api\CoreController::class, 'listings']);
Route::get('/groups', [\App\Http\Controllers\Api\CoreController::class, 'groups']);
// Legacy GET /messages removed — all clients use /v2/messages (MessagesController)
Route::get('/notifications', [\App\Http\Controllers\Api\CoreController::class, 'notifications']);
Route::get('/notifications/check', [\App\Http\Controllers\Api\CoreController::class, 'checkNotifications']);
Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\CoreController::class, 'unreadCount']);
Route::get('/notifications/poll', [\App\Http\Controllers\Api\NotificationsController::class, 'poll']);
Route::post('/notifications/read', [\App\Http\Controllers\Api\NotificationsController::class, 'markRead']);
Route::post('/notifications/delete', [\App\Http\Controllers\Api\NotificationsController::class, 'delete']);
Route::post('/listings/delete', [\App\Http\Controllers\Api\ListingsController::class, 'delete']);

}); // End Route::middleware('auth:sanctum') — Misc/legacy routes

// ============================================
// MIGRATED ROUTES — Federation API V1
// Source: httpdocs/routes/federation-api-v1.php
// These routes use their own fedAuth() method (FederationApiMiddleware)
// for authentication — they must NOT be inside auth:sanctum.
// ============================================
Route::get('/v1/federation', [\App\Http\Controllers\Api\FederationController::class, 'index']);
Route::get('/v1/federation/timebanks', [\App\Http\Controllers\Api\FederationController::class, 'timebanks']);
Route::get('/v1/federation/members', [\App\Http\Controllers\Api\FederationController::class, 'members']);
Route::get('/v1/federation/members/{id}', [\App\Http\Controllers\Api\FederationController::class, 'member']);
Route::get('/v1/federation/listings', [\App\Http\Controllers\Api\FederationController::class, 'listings']);
Route::get('/v1/federation/listings/{id}', [\App\Http\Controllers\Api\FederationController::class, 'listing']);
Route::get('/v1/federation/messages', [\App\Http\Controllers\Api\FederationController::class, 'getMessages']);
Route::get('/v1/federation/reviews', [\App\Http\Controllers\Api\FederationController::class, 'getReviews']);
Route::get('/v1/federation/transactions/{id}', [\App\Http\Controllers\Api\FederationController::class, 'getTransaction']);
// Write operations rate-limited to prevent abuse (20 req/min per IP)
Route::middleware('throttle:20,1')->group(function () {
    Route::post('/v1/federation/messages', [\App\Http\Controllers\Api\FederationController::class, 'sendMessage']);
    Route::post('/v1/federation/transactions', [\App\Http\Controllers\Api\FederationController::class, 'createTransaction']);
    Route::post('/v1/federation/reviews', [\App\Http\Controllers\Api\FederationController::class, 'createReview']);
});
Route::post('/v1/federation/oauth/token', [\App\Http\Controllers\Api\FederationController::class, 'oauthToken'])->middleware('throttle:10,1');
Route::post('/v1/federation/webhooks/test', [\App\Http\Controllers\Api\FederationController::class, 'testWebhook']);

// ============================================
// PUBLIC LEGAL DOCUMENT ROUTES — No auth required
// Custom tenant legal docs (Terms, Privacy, etc.) must be accessible
// without authentication — the React useLegalDocument hook fetches
// these with skipAuth:true for all visitors including non-logged-in users.
// ============================================
Route::get('/v2/legal/versions/compare', [\App\Http\Controllers\Api\LegalController::class, 'apiCompareVersions']);
Route::get('/v2/legal/version/{versionId}', [\App\Http\Controllers\Api\LegalController::class, 'apiGetVersion']);
Route::get('/v2/legal/{type}/versions', [\App\Http\Controllers\Api\LegalController::class, 'apiGetVersions']);
Route::get('/v2/legal/{type}', [\App\Http\Controllers\Api\LegalController::class, 'apiGetDocument']);

// Legal acceptance routes — require auth (user must be identified to record acceptance)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/v2/legal/acceptance/status', [\App\Http\Controllers\Api\LegalAcceptanceController::class, 'getStatus']);
    Route::post('/v2/legal/acceptance/accept-all', [\App\Http\Controllers\Api\LegalAcceptanceController::class, 'acceptAll']);
    Route::post('/legal/accept', [\App\Http\Controllers\Api\LegalController::class, 'accept']);
    Route::post('/legal/accept-all', [\App\Http\Controllers\Api\LegalController::class, 'acceptAll']);
    Route::get('/legal/status', [\App\Http\Controllers\Api\LegalController::class, 'status']);
});

// ============================================
// PUBLIC WEBHOOK ROUTES — No auth required
// SendGrid sends event notifications directly to this endpoint;
// it cannot authenticate via Sanctum tokens.
// ============================================
Route::post('/webhooks/sendgrid/events', [\App\Http\Controllers\Api\SendGridWebhookController::class, 'events'])->middleware('throttle:120,1');

// Identity verification provider webhooks (e.g., Onfido, Jumio)
// Must be public — providers send callbacks without Sanctum tokens.
Route::post('/v2/webhooks/identity/{provider_slug}', [\App\Http\Controllers\Api\IdentityWebhookController::class, 'handleWebhook'])->middleware('throttle:60,1');

// Stripe webhook (no auth, no CSRF — signature verified in controller)
Route::post('/v2/webhooks/stripe', [\App\Http\Controllers\Api\StripeWebhookController::class, 'handleWebhook'])->middleware('throttle:120,1');

// Public billing — available plans (pricing page, no auth required)
Route::get('/v2/billing/plans', [\App\Http\Controllers\Api\AdminBillingController::class, 'getPlansPublic']);
