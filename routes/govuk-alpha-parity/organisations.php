<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Http\Controllers\GovukAlpha\AlphaController;
use App\Http\Controllers\GovukAlpha\Middleware\RequireAccessibleAuthentication;
use Illuminate\Support\Facades\Route;

/**
 * Organisations parity routes (accessible / GOV.UK frontend).
 *
 * Auto-required INSIDE the {tenantSlug}/alpha + govuk-alpha. group, so each path
 * becomes /{tenantSlug}/alpha/organisations/... named govuk-alpha.organisations.*.
 *
 * The simple directory + embedded register form live at /organisations
 * (govuk-alpha.organisations.index/show/store, defined in routes/govuk-alpha.php).
 * The parity additions registered here are: a paginated browse list, a dedicated
 * registration page, a "manage my organisations" entry, a per-org open-jobs
 * listing, and an HTML-first apply-to-opportunity confirm page. Static segments
 * are registered before the numeric wildcard within this file.
 *
 * NOTE: the apply confirm page POSTs to the EXISTING govuk-alpha.volunteering.apply.store
 * route (routes/govuk-alpha.php); no new apply route is defined here.
 */

Route::middleware(RequireAccessibleAuthentication::class)->group(function () {
// Paginated browse list (cursor "load more"). Static before {id}.
Route::get('/organisations/browse', [AlphaController::class, 'organisationsBrowse'])
    ->name('organisations.browse');

// Dedicated full-page registration form + store.
Route::get('/organisations/register', [AlphaController::class, 'organisationsRegisterForm'])
    ->name('organisations.register.form');
Route::post('/organisations/register', [AlphaController::class, 'organisationsRegister'])
    ->middleware('throttle:10,1')
    ->name('organisations.register');

// "Manage my organisations" entry page.
Route::get('/organisations/manage', [AlphaController::class, 'organisationsManage'])
    ->name('organisations.manage');

// HTML-first apply-to-opportunity confirm page (posts to volunteering.apply.store).
Route::get('/organisations/opportunities/{id}/apply', [AlphaController::class, 'organisationsApplyForm'])
    ->whereNumber('id')
    ->name('organisations.apply.form');

// Per-organisation open-jobs / vacancies listing (numeric id).
Route::get('/organisations/{id}/jobs', [AlphaController::class, 'organisationsJobs'])
    ->whereNumber('id')
    ->name('organisations.jobs');
});
