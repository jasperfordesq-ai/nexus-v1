<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Http\Controllers\GovukAlpha\AlphaController;
use Illuminate\Support\Facades\Route;

/*
 * AI Chat parity routes (accessible GOV.UK frontend).
 *
 * Auto-required inside the {tenantSlug}/alpha + govuk-alpha. group, so each path
 * becomes /{tenantSlug}/alpha/chat and each name govuk-alpha.chat.<x>. No-JS,
 * single-turn-per-reload (see AiChatParity). Feature-gated on 'ai_chat'.
 */

Route::get('/chat', [AlphaController::class, 'aiChat'])->name('chat.index');
Route::post('/chat', [AlphaController::class, 'aiChatSend'])
    ->middleware('throttle:20,1')->name('chat.send');
