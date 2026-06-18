<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Http\Controllers\GovukAlpha\AlphaController;
use Illuminate\Support\Facades\Route;

/*
 * Accessible (GOV.UK) frontend — Gamification parity routes
 * (XP shop, badge collections/detail/showcase, competitive leaderboard with the
 * nexus_score metric, seasons, personal journey, member spotlight, engagement
 * history, nexus tier ladder, and ranked-choice / managed polls).
 *
 * Auto-required INSIDE the {tenantSlug}/alpha group in routes/govuk-alpha.php,
 * so each path becomes /{tenantSlug}/alpha/... and each name 'govuk-alpha.gamification....'.
 * Static segments are registered BEFORE wildcard {pollId}/{key} segments. Every
 * POST is throttled. Numeric route params use whereNumber().
 */

// --- Achievements: XP shop ---
Route::get('/achievements/shop', [AlphaController::class, 'gamificationShop'])
    ->name('gamification.shop');
Route::post('/achievements/shop/purchase', [AlphaController::class, 'gamificationPurchase'])
    ->middleware('throttle:10,1')->name('gamification.shop.purchase');

// --- Achievements: badge collections ---
Route::get('/achievements/collections', [AlphaController::class, 'gamificationCollections'])
    ->name('gamification.collections');

// --- Achievements: showcase management (static before badge {key}) ---
Route::get('/achievements/showcase', [AlphaController::class, 'gamificationShowcase'])
    ->name('gamification.showcase');
Route::post('/achievements/showcase', [AlphaController::class, 'gamificationUpdateShowcase'])
    ->middleware('throttle:10,1')->name('gamification.showcase.update');

// --- Achievements: engagement history ---
Route::get('/achievements/engagement', [AlphaController::class, 'gamificationEngagement'])
    ->name('gamification.engagement');

// --- Achievements: badge detail (wildcard {key} last under /achievements/badges) ---
Route::get('/achievements/badges/{key}', [AlphaController::class, 'gamificationBadgeDetail'])
    ->where('key', '[A-Za-z0-9_\-]+')->name('gamification.badge');

// --- Leaderboard: competitive (4 metrics incl. nexus_score) ---
Route::get('/leaderboard/competitive', [AlphaController::class, 'gamificationCompetitive'])
    ->name('gamification.competitive');
// --- Leaderboard: seasons ---
Route::get('/leaderboard/seasons', [AlphaController::class, 'gamificationSeasons'])
    ->name('gamification.seasons');
// --- Leaderboard: personal journey ---
Route::get('/leaderboard/journey', [AlphaController::class, 'gamificationPersonalJourney'])
    ->name('gamification.journey');
// --- Leaderboard: member spotlight ---
Route::get('/leaderboard/spotlight', [AlphaController::class, 'gamificationSpotlight'])
    ->name('gamification.spotlight');

// --- Nexus score: tier ladder ---
Route::get('/nexus-score/tiers', [AlphaController::class, 'gamificationTierLadder'])
    ->name('gamification.tiers');

// --- Polls: parity create (supports the ranked type) + management (static before {pollId}) ---
Route::get('/polls/parity/create', [AlphaController::class, 'gamificationCreatePoll'])
    ->name('gamification.poll.create');
Route::post('/polls/parity/create', [AlphaController::class, 'gamificationStorePoll'])
    ->middleware('throttle:10,1')->name('gamification.poll.store');
Route::get('/polls/parity/manage', [AlphaController::class, 'gamificationManagePolls'])
    ->name('gamification.poll.manage');

// --- Polls: ranked-choice voting / results / export / delete (wildcard {pollId} last) ---
Route::get('/polls/{pollId}/rank', [AlphaController::class, 'gamificationRankedVote'])
    ->whereNumber('pollId')->name('gamification.poll.rank');
Route::post('/polls/{pollId}/rank', [AlphaController::class, 'gamificationStoreRankedVote'])
    ->whereNumber('pollId')->middleware('throttle:20,1')->name('gamification.poll.rank.store');
Route::get('/polls/{pollId}/export', [AlphaController::class, 'gamificationExportPoll'])
    ->whereNumber('pollId')->middleware('throttle:10,1')->name('gamification.poll.export');
Route::post('/polls/{pollId}/delete', [AlphaController::class, 'gamificationDeletePoll'])
    ->whereNumber('pollId')->middleware('throttle:10,1')->name('gamification.poll.delete');

// --- Polls: detail + social (like / comment), mirroring the React poll card's
//     SocialInteractionPanel. Static sub-segments (/rank, /export, /delete,
//     /like, /comment) are registered BEFORE the bare /polls/{pollId} detail. ---
Route::post('/polls/{pollId}/like', [AlphaController::class, 'gamificationPollLike'])
    ->whereNumber('pollId')->middleware('throttle:60,1')->name('gamification.poll.like');
Route::post('/polls/{pollId}/comment', [AlphaController::class, 'gamificationPollComment'])
    ->whereNumber('pollId')->middleware('throttle:30,1')->name('gamification.poll.comment');
Route::get('/polls/{pollId}', [AlphaController::class, 'gamificationPollDetail'])
    ->whereNumber('pollId')->name('gamification.poll.detail');
