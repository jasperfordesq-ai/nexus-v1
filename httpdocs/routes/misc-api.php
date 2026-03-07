<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.
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
$router->add('GET', '/api/push/vapid-public-key', 'Nexus\Controllers\Api\PushApiController@vapidKey'); // Legacy alias
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
// POST preferred (token in body); GET kept as deprecated fallback
$router->add('POST', '/api/auth/admin-session', 'Nexus\Controllers\Api\AuthController@adminSession');
$router->add('GET', '/api/auth/admin-session', 'Nexus\Controllers\Api\AuthController@adminSession');

// CSRF Token API (for SPAs using session auth - Bearer clients don't need this)
$router->add('GET', '/api/auth/csrf-token', 'Nexus\Controllers\Api\AuthController@getCsrfToken');
$router->add('GET', '/api/v2/csrf-token', 'Nexus\Controllers\Api\AuthController@getCsrfToken'); // V2 alias
$router->add('GET', '/api/csrf-token', 'Nexus\Controllers\Api\AuthController@getCsrfToken'); // SPA alias (used by React fetchCsrfToken)

// V2 Registration API (returns tokens immediately, field-level errors)
$router->add('POST', '/api/v2/auth/register', 'Nexus\Controllers\Api\RegistrationApiController@register');

// Identity Verification API (user-facing)
$router->add('GET', '/api/v2/auth/verification-status', 'Nexus\Controllers\Api\RegistrationPolicyApiController@getVerificationStatus');
$router->add('POST', '/api/v2/auth/start-verification', 'Nexus\Controllers\Api\RegistrationPolicyApiController@startVerification');
$router->add('POST', '/api/v2/auth/validate-invite', 'Nexus\Controllers\Api\RegistrationPolicyApiController@validateInviteCode');
$router->add('GET', '/api/v2/auth/registration-info', 'Nexus\Controllers\Api\RegistrationPolicyApiController@getRegistrationInfo');

// Identity Verification Webhooks (provider callbacks — no auth, signature-verified)
$router->add('POST', '/api/v2/webhooks/identity/{provider_slug}', 'Nexus\Controllers\Api\IdentityWebhookController@handleWebhook');

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
$router->add('POST', '/api/auth/resend-verification-by-email', 'Nexus\Controllers\Api\EmailVerificationApiController@resendVerificationByEmail');

// TOTP 2FA API (Legacy V1)
$router->add('POST', '/api/totp/verify', 'Nexus\Controllers\Api\TotpApiController@verify');
$router->add('GET', '/api/totp/status', 'Nexus\Controllers\Api\TotpApiController@status');

// TOTP 2FA API (V2 - Bearer token compatible, used by React SPA)
$router->add('GET', '/api/v2/auth/2fa/status', 'Nexus\Controllers\Api\TwoFactorApiController@status');
$router->add('POST', '/api/v2/auth/2fa/setup', 'Nexus\Controllers\Api\TwoFactorApiController@setup');
$router->add('POST', '/api/v2/auth/2fa/verify', 'Nexus\Controllers\Api\TwoFactorApiController@verify');
$router->add('POST', '/api/v2/auth/2fa/disable', 'Nexus\Controllers\Api\TwoFactorApiController@disable');

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
$router->add('POST', '/api/webauthn/rename', 'Nexus\Controllers\Api\WebAuthnApiController@rename');
$router->add('POST', '/api/webauthn/remove-all', 'Nexus\Controllers\Api\WebAuthnApiController@removeAll'); // SECURITY: Changed to POST only
$router->add('GET', '/api/webauthn/credentials', 'Nexus\Controllers\Api\WebAuthnApiController@credentials');
$router->add('GET', '/api/webauthn/status', 'Nexus\Controllers\Api\WebAuthnApiController@status'); // Status endpoint

// WebAuthn Aliases
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

// Menu API (for mobile apps)
$router->add('GET', '/api/menus', 'Nexus\Controllers\Api\MenuApiController@index');
$router->add('GET', '/api/menus/config', 'Nexus\Controllers\Api\MenuApiController@config');
$router->add('GET', '/api/menus/mobile', 'Nexus\Controllers\Api\MenuApiController@mobile');
$router->add('GET', '/api/menus/{slug}', 'Nexus\Controllers\Api\MenuApiController@show');
$router->add('POST', '/api/menus/clear-cache', 'Nexus\Controllers\Api\MenuApiController@clearCache');

