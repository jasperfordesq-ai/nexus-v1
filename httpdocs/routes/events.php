<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.
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
