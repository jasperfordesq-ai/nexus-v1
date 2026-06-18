<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Http\Controllers\GovukAlpha\AlphaController;
use Illuminate\Support\Facades\Route;

/*
 * Wallet parity routes (accessible GOV.UK frontend).
 *
 * Required INSIDE the {tenantSlug}/alpha + govuk-alpha. group, so the path
 * below resolves to /{tenantSlug}/alpha/wallet/manage and the name to
 * govuk-alpha.wallet.manage.
 *
 * The core wallet routes (wallet.index / wallet.transfer / wallet.donate /
 * wallet.export / wallet.recipients) live in routes/govuk-alpha.php and are
 * owned by AlphaController. This file adds ONLY the new "manage credits" hub,
 * which is a read-only GET — every mutation it offers POSTs to the existing
 * wallet.transfer / wallet.donate handlers. No POST route is introduced here,
 * so there is no throttle to declare.
 *
 * The literal /wallet/manage segment is registered as a fixed path and never
 * collides with the core /wallet or /wallet/* routes (export.csv, donate,
 * transfer, recipients) because none of those use a {wildcard} that would
 * shadow "manage".
 */
Route::get('/wallet/manage', [AlphaController::class, 'walletManage'])
    ->name('wallet.manage');
