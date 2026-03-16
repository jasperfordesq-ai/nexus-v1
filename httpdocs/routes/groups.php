<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.
// MIGRATED TO LARAVEL — see routes/api.php
// ============================================
// API V2 - GROUPS (RESTful Group Management)
// ============================================
// $router->add('GET', '/api/v2/groups', 'Nexus\Controllers\Api\GroupsApiController@index');
// $router->add('POST', '/api/v2/groups', 'Nexus\Controllers\Api\GroupsApiController@store');

// ── Group Recommendations (static paths must precede /{id} wildcards) ──────
// $router->add('GET',  '/api/v2/groups/recommendations',             'Nexus\Controllers\Api\GroupRecommendationController@index');
// $router->add('POST', '/api/v2/groups/recommendations/track',       'Nexus\Controllers\Api\GroupRecommendationController@track');
// $router->add('GET',  '/api/v2/groups/recommendations/metrics',     'Nexus\Controllers\Api\GroupRecommendationController@metrics');

// $router->add('GET', '/api/v2/groups/{id}', 'Nexus\Controllers\Api\GroupsApiController@show');
// $router->add('PUT', '/api/v2/groups/{id}', 'Nexus\Controllers\Api\GroupsApiController@update');
// $router->add('DELETE', '/api/v2/groups/{id}', 'Nexus\Controllers\Api\GroupsApiController@destroy');
// $router->add('GET',  '/api/v2/groups/{id}/similar', 'Nexus\Controllers\Api\GroupRecommendationController@similar');
// $router->add('POST', '/api/v2/groups/{id}/join', 'Nexus\Controllers\Api\GroupsApiController@join');
// $router->add('DELETE', '/api/v2/groups/{id}/membership', 'Nexus\Controllers\Api\GroupsApiController@leave');
// $router->add('GET', '/api/v2/groups/{id}/members', 'Nexus\Controllers\Api\GroupsApiController@members');
// $router->add('PUT', '/api/v2/groups/{id}/members/{userId}', 'Nexus\Controllers\Api\GroupsApiController@updateMember');
// $router->add('DELETE', '/api/v2/groups/{id}/members/{userId}', 'Nexus\Controllers\Api\GroupsApiController@removeMember');
// $router->add('GET', '/api/v2/groups/{id}/requests', 'Nexus\Controllers\Api\GroupsApiController@pendingRequests');
// $router->add('POST', '/api/v2/groups/{id}/requests/{userId}', 'Nexus\Controllers\Api\GroupsApiController@handleRequest');
// $router->add('GET', '/api/v2/groups/{id}/discussions', 'Nexus\Controllers\Api\GroupsApiController@discussions');
// $router->add('POST', '/api/v2/groups/{id}/discussions', 'Nexus\Controllers\Api\GroupsApiController@createDiscussion');
// $router->add('GET', '/api/v2/groups/{id}/discussions/{discussionId}', 'Nexus\Controllers\Api\GroupsApiController@discussionMessages');
// $router->add('POST', '/api/v2/groups/{id}/discussions/{discussionId}/messages', 'Nexus\Controllers\Api\GroupsApiController@postToDiscussion');
// $router->add('POST', '/api/v2/groups/{id}/image', 'Nexus\Controllers\Api\GroupsApiController@uploadImage');
// $router->add('GET', '/api/v2/groups/{id}/announcements', 'Nexus\Controllers\Api\GroupsApiController@announcements');
// $router->add('POST', '/api/v2/groups/{id}/announcements', 'Nexus\Controllers\Api\GroupsApiController@createAnnouncement');
// $router->add('PUT', '/api/v2/groups/{id}/announcements/{announcementId}', 'Nexus\Controllers\Api\GroupsApiController@updateAnnouncement');
// $router->add('DELETE', '/api/v2/groups/{id}/announcements/{announcementId}', 'Nexus\Controllers\Api\GroupsApiController@deleteAnnouncement');

// ============================================
// API V2 - CONNECTIONS (User Friend Requests)
// ============================================
// $router->add('GET', '/api/v2/connections', 'Nexus\Controllers\Api\ConnectionsApiController@index');
// $router->add('GET', '/api/v2/connections/pending', 'Nexus\Controllers\Api\ConnectionsApiController@pendingCounts');
$router->add('GET', '/api/v2/connections/status/me', function () { http_response_code(422); echo json_encode(['errors' => [['code' => 'invalid_user', 'message' => 'Cannot check connection status with yourself']]]); }); // Guard: reject literal "me"
// $router->add('GET', '/api/v2/connections/status/{userId}', 'Nexus\Controllers\Api\ConnectionsApiController@status');
// $router->add('POST', '/api/v2/connections/request', 'Nexus\Controllers\Api\ConnectionsApiController@request');
// $router->add('POST', '/api/v2/connections/{id}/accept', 'Nexus\Controllers\Api\ConnectionsApiController@accept');
// $router->add('DELETE', '/api/v2/connections/{id}', 'Nexus\Controllers\Api\ConnectionsApiController@destroy');
