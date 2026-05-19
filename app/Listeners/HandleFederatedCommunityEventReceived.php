<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\FederatedCommunityEventReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * HandleFederatedCommunityEventReceived — fires after a partner platform
 * pushes us a community event. Persistence is already complete (the
 * controller upserted into `federation_events`). Observability-only for
 * now; the row is what surfaces inbound events on the calendar.
 */
class HandleFederatedCommunityEventReceived implements ShouldQueue
{
    public string $queue = 'federation';

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function handle(FederatedCommunityEventReceived $event): void
    {
        $previousTenantId = TenantContext::currentId();

        try {
            TenantContext::setById($event->tenantId);

            $exists = DB::table('federation_events')
                ->where('id', $event->localId)
                ->where('tenant_id', $event->tenantId)
                ->exists();
            if (! $exists) {
                Log::warning('[HandleFederatedCommunityEventReceived] shadow row missing — skipping', [
                    'tenant_id'  => $event->tenantId,
                    'partner_id' => $event->externalPartnerId,
                    'local_id'   => $event->localId,
                ]);
                return;
            }

            Log::info('[HandleFederatedCommunityEventReceived] inbound event persisted', [
                'tenant_id'   => $event->tenantId,
                'partner_id'  => $event->externalPartnerId,
                'local_id'    => $event->localId,
                'external_id' => $event->shadowRow['external_id'] ?? null,
                'title'       => $event->shadowRow['title'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('HandleFederatedCommunityEventReceived failed', [
                'tenant_id'  => $event->tenantId ?? null,
                'partner_id' => $event->externalPartnerId ?? null,
                'local_id'   => $event->localId ?? null,
                'error'      => $e->getMessage(),
            ]);
        } finally {
            TenantContext::restoreAfterScopedListener($previousTenantId);
        }
    }
}