$router->add('POST', '/api/v2/contact', 'Nexus\Controllers\ContactController@apiSubmit');
$router->add('POST', '/api/help/feedback', 'Nexus\Controllers\HelpController@feedback');
$router->add('GET', '/sitemap.xml', 'Nexus\Controllers\SitemapController@index');
$router->add('GET', '/robots.txt', 'Nexus\Controllers\RobotsController@index');

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
$router->add('GET', '/api/leaderboard', 'Nexus\Controllers\LeaderboardController@api');
$router->add('GET', '/api/leaderboard/widget', 'Nexus\Controllers\LeaderboardController@widget');
$router->add('GET', '/api/streaks', 'Nexus\Controllers\LeaderboardController@streaks');

// Achievements API
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
$router->add('GET', '/profile/edit', function () {
    $base = \Nexus\Core\TenantContext::getBasePath();
    header("Location: {$base}/settings?section=profile");
    exit;
});

// Connections (legacy PHP views removed — handled by React frontend and API)

$router->add('GET', '/admin-legacy/smart-matching', 'Nexus\Controllers\Admin\SmartMatchingController@index');
$router->add('GET', '/admin-legacy/smart-matching/analytics', 'Nexus\Controllers\Admin\SmartMatchingController@analytics');
$router->add('GET', '/admin-legacy/smart-matching/configuration', 'Nexus\Controllers\Admin\SmartMatchingController@configuration');
$router->add('POST', '/admin-legacy/smart-matching/configuration', 'Nexus\Controllers\Admin\SmartMatchingController@configuration');
$router->add('POST', '/admin-legacy/smart-matching/clear-cache', 'Nexus\Controllers\Admin\SmartMatchingController@clearCache');
$router->add('POST', '/admin-legacy/smart-matching/warmup-cache', 'Nexus\Controllers\Admin\SmartMatchingController@warmupCache');
$router->add('POST', '/admin-legacy/smart-matching/run-geocoding', 'Nexus\Controllers\Admin\SmartMatchingController@runGeocoding');
$router->add('GET', '/admin-legacy/smart-matching/api/stats', 'Nexus\Controllers\Admin\SmartMatchingController@apiStats');

$router->add('GET', '/admin-legacy/match-approvals', 'Nexus\Controllers\Admin\MatchApprovalsController@index');
$router->add('GET', '/admin-legacy/match-approvals/history', 'Nexus\Controllers\Admin\MatchApprovalsController@history');
$router->add('GET', '/admin-legacy/match-approvals/{id}', 'Nexus\Controllers\Admin\MatchApprovalsController@show');
$router->add('POST', '/admin-legacy/match-approvals/approve', 'Nexus\Controllers\Admin\MatchApprovalsController@approve');
$router->add('POST', '/admin-legacy/match-approvals/reject', 'Nexus\Controllers\Admin\MatchApprovalsController@reject');
$router->add('GET', '/admin-legacy/match-approvals/api/stats', 'Nexus\Controllers\Admin\MatchApprovalsController@apiStats');

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

$router->add('GET', '/admin-legacy/seed-generator', 'Nexus\Controllers\Admin\SeedGeneratorController@index');
$router->add('GET', '/admin-legacy/seed-generator/verification', 'Nexus\Controllers\Admin\SeedGeneratorVerificationController@index');
$router->add('POST', '/admin-legacy/seed-generator/generate-production', 'Nexus\Controllers\Admin\SeedGeneratorController@generateProduction');
$router->add('POST', '/admin-legacy/seed-generator/generate-demo', 'Nexus\Controllers\Admin\SeedGeneratorController@generateDemo');
$router->add('GET', '/admin-legacy/seed-generator/preview', 'Nexus\Controllers\Admin\SeedGeneratorController@preview');
$router->add('GET', '/admin-legacy/seed-generator/download', 'Nexus\Controllers\Admin\SeedGeneratorController@download');
$router->add('GET', '/admin-legacy/seed-generator/test', 'Nexus\Controllers\Admin\SeedGeneratorVerificationController@runLiveTest');

