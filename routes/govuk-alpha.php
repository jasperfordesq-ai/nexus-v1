<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Http\Controllers\GovukAlpha\AlphaController;
use Illuminate\Support\Facades\Route;

Route::pattern('tenantSlug', '[a-zA-Z0-9_-]+');

Route::get('/', [AlphaController::class, 'tenantChooser'])->name('govuk-alpha.tenant-chooser');

Route::prefix('alpha')
    ->name('govuk-alpha.host.')
    ->group(function () {
        Route::get('/', [AlphaController::class, 'hostHome'])->name('home');
        Route::get('/login', [AlphaController::class, 'hostLogin'])->name('login');
        Route::get('/register', [AlphaController::class, 'hostRegister'])->name('register');
    });

Route::prefix('{tenantSlug}/alpha')
    ->name('govuk-alpha.')
    ->group(function () {
        Route::get('/', [AlphaController::class, 'home'])->name('home');
        Route::get('/login', [AlphaController::class, 'login'])->name('login');
        Route::post('/login', [AlphaController::class, 'storeLogin'])->middleware('throttle:30,1')->name('login.store');
        Route::get('/register', [AlphaController::class, 'register'])->name('register');
        Route::post('/register', [AlphaController::class, 'storeRegister'])->middleware('throttle:30,1')->name('register.store');

        Route::get('/dashboard', [AlphaController::class, 'dashboard'])->name('dashboard');
        Route::get('/events', [AlphaController::class, 'events'])->name('events.index');
        Route::get('/events/{id}', [AlphaController::class, 'event'])->whereNumber('id')->name('events.show');
        Route::post('/events/{id}/rsvp', [AlphaController::class, 'storeEventRsvp'])->whereNumber('id')->name('events.rsvp.store');
        Route::get('/volunteering', [AlphaController::class, 'volunteering'])->name('volunteering.index');
        Route::get('/volunteering/hours', [AlphaController::class, 'volunteeringHours'])->name('volunteering.hours');
        Route::post('/volunteering/hours', [AlphaController::class, 'storeVolunteeringHours'])->middleware('throttle:20,1')->name('volunteering.hours.store');
        Route::get('/volunteering/opportunities/{id}', [AlphaController::class, 'volunteerOpportunity'])->whereNumber('id')->name('volunteering.show');
        Route::post('/volunteering/opportunities/{id}/apply', [AlphaController::class, 'applyVolunteerOpportunity'])->middleware('throttle:20,1')->whereNumber('id')->name('volunteering.apply.store');
        Route::get('/feed', [AlphaController::class, 'feed'])->name('feed');
        Route::post('/feed/posts', [AlphaController::class, 'storeFeedPost'])->name('feed.posts.store');
        Route::get('/listings', [AlphaController::class, 'listings'])->name('listings.index');
        Route::get('/listings/{listingId}/exchange-request', [AlphaController::class, 'requestExchange'])->whereNumber('listingId')->name('exchanges.request');
        Route::post('/listings/{listingId}/exchange-request', [AlphaController::class, 'storeExchangeRequest'])->middleware('throttle:20,1')->whereNumber('listingId')->name('exchanges.request.store');
        Route::get('/listings/{id}', [AlphaController::class, 'listing'])->whereNumber('id')->name('listings.show');
        Route::get('/exchanges', [AlphaController::class, 'exchanges'])->name('exchanges.index');
        Route::get('/exchanges/{id}', [AlphaController::class, 'exchange'])->whereNumber('id')->name('exchanges.show');
        Route::post('/exchanges/{id}', [AlphaController::class, 'storeExchangeAction'])->middleware('throttle:30,1')->whereNumber('id')->name('exchanges.action.store');
        Route::get('/messages', [AlphaController::class, 'messages'])->name('messages.index');
        Route::get('/messages/new/{userId}', [AlphaController::class, 'conversation'])->whereNumber('userId')->name('messages.new');
        Route::get('/messages/{userId}', [AlphaController::class, 'conversation'])->whereNumber('userId')->name('messages.show');
        Route::post('/messages/{userId}', [AlphaController::class, 'storeMessage'])->middleware('throttle:30,1')->whereNumber('userId')->name('messages.store');
        Route::post('/messages/{userId}/archive', [AlphaController::class, 'archiveConversation'])->middleware('throttle:20,1')->whereNumber('userId')->name('messages.archive');
        Route::post('/messages/{userId}/restore', [AlphaController::class, 'restoreConversation'])->middleware('throttle:20,1')->whereNumber('userId')->name('messages.restore');
        Route::get('/members', [AlphaController::class, 'members'])->name('members.index');
        Route::get('/members/{id}', [AlphaController::class, 'memberProfile'])->whereNumber('id')->name('members.show');
        Route::get('/profile', [AlphaController::class, 'myProfile'])->name('profile.me');
        Route::get('/profile/settings', [AlphaController::class, 'profileSettings'])->name('profile.settings');
        Route::post('/profile/settings', [AlphaController::class, 'updateProfileSettings'])->middleware('throttle:20,1')->name('profile.settings.update');
    });
