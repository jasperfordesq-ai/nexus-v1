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
 * Note on `vol_opportunities`:
 *   The local `vol_opportunities` table currently has no `is_federated` /
 *   `federation_source` / `external_id` columns, so federated opportunities
 *   live exclusively in the `federation_volunteering` shadow table (matching
 *   the established pattern used by HandleFederatedGroupReceived for
 *   federated groups). When those columns are added by a future migration,
 *   extend this listener with an idempotent upsert into `vol_opportunities`
 *   keyed on (federation_source, external_id).
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

            // Future extension points (all must remain idempotent on
            // federation_source = external_partner_id + external_id):
            //   - Mirror into vol_opportunities once federation columns exist.
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
}