$router->add('GET', '/wallet/insights', 'Nexus\Controllers\InsightsController@index');
$router->add('GET', '/insights', 'Nexus\Controllers\InsightsController@index'); // Alias
$router->add('GET', '/api/insights', 'Nexus\Controllers\InsightsController@apiInsights');

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

$router->add('GET', '/feed', function() {
    header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/');
    exit;
});
$router->add('GET', '/post/{id}', 'Nexus\Controllers\FeedController@show');
$router->add('POST', '/feed/store', 'Nexus\Controllers\FeedController@store');
$router->add('POST', '/api/feed/hide', 'Nexus\Controllers\FeedController@hidePost');
$router->add('POST', '/api/feed/mute', 'Nexus\Controllers\FeedController@muteUser');
$router->add('POST', '/api/feed/report', 'Nexus\Controllers\FeedController@reportPost');

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

$router->add('GET', '/admin-legacy/algorithm-settings', 'Nexus\Controllers\AdminController@algorithmSettings');
$router->add('POST', '/admin-legacy/algorithm-settings/save', 'Nexus\Controllers\AdminController@saveAlgorithmSettings');

// Admin Live Search API (for command palette)
$router->add('GET', '/admin-legacy/api/search', 'Nexus\Controllers\AdminController@liveSearch');

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

$router->add('GET', '/newsletter/subscribe', 'Nexus\Controllers\NewsletterSubscriptionController@showForm');
$router->add('POST', '/newsletter/subscribe', 'Nexus\Controllers\NewsletterSubscriptionController@subscribe');
$router->add('GET', '/newsletter/confirm', 'Nexus\Controllers\NewsletterSubscriptionController@confirm');
$router->add('GET', '/newsletter/unsubscribe', 'Nexus\Controllers\NewsletterSubscriptionController@showUnsubscribe');
$router->add('POST', '/newsletter/unsubscribe', 'Nexus\Controllers\NewsletterSubscriptionController@unsubscribe');
$router->add('GET', '/newsletter/unsubscribe/confirm', 'Nexus\Controllers\NewsletterSubscriptionController@oneClickUnsubscribe');
$router->add('POST', '/newsletter/unsubscribe/confirm', 'Nexus\Controllers\NewsletterSubscriptionController@oneClickUnsubscribe');

// V2 JSON API for newsletter unsubscribe (called from the React frontend unsubscribe page)
$router->add('POST', '/api/v2/newsletter/unsubscribe', 'Nexus\Controllers\Api\NewsletterApiController@unsubscribe');

$router->add('GET', '/newsletter/track/open/{newsletterId}/{trackingToken}', 'Nexus\Controllers\NewsletterTrackingController@trackOpen');
$router->add('GET', '/newsletter/track/click/{newsletterId}/{linkId}/{trackingToken}', 'Nexus\Controllers\NewsletterTrackingController@trackClick');

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
$router->add('POST', '/api/gdpr/consent', 'Nexus\Controllers\Api\GdprApiController@updateConsent');
$router->add('POST', '/api/gdpr/request', 'Nexus\Controllers\Api\GdprApiController@createRequest');
$router->add('POST', '/api/gdpr/delete-account', 'Nexus\Controllers\Api\GdprApiController@deleteAccount');

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

// Identity Verification Maintenance
$router->add('GET', '/cron/verification-reminders', 'Nexus\Controllers\CronController@verificationReminders');
$router->add('GET', '/cron/expire-verifications', 'Nexus\Controllers\CronController@expireVerifications');

// Maintenance
$router->add('GET', '/cron/cleanup', 'Nexus\Controllers\CronController@cleanup');

// Master Cron (runs all tasks based on schedule)
$router->add('GET', '/cron/run-all', 'Nexus\Controllers\CronController@runAll');

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

