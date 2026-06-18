<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Http\Controllers\GovukAlpha\AlphaController;
use Illuminate\Support\Facades\Route;

/**
 * Search parity routes (accessible / GOV.UK frontend).
 *
 * Auto-required INSIDE the {tenantSlug}/alpha + govuk-alpha. group, so each
 * path becomes /{tenantSlug}/alpha/search/... named govuk-alpha.search.*.
 *
 * The simple search page lives at /search (govuk-alpha.search, defined in
 * routes/govuk-alpha.php). The full-featured advanced search (filters, popular
 * tags, saved searches, result thumbnails) is added here. Static segments are
 * registered before the numeric {id} wildcard.
 */

// Full-featured advanced search page (filters + saved searches + results).
Route::get('/search/advanced', [AlphaController::class, 'searchAdvanced'])
    ->name('search.advanced');

// Save the current search. Static segment, before {id}.
Route::post('/search/saved', [AlphaController::class, 'searchSaveSearch'])
    ->middleware('throttle:30,1')
    ->name('search.saved.save');

// Per-saved-search actions (numeric id).
Route::get('/search/saved/{id}/delete', [AlphaController::class, 'searchDeleteSavedConfirm'])
    ->whereNumber('id')
    ->name('search.saved.delete.confirm');
Route::post('/search/saved/{id}/delete', [AlphaController::class, 'searchDeleteSaved'])
    ->whereNumber('id')
    ->middleware('throttle:30,1')
    ->name('search.saved.delete');
Route::post('/search/saved/{id}/run', [AlphaController::class, 'searchRunSaved'])
    ->whereNumber('id')
    ->middleware('throttle:60,1')
    ->name('search.saved.run');
