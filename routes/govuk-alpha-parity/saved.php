<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Http\Controllers\GovukAlpha\AlphaController;
use Illuminate\Support\Facades\Route;

/*
 * Saved collections & appreciation parity routes (accessible GOV.UK frontend).
 *
 * Required INSIDE the {tenantSlug}/alpha + govuk-alpha. group, so the paths
 * below resolve to /{tenantSlug}/alpha/... and the names to govuk-alpha....
 *
 * These complement the core flat bookmark list (saved() / destroySaved()) with
 * the React-parity collections + appreciation experiences:
 *   - /me/collections                 grid + create  (MyCollectionsPage.tsx)
 *   - /me/collections/{id}            detail + items (CollectionDetailPage.tsx)
 *   - /users/{userId}/collections     public view    (UserCollectionsView.tsx)
 *   - /users/{userId}/appreciations   thank-you wall (AppreciationWallPage.tsx)
 *
 * Static segments are registered before any wildcard within this file, and the
 * literal `/me/...` prefix never collides with the `/users/{userId}/...` group.
 */

// ===== My collections (owner) =====
Route::get('/me/collections', [AlphaController::class, 'savedMyCollections'])
    ->name('saved.collections');

Route::post('/me/collections', [AlphaController::class, 'savedCreateCollection'])
    ->middleware('throttle:nexus-route-20-per-1m')
    ->name('saved.collections.store');

// Static action segments under a collection id are declared with the {id}
// wildcard constrained to a number, and the actions sit on distinct suffixes
// so there is no ambiguity with the bare detail GET.
Route::get('/me/collections/{id}', [AlphaController::class, 'savedCollectionDetail'])
    ->whereNumber('id')
    ->name('saved.collection-detail');

Route::post('/me/collections/{id}/update', [AlphaController::class, 'savedUpdateCollection'])
    ->whereNumber('id')
    ->middleware('throttle:nexus-route-20-per-1m')
    ->name('saved.collections.update');

Route::post('/me/collections/{id}/delete', [AlphaController::class, 'savedDeleteCollection'])
    ->whereNumber('id')
    ->middleware('throttle:nexus-route-20-per-1m')
    ->name('saved.collections.delete');

Route::post('/me/collections/{id}/items/{itemId}/remove', [AlphaController::class, 'savedRemoveItem'])
    ->whereNumber('id')
    ->whereNumber('itemId')
    ->middleware('throttle:nexus-route-30-per-1m')
    ->name('saved.collections.item-remove');

// ===== Public collections + appreciation wall (by member) =====
Route::get('/users/{userId}/collections', [AlphaController::class, 'savedPublicCollections'])
    ->whereNumber('userId')
    ->name('saved.public-collections');

Route::get('/users/{userId}/appreciations', [AlphaController::class, 'savedAppreciationWall'])
    ->whereNumber('userId')
    ->name('saved.appreciations');

Route::post('/users/{userId}/appreciations', [AlphaController::class, 'savedSendAppreciation'])
    ->whereNumber('userId')
    ->middleware('throttle:nexus-route-10-per-1m')
    ->name('saved.appreciations.send');

Route::post('/appreciations/{id}/react', [AlphaController::class, 'savedReactAppreciation'])
    ->whereNumber('id')
    ->middleware('throttle:nexus-route-60-per-1m')
    ->name('saved.appreciations.react');