// ============================================
// API V2 - WALLET FEATURES (New endpoints)
// ============================================
$router->add('GET', '/api/v2/wallet/statement', 'Nexus\Controllers\Api\WalletFeaturesApiController@statement');
$router->add('GET', '/api/v2/wallet/categories', 'Nexus\Controllers\Api\WalletFeaturesApiController@listCategories');
$router->add('POST', '/api/v2/wallet/categories', 'Nexus\Controllers\Api\WalletFeaturesApiController@createCategory');
$router->add('PUT', '/api/v2/wallet/categories/{id}', 'Nexus\Controllers\Api\WalletFeaturesApiController@updateCategory');
$router->add('DELETE', '/api/v2/wallet/categories/{id}', 'Nexus\Controllers\Api\WalletFeaturesApiController@deleteCategory');
$router->add('GET', '/api/v2/wallet/community-fund', 'Nexus\Controllers\Api\WalletFeaturesApiController@communityFundBalance');
$router->add('GET', '/api/v2/wallet/community-fund/transactions', 'Nexus\Controllers\Api\WalletFeaturesApiController@communityFundTransactions');
$router->add('POST', '/api/v2/wallet/community-fund/deposit', 'Nexus\Controllers\Api\WalletFeaturesApiController@communityFundDeposit');
$router->add('POST', '/api/v2/wallet/community-fund/withdraw', 'Nexus\Controllers\Api\WalletFeaturesApiController@communityFundWithdraw');
$router->add('POST', '/api/v2/wallet/community-fund/donate', 'Nexus\Controllers\Api\WalletFeaturesApiController@communityFundDonate');
$router->add('POST', '/api/v2/wallet/donate', 'Nexus\Controllers\Api\WalletFeaturesApiController@donate');
$router->add('GET', '/api/v2/wallet/donations', 'Nexus\Controllers\Api\WalletFeaturesApiController@donationHistory');
$router->add('GET', '/api/v2/wallet/starting-balance', 'Nexus\Controllers\Api\WalletFeaturesApiController@getStartingBalance');
$router->add('PUT', '/api/v2/wallet/starting-balance', 'Nexus\Controllers\Api\WalletFeaturesApiController@setStartingBalance');
$router->add('POST', '/api/v2/exchanges/{id}/rate', 'Nexus\Controllers\Api\WalletFeaturesApiController@rateExchange');
$router->add('GET', '/api/v2/exchanges/{id}/ratings', 'Nexus\Controllers\Api\WalletFeaturesApiController@exchangeRatings');
$router->add('GET', '/api/v2/users/{id}/rating', 'Nexus\Controllers\Api\WalletFeaturesApiController@userRating');

// ============================================
// API V2 - EVENT FEATURES (New endpoints)
// ============================================
$router->add('POST', '/api/v2/events/recurring', 'Nexus\Controllers\Api\EventsApiController@createRecurring');
$router->add('GET', '/api/v2/events/series', 'Nexus\Controllers\Api\EventsApiController@listSeries');
$router->add('POST', '/api/v2/events/series', 'Nexus\Controllers\Api\EventsApiController@createSeries');
$router->add('GET', '/api/v2/events/series/{seriesId}', 'Nexus\Controllers\Api\EventsApiController@showSeries');
$router->add('PUT', '/api/v2/events/{id}/recurring', 'Nexus\Controllers\Api\EventsApiController@updateRecurring');
$router->add('POST', '/api/v2/events/{id}/cancel', 'Nexus\Controllers\Api\EventsApiController@cancel');
$router->add('GET', '/api/v2/events/{id}/waitlist', 'Nexus\Controllers\Api\EventsApiController@waitlist');
$router->add('POST', '/api/v2/events/{id}/waitlist', 'Nexus\Controllers\Api\EventsApiController@joinWaitlist');
$router->add('DELETE', '/api/v2/events/{id}/waitlist', 'Nexus\Controllers\Api\EventsApiController@leaveWaitlist');
$router->add('GET', '/api/v2/events/{id}/reminders', 'Nexus\Controllers\Api\EventsApiController@getReminders');
$router->add('PUT', '/api/v2/events/{id}/reminders', 'Nexus\Controllers\Api\EventsApiController@updateReminders');
$router->add('GET', '/api/v2/events/{id}/attendance', 'Nexus\Controllers\Api\EventsApiController@getAttendance');
$router->add('POST', '/api/v2/events/{id}/attendance', 'Nexus\Controllers\Api\EventsApiController@markAttendance');
$router->add('POST', '/api/v2/events/{id}/attendance/bulk', 'Nexus\Controllers\Api\EventsApiController@bulkMarkAttendance');
$router->add('POST', '/api/v2/events/{id}/series', 'Nexus\Controllers\Api\EventsApiController@linkToSeries');

