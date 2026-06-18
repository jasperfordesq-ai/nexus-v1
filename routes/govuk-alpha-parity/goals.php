<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Http\Controllers\GovukAlpha\AlphaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Goals — accessible (GOV.UK) frontend parity routes
|--------------------------------------------------------------------------
|
| Auto-required INSIDE the {tenantSlug}/alpha + govuk-alpha. group, so each
| path below becomes /{tenantSlug}/alpha/... named govuk-alpha.goals....
|
| These complement the core goals routes in routes/govuk-alpha.php (browse,
| detail, create, edit, delete, progress, complete, templates, buddying,
| discover, buddy-nudge). They close React parity gaps:
|   - Goal Insights Panel  (GoalInsightsPanel.tsx)
|   - Check-in logging     (GoalCheckinModal.tsx)
|   - Reminder settings    (GoalReminderToggle.tsx)
|   - Multi-type buddy actions (GoalsPage.tsx handleBuddyAction)
|
| All paths are /goals/{id}/<segment>, strictly more specific than the core
| /goals/{id} route, so there is no collision regardless of load order.
*/

// Insights panel — owner / buddy / public viewer.
Route::get('/goals/{id}/insights', [AlphaController::class, 'goalsInsights'])
    ->whereNumber('id')
    ->name('goals.insights');

// Check-in (owner only): form + history, then record.
Route::get('/goals/{id}/checkin', [AlphaController::class, 'goalsCheckin'])
    ->whereNumber('id')
    ->name('goals.checkin');
Route::post('/goals/{id}/checkin', [AlphaController::class, 'goalsStoreCheckin'])
    ->whereNumber('id')
    ->middleware('throttle:20,1')
    ->name('goals.checkin.store');

// Reminder settings (owner, or any member for a public goal).
Route::get('/goals/{id}/reminder', [AlphaController::class, 'goalsReminder'])
    ->whereNumber('id')
    ->name('goals.reminder');
Route::post('/goals/{id}/reminder', [AlphaController::class, 'goalsSaveReminder'])
    ->whereNumber('id')
    ->middleware('throttle:20,1')
    ->name('goals.reminder.save');
Route::post('/goals/{id}/reminder/delete', [AlphaController::class, 'goalsDeleteReminder'])
    ->whereNumber('id')
    ->middleware('throttle:20,1')
    ->name('goals.reminder.delete');

// Buddy actions (buddy only): choose nudge / encouragement / offer_help.
Route::get('/goals/{id}/buddy-actions', [AlphaController::class, 'goalsBuddyActions'])
    ->whereNumber('id')
    ->name('goals.buddy-actions');
Route::post('/goals/{id}/buddy-actions', [AlphaController::class, 'goalsStoreBuddyAction'])
    ->whereNumber('id')
    ->middleware('throttle:20,1')
    ->name('goals.buddy-actions.send');

// Social (owner / buddy / public viewer): heart-like + threaded comments,
// mirroring the React GoalDetailPage <SocialInteractionPanel targetType="goal">.
Route::get('/goals/{id}/social', [AlphaController::class, 'goalsSocial'])
    ->whereNumber('id')
    ->name('goals.social');
Route::post('/goals/{id}/like', [AlphaController::class, 'goalsToggleLike'])
    ->whereNumber('id')
    ->middleware('throttle:60,1')
    ->name('goals.like');
Route::post('/goals/{id}/comments', [AlphaController::class, 'goalsStoreComment'])
    ->whereNumber('id')
    ->middleware('throttle:20,1')
    ->name('goals.comments.store');
Route::post('/goals/{id}/comments/{commentId}/delete', [AlphaController::class, 'goalsDeleteComment'])
    ->whereNumber('id')
    ->whereNumber('commentId')
    ->middleware('throttle:30,1')
    ->name('goals.comments.delete');
