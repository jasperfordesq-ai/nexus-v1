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
    Route::get('/events/{id}/communications', [AlphaController::class, 'eventsCommunications'])
        ->whereNumber('id')->name('events.communications.index');
    Route::post('/events/{id}/communications/preview', [AlphaController::class, 'eventsCommunicationsPreview'])
        ->whereNumber('id')->middleware('throttle:nexus-route-60-per-1m')->name('events.communications.preview');
    Route::post('/events/{id}/communications', [AlphaController::class, 'eventsCommunicationsCreate'])
        ->whereNumber('id')->middleware('throttle:nexus-route-20-per-1m')->name('events.communications.create');
    Route::post('/events/{id}/communications/{broadcastId}/schedule', [AlphaController::class, 'eventsCommunicationsSchedule'])
        ->whereNumber('id')->whereNumber('broadcastId')->middleware('throttle:nexus-route-20-per-1m')
        ->name('events.communications.schedule');
    Route::post('/events/{id}/communications/{broadcastId}/cancel', [AlphaController::class, 'eventsCommunicationsCancel'])
        ->whereNumber('id')->whereNumber('broadcastId')->middleware('throttle:nexus-route-20-per-1m')
        ->name('events.communications.cancel');
    Route::post('/events/{id}/communications/{broadcastId}/retry', [AlphaController::class, 'eventsCommunicationsRetry'])
        ->whereNumber('id')->whereNumber('broadcastId')->middleware('throttle:nexus-route-20-per-1m')
        ->name('events.communications.retry');
});
