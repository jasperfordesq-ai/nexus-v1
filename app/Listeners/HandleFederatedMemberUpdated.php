<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\FederatedMemberUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * HandleFederatedMemberUpdated — fires when a partner platform pushes us
 * an updated member profile. Persistence is already complete (controller
 * upserted into `federation_members`). Observability-only for now.
 *
 * Future extension points: bust the federated profile cache, push the
 * updated profile fields into search index, notify local users who follow
 * this federated member.
 */
class HandleFederatedMemberUpdated implements ShouldQueue
{
    public string $queue = 'federation';

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function handle(FederatedMemberUpdated $event): void
    {
        $previousTenantId = TenantContext::currentId();

        try {
            TenantContext::setById($event->tenantId);

            $exists = DB::table('federation_members')
                ->where('id', $event->localId)
                ->where('tenant_id', $event->tenantId)
                ->exists();
            if (! $exists) {
                Log::warning('[HandleFederatedMemberUpdated] shadow row missing — skipping', [
                    'tenant_id'  => $event->tenantId,
                    'partner_id' => $event->externalPartnerId,
                    'local_id'   => $event->localId,
                ]);
                return;
            }

            Log::info('[HandleFederatedMemberUpdated] inbound member update persisted', [
                'tenant_id'   => $event->tenantId,
                'partner_id'  => $event->externalPartnerId,
                'local_id'    => $event->localId,
                'external_id' => $event->shadowRow['external_id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('HandleFederatedMemberUpdated failed', [
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
