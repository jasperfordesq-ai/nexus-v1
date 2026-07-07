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
use Illuminate\Support\Facades\DB;
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

            // Resolve the organisation name for display (metadata is denormalised
            // into the feed row; the frontend renders a localised title from the
            // hours, so this stored title is only a non-localised fallback).
            $orgName = null;
            if ($log->organization_id !== null) {
                $orgName = DB::table('vol_organizations')
                    ->where('id', (int) $log->organization_id)
                    ->where('tenant_id', $event->tenantId)
                    ->value('name');
            }

            // Non-localised fallback title. The React feed card renders a localised
            // title from the numeric `hours` metadata (card.volunteer_hours_title),
            // so this value is only used by non-localising readers.
            $title = sprintf('Volunteered %.2f hours', $hours);
            $content = (string) ($log->description ?? '');

            // source_type 'volunteer_hours' (mapped to vol_logs), NOT 'volunteer'
            // (mapped to vol_opportunities) — keying an hour-log row under
            // 'volunteer' made every reader treat it as an orphaned opportunity and
            // silently drop it from the feed.
            $this->feedActivityService->recordActivity(
                $event->tenantId,
                $userId,
                'volunteer_hours',
                $event->volLogId,
                [
                    'title'    => $title,
                    'content'  => $content !== '' ? $content : null,
                    'metadata' => [
                        'vol_log_id'      => $event->volLogId,
                        'organization_id' => $log->organization_id !== null
                            ? (int) $log->organization_id
                            : null,
                        'organization'    => $orgName,
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
            TenantContext::restoreAfterScopedListener($previousTenantId);
        }
    }
}
