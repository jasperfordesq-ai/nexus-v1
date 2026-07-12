<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Jobs;

use App\Services\GroupWebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Performs one durable group-webhook delivery attempt.
 *
 * Persistent retry state lives in group_webhook_deliveries, so the queue job
 * itself deliberately runs once. The scheduled dispatcher recovers both
 * failed dispatches and expired worker leases without duplicating delivery.
 */
final class DeliverGroupWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 20;

    public function __construct(
        public readonly string $deliveryId,
        public readonly int $tenantId,
    ) {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        GroupWebhookService::deliver($this->deliveryId, $this->tenantId);
    }
}
