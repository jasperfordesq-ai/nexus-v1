<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Listeners;

use App\Events\FederatedGroupReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * HandleFederatedGroupReceived — logs inbound federated group events and
 * provides a hook for future processing pipelines.
 *
 * The shadow-table write is already done in FederationExternalWebhookController
 * before this event is fired. This listener handles any post-persistence work
 * (notifications, denormalisation, search index updates, etc.).
 *
 * Currently: structured logging only. A full processing pipeline can be wired
 * in here without touching the controller or the event class.
 */
class HandleFederatedGroupReceived implements ShouldQueue
{
    /** Route to the standard federation queue (bulk, non-time-critical). */
    public string $queue = 'federation';

    public function handle(FederatedGroupReceived $event): void
    {
        Log::info('[HandleFederatedGroupReceived] Inbound federated group persisted', [
            'tenant_id'          => $event->tenantId,
            'external_partner_id' => $event->externalPartnerId,
            'local_id'           => $event->localId,
            'kind'               => $event->kind,
            'group_name'         => $event->shadowRow['name'] ?? null,
        ]);

        // Future extension points:
        // - Notify admins of a new federated group available to members
        // - Update a search index (Meilisearch) with the shadow group
        // - Trigger UI push notification via Pusher for "new partner content"
    }
}
