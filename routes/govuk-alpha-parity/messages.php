<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Http\Controllers\GovukAlpha\AlphaController;
use Illuminate\Support\Facades\Route;

/*
 * Accessible-frontend MESSAGES parity routes — group messaging (the high gap).
 *
 * Auto-required inside the {tenantSlug}/alpha + govuk-alpha. route group, so
 * every path below becomes /{tenantSlug}/alpha/messages/groups/... named
 * govuk-alpha.messages.groups.*.
 *
 * Static segments are registered BEFORE the {conversationId} wildcard so
 * `/messages/groups/new` can never be swallowed by `/messages/groups/{id}`.
 */

// --- group conversation list + creation (static segments first) ---
Route::get('/messages/groups', [AlphaController::class, 'messagesGroupsIndex'])
    ->name('messages.groups.index');

Route::get('/messages/groups/new', [AlphaController::class, 'messagesCreateGroupForm'])
    ->name('messages.groups.create');

Route::post('/messages/groups', [AlphaController::class, 'messagesStoreGroup'])
    ->middleware('throttle:nexus-route-10-per-1m')
    ->name('messages.groups.store');

// --- a single group conversation (wildcard) ---
Route::get('/messages/groups/{conversationId}', [AlphaController::class, 'messagesGroupShow'])
    ->whereNumber('conversationId')
    ->name('messages.groups.show');

Route::post('/messages/groups/{conversationId}', [AlphaController::class, 'messagesStoreGroupMessage'])
    ->middleware('throttle:nexus-route-30-per-1m')
    ->whereNumber('conversationId')
    ->name('messages.groups.message');

Route::post('/messages/groups/{conversationId}/members', [AlphaController::class, 'messagesGroupAddMember'])
    ->middleware('throttle:nexus-route-20-per-1m')
    ->whereNumber('conversationId')
    ->name('messages.groups.members.add');

Route::post('/messages/groups/{conversationId}/members/{targetUserId}/remove', [AlphaController::class, 'messagesGroupRemoveMember'])
    ->middleware('throttle:nexus-route-20-per-1m')
    ->whereNumber('conversationId')
    ->whereNumber('targetUserId')
    ->name('messages.groups.members.remove');

Route::post('/messages/groups/{conversationId}/m/{messageId}/react', [AlphaController::class, 'messagesToggleReaction'])
    ->middleware('throttle:nexus-route-60-per-1m')
    ->whereNumber('conversationId')
    ->whereNumber('messageId')
    ->name('messages.groups.react');

// --- per-message translation in a 1-to-1 conversation (no-JS, parity with
//     the React MessageBubble translate button). The static "groups" segment
//     above is registered first, so this {userId} route never swallows it. ---
Route::post('/messages/{userId}/m/{messageId}/translate', [AlphaController::class, 'messagesTranslateMessage'])
    ->middleware('throttle:nexus-route-20-per-1m')
    ->whereNumber('userId')
    ->whereNumber('messageId')
    ->name('messages.translate');

// --- send a voice message in a 1-to-1 conversation (no-JS): upload a recorded
//     audio clip (mobile `capture` opens the recorder). Mirrors the React
//     MediaRecorder voice send via the same AudioUploader + voice send path. ---
Route::post('/messages/{userId}/voice', [AlphaController::class, 'storeVoiceMessage'])
    ->middleware('throttle:nexus-route-10-per-1m')
    ->whereNumber('userId')
    ->name('messages.voice');
