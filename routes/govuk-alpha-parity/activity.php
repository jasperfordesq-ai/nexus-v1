<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Http\Controllers\GovukAlpha\AlphaController;
use Illuminate\Support\Facades\Route;

/*
 * Activity insights parity route (accessible GOV.UK frontend).
 *
 * Required INSIDE the {tenantSlug}/alpha + govuk-alpha. group, so the path
 * below resolves to /{tenantSlug}/alpha/activity/insights and the name to
 * govuk-alpha.activity.insights.
 *
 * The core /alpha/activity dashboard is registered in routes/govuk-alpha.php
 * (AlphaController::activity). This adds the React-parity "Activity insights"
 * page (ActivityDashboardPage.tsx) which renders the same
 * MemberActivityService::getDashboardData() with the richer visualisation the
 * core summary omits — activity-type badges, skill offer/request tags +
 * endorsements, a dual-bar monthly chart, sign-aware net balance, and a
 * two-column quick-stats layout.
 *
 * Distinct path from the core /activity route (an extra /insights segment),
 * so there is no collision.
 */
Route::get('/activity/insights', [AlphaController::class, 'activityInsights'])
    ->name('activity.insights');
