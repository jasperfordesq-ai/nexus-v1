<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\ConnectionRequested;
use App\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Sends a notification to the target user when they receive a connection request.
 */
class NotifyConnectionRequest implements ShouldQueue
{
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ConnectionRequested $event): void
    {
        try {
            // Ensure tenant context is set (required when running via async queue)
            TenantContext::setById($event->tenantId);

            $requesterName = $event->requester->first_name ?? $event->requester->name ?? 'Someone';
            $targetUserId = $event->target->id;

            NotificationDispatcher::dispatch(
                $targetUserId,
                'global',
                null,
                'connection_request',
                "{$requesterName} sent you a connection request",
                '/connections',
                null
            );
        } catch (\Throwable $e) {
            Log::error('NotifyConnectionRequest listener failed', [
                'requester_id' => $event->requester->id ?? null,
                'target_id' => $event->target->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
