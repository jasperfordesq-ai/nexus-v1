<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Http\Controllers\GovukAlpha\AlphaController;
use Illuminate\Support\Facades\Route;

/*
 * Volunteering parity routes (accessible GOV.UK frontend).
 *
 * Auto-required INSIDE the {tenantSlug}/alpha + govuk-alpha. group, so each
 * path below becomes /{tenantSlug}/alpha/... and each name becomes
 * govuk-alpha.volunteering.<x>. These ADD to the existing volunteering routes
 * declared in routes/govuk-alpha.php — they never collide because every name
 * here is new (my-organisations, recommended-shifts, emergency-alerts,
 * credentials, wellbeing, opportunities.create, org.dashboard/settings/wallet).
 *
 * Static segments are declared before any {id} wildcard. All POSTs are
 * rate-limited. Numeric route params use ->whereNumber('id').
 */

// ----- Member-facing volunteering features -----
Route::get('/volunteering/my-organisations', [AlphaController::class, 'volunteeringMyOrganisations'])
    ->name('volunteering.my-organisations');

Route::get('/volunteering/recommended-shifts', [AlphaController::class, 'volunteeringRecommendedShifts'])
    ->name('volunteering.recommended-shifts');

Route::get('/volunteering/emergency-alerts', [AlphaController::class, 'volunteeringEmergencyAlerts'])
    ->name('volunteering.emergency-alerts');
Route::post('/volunteering/emergency-alerts/{id}/respond', [AlphaController::class, 'volunteeringRespondEmergencyAlert'])
    ->whereNumber('id')->middleware('throttle:20,1')->name('volunteering.emergency-alerts.respond');

Route::get('/volunteering/credentials', [AlphaController::class, 'volunteeringCredentials'])
    ->name('volunteering.credentials');
Route::post('/volunteering/credentials', [AlphaController::class, 'volunteeringUploadCredential'])
    ->middleware('throttle:10,1')->name('volunteering.credentials.upload');
Route::post('/volunteering/credentials/{id}/delete', [AlphaController::class, 'volunteeringDeleteCredential'])
    ->whereNumber('id')->middleware('throttle:10,1')->name('volunteering.credentials.delete');

Route::get('/volunteering/wellbeing', [AlphaController::class, 'volunteeringWellbeing'])
    ->name('volunteering.wellbeing');
Route::post('/volunteering/wellbeing/checkin', [AlphaController::class, 'volunteeringWellbeingCheckin'])
    ->middleware('throttle:10,1')->name('volunteering.wellbeing.checkin');

// Donations / giving — community fundraising page + offline donate form.
Route::get('/volunteering/donations', [AlphaController::class, 'volunteeringDonations'])
    ->name('volunteering.donations');
Route::post('/volunteering/donations', [AlphaController::class, 'volunteeringStoreDonation'])
    ->middleware('throttle:10,1')->name('volunteering.donations.store');

// ----- Organisation management suite -----
// "/opportunities/create" is declared here; the existing numeric show route
// "/volunteering/opportunities/{id}" is constrained with whereNumber so the
// literal "create" segment can never match the wildcard.
Route::get('/volunteering/opportunities/create', [AlphaController::class, 'volunteeringCreateOpportunity'])
    ->name('volunteering.opportunities.create');
Route::post('/volunteering/opportunities/create', [AlphaController::class, 'volunteeringStoreOpportunity'])
    ->middleware('throttle:10,1')->name('volunteering.opportunities.store');

Route::get('/volunteering/organisations/{id}/dashboard', [AlphaController::class, 'volunteeringOrgDashboard'])
    ->whereNumber('id')->name('volunteering.org.dashboard');

Route::get('/volunteering/organisations/{id}/volunteers', [AlphaController::class, 'volunteeringOrgVolunteers'])
    ->whereNumber('id')->name('volunteering.org.volunteers');

Route::get('/volunteering/organisations/{id}/settings', [AlphaController::class, 'volunteeringOrgSettings'])
    ->whereNumber('id')->name('volunteering.org.settings');
Route::post('/volunteering/organisations/{id}/settings', [AlphaController::class, 'volunteeringUpdateOrgSettings'])
    ->whereNumber('id')->middleware('throttle:20,1')->name('volunteering.org.settings.update');

Route::get('/volunteering/organisations/{id}/wallet', [AlphaController::class, 'volunteeringOrgWallet'])
    ->whereNumber('id')->name('volunteering.org.wallet');
Route::post('/volunteering/organisations/{id}/wallet/deposit', [AlphaController::class, 'volunteeringOrgWalletDeposit'])
    ->whereNumber('id')->middleware('throttle:10,1')->name('volunteering.org.wallet.deposit');
Route::post('/volunteering/organisations/{id}/wallet/auto-pay', [AlphaController::class, 'volunteeringOrgAutoPay'])
    ->whereNumber('id')->middleware('throttle:20,1')->name('volunteering.org.wallet.auto-pay');

// ----- Group sign-ups (team shift reservations) -----
Route::get('/volunteering/group-signups', [AlphaController::class, 'volunteeringGroupSignups'])
    ->name('volunteering.group-signups');
Route::post('/volunteering/group-signups/{id}/members', [AlphaController::class, 'volunteeringAddGroupMember'])
    ->whereNumber('id')->middleware('throttle:20,1')->name('volunteering.group-signups.members.add');
Route::post('/volunteering/group-signups/{id}/members/{userId}/remove', [AlphaController::class, 'volunteeringRemoveGroupMember'])
    ->whereNumber('id')->whereNumber('userId')->middleware('throttle:20,1')->name('volunteering.group-signups.members.remove');
Route::post('/volunteering/group-signups/{id}/cancel', [AlphaController::class, 'volunteeringCancelGroupReservation'])
    ->whereNumber('id')->middleware('throttle:10,1')->name('volunteering.group-signups.cancel');

// ----- Expenses (volunteer expense claims) -----
Route::get('/volunteering/expenses', [AlphaController::class, 'volunteeringExpenses'])
    ->name('volunteering.expenses');
Route::post('/volunteering/expenses', [AlphaController::class, 'volunteeringSubmitExpense'])
    ->middleware('throttle:10,1')->name('volunteering.expenses.submit');

// ----- Safeguarding (training records + incident reports) -----
// Static "training" and "incidents" segments are declared before any wildcard so
// they can never collide with a future numeric route.
Route::get('/volunteering/training', [AlphaController::class, 'volunteeringSafeguarding'])
    ->name('volunteering.training');
Route::post('/volunteering/training', [AlphaController::class, 'volunteeringSafeguardingLogTraining'])
    ->middleware('throttle:10,1')->name('volunteering.training.store');

Route::get('/volunteering/incidents', [AlphaController::class, 'volunteeringSafeguarding'])
    ->name('volunteering.incidents');
Route::post('/volunteering/incidents', [AlphaController::class, 'volunteeringSafeguardingReportIncident'])
    ->middleware('throttle:10,1')->name('volunteering.incidents.store');
