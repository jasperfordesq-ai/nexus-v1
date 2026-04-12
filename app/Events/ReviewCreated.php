<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Events;

use App\Models\Review;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a Review has been persisted.
 *
 * Listeners use this to propagate the review to federated partners so that
 * reputation portability works across tenants/platforms (the reviewed user
 * may be a remote member on another federation node).
 *
 * Queue workers start with NO tenant context, so we carry the tenant id on
 * the event payload. Listeners MUST call TenantContext::setById($tenantId)
 * before any DB query.
 */
class ReviewCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Review $review,
        public readonly int $tenantId = 0,
    ) {}
}
