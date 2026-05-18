<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\VolLogStatusChanged;
use App\Models\UserXpLog;
use App\Models\VolLog;
use App\Services\GamificationService;
use Illuminate\Support\Facades\Log;

/**
 * Award XP and re-check vol-hour achievements when a vol_log row transitions
 * pending → approved.
 *
 * XP rule: 20 XP per volunteer hour (GamificationService::XP_VALUES['volunteer_hour']).
 *
 * Idempotency: the user_xp_log table has no native unique key on
 * (user_id, action, reference_id), so we encode the vol_log id into the
 * description as a stable token ("vol_log:{id}") and bail out if a row with
 * that exact (user_id, action='volunteer_hour', description LIKE '...') tuple
 * already exists. Re-approving the same log therefore awards XP exactly once.
 *
 * Achievement re-check: vol_1h..vol_500h thresholds are evaluated by
 * GamificationService::runAllBadgeChecks(), which itself uses
 * UserBadge unique-by-key idempotency — safe to call repeatedly.
 */
class AwardXpOnVolLogApproved
{
    public function handle(VolLogStatusChanged $event): void
    {
        // Only act on the credit transition: pending → approved.
        if ($event->newStatus !== 'approved') {
            return;
        }
        if ($event->previousStatus === 'approved') {
            // Already-approved → approved (no-op resave) — nothing to do.
            return;
        }

        $previousTenantId = TenantContext::currentId();

        try {
            TenantContext::setById($event->tenantId);

            /** @var VolLog|null $log */
            $log = VolLog::query()->find($event->volLogId);
            if ($log === null) {
                return;
            }

            $userId = (int) $log->user_id;
            $hours = (float) $log->hours;
            if ($userId <= 0 || $hours <= 0) {
                return;
            }

            $xpPerHour = (int) (GamificationService::XP_VALUES['volunteer_hour'] ?? 20);
            $amount = (int) round($hours * $xpPerHour);
            if ($amount <= 0) {
                return;
            }

            $reference = sprintf('vol_log:%d', $event->volLogId);

            // Idempotency: if we've already logged XP for this vol_log, skip.
            $alreadyAwarded = UserXpLog::query()
                ->where('user_id', $userId)
                ->where('action', 'volunteer_hour')
                ->where('description', 'like', '%' . $reference . '%')
                ->exists();

            if ($alreadyAwarded) {
                return;
            }

            $description = sprintf(
                'Volunteer hours approved (%.2fh) [%s]',
                $hours,
                $reference,
            );

            GamificationService::awardXP($userId, $amount, 'volunteer_hour', $description);

            // Re-check vol_1h..vol_500h (and other) badge thresholds. The
            // underlying UserBadge unique-by-key index keeps this idempotent.
            GamificationService::runAllBadgeChecks($userId);
        } catch (\Throwable $e) {
            // Never break the parent vol_log update flow.
            Log::warning('AwardXpOnVolLogApproved failed', [
                'tenant_id'  => $event->tenantId,
                'vol_log_id' => $event->volLogId,
                'error'      => $e->getMessage(),
            ]);
        } finally {
            if ($previousTenantId > 0) {
                TenantContext::setById($previousTenantId);
            }
        }
    }
}
