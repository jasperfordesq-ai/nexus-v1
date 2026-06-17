<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

/**
 * Marketplace, courses, podcasts, coupons, premium & clubs — accessible (GOV.UK) frontend parity methods.
 *
 * Composed into AlphaController. Trait methods may call the controller's
 * private helpers ($this->view, $this->currentUserId, $this->assertTenantSlug,
 * $this->allowed, self::asStr). New method names MUST be module-prefixed and
 * unique across AlphaController and every sibling trait. Resolve services via
 * app(SomeService::class) rather than the constructor.
 */
trait CommerceParity
{
    // Parity methods are added here by the build. (Intentionally empty stub.)
}