// ============================================
// API V2 - VOLUNTEERING FEATURES (New endpoints)
// ============================================
$router->add('GET', '/api/v2/volunteering/recommended-shifts', 'Nexus\Controllers\Api\VolunteerApiController@recommendedShifts');
$router->add('GET', '/api/v2/volunteering/certificates', 'Nexus\Controllers\Api\VolunteerApiController@myCertificates');
$router->add('POST', '/api/v2/volunteering/certificates', 'Nexus\Controllers\Api\VolunteerApiController@generateCertificate');
$router->add('GET', '/api/v2/volunteering/certificates/verify/{code}', 'Nexus\Controllers\Api\VolunteerApiController@verifyCertificate');
$router->add('GET', '/api/v2/volunteering/certificates/{code}/html', 'Nexus\Controllers\Api\VolunteerApiController@certificateHtml');
// Credential verification (separate from impact certificates)
$router->add('GET', '/api/v2/volunteering/credentials', 'Nexus\Controllers\Api\VolunteerApiController@myCredentials');
$router->add('POST', '/api/v2/volunteering/credentials', 'Nexus\Controllers\Api\VolunteerApiController@uploadCredential');
$router->add('DELETE', '/api/v2/volunteering/credentials/{id}', 'Nexus\Controllers\Api\VolunteerApiController@deleteCredential');
$router->add('GET', '/api/v2/volunteering/emergency-alerts', 'Nexus\Controllers\Api\VolunteerApiController@myEmergencyAlerts');
$router->add('POST', '/api/v2/volunteering/emergency-alerts', 'Nexus\Controllers\Api\VolunteerApiController@createEmergencyAlert');
$router->add('PUT', '/api/v2/volunteering/emergency-alerts/{id}', 'Nexus\Controllers\Api\VolunteerApiController@respondToEmergencyAlert');
$router->add('DELETE', '/api/v2/volunteering/emergency-alerts/{id}', 'Nexus\Controllers\Api\VolunteerApiController@cancelEmergencyAlert');
$router->add('GET', '/api/v2/volunteering/wellbeing', 'Nexus\Controllers\Api\VolunteerApiController@wellbeingDashboard');
$router->add('POST', '/api/v2/volunteering/wellbeing/checkin', 'Nexus\Controllers\Api\VolunteerApiController@wellbeingCheckin');
$router->add('GET', '/api/v2/volunteering/wellbeing/my-status', 'Nexus\Controllers\Api\VolunteerApiController@myWellbeingStatus');
$router->add('GET', '/api/v2/volunteering/swaps', 'Nexus\Controllers\Api\VolunteerApiController@getSwapRequests');
$router->add('POST', '/api/v2/volunteering/swaps', 'Nexus\Controllers\Api\VolunteerApiController@requestSwap');
$router->add('PUT', '/api/v2/volunteering/swaps/{id}', 'Nexus\Controllers\Api\VolunteerApiController@respondToSwap');
$router->add('DELETE', '/api/v2/volunteering/swaps/{id}', 'Nexus\Controllers\Api\VolunteerApiController@cancelSwap');
$router->add('GET', '/api/v2/volunteering/my-waitlists', 'Nexus\Controllers\Api\VolunteerApiController@myWaitlists');
$router->add('POST', '/api/v2/volunteering/shifts/{id}/waitlist', 'Nexus\Controllers\Api\VolunteerApiController@joinWaitlist');
$router->add('DELETE', '/api/v2/volunteering/shifts/{id}/waitlist', 'Nexus\Controllers\Api\VolunteerApiController@leaveWaitlist');
$router->add('POST', '/api/v2/volunteering/shifts/{id}/waitlist/promote', 'Nexus\Controllers\Api\VolunteerApiController@promoteFromWaitlist');
$router->add('GET', '/api/v2/volunteering/group-reservations', 'Nexus\Controllers\Api\VolunteerApiController@myGroupReservations');
$router->add('POST', '/api/v2/volunteering/shifts/{id}/group-reserve', 'Nexus\Controllers\Api\VolunteerApiController@groupReserve');
$router->add('POST', '/api/v2/volunteering/group-reservations/{id}/members', 'Nexus\Controllers\Api\VolunteerApiController@addGroupMember');
$router->add('DELETE', '/api/v2/volunteering/group-reservations/{id}/members/{userId}', 'Nexus\Controllers\Api\VolunteerApiController@removeGroupMember');
$router->add('DELETE', '/api/v2/volunteering/group-reservations/{id}', 'Nexus\Controllers\Api\VolunteerApiController@cancelGroupReservation');
$router->add('GET', '/api/v2/volunteering/shifts/{id}/checkin', 'Nexus\Controllers\Api\VolunteerApiController@getCheckIn');
$router->add('POST', '/api/v2/volunteering/checkin/verify/{token}', 'Nexus\Controllers\Api\VolunteerApiController@verifyCheckIn');
$router->add('POST', '/api/v2/volunteering/checkin/checkout/{token}', 'Nexus\Controllers\Api\VolunteerApiController@checkOut');
$router->add('GET', '/api/v2/volunteering/shifts/{id}/checkins', 'Nexus\Controllers\Api\VolunteerApiController@shiftCheckIns');

