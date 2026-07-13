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
    Route::get('/events/{id}/tickets', [AlphaController::class, 'eventsTickets'])
        ->whereNumber('id')->name('events.tickets.index');
    Route::post('/events/{id}/tickets/{ticketTypeId}/allocate', [AlphaController::class, 'eventsTicketAllocate'])
        ->whereNumber('id')->whereNumber('ticketTypeId')->middleware('throttle:nexus-route-20-per-1m')
        ->name('events.tickets.allocate');
    Route::get('/events/{id}/tickets/entitlements/{entitlementId}/cancel', [AlphaController::class, 'eventsTicketCancelForm'])
        ->whereNumber('id')->whereNumber('entitlementId')->name('events.tickets.cancel.form');
    Route::post('/events/{id}/tickets/entitlements/{entitlementId}/cancel', [AlphaController::class, 'eventsTicketCancel'])
        ->whereNumber('id')->whereNumber('entitlementId')->middleware('throttle:nexus-route-20-per-1m')
        ->name('events.tickets.cancel');
});
