<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a vol_log row's status transitions from one value to another
 * (e.g. approved -> pending, approved -> declined). Used by the regional-points
 * cascade-revert listener to claw back any points that were auto-issued when
 * the log was approved, so members cannot "print" points by getting hours
 * approved and then having them reverted.
 *
 * Tenant-scoped: callers must pass the tenant the vol_log belongs to. The
 * listener uses TenantContext::setById() to bind queries to the right tenant
 * before invoking the regional-points service.
 */
class VolLogStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $volLogId,
        public readonly string $previousStatus,
        public readonly string $newStatus,
    ) {}
}
