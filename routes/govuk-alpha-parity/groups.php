<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Http\Controllers\GovukAlpha\AlphaController;
use App\Http\Controllers\GovukAlpha\Middleware\RequireAccessibleAuthentication;
use Illuminate\Support\Facades\Route;

/*
 * Accessible (GOV.UK) frontend — Groups parity routes.
 *
 * Auto-required INSIDE the {tenantSlug}/alpha group in routes/govuk-alpha.php,
 * so each path becomes /{tenantSlug}/alpha/... and each name 'govuk-alpha.groups....'.
 * The base group routes (index/show/create/edit/manage/discussions/feed) are
 * already registered in routes/govuk-alpha.php; this file ONLY adds the parity
 * gaps (invites, per-group notification preferences, avatar/cover images).
 * Every POST is throttled. Numeric route params use whereNumber().
 */

Route::middleware(RequireAccessibleAuthentication::class)->group(function () {
// --- Invite members (owner/admin) ---
Route::get('/groups/{id}/invite', [AlphaController::class, 'groupsInvite'])
    ->whereNumber('id')->name('groups.invite');
Route::post('/groups/{id}/invite/link', [AlphaController::class, 'groupsCreateInviteLink'])
    ->whereNumber('id')->middleware('throttle:15,1')->name('groups.invite.link');
Route::post('/groups/{id}/invite/email', [AlphaController::class, 'groupsSendInvites'])
    ->whereNumber('id')->middleware('throttle:10,1')->name('groups.invite.email');
Route::post('/groups/{id}/invite/{inviteId}/revoke', [AlphaController::class, 'groupsRevokeInvite'])
    ->whereNumber('id')->whereNumber('inviteId')->middleware('throttle:20,1')->name('groups.invite.revoke');

// --- Per-group notification preferences (members + admins) ---
Route::get('/groups/{id}/notifications', [AlphaController::class, 'groupsNotificationPrefs'])
    ->whereNumber('id')->name('groups.notifications');
Route::post('/groups/{id}/notifications', [AlphaController::class, 'groupsUpdateNotificationPrefs'])
    ->whereNumber('id')->middleware('throttle:20,1')->name('groups.notifications.update');

// --- Avatar + cover image management (owner/admin) ---
Route::get('/groups/{id}/image', [AlphaController::class, 'groupsImage'])
    ->whereNumber('id')->name('groups.image');
Route::post('/groups/{id}/image', [AlphaController::class, 'groupsUpdateImage'])
    ->whereNumber('id')->middleware('throttle:15,1')->name('groups.image.update');

// --- Files (list + upload visible to members; delete by uploader or admin) ---
Route::get('/groups/{id}/files', [AlphaController::class, 'groupsFiles'])
    ->whereNumber('id')->name('groups.files.index');
Route::post('/groups/{id}/files', [AlphaController::class, 'groupsUploadFile'])
    ->whereNumber('id')->middleware('throttle:20,1')->name('groups.files.upload');
Route::get('/groups/{id}/files/{fileId}/download', [AlphaController::class, 'groupsDownloadFile'])
    ->whereNumber('id')->whereNumber('fileId')->name('groups.files.download');
Route::post('/groups/{id}/files/{fileId}/delete', [AlphaController::class, 'groupsDeleteFile'])
    ->whereNumber('id')->whereNumber('fileId')->middleware('throttle:20,1')->name('groups.files.delete');

// --- Announcements (list visible to members; create/edit/delete/pin admin-only) ---
Route::get('/groups/{id}/announcements', [AlphaController::class, 'groupsAnnouncements'])
    ->whereNumber('id')->name('groups.announcements');
Route::post('/groups/{id}/announcements', [AlphaController::class, 'groupsCreateAnnouncement'])
    ->whereNumber('id')->middleware('throttle:30,1')->name('groups.announcements.create');
Route::get('/groups/{id}/announcements/{annId}/edit', [AlphaController::class, 'groupsEditAnnouncement'])
    ->whereNumber('id')->whereNumber('annId')->name('groups.announcements.edit');
Route::post('/groups/{id}/announcements/{annId}/edit', [AlphaController::class, 'groupsUpdateAnnouncement'])
    ->whereNumber('id')->whereNumber('annId')->middleware('throttle:30,1')->name('groups.announcements.update');
Route::post('/groups/{id}/announcements/{annId}/delete', [AlphaController::class, 'groupsDeleteAnnouncement'])
    ->whereNumber('id')->whereNumber('annId')->middleware('throttle:30,1')->name('groups.announcements.delete');
Route::post('/groups/{id}/announcements/{annId}/pin', [AlphaController::class, 'groupsPinAnnouncement'])
    ->whereNumber('id')->whereNumber('annId')->middleware('throttle:30,1')->name('groups.announcements.pin');
});
