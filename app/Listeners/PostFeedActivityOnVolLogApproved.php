<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\VolLogStatusChanged;
use App\Models\VolLog;
use App\Services\FeedActivityService;
use Illuminate\Support\Facades\Log;

/**
 * Post a `volunteer` feed_activity row when a vol_log transitions
 * pending → approved.
 *
 * Idempotency: FeedActivityService::recordActivity() uses
 * INSERT ... ON DUPLICATE KEY UPDATE keyed on
 * (tenant_id, source_type, source_id), so re-approval of the same log only
 * refreshes the existing row instead of inserting a duplicate.
 */
class PostFeedActivityOnVolLogApproved
{
    public function __construct(
        private readonly FeedActivityService $feedActivityService,
    ) {
    }

    public function handle(VolLogStatusChanged $event): void
    {
        if ($event->newStatus !== 'approved') {
            return;
        }
        if ($event->previousStatus === 'approved') {
            return;
        }

        $previousTenantId = TenantContext::getId();

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

            $title = sprintf('Volunteered %.2f hours', $hours);
            $content = (string) ($log->description ?? '');

            $this->feedActivityService->recordActivity(
                $event->tenantId,
                $userId,
                'volunteer',
                $event->volLogId,
                [
                    'title'    => $title,
                    'content'  => $content !== '' ? $content : null,
                    'metadata' => [
                        'vol_log_id'      => $event->volLogId,
                        'organization_id' => $log->organization_id !== null
                            ? (int) $log->organization_id
                            : null,
                        'opportunity_id'  => $log->opportunity_id !== null
                            ? (int) $log->opportunity_id
                            : null,
                        'hours'           => $hours,
                    ],
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('PostFeedActivityOnVolLogApproved failed', [
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
