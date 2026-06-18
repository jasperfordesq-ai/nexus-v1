<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Http\Controllers\GovukAlpha\AlphaController;
use Illuminate\Support\Facades\Route;

/*
 * Federation parity routes (accessible GOV.UK frontend).
 *
 * Required INSIDE the {tenantSlug}/alpha + govuk-alpha. group, so the paths
 * below resolve to /{tenantSlug}/alpha/... and the names to govuk-alpha....
 *
 * The full federation surface (hub, opt-in/out, settings, members, listings,
 * events, groups, connections, messages, transfer) is registered in
 * routes/govuk-alpha.php. This file adds the ONE React-parity page that was
 * missing: the guided 4-step Federation Onboarding wizard
 * (Welcome -> Privacy -> Communication -> Confirm), mirroring
 * react-frontend/src/pages/federation/FederationOnboardingPage.tsx.
 *
 * Distinct /federation/onboarding path — no collision with the generic member
 * onboarding routes (/onboarding) or the flat /federation/opt-in form.
 */
Route::get('/federation/onboarding', [AlphaController::class, 'federationOnboarding'])
    ->name('federation.onboarding');

Route::post('/federation/onboarding', [AlphaController::class, 'federationOnboardingStore'])
    ->middleware('throttle:20,1')
    ->name('federation.onboarding.store');