// V8: Recurring shift patterns
$router->add('GET', '/api/v2/volunteering/opportunities/{id}/recurring-patterns', 'Nexus\Controllers\Api\VolunteerApiController@recurringPatterns');
$router->add('POST', '/api/v2/volunteering/opportunities/{id}/recurring-patterns', 'Nexus\Controllers\Api\VolunteerApiController@createRecurringPattern');
$router->add('PUT', '/api/v2/volunteering/recurring-patterns/{id}', 'Nexus\Controllers\Api\VolunteerApiController@updateRecurringPattern');
$router->add('DELETE', '/api/v2/volunteering/recurring-patterns/{id}', 'Nexus\Controllers\Api\VolunteerApiController@deleteRecurringPattern');

// ============================================
// API V2 - IDEATION FEATURES (New endpoints)
// ============================================
$router->add('GET', '/api/v2/ideation-categories', 'Nexus\Controllers\Api\IdeationChallengesApiController@listCategories');
$router->add('POST', '/api/v2/ideation-categories', 'Nexus\Controllers\Api\IdeationChallengesApiController@createCategory');
$router->add('PUT', '/api/v2/ideation-categories/{id}', 'Nexus\Controllers\Api\IdeationChallengesApiController@updateCategory');
$router->add('DELETE', '/api/v2/ideation-categories/{id}', 'Nexus\Controllers\Api\IdeationChallengesApiController@deleteCategory');
$router->add('GET', '/api/v2/ideation-tags/popular', 'Nexus\Controllers\Api\IdeationChallengesApiController@popularTags');
$router->add('GET', '/api/v2/ideation-tags', 'Nexus\Controllers\Api\IdeationChallengesApiController@listTags');
$router->add('POST', '/api/v2/ideation-tags', 'Nexus\Controllers\Api\IdeationChallengesApiController@createTag');
$router->add('DELETE', '/api/v2/ideation-tags/{id}', 'Nexus\Controllers\Api\IdeationChallengesApiController@deleteTag');
$router->add('GET', '/api/v2/ideation-ideas/{id}/media', 'Nexus\Controllers\Api\IdeationChallengesApiController@listIdeaMedia');
$router->add('POST', '/api/v2/ideation-ideas/{id}/media', 'Nexus\Controllers\Api\IdeationChallengesApiController@addIdeaMedia');
$router->add('DELETE', '/api/v2/ideation-media/{id}', 'Nexus\Controllers\Api\IdeationChallengesApiController@deleteIdeaMedia');
$router->add('GET', '/api/v2/ideation-campaigns', 'Nexus\Controllers\Api\IdeationChallengesApiController@listCampaigns');
$router->add('GET', '/api/v2/ideation-campaigns/{id}', 'Nexus\Controllers\Api\IdeationChallengesApiController@showCampaign');
$router->add('POST', '/api/v2/ideation-campaigns', 'Nexus\Controllers\Api\IdeationChallengesApiController@createCampaign');
$router->add('PUT', '/api/v2/ideation-campaigns/{id}', 'Nexus\Controllers\Api\IdeationChallengesApiController@updateCampaign');
$router->add('DELETE', '/api/v2/ideation-campaigns/{id}', 'Nexus\Controllers\Api\IdeationChallengesApiController@deleteCampaign');
$router->add('POST', '/api/v2/ideation-campaigns/{id}/challenges', 'Nexus\Controllers\Api\IdeationChallengesApiController@linkChallengeToCampaign');
$router->add('DELETE', '/api/v2/ideation-campaigns/{id}/challenges/{challengeId}', 'Nexus\Controllers\Api\IdeationChallengesApiController@unlinkChallengeFromCampaign');
$router->add('GET', '/api/v2/ideation-templates', 'Nexus\Controllers\Api\IdeationChallengesApiController@listTemplates');
$router->add('GET', '/api/v2/ideation-templates/{id}', 'Nexus\Controllers\Api\IdeationChallengesApiController@showTemplate');
$router->add('POST', '/api/v2/ideation-templates', 'Nexus\Controllers\Api\IdeationChallengesApiController@createTemplate');
$router->add('PUT', '/api/v2/ideation-templates/{id}', 'Nexus\Controllers\Api\IdeationChallengesApiController@updateTemplate');
$router->add('DELETE', '/api/v2/ideation-templates/{id}', 'Nexus\Controllers\Api\IdeationChallengesApiController@deleteTemplate');
$router->add('GET', '/api/v2/ideation-templates/{id}/data', 'Nexus\Controllers\Api\IdeationChallengesApiController@getTemplateData');
$router->add('GET', '/api/v2/ideation-challenges/{id}/outcome', 'Nexus\Controllers\Api\IdeationChallengesApiController@getOutcome');
$router->add('PUT', '/api/v2/ideation-challenges/{id}/outcome', 'Nexus\Controllers\Api\IdeationChallengesApiController@upsertOutcome');
$router->add('GET', '/api/v2/ideation-outcomes/dashboard', 'Nexus\Controllers\Api\IdeationChallengesApiController@outcomesDashboard');
$router->add('GET', '/api/v2/ideation-challenges/{id}/team-links', 'Nexus\Controllers\Api\IdeationChallengesApiController@getTeamLinks');
$router->add('GET', '/api/v2/groups/{id}/chatrooms', 'Nexus\Controllers\Api\IdeationChallengesApiController@listChatrooms');
$router->add('POST', '/api/v2/groups/{id}/chatrooms', 'Nexus\Controllers\Api\IdeationChallengesApiController@createChatroom');
$router->add('DELETE', '/api/v2/group-chatrooms/{id}', 'Nexus\Controllers\Api\IdeationChallengesApiController@deleteChatroom');
$router->add('GET', '/api/v2/group-chatrooms/{id}/messages', 'Nexus\Controllers\Api\IdeationChallengesApiController@chatroomMessages');
$router->add('POST', '/api/v2/group-chatrooms/{id}/messages', 'Nexus\Controllers\Api\IdeationChallengesApiController@postChatroomMessage');
$router->add('DELETE', '/api/v2/group-chatroom-messages/{id}', 'Nexus\Controllers\Api\IdeationChallengesApiController@deleteChatroomMessage');
$router->add('GET', '/api/v2/groups/{id}/tasks', 'Nexus\Controllers\Api\IdeationChallengesApiController@listTasks');
$router->add('POST', '/api/v2/groups/{id}/tasks', 'Nexus\Controllers\Api\IdeationChallengesApiController@createTask');
$router->add('GET', '/api/v2/team-tasks/{id}', 'Nexus\Controllers\Api\IdeationChallengesApiController@showTask');
$router->add('PUT', '/api/v2/team-tasks/{id}', 'Nexus\Controllers\Api\IdeationChallengesApiController@updateTask');
$router->add('DELETE', '/api/v2/team-tasks/{id}', 'Nexus\Controllers\Api\IdeationChallengesApiController@deleteTask');
$router->add('GET', '/api/v2/groups/{id}/task-stats', 'Nexus\Controllers\Api\IdeationChallengesApiController@taskStats');
$router->add('GET', '/api/v2/groups/{id}/documents', 'Nexus\Controllers\Api\IdeationChallengesApiController@listDocuments');
$router->add('POST', '/api/v2/groups/{id}/documents', 'Nexus\Controllers\Api\IdeationChallengesApiController@uploadDocument');
$router->add('DELETE', '/api/v2/team-documents/{id}', 'Nexus\Controllers\Api\IdeationChallengesApiController@deleteDocument');

