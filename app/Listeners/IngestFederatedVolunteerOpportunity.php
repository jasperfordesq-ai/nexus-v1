<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Listeners;

use App\Events\FederatedVolunteeringReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * IngestFederatedVolunteerOpportunity — handles inbound federated volunteering
 * opportunities published by external partners (e.g. TimeOverflow).
 *
 * Persistence flow:
 *   1. FederationExternalWebhookController has already upserted the opportunity
 *      into the `federation_volunteering` shadow table keyed on
 *      (external_partner_id, external_id) — that upsert is the source of
 *      record for federated opportunities.
 *   2. This listener fires after that write and is responsible for any
 *      post-persistence work that should NOT block the webhook response:
 *      structured audit logging, future search-index sync, and admin
 *      notifications.
 *
 * Idempotency guarantees:
 *   - The controller-side upsert is keyed on (external_partner_id, external_id)
 *     so a duplicate webhook delivery will UPDATE the same shadow row rather
 *     than insert a second one. The `localId` carried on the event is the
 *     same id on every redelivery.
 *   - This listener performs no additional writes that could create duplicates;
 *     any future persistence added here MUST upsert keyed on
 *     (federation_source = external_partner_id, external_id).
 *
 * Mirror into vol_opportunities:
 *   When the federation columns exist on `vol_opportunities`
 *   (is_federated / external_partner_id / external_id, added by migration
 *   2026_05_08_000002), the listener also mirrors the opportunity into
 *   `vol_opportunities` so federated content surfaces in normal listing/search.
 *   The mirror is idempotent via UNIQUE KEY (external_partner_id, external_id).
 *   On older schemas without those columns, the listener falls back to
 *   shadow-only persistence.
 */
class IngestFederatedVolunteerOpportunity implements ShouldQueue
{
    /** Route to the standard federation queue (bulk, non-time-critical). */
    public string $queue = 'federation';

    public function handle(FederatedVolunteeringReceived $event): void
    {
        try {
            // Defensive: confirm the shadow row still exists. If the controller
            // upsert was rolled back for any reason, log and bail.
            $exists = DB::table('federation_volunteering')
                ->where('id', $event->localId)
                ->where('tenant_id', $event->tenantId)
                ->exists();

            if (! $exists) {
                Log::warning('[IngestFederatedVolunteerOpportunity] shadow row missing — skipping', [
                    'tenant_id'           => $event->tenantId,
                    'external_partner_id' => $event->externalPartnerId,
                    'local_id'            => $event->localId,
                ]);
                return;
            }

            Log::info('[IngestFederatedVolunteerOpportunity] Inbound federated volunteering opportunity persisted', [
                'tenant_id'           => $event->tenantId,
                'external_partner_id' => $event->externalPartnerId,
                'local_id'            => $event->localId,
                'external_id'         => $event->shadowRow['external_id'] ?? null,
                'title'               => $event->shadowRow['title'] ?? null,
            ]);

            $this->mirrorIntoVolOpportunities($event);

            // Future extension points (all must remain idempotent on
            // federation_source = external_partner_id + external_id):
            //   - Push the opportunity into Meilisearch.
            //   - Notify tenant admins of new partner content.
        } catch (\Throwable $e) {
            // Never break the webhook flow — the shadow row is already saved.
            Log::warning('IngestFederatedVolunteerOpportunity failed', [
                'tenant_id'           => $event->tenantId,
                'external_partner_id' => $event->externalPartnerId,
                'local_id'            => $event->localId,
                'error'               => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mirror the federated opportunity into vol_opportunities, idempotent on
     * (external_partner_id, external_id) via UNIQUE KEY uk_vol_opp_partner_ext.
     * Skips silently if the federation columns aren't present (older schema).
     */
    private function mirrorIntoVolOpportunities(FederatedVolunteeringReceived $event): void
    {
        if (! Schema::hasColumn('vol_opportunities', 'is_federated')
            || ! Schema::hasColumn('vol_opportunities', 'external_partner_id')
            || ! Schema::hasColumn('vol_opportunities', 'external_id')) {
            return;
        }

        $externalId = (string) ($event->shadowRow['external_id'] ?? '');
        $title      = (string) ($event->shadowRow['title'] ?? '');
        if ($externalId === '' || $title === '') {
            return;
        }

        $now = now();
        $row = [
            'tenant_id'           => $event->tenantId,
            'organization_id'     => null,
            'title'               => mb_substr($title, 0, 255),
            'description'         => (string) ($event->shadowRow['description'] ?? ''),
            'location'            => $event->shadowRow['location'] ?? null,
            'start_date'          => isset($event->shadowRow['starts_at'])
                ? substr((string) $event->shadowRow['starts_at'], 0, 10)
                : null,
            'is_active'           => 1,
            'status'              => 'open',
            'is_federated'        => 1,
            'external_partner_id' => $event->externalPartnerId,
            'external_id'         => $externalId,
            'updated_at'          => $now,
        ];

        $existing = DB::table('vol_opportunities')
            ->where('external_partner_id', $event->externalPartnerId)
            ->where('external_id', $externalId)
            ->first(['id']);

        if ($existing) {
            DB::table('vol_opportunities')->where('id', $existing->id)->update($row);
        } else {
            DB::table('vol_opportunities')->insert(array_merge($row, ['created_at' => $now]));
        }
    }
}
