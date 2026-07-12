<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\CommunityEventCreated;
use App\Events\CommunityEventUpdated;
use App\Services\EventFederationPublisher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Compatibility boundary for legacy event dispatchers.
 *
 * Canonical lifecycle/update writes enqueue transactionally and are no longer
 * mapped to this listener. A direct legacy invocation can only create the
 * strict privacy-safe durable fact; it never performs network delivery or
 * assembles an ad-hoc payload containing private Event fields.
 */
final class PushCommunityEventToFederatedPartners implements ShouldQueue
{
    public string $queue = 'federation';

    public function __construct(
        private readonly EventFederationPublisher $publisher,
    ) {}

    public function handle(CommunityEventCreated|CommunityEventUpdated $event): void
    {
        $eventModel = $event->event;
        $tenantId = $event->tenantId;
        $previousTenantId = TenantContext::currentId();

        try {
            if (! TenantContext::setById($tenantId)) {
                Log::warning('PushCommunityEventToFederatedPartners: tenant not found, skipping', [
                    'tenant_id' => $tenantId,
                    'event_id' => $eventModel->id ?? null,
                ]);

                return;
            }

            $this->publisher->publish($eventModel);
        } catch (\Throwable $exception) {
            Log::error('PushCommunityEventToFederatedPartners listener failed', [
                'tenant_id' => $tenantId,
                'event_id' => $eventModel->id ?? null,
                'exception' => $exception::class,
                'reason_code' => $exception->getMessage(),
            ]);
        } finally {
            TenantContext::restoreAfterScopedListener($previousTenantId);
        }
    }
}