// ============================================
// API V2 - ADMIN ANALYTICS & REPORTS (Additional endpoints)
// Note: /reports/social-value, /members, /hours, /export-types, /{type}/export
//       moved above /reports/{id} catch-all in the Admin Reports section
// ============================================
$router->add('GET', '/api/v2/admin/members/inactive', 'Nexus\Controllers\Api\AdminAnalyticsReportsApiController@inactiveMembers');
$router->add('POST', '/api/v2/admin/members/inactive/detect', 'Nexus\Controllers\Api\AdminAnalyticsReportsApiController@detectInactive');
$router->add('POST', '/api/v2/admin/members/inactive/notify', 'Nexus\Controllers\Api\AdminAnalyticsReportsApiController@markInactiveNotified');
$router->add('GET', '/api/v2/admin/moderation/queue', 'Nexus\Controllers\Api\AdminAnalyticsReportsApiController@moderationQueue');
$router->add('POST', '/api/v2/admin/moderation/{id}/review', 'Nexus\Controllers\Api\AdminAnalyticsReportsApiController@moderationReview');
$router->add('GET', '/api/v2/admin/moderation/stats', 'Nexus\Controllers\Api\AdminAnalyticsReportsApiController@moderationStats');
$router->add('GET', '/api/v2/admin/moderation/settings', 'Nexus\Controllers\Api\AdminAnalyticsReportsApiController@moderationSettings');
$router->add('PUT', '/api/v2/admin/moderation/settings', 'Nexus\Controllers\Api\AdminAnalyticsReportsApiController@updateModerationSettings');

