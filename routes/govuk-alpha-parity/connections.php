<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Http\Controllers\GovukAlpha\AlphaController;
use Illuminate\Support\Facades\Route;

/*
 * Connections & matches parity routes (accessible GOV.UK frontend).
 *
 * Required INSIDE the {tenantSlug}/alpha + govuk-alpha. group, so the paths
 * below resolve to /{tenantSlug}/alpha/... and the names to govuk-alpha....
 *
 * These complement the core connections() / matches() pages with the
 * React-parity richer experiences (cursor pagination, stats dashboard,
 * matched-user info, reason-aware dismiss). Static segments are registered
 * before any wildcard within this file.
 */

// "My network" — three-tab, cursor-paginated connections page.
Route::get('/connections/network', [AlphaController::class, 'connectionsNetwork'])
    ->name('connections.network');

// Matches dashboard — stats grid, per-source counts, dismiss-with-reason.
Route::get('/matches/board', [AlphaController::class, 'connectionsMatchesBoard'])
    ->name('connections.matches-board');

Route::post('/matches/board/{listingId}/dismiss', [AlphaController::class, 'connectionsDismissMatch'])
    ->whereNumber('listingId')
    ->middleware('throttle:60,1')
    ->name('connections.matches-board.dismiss');
