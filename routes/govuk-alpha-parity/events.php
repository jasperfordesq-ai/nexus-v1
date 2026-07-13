<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Http\Controllers\GovukAlpha\AlphaController;
use App\Http\Controllers\GovukAlpha\Middleware\RequireAccessibleAuthentication;
use Illuminate\Support\Facades\Route;

/*
 * Accessible (GOV.UK) frontend — Events parity routes.
 *
 * Auto-required INSIDE the {tenantSlug}/alpha group in routes/govuk-alpha.php,
 * so each path becomes /{tenantSlug}/alpha/... and each name 'govuk-alpha.events....'.
 * The base events routes (index/show/create/edit/update/cancel/delete/rsvp/
 * waitlist/poll-vote/check-in) are already registered in routes/govuk-alpha.php;
 * this file ONLY adds the parity gaps:
 *   - category toggle-button browse
 *   - accessible location map / directions
 *   - recurring-series occurrence edit with "this / all future" scope
 *   - attach / detach polls to an owned event
 *   - on-demand description translation
 * Every POST is throttled. Numeric route params use whereNumber(). Static
 * segments are registered BEFORE wildcard {id} routes within this file.
 */

Route::middleware(RequireAccessibleAuthentication::class)->group(function () {
// --- Category toggle-button browse (static segment, before any {id}) ---
Route::get('/events/browse', [AlphaController::class, 'eventsBrowse'])
    ->name('events.browse');

// --- Accessible location map / directions (Maps feature) ---
Route::get('/events/{id}/map', [AlphaController::class, 'eventsMap'])
    ->whereNumber('id')->name('events.map');

// --- Recurring-series occurrence edit with scope ---
Route::get('/events/{id}/recurring-edit', [AlphaController::class, 'eventsRecurringEdit'])
    ->whereNumber('id')->name('events.recurring.edit');
Route::post('/events/{id}/recurring-edit', [AlphaController::class, 'eventsUpdateRecurring'])
    ->whereNumber('id')->middleware('throttle:nexus-route-10-per-1m')->name('events.recurring.update');
Route::post('/events/{id}/recurring-edit/commit', [AlphaController::class, 'eventsCommitRecurringRevision'])
    ->whereNumber('id')->middleware('throttle:nexus-route-10-per-1m')->name('events.recurring.commit');

// --- Future occurrence definition blueprints (preview-first, immutable) ---
Route::get('/events/{id}/recurrence-definition-blueprints', [AlphaController::class, 'eventsRecurrenceDefinitionBlueprints'])
    ->whereNumber('id')->name('events.recurrence-definitions.index');
Route::post('/events/{id}/recurrence-definition-blueprints/preview', [AlphaController::class, 'eventsPreviewRecurrenceDefinitions'])
    ->whereNumber('id')->middleware('throttle:nexus-route-10-per-1m')->name('events.recurrence-definitions.preview');
Route::post('/events/{id}/recurrence-definition-blueprints/commit', [AlphaController::class, 'eventsCommitRecurrenceDefinitions'])
    ->whereNumber('id')->middleware('throttle:nexus-route-10-per-1m')->name('events.recurrence-definitions.commit');

// --- Attach / detach polls to an owned event ---
Route::get('/events/{id}/polls', [AlphaController::class, 'eventsPolls'])
    ->whereNumber('id')->name('events.polls');
Route::post('/events/{id}/polls', [AlphaController::class, 'eventsUpdatePolls'])
    ->whereNumber('id')->middleware('throttle:nexus-route-20-per-1m')->name('events.polls.update');

// --- On-demand description translation ---
Route::get('/events/{id}/translate', [AlphaController::class, 'eventsTranslate'])
    ->whereNumber('id')->name('events.translate');
Route::post('/events/{id}/translate', [AlphaController::class, 'eventsRunTranslate'])
    ->whereNumber('id')->middleware('throttle:nexus-route-30-per-1m')->name('events.translate.run');
});
