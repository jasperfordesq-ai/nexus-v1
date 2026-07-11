<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Http\Controllers\GovukAlpha\AlphaController;
use App\Http\Controllers\GovukAlpha\Middleware\RequireAccessibleAuthentication;
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

Route::middleware(RequireAccessibleAuthentication::class)->group(function () {
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

// Social interactions — react + comment thread.
// NOTE: /comments/add avoids clobbering a potential base GET thread route
// (same pattern as blogreviews.blog.comments.store).
Route::post('/resources/{id}/react', [AlphaController::class, 'resourcesReact'])
    ->whereNumber('id')
    ->middleware('throttle:30,1')
    ->name('resources.react');
Route::get('/resources/{id}/comments', [AlphaController::class, 'resourcesComments'])
    ->whereNumber('id')
    ->name('resources.comments');
Route::post('/resources/{id}/comments/add', [AlphaController::class, 'resourcesStoreComment'])
    ->whereNumber('id')
    ->middleware('throttle:30,1')
    ->name('resources.comments.store');
Route::post('/resources/{id}/comments/{commentId}/delete', [AlphaController::class, 'resourcesDeleteComment'])
    ->whereNumber('id')
    ->whereNumber('commentId')
    ->middleware('throttle:30,1')
    ->name('resources.comments.delete');
});
