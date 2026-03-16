<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.
// MIGRATED TO LARAVEL — see routes/api.php
// $router->add('GET', '/api/polls', 'Nexus\Controllers\Api\PollApiController@index');
// $router->add('POST', '/api/polls/vote', 'Nexus\Controllers\Api\PollApiController@vote');

// Goals (deprecated — use /api/v2/goals)
// $router->add('GET', '/api/goals', 'Nexus\Controllers\Api\GoalApiController@index');
// $router->add('POST', '/api/goals/update', 'Nexus\Controllers\Api\GoalApiController@updateProgress'); // Method is updateProgress
// $router->add('POST', '/api/goals/offer-buddy', 'Nexus\Controllers\Api\GoalApiController@offerBuddy'); // Offer to be a goal buddy

// Volunteering (deprecated — use /api/v2/volunteering)
// $router->add('GET', '/api/vol_opportunities', 'Nexus\Controllers\Api\VolunteeringApiController@index');

// Events (deprecated — use /api/v2/events)
// $router->add('GET', '/api/events', 'Nexus\Controllers\Api\EventApiController@index');
// $router->add('POST', '/api/events/rsvp', 'Nexus\Controllers\Api\EventApiController@rsvp');

// Wallet (deprecated — use /api/v2/wallet/*)
// $router->add('GET', '/api/wallet/balance', 'Nexus\Controllers\Api\WalletApiController@balance');

// Cookie Consent API (EU Compliance)
// $router->add('GET', '/api/cookie-consent', 'Nexus\Controllers\Api\CookieConsentController@show');
// $router->add('POST', '/api/cookie-consent', 'Nexus\Controllers\Api\CookieConsentController@store');
// IMPORTANT: Literal GET routes must come before {id} wildcard to avoid being shadowed
// $router->add('GET', '/api/cookie-consent/inventory', 'Nexus\Controllers\Api\CookieConsentController@inventory');
// $router->add('GET', '/api/cookie-consent/check/{category}', 'Nexus\Controllers\Api\CookieConsentController@check');
// $router->add('PUT', '/api/cookie-consent/{id}', 'Nexus\Controllers\Api\CookieConsentController@update');
// $router->add('DELETE', '/api/cookie-consent/{id}', 'Nexus\Controllers\Api\CookieConsentController@withdraw');

// Legal Documents API (Public Content + User Acceptance Tracking)
// IMPORTANT: Literal routes MUST come before parameterized {type} routes — the router
// matches first-registered, so /versions/compare and /version/{id} would be swallowed
// by the {type} wildcard if registered after it.
// $router->add('GET', '/api/v2/legal/versions/compare', 'Nexus\Controllers\LegalDocumentController@apiCompareVersions');
// $router->add('GET', '/api/v2/legal/version/{versionId}', 'Nexus\Controllers\LegalDocumentController@apiGetVersion');
// $router->add('GET', '/api/v2/legal/{type}/versions', 'Nexus\Controllers\LegalDocumentController@apiGetVersions');
// V2 user acceptance endpoints (Bearer token + session auth via ApiAuth trait)
// IMPORTANT: Must come before {type} wildcard to avoid being shadowed
// $router->add('GET', '/api/v2/legal/acceptance/status', 'Nexus\Controllers\Api\LegalAcceptanceApiController@getStatus');
// $router->add('POST', '/api/v2/legal/acceptance/accept-all', 'Nexus\Controllers\Api\LegalAcceptanceApiController@acceptAll');
// $router->add('GET', '/api/v2/legal/{type}', 'Nexus\Controllers\LegalDocumentController@apiGetDocument');
// Legacy session-based acceptance endpoints (kept for PHP admin views)
// $router->add('POST', '/api/legal/accept', 'Nexus\Controllers\LegalDocumentController@accept');
// $router->add('POST', '/api/legal/accept-all', 'Nexus\Controllers\LegalDocumentController@acceptAll');
// $router->add('GET', '/api/legal/status', 'Nexus\Controllers\LegalDocumentController@status');

// Nexus Score API
// $router->add('GET', '/api/nexus-score', 'Nexus\Controllers\NexusScoreController@apiGetScore');
// $router->add('POST', '/api/nexus-score/recalculate', 'Nexus\Controllers\NexusScoreController@apiRecalculateScores');
// Wallet continued (deprecated — use /api/v2/wallet/*)
// $router->add('GET', '/api/wallet/transactions', 'Nexus\Controllers\Api\WalletApiController@transactions');
// $router->add('GET', '/api/wallet/pending-count', 'Nexus\Controllers\Api\WalletApiController@pendingCount'); // Badge updates
// $router->add('POST', '/api/wallet/transfer', 'Nexus\Controllers\Api\WalletApiController@transfer');
// $router->add('POST', '/api/wallet/delete', 'Nexus\Controllers\Api\WalletApiController@delete');
// $router->add('POST', '/api/wallet/user-search', 'Nexus\Controllers\Api\WalletApiController@userSearch'); // User autocomplete

// Core — Directory, Feed, etc. (deprecated — use /api/v2/users, /api/v2/listings, /api/v2/groups, /api/v2/messages, /api/v2/notifications)
// $router->add('GET', '/api/members', 'Nexus\Controllers\Api\CoreApiController@members');
// $router->add('GET', '/api/listings', 'Nexus\Controllers\Api\CoreApiController@listings');
// $router->add('GET', '/api/groups', 'Nexus\Controllers\Api\CoreApiController@groups');
// $router->add('GET', '/api/messages', 'Nexus\Controllers\Api\CoreApiController@messages');
// $router->add('GET', '/api/notifications', 'Nexus\Controllers\Api\CoreApiController@notifications');
// $router->add('GET', '/api/notifications/check', 'Nexus\Controllers\Api\CoreApiController@checkNotifications'); // ADDED
// $router->add('GET', '/api/notifications/unread-count', 'Nexus\Controllers\Api\CoreApiController@unreadCount'); // Badge updates
// $router->add('GET', '/api/notifications/poll', 'Nexus\Controllers\NotificationController@poll'); // Lightweight polling for badge updates
// $router->add('POST', '/api/notifications/read', 'Nexus\Controllers\NotificationController@markRead');
// $router->add('POST', '/api/notifications/delete', 'Nexus\Controllers\NotificationController@delete'); // New Delete API

// Listings delete (deprecated — use DELETE /api/v2/listings/{id})
// $router->add('POST', '/api/listings/delete', 'Nexus\Controllers\ListingController@delete'); // Listings Delete API
