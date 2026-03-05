<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.
// ============================================
// LISTINGS API v2 - RESTful CRUD
// Full API for mobile/SPA with standardized responses
// ============================================

// Categories endpoint (public - for listing/event forms)
$router->add('GET', '/api/v2/categories', function () {
    try {
        header('Content-Type: application/json');
        $type = $_GET['type'] ?? 'listing';
        // Validate type against allowlist to prevent tainted input
        $allowedTypes = ['listing', 'event', 'volunteering', 'resource'];
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'listing';
        }
        $categories = \Nexus\Models\Category::getByType($type);
        // nosemgrep: php.lang.security.injection.echoed-request.echoed-request -- json_encode + Content-Type: application/json
        echo json_encode(['data' => $categories]);
    } catch (\Throwable $e) {
        error_log("API /v2/categories error: " . $e->getMessage());
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
    }
});

$router->add('GET', '/api/v2/listings', 'Nexus\Controllers\Api\ListingsApiController@index');
$router->add('GET', '/api/v2/listings/nearby', 'Nexus\Controllers\Api\ListingsApiController@nearby');
$router->add('GET', '/api/v2/listings/saved', 'Nexus\Controllers\Api\ListingsApiController@getSavedListings');
$router->add('GET', '/api/v2/listings/featured', 'Nexus\Controllers\Api\ListingsApiController@featured');
$router->add('GET', '/api/v2/listings/tags/popular', 'Nexus\Controllers\Api\ListingsApiController@popularTags');
$router->add('GET', '/api/v2/listings/tags/autocomplete', 'Nexus\Controllers\Api\ListingsApiController@autocompleteTags');
$router->add('POST', '/api/v2/listings', 'Nexus\Controllers\Api\ListingsApiController@store');
$router->add('GET', '/api/v2/listings/{id}', 'Nexus\Controllers\Api\ListingsApiController@show');
$router->add('PUT', '/api/v2/listings/{id}', 'Nexus\Controllers\Api\ListingsApiController@update');
$router->add('DELETE', '/api/v2/listings/{id}', 'Nexus\Controllers\Api\ListingsApiController@destroy');
$router->add('POST', '/api/v2/listings/{id}/save', 'Nexus\Controllers\Api\ListingsApiController@saveListing');
$router->add('DELETE', '/api/v2/listings/{id}/save', 'Nexus\Controllers\Api\ListingsApiController@unsaveListing');
$router->add('POST', '/api/v2/listings/{id}/image', 'Nexus\Controllers\Api\ListingsApiController@uploadImage');
$router->add('DELETE', '/api/v2/listings/{id}/image', 'Nexus\Controllers\Api\ListingsApiController@deleteImage');
