<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Http\Controllers\GovukAlpha\AlphaController;
use App\Http\Controllers\GovukAlpha\Middleware\RequireAccessibleAuthentication;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Listings — accessible (GOV.UK) frontend parity routes
|--------------------------------------------------------------------------
|
| Auto-required INSIDE the {tenantSlug}/alpha + govuk-alpha. group, so each
| path below becomes /{tenantSlug}/alpha/... named govuk-alpha.listings....
|
| These complement the core listings/exchanges routes in routes/govuk-alpha.php.
| They close React parity gap #12 (owner-only listing analytics panel). The
| /listings/{id}/analytics path is a distinct segment from the core
| /listings/{id} detail route, so there is no wildcard collision.
*/

Route::middleware(RequireAccessibleAuthentication::class)->group(function () {
Route::get('/listings/{id}/analytics', [AlphaController::class, 'listingsAnalytics'])
    ->whereNumber('id')
    ->name('listings.analytics');

// AI description helper — no-JS generate round-trip for the create/edit forms.
// Static segment, registered before any wildcard listing routes in this file.
Route::post('/listings/generate-description', [AlphaController::class, 'listingsGenerateDescription'])
    ->middleware('throttle:nexus-route-5-per-1m')
    ->name('listings.generate-description');

// Listing comment thread — server-rendered list + add-comment form. The
// /comments segment is distinct from the core /listings/{id} detail route.
Route::get('/listings/{id}/comments', [AlphaController::class, 'listingsComments'])
    ->whereNumber('id')
    ->name('listings.comments');
Route::post('/listings/{id}/comments', [AlphaController::class, 'listingsStoreComment'])
    ->whereNumber('id')
    ->middleware('throttle:nexus-route-20-per-1m')
    ->name('listings.comments.store');
});
