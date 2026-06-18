<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Http\Controllers\GovukAlpha\AlphaController;
use Illuminate\Support\Facades\Route;

/**
 * Resources parity routes (accessible / GOV.UK frontend).
 *
 * Auto-required INSIDE the {tenantSlug}/alpha + govuk-alpha. group, so each
 * path becomes /{tenantSlug}/alpha/resources/... named govuk-alpha.resources.*.
 * The existing simple browse page lives at /resources (govuk-alpha.resources.index,
 * defined in routes/govuk-alpha.php); the full-featured library + actions are added
 * here. Static segments are registered before the numeric wildcard.
 */

// Full-featured library (tree sidebar, filters, pagination, metadata, reorder).
Route::get('/resources/library', [AlphaController::class, 'resourcesLibrary'])
    ->name('resources.library');

// Upload (form + store). Static segments before {id}.
Route::get('/resources/upload', [AlphaController::class, 'resourcesUploadForm'])
    ->name('resources.upload.form');
Route::post('/resources/upload', [AlphaController::class, 'resourcesUpload'])
    ->middleware('throttle:20,1')
    ->name('resources.upload');

// Admin reorder (single up/down move).
Route::post('/resources/reorder', [AlphaController::class, 'resourcesReorder'])
    ->middleware('throttle:60,1')
    ->name('resources.reorder');

// Per-resource actions (numeric id).
Route::get('/resources/{id}/download', [AlphaController::class, 'resourcesDownload'])
    ->whereNumber('id')
    ->name('resources.download');
Route::get('/resources/{id}/delete', [AlphaController::class, 'resourcesDeleteConfirm'])
    ->whereNumber('id')
    ->name('resources.delete.confirm');
Route::post('/resources/{id}/delete', [AlphaController::class, 'resourcesDelete'])
    ->whereNumber('id')
    ->middleware('throttle:30,1')
    ->name('resources.delete');
