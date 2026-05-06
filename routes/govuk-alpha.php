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

        Route::get('/feed', [AlphaController::class, 'feed'])->name('feed');
        Route::post('/feed/posts', [AlphaController::class, 'storeFeedPost'])->name('feed.posts.store');
        Route::get('/listings', [AlphaController::class, 'listings'])->name('listings.index');
        Route::get('/listings/{id}', [AlphaController::class, 'listing'])->whereNumber('id')->name('listings.show');
        Route::get('/members', [AlphaController::class, 'members'])->name('members.index');
    });
