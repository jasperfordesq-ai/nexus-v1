<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Http\Controllers\GovukAlpha\AlphaController;
use App\Http\Controllers\GovukAlpha\Middleware\RequireAccessibleAuthentication;
use Illuminate\Support\Facades\Route;

/**
 * Blog & Reviews parity routes (accessible / GOV.UK frontend).
 *
 * Auto-required INSIDE the {tenantSlug}/alpha + govuk-alpha. group, so each
 * path becomes /{tenantSlug}/alpha/... named govuk-alpha.blogreviews.*.
 *
 * The simple blog index/detail/feed + a flat add-comment form already live in
 * routes/govuk-alpha.php (govuk-alpha.blog.*); these routes add the rich
 * comment thread (edit/delete/reply/reactions), blog-post likes + likers page,
 * paginated reviews list, and review moderation. Static segments are registered
 * before any wildcard within each path.
 */

Route::middleware(RequireAccessibleAuthentication::class)->group(function () {
// --- Blog post comment thread (edit / delete / reply / reactions) ---------

// Comment-level actions (numeric id) — registered before the {slug} thread so
// "comments" can never be captured as a post slug.
Route::post('/blog/comments/{id}/update', [AlphaController::class, 'blogReviewsUpdateComment'])
    ->whereNumber('id')
    ->middleware('throttle:30,1')
    ->name('blogreviews.blog.comments.update');
Route::post('/blog/comments/{id}/delete', [AlphaController::class, 'blogReviewsDeleteComment'])
    ->whereNumber('id')
    ->middleware('throttle:30,1')
    ->name('blogreviews.blog.comments.delete');
Route::post('/blog/comments/{id}/react', [AlphaController::class, 'blogReviewsCommentReaction'])
    ->whereNumber('id')
    ->middleware('throttle:60,1')
    ->name('blogreviews.blog.comments.react');

// Per-post comment thread + post-level reactions/likers (slug param).
Route::get('/blog/{slug}/comments', [AlphaController::class, 'blogReviewsPostComments'])
    ->where('slug', '[a-zA-Z0-9_-]+')
    ->name('blogreviews.blog.comments');
// NOTE: path is /comments/add (NOT /comments) so it does not collide with the
// base POST /blog/{slug}/comments (govuk-alpha.blog.comments.store) — Laravel
// silently overwrites a same-method+path route, which would unname the base one.
Route::post('/blog/{slug}/comments/add', [AlphaController::class, 'blogReviewsStorePostComment'])
    ->where('slug', '[a-zA-Z0-9_-]+')
    ->middleware('throttle:20,1')
    ->name('blogreviews.blog.comments.store');
Route::post('/blog/{slug}/react', [AlphaController::class, 'blogReviewsPostReaction'])
    ->where('slug', '[a-zA-Z0-9_-]+')
    ->middleware('throttle:60,1')
    ->name('blogreviews.blog.react');
Route::get('/blog/{slug}/likers/{reaction}', [AlphaController::class, 'blogReviewsPostLikers'])
    ->where('slug', '[a-zA-Z0-9_-]+')
    ->where('reaction', '[a-zA-Z_]+')
    ->name('blogreviews.blog.likers');

// --- Reviews: paginated list (received / given) with cursor load-more -----

Route::get('/reviews/list', [AlphaController::class, 'blogReviewsList'])
    ->name('blogreviews.reviews.list');

// --- Reviews: moderation (comment + react on a review) --------------------

Route::get('/reviews/{id}/comments', [AlphaController::class, 'blogReviewsReviewComments'])
    ->whereNumber('id')
    ->name('blogreviews.reviews.comments');
Route::post('/reviews/{id}/comments', [AlphaController::class, 'blogReviewsStoreReviewComment'])
    ->whereNumber('id')
    ->middleware('throttle:20,1')
    ->name('blogreviews.reviews.comments.store');
Route::post('/reviews/{id}/react', [AlphaController::class, 'blogReviewsReviewReaction'])
    ->whereNumber('id')
    ->middleware('throttle:60,1')
    ->name('blogreviews.reviews.react');
});
