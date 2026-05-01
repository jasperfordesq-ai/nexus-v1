<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\VolLogStatusChanged;
use App\Services\CaringCommunity\CaringRegionalPointService;
use Illuminate\Support\Facades\Log;

/**
 * When a vol_log transitions AWAY from `approved` (e.g. approved -> pending,
 * approved -> declined), reverse any caring-community regional points that
 * were auto-issued when the log was approved. Without this listener members
 * could "print" points by getting hours approved, then reverting the log.
 *
 * If the log moves back into `approved` later, the existing
 * CaringRegionalPointService::awardForApprovedHours() idempotency guard
 * (existing reference_type=vol_log/type=earned_for_hours uniqueness check)
 * blocks a duplicate credit. We do NOT re-issue here — re-approval is handled
 * by the existing approval flow.
 */
class RevertRegionalPointsOnVolLogChange
{
    public function __construct(
        private readonly CaringRegionalPointService $regionalPointService,
    ) {
    }

    public function handle(VolLogStatusChanged $event): void
    {
        // Only react when leaving the `approved` state. A pending->approved
        // transition is the credit path and is already handled by the caller.
        if ($event->previousStatus !== 'approved') {
            return;
        }
        if ($event->newStatus === 'approved') {
            return;
        }

        $previousTenantId = TenantContext::getId();

        try {
            TenantContext::setById($event->tenantId);

            $this->regionalPointService->reverseFromVolLog(
                $event->volLogId,
                sprintf(
                    'vol_log status changed from %s to %s',
                    $event->previousStatus,
                    $event->newStatus,
                ),
            );
        } catch (\Throwable $e) {
            // A failure here must not break the parent vol_log update flow.
            Log::warning('RevertRegionalPointsOnVolLogChange failed', [
                'tenant_id'       => $event->tenantId,
                'vol_log_id'      => $event->volLogId,
                'previous_status' => $event->previousStatus,
                'new_status'      => $event->newStatus,
                'error'           => $e->getMessage(),
            ]);
        } finally {
            // Restore prior tenant context so we don't leak across listeners.
            if ($previousTenantId > 0) {
                TenantContext::setById($previousTenantId);
            }
        }
    }
}