// ============================================
// API V2 - LISTING FEATURES (New endpoints)
// Note: /featured, /tags/* moved above /listings/{id} in main listings section
// ============================================
$router->add('POST', '/api/v2/listings/{id}/renew', 'Nexus\Controllers\Api\ListingsApiController@renew');
$router->add('GET', '/api/v2/listings/{id}/analytics', 'Nexus\Controllers\Api\ListingsApiController@analytics');
$router->add('PUT', '/api/v2/listings/{id}/tags', 'Nexus\Controllers\Api\ListingsApiController@setSkillTags');
$router->add('POST', '/api/v2/admin/listings/{id}/feature', 'Nexus\Controllers\Api\AdminListingsApiController@feature');
$router->add('DELETE', '/api/v2/admin/listings/{id}/feature', 'Nexus\Controllers\Api\AdminListingsApiController@unfeature');
$router->add('POST', '/api/v2/admin/listings/{id}/reject', 'Nexus\Controllers\Api\AdminListingsApiController@reject');
// ============================================
// API V2 - SEARCH FEATURES (New endpoints)
// ============================================
$router->add('GET', '/api/v2/search/saved', 'Nexus\Controllers\Api\SearchApiController@savedSearches');
$router->add('POST', '/api/v2/search/saved', 'Nexus\Controllers\Api\SearchApiController@saveSearch');
$router->add('DELETE', '/api/v2/search/saved/{id}', 'Nexus\Controllers\Api\SearchApiController@deleteSavedSearch');
$router->add('POST', '/api/v2/search/saved/{id}/run', 'Nexus\Controllers\Api\SearchApiController@runSavedSearch');
$router->add('GET', '/api/v2/search/trending', 'Nexus\Controllers\Api\SearchApiController@trending');
$router->add('GET', '/api/v2/admin/search/analytics', 'Nexus\Controllers\Api\AdminListingsApiController@searchAnalytics');
$router->add('GET', '/api/v2/admin/search/trending', 'Nexus\Controllers\Api\AdminListingsApiController@searchTrending');
$router->add('GET', '/api/v2/admin/search/zero-results', 'Nexus\Controllers\Api\AdminListingsApiController@searchZeroResults');

// ============================================
// API V2 - CROSS-MODULE MATCHING (New endpoints)
// ============================================
$router->add('GET',  '/api/v2/matches/all',           'Nexus\Controllers\Api\MatchingApiController@allMatches');
$router->add('POST', '/api/v2/matches/{id}/dismiss', 'Nexus\Controllers\Api\MatchingApiController@dismiss');

// ============================================
// WEBHOOKS - External Service Callbacks
// ============================================
// SendGrid Event Webhook (unauthenticated — verified by signature)
$router->add('POST', '/api/webhooks/sendgrid/events', 'Nexus\Controllers\Api\SendGridWebhookController@events');

