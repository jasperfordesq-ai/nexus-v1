<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.
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
