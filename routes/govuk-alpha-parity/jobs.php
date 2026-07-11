<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Http\Controllers\GovukAlpha\AlphaController;
use App\Http\Controllers\GovukAlpha\Middleware\RequireAccessibleAuthentication;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Jobs — accessible (GOV.UK) frontend parity routes
|--------------------------------------------------------------------------
|
| Auto-required INSIDE the {tenantSlug}/alpha + govuk-alpha. group, so each
| path below becomes /{tenantSlug}/alpha/... named govuk-alpha.jobs....
|
| These complement the core jobs routes in routes/govuk-alpha.php (browse,
| detail, apply, save, my-postings, alerts, applicants). They close React
| parity gaps J8 (analytics dashboard), J3 (pipeline board), J5 (qualification
| tool), plus talent search and the employer brand page.
|
| Static segments are registered BEFORE wildcard {employerId}/{candidateId}
| within this file. The numeric {id} sub-routes (/jobs/{id}/analytics, etc.)
| are distinct paths from the core /jobs/{id} route so there is no collision.
*/

Route::middleware(RequireAccessibleAuthentication::class)->group(function () {
// Bias audit — admin-only hiring-fairness analytics (static, before wildcards).
// Throttled to match the API (AdminJobsController::biasAudit, 10/min) — caps
// rapid re-querying that could harvest PII patterns from the aggregations.
Route::get('/jobs/bias-audit', [AlphaController::class, 'jobsBiasAudit'])
    ->middleware('throttle:10,1')
    ->name('jobs.bias-audit');

// Talent search — employer-only candidate discovery (static, before wildcards).
Route::get('/jobs/talent-search', [AlphaController::class, 'jobsTalentSearch'])
    ->name('jobs.talent');
Route::get('/jobs/talent-search/{candidateId}', [AlphaController::class, 'jobsTalentProfile'])
    ->whereNumber('candidateId')
    ->name('jobs.talent.profile');

// Employer brand page — open jobs + reviews for a given employer.
Route::get('/jobs/employers/{employerId}', [AlphaController::class, 'jobsEmployerBrand'])
    ->whereNumber('employerId')
    ->name('jobs.employer');

// Employer onboarding landing — guided funnel into the create-vacancy form
// (static; registered before the wildcard {id} sub-routes below).
Route::get('/jobs/employer-onboarding', [AlphaController::class, 'jobsEmployerOnboarding'])
    ->name('jobs.onboarding');

// Candidate interviews & offers inbox (static; before wildcards).
Route::get('/jobs/responses', [AlphaController::class, 'jobsResponses'])
    ->name('jobs.responses');

// Per-vacancy owner tools.
Route::get('/jobs/{id}/analytics', [AlphaController::class, 'jobsAnalytics'])
    ->whereNumber('id')
    ->name('jobs.analytics');
Route::get('/jobs/{id}/pipeline', [AlphaController::class, 'jobsPipeline'])
    ->whereNumber('id')
    ->name('jobs.pipeline');

// "Am I qualified?" assessment for a single vacancy (any member).
Route::get('/jobs/{id}/qualified', [AlphaController::class, 'jobsQualification'])
    ->whereNumber('id')
    ->name('jobs.qualified');

// CV download for an application — applicant / poster / admin only, blind-hiring
// aware (mirrors JobVacanciesController::downloadCv). Throttled like the API.
Route::get('/jobs/applications/{applicationId}/cv', [AlphaController::class, 'jobsDownloadCv'])
    ->whereNumber('applicationId')
    ->middleware('throttle:20,1')
    ->name('jobs.applications.cv');

// Application status-history timeline — applicant / owner / admin only
// (JobVacancyService::getApplicationHistory enforces access + redacts).
Route::get('/jobs/applications/{applicationId}/history', [AlphaController::class, 'jobsApplicationHistory'])
    ->whereNumber('applicationId')
    ->name('jobs.applications.history');

// Candidate responses — accept/decline an interview, accept/reject an offer.
// Each service method carries its own owner + state checks; throttled POSTs.
Route::post('/jobs/interviews/{interviewId}/accept', [AlphaController::class, 'jobsAcceptInterview'])
    ->whereNumber('interviewId')
    ->middleware('throttle:30,1')
    ->name('jobs.interviews.accept');
Route::post('/jobs/interviews/{interviewId}/decline', [AlphaController::class, 'jobsDeclineInterview'])
    ->whereNumber('interviewId')
    ->middleware('throttle:30,1')
    ->name('jobs.interviews.decline');
Route::post('/jobs/offers/{offerId}/accept', [AlphaController::class, 'jobsAcceptOffer'])
    ->whereNumber('offerId')
    ->middleware('throttle:20,1')
    ->name('jobs.offers.accept');
Route::post('/jobs/offers/{offerId}/reject', [AlphaController::class, 'jobsRejectOffer'])
    ->whereNumber('offerId')
    ->middleware('throttle:20,1')
    ->name('jobs.offers.reject');
});
