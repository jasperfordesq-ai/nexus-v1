<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Http\Controllers\GovukAlpha\AlphaController;
use App\Http\Controllers\GovukAlpha\Middleware\RequireAccessibleAuthentication;
use Illuminate\Support\Facades\Route;

/*
 * Accessible Events moderation. The controller concern performs the strict
 * tenant-admin check because accessible authentication also supports a secure
 * auth_token cookie and legacy session identities that are not always exposed
 * through Laravel's default request guard.
 */
Route::middleware(RequireAccessibleAuthentication::class)->group(function (): void {
    Route::get('/events/moderation', [AlphaController::class, 'eventsModerationQueue'])
        ->name('events.moderation.index');

    Route::get('/events/moderation/{id}/approve', [AlphaController::class, 'eventsModerationApproveConfirmation'])
        ->whereNumber('id')
        ->name('events.moderation.approve.confirm');
    Route::post('/events/moderation/{id}/approve', [AlphaController::class, 'eventsModerationApprove'])
        ->whereNumber('id')
        ->middleware('throttle:nexus-route-20-per-1m')
        ->name('events.moderation.approve');

    Route::get('/events/moderation/{id}/reject', [AlphaController::class, 'eventsModerationRejectConfirmation'])
        ->whereNumber('id')
        ->name('events.moderation.reject.confirm');
    Route::post('/events/moderation/{id}/reject', [AlphaController::class, 'eventsModerationReject'])
        ->whereNumber('id')
        ->middleware('throttle:nexus-route-20-per-1m')
        ->name('events.moderation.reject');
});
