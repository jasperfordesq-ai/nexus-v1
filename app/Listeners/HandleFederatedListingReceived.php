<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\FederatedListingReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * HandleFederatedListingReceived — fires after a partner platform pushes
 * us a listing. Persistence is already complete (the controller upserted
 * into `federation_listings` keyed on (external_partner_id, external_id)).
 *
 * This listener is observability-only for now: confirms the shadow row
 * survived and emits a structured audit log so admins can trace inbound
 * federation traffic. Future extension points (kept here so they don't
 * get re-discovered as missing later): Meilisearch sync, broadcast to
 * subscribers of saved searches matching the listing.
 */
class HandleFederatedListingReceived implements ShouldQueue
{
    public string $queue = 'federation';

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function handle(FederatedListingReceived $event): void
    {
        $previousTenantId = TenantContext::currentId();

        try {
            if (!TenantContext::setById($event->tenantId)) {
                Log::warning('[HandleFederatedListingReceived] tenant not found, skipping', [
                    'tenant_id'  => $event->tenantId,
                    'partner_id' => $event->externalPartnerId,
                    'local_id'   => $event->localId,
                ]);
                return;
            }

            $exists = DB::table('federation_listings')
                ->where('id', $event->localId)
                ->where('tenant_id', $event->tenantId)
                ->exists();
            if (! $exists) {
                Log::warning('[HandleFederatedListingReceived] shadow row missing — skipping', [
                    'tenant_id'  => $event->tenantId,
                    'partner_id' => $event->externalPartnerId,
                    'local_id'   => $event->localId,
                ]);
                return;
            }

            Log::info('[HandleFederatedListingReceived] inbound listing persisted', [
                'tenant_id'   => $event->tenantId,
                'partner_id'  => $event->externalPartnerId,
                'local_id'    => $event->localId,
                'external_id' => $event->shadowRow['external_id'] ?? null,
                'title'       => $event->shadowRow['title'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('HandleFederatedListingReceived failed', [
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
