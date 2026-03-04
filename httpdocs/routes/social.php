<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.
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
// API V2 - FEED SHARING & HASHTAGS (F2, F4)
// ============================================
$router->add('POST', '/api/v2/feed/posts/{id}/share', 'Nexus\Controllers\Api\FeedSocialApiController@sharePost');
$router->add('DELETE', '/api/v2/feed/posts/{id}/share', 'Nexus\Controllers\Api\FeedSocialApiController@unsharePost');
$router->add('GET', '/api/v2/feed/posts/{id}/sharers', 'Nexus\Controllers\Api\FeedSocialApiController@getSharers');
$router->add('GET', '/api/v2/feed/hashtags/trending', 'Nexus\Controllers\Api\FeedSocialApiController@getTrendingHashtags');
$router->add('GET', '/api/v2/feed/hashtags/search', 'Nexus\Controllers\Api\FeedSocialApiController@searchHashtags');
$router->add('GET', '/api/v2/feed/hashtags/{tag}', 'Nexus\Controllers\Api\FeedSocialApiController@getHashtagPosts');

// ============================================
// API V2 - REALTIME (Pusher Configuration)
// ============================================
$router->add('GET', '/api/v2/realtime/config', function () {
    try {
        header('Content-Type: application/json');
        $config = \Nexus\Services\RealtimeService::getFrontendConfig();
        echo json_encode(['data' => $config]);
    } catch (\Throwable $e) {
        error_log("API /v2/realtime/config error: " . $e->getMessage());
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
    }
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
$router->add('GET', '/api/v2/polls/categories', 'Nexus\Controllers\Api\PollsApiController@categories');
$router->add('GET', '/api/v2/polls/{id}', 'Nexus\Controllers\Api\PollsApiController@show');
$router->add('PUT', '/api/v2/polls/{id}', 'Nexus\Controllers\Api\PollsApiController@update');
$router->add('DELETE', '/api/v2/polls/{id}', 'Nexus\Controllers\Api\PollsApiController@destroy');
$router->add('POST', '/api/v2/polls/{id}/vote', 'Nexus\Controllers\Api\PollsApiController@vote');
$router->add('POST', '/api/v2/polls/{id}/rank', 'Nexus\Controllers\Api\PollsApiController@rank');
$router->add('GET', '/api/v2/polls/{id}/ranked-results', 'Nexus\Controllers\Api\PollsApiController@rankedResults');
$router->add('GET', '/api/v2/polls/{id}/export', 'Nexus\Controllers\Api\PollsApiController@export');
