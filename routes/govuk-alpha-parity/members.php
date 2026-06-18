<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Http\Controllers\GovukAlpha\AlphaController;
use Illuminate\Support\Facades\Route;

/*
 * Members directory & profiles parity routes (accessible GOV.UK frontend).
 *
 * Required INSIDE the {tenantSlug}/alpha + govuk-alpha. group, so the paths
 * below resolve to /{tenantSlug}/alpha/... and the names to govuk-alpha....
 *
 * The core members.index / members.show pages are registered in
 * routes/govuk-alpha.php. This file adds the React-parity reputation surface
 * that the core profile page does not render: the per-member "Reputation and
 * recognition" page (NEXUS score, full stats incl. groups/events, per-method
 * verification badges, showcased earned badges).
 *
 * Distinct path from the /members/{id} core route (an extra /insights segment),
 * so there is no collision with the numeric members.show wildcard.
 */
Route::get('/members/{id}/insights', [AlphaController::class, 'membersInsights'])
    ->whereNumber('id')
    ->name('members.insights');
