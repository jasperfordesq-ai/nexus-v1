<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Http\Controllers\GovukAlpha\AlphaController;
use App\Http\Controllers\GovukAlpha\Middleware\RequireAccessibleAuthentication;
use Illuminate\Support\Facades\Route;

/*
 * Accessible (GOV.UK) frontend — Ideation parity routes.
 *
 * Auto-required INSIDE the {tenantSlug}/alpha group in routes/govuk-alpha.php,
 * so each path becomes /{tenantSlug}/alpha/... and each name 'govuk-alpha.ideation....'.
 * Static segments are registered BEFORE wildcard {id} segments. Every POST is
 * throttled. Numeric route params use whereNumber().
 */

Route::middleware(RequireAccessibleAuthentication::class)->group(function () {
// --- Campaigns (static prefix before challenge {id}) ---
Route::get('/ideation/campaigns', [AlphaController::class, 'ideationCampaigns'])
    ->name('ideation.campaigns');
Route::post('/ideation/campaigns', [AlphaController::class, 'ideationStoreCampaign'])
    ->middleware('throttle:nexus-route-20-per-1m')->name('ideation.campaigns.store');
Route::get('/ideation/campaigns/{id}', [AlphaController::class, 'ideationCampaignDetail'])
    ->whereNumber('id')->name('ideation.campaign');
Route::post('/ideation/campaigns/{id}', [AlphaController::class, 'ideationUpdateCampaign'])
    ->whereNumber('id')->middleware('throttle:nexus-route-20-per-1m')->name('ideation.campaign.update');
Route::post('/ideation/campaigns/{id}/delete', [AlphaController::class, 'ideationDeleteCampaign'])
    ->whereNumber('id')->middleware('throttle:nexus-route-20-per-1m')->name('ideation.campaign.delete');
Route::post('/ideation/campaigns/{id}/challenges/{challengeId}/unlink', [AlphaController::class, 'ideationUnlinkCampaignChallenge'])
    ->whereNumber('id')->whereNumber('challengeId')->middleware('throttle:nexus-route-20-per-1m')->name('ideation.campaign.unlink');

// --- Outcomes dashboard (static prefix before challenge {id}) ---
Route::get('/ideation/outcomes', [AlphaController::class, 'ideationOutcomes'])
    ->name('ideation.outcomes');

// --- Browse by popular tag (static prefix before challenge {id}) ---
Route::get('/ideation/tags', [AlphaController::class, 'ideationPopularTags'])
    ->name('ideation.tags');

// --- Challenge create (static segment before {id}) ---
Route::get('/ideation/new', [AlphaController::class, 'ideationCreateChallenge'])
    ->name('ideation.create');
Route::post('/ideation/new', [AlphaController::class, 'ideationStoreChallenge'])
    ->middleware('throttle:nexus-route-10-per-1m')->name('ideation.store');

// --- Challenge edit / lifecycle / favorite / duplicate / delete / link / outcome ---
Route::get('/ideation/{id}/manage', [AlphaController::class, 'ideationManageChallenge'])
    ->whereNumber('id')->name('ideation.manage');
Route::get('/ideation/{id}/edit', [AlphaController::class, 'ideationEditChallenge'])
    ->whereNumber('id')->name('ideation.edit');
Route::post('/ideation/{id}/edit', [AlphaController::class, 'ideationUpdateChallenge'])
    ->whereNumber('id')->middleware('throttle:nexus-route-10-per-1m')->name('ideation.update');
Route::post('/ideation/{id}/status', [AlphaController::class, 'ideationChallengeStatus'])
    ->whereNumber('id')->middleware('throttle:nexus-route-20-per-1m')->name('ideation.challenge.status');
Route::post('/ideation/{id}/favorite', [AlphaController::class, 'ideationToggleFavorite'])
    ->whereNumber('id')->middleware('throttle:nexus-route-40-per-1m')->name('ideation.favorite');
Route::post('/ideation/{id}/duplicate', [AlphaController::class, 'ideationDuplicateChallenge'])
    ->whereNumber('id')->middleware('throttle:nexus-route-10-per-1m')->name('ideation.duplicate');
Route::post('/ideation/{id}/delete', [AlphaController::class, 'ideationDeleteChallenge'])
    ->whereNumber('id')->middleware('throttle:nexus-route-10-per-1m')->name('ideation.delete');
Route::post('/ideation/{id}/link-campaign', [AlphaController::class, 'ideationLinkCampaign'])
    ->whereNumber('id')->middleware('throttle:nexus-route-20-per-1m')->name('ideation.link-campaign');
Route::get('/ideation/{id}/outcome', [AlphaController::class, 'ideationOutcomeEdit'])
    ->whereNumber('id')->name('ideation.outcome');
Route::post('/ideation/{id}/outcome', [AlphaController::class, 'ideationStoreOutcome'])
    ->whereNumber('id')->middleware('throttle:nexus-route-20-per-1m')->name('ideation.outcome.store');

// --- Draft ideas (list + save/publish an existing draft) ---
Route::get('/ideation/{id}/drafts', [AlphaController::class, 'ideationDrafts'])
    ->whereNumber('id')->name('ideation.drafts');
Route::post('/ideation/{id}/drafts/{ideaId}', [AlphaController::class, 'ideationUpdateDraftIdea'])
    ->whereNumber('id')->whereNumber('ideaId')->middleware('throttle:nexus-route-20-per-1m')->name('ideation.drafts.update');

// --- Idea detail + interactions (deepest path) ---
Route::get('/ideation/{id}/ideas/{ideaId}', [AlphaController::class, 'ideationIdeaDetail'])
    ->whereNumber('id')->whereNumber('ideaId')->name('ideation.idea');
Route::post('/ideation/{id}/ideas/{ideaId}/comments', [AlphaController::class, 'ideationStoreComment'])
    ->whereNumber('id')->whereNumber('ideaId')->middleware('throttle:nexus-route-20-per-1m')->name('ideation.idea.comments.store');
Route::post('/ideation/{id}/ideas/{ideaId}/comments/{commentId}/delete', [AlphaController::class, 'ideationDeleteComment'])
    ->whereNumber('id')->whereNumber('ideaId')->whereNumber('commentId')->middleware('throttle:nexus-route-20-per-1m')->name('ideation.idea.comments.delete');
Route::post('/ideation/{id}/ideas/{ideaId}/toggle-vote', [AlphaController::class, 'ideationIdeaVote'])
    ->whereNumber('id')->whereNumber('ideaId')->middleware('throttle:nexus-route-30-per-1m')->name('ideation.idea.vote');
Route::post('/ideation/{id}/ideas/{ideaId}/status', [AlphaController::class, 'ideationIdeaStatus'])
    ->whereNumber('id')->whereNumber('ideaId')->middleware('throttle:nexus-route-20-per-1m')->name('ideation.idea.status');
Route::post('/ideation/{id}/ideas/{ideaId}/delete', [AlphaController::class, 'ideationDeleteIdea'])
    ->whereNumber('id')->whereNumber('ideaId')->middleware('throttle:nexus-route-20-per-1m')->name('ideation.idea.delete');
Route::post('/ideation/{id}/ideas/{ideaId}/media', [AlphaController::class, 'ideationAddMedia'])
    ->whereNumber('id')->whereNumber('ideaId')->middleware('throttle:nexus-route-20-per-1m')->name('ideation.idea.media.store');
Route::post('/ideation/{id}/ideas/{ideaId}/convert', [AlphaController::class, 'ideationConvertToGroup'])
    ->whereNumber('id')->whereNumber('ideaId')->middleware('throttle:nexus-route-10-per-1m')->name('ideation.idea.convert');
});
