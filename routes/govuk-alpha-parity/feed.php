<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Http\Controllers\GovukAlpha\AlphaController;
use App\Http\Controllers\GovukAlpha\Middleware\RequireAccessibleAuthentication;
use Illuminate\Support\Facades\Route;

/*
 * Feed parity routes (accessible GOV.UK frontend).
 *
 * Required INSIDE the {tenantSlug}/alpha + govuk-alpha. group, so the paths
 * below resolve to /{tenantSlug}/alpha/... and the names to govuk-alpha....
 *
 * These complement the core feed coverage (feed, posts CRUD, likes, comments,
 * reactions, share, save, poll voting, hide/mute/report, post permalink) with
 * the remaining React-parity experiences:
 *   - /feed/hashtags            trending + search   (HashtagsDiscoveryPage.tsx)
 *   - /feed/hashtag/{tag}       posts for a tag     (HashtagPage.tsx)
 *   - /feed/item/{type}/{id}    polymorphic detail  (PostDetailPage.tsx)
 *   - /feed/items/{type}/{id}/not-interested   soft negative signal
 *   - /feed/items/{type}/{id}/react            emoji reaction on typed items
 *
 * Static segments are registered before the wildcard {tag} so /feed/hashtags
 * never collides with /feed/hashtag/{tag}, and the typed /feed/items/... POST
 * routes sit on distinct suffixes from the existing core feed.items.* routes.
 */

Route::middleware(RequireAccessibleAuthentication::class)->group(function () {
    // ===== Hashtag discovery + browse =====
    Route::get('/feed/hashtags', [AlphaController::class, 'feedHashtagsDiscovery'])
        ->name('feed.hashtags');

    Route::get('/feed/hashtag/{tag}', [AlphaController::class, 'feedHashtag'])
        ->where('tag', '[A-Za-z0-9_]{1,100}')
        ->name('feed.hashtag');

    // ===== Polymorphic feed-item permalink =====
    Route::get('/feed/item/{type}/{id}', [AlphaController::class, 'feedItemDetail'])
        ->where('type', '[a-z]+')
        ->whereNumber('id')
        ->name('feed.item');

    // ===== Typed-item engagement (parity with FeedCard on non-post cards) =====
    Route::post('/feed/items/{type}/{id}/not-interested', [AlphaController::class, 'feedItemNotInterested'])
        ->where('type', '[a-z]+')
        ->whereNumber('id')
        ->middleware('throttle:30,1')
        ->name('feed.items.not-interested');

    Route::post('/feed/items/{type}/{id}/react', [AlphaController::class, 'feedItemReaction'])
        ->where('type', '[a-z]+')
        ->whereNumber('id')
        ->middleware('throttle:60,1')
        ->name('feed.items.react');
});
