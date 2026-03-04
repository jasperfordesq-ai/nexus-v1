<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.
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
