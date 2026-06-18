<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Http\Controllers\GovukAlpha\AlphaController;
use Illuminate\Support\Facades\Route;

/*
 * Settings / auth / onboarding parity routes (accessible GOV.UK frontend).
 *
 * Required INSIDE the {tenantSlug}/alpha + govuk-alpha. group, so the paths
 * below resolve to /{tenantSlug}/alpha/... and the names to govuk-alpha....
 *
 * These complement the core profile-settings page with the two member-facing
 * React settings tabs the audit flagged as missing:
 *   - Linked / sub-account management (React LinkedAccountsTab)
 *   - Appearance / theme (React AppearanceSettings)
 * No wildcard route params here (the relationship/theme ids are form fields),
 * so ordering is not a concern, but static segments are still grouped together.
 */

// Linked / sub-account management.
Route::get('/settings/linked-accounts', [AlphaController::class, 'settingsLinkedAccounts'])
    ->name('settings.linked-accounts');

Route::post('/settings/linked-accounts/request', [AlphaController::class, 'settingsRequestLinkedAccount'])
    ->middleware('throttle:10,1')
    ->name('settings.linked-accounts.request');

Route::post('/settings/linked-accounts/approve', [AlphaController::class, 'settingsApproveLinkedAccount'])
    ->middleware('throttle:20,1')
    ->name('settings.linked-accounts.approve');

Route::post('/settings/linked-accounts/permissions', [AlphaController::class, 'settingsUpdateLinkedPermissions'])
    ->middleware('throttle:20,1')
    ->name('settings.linked-accounts.permissions');

Route::post('/settings/linked-accounts/revoke', [AlphaController::class, 'settingsRevokeLinkedAccount'])
    ->middleware('throttle:20,1')
    ->name('settings.linked-accounts.revoke');

// Appearance / theme.
Route::get('/settings/appearance', [AlphaController::class, 'settingsAppearance'])
    ->name('settings.appearance');

Route::post('/settings/appearance', [AlphaController::class, 'settingsUpdateAppearance'])
    ->middleware('throttle:20,1')
    ->name('settings.appearance.update');

// GDPR data-subject requests (portability / rectification / restriction / objection).
Route::get('/settings/data-rights', [AlphaController::class, 'settingsDataRights'])
    ->name('settings.data-rights');

Route::post('/settings/data-rights', [AlphaController::class, 'settingsRequestDataRights'])
    ->middleware('throttle:10,1')
    ->name('settings.data-rights.request');

// Insurance certificates (compliance-gated).
Route::get('/settings/insurance', [AlphaController::class, 'settingsInsurance'])
    ->name('settings.insurance');

Route::post('/settings/insurance', [AlphaController::class, 'settingsUploadInsurance'])
    ->middleware('throttle:10,1')
    ->name('settings.insurance.upload');
