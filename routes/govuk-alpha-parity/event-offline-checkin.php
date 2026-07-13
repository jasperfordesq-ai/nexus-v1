<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use App\Http\Controllers\GovukAlpha\AlphaController;
use App\Http\Controllers\GovukAlpha\Middleware\RequireAccessibleAuthentication;
use Illuminate\Support\Facades\Route;

Route::middleware(RequireAccessibleAuthentication::class)->group(function (): void {
    Route::get('/events/{id}/check-in/credential', [AlphaController::class, 'eventsAttendeeCheckinCredential'])
        ->whereNumber('id')
        ->name('events.check-in.credential');
    Route::post('/events/{id}/check-in/credential/issue', [AlphaController::class, 'eventsIssueAttendeeCheckinCredential'])
        ->whereNumber('id')
        ->middleware('throttle:nexus-route-20-per-1m')
        ->name('events.check-in.credential.issue');
    Route::post('/events/{id}/check-in/credential/rotate', [AlphaController::class, 'eventsRotateAttendeeCheckinCredential'])
        ->whereNumber('id')
        ->middleware('throttle:nexus-route-20-per-1m')
        ->name('events.check-in.credential.rotate');
    Route::post('/events/{id}/check-in/credential/revoke', [AlphaController::class, 'eventsRevokeAttendeeCheckinCredential'])
        ->whereNumber('id')
        ->middleware('throttle:nexus-route-20-per-1m')
        ->name('events.check-in.credential.revoke');
    Route::post('/events/{id}/check-in/code', [AlphaController::class, 'eventsOfflineCheckinCode'])
        ->whereNumber('id')
        ->middleware('throttle:nexus-route-60-per-1m')
        ->name('events.check-in.code');
});
