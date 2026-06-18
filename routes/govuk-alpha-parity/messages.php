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
    ->middleware('throttle:10,1')
    ->name('messages.groups.store');

// --- a single group conversation (wildcard) ---
Route::get('/messages/groups/{conversationId}', [AlphaController::class, 'messagesGroupShow'])
    ->whereNumber('conversationId')
    ->name('messages.groups.show');

Route::post('/messages/groups/{conversationId}', [AlphaController::class, 'messagesStoreGroupMessage'])
    ->middleware('throttle:30,1')
    ->whereNumber('conversationId')
    ->name('messages.groups.message');

Route::post('/messages/groups/{conversationId}/members', [AlphaController::class, 'messagesGroupAddMember'])
    ->middleware('throttle:20,1')
    ->whereNumber('conversationId')
    ->name('messages.groups.members.add');

Route::post('/messages/groups/{conversationId}/members/{targetUserId}/remove', [AlphaController::class, 'messagesGroupRemoveMember'])
    ->middleware('throttle:20,1')
    ->whereNumber('conversationId')
    ->whereNumber('targetUserId')
    ->name('messages.groups.members.remove');

Route::post('/messages/groups/{conversationId}/m/{messageId}/react', [AlphaController::class, 'messagesToggleReaction'])
    ->middleware('throttle:60,1')
    ->whereNumber('conversationId')
    ->whereNumber('messageId')
    ->name('messages.groups.react');
