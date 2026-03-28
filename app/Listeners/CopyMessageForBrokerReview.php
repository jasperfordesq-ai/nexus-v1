<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Events\MessageSent;
use App\Services\BrokerMessageVisibilityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Checks every sent message against broker visibility rules and copies
 * it to the broker review queue when criteria are met.
 *
 * Criteria (evaluated by BrokerMessageVisibilityService):
 * - Sender is under safeguarding monitoring (flagged user)
 * - First contact between two members
 * - Sender is a new member (within monitoring window)
 * - Message relates to a high-risk listing
 * - Random compliance sampling
 *
 * Queued so it never delays message delivery to the recipient.
 */
class CopyMessageForBrokerReview implements ShouldQueue
{
    public function __construct()
    {
        //
    }

    public function handle(MessageSent $event): void
    {
        try {
            $message = $event->message;
            $senderId = (int) $message->sender_id;
            $receiverId = (int) $message->receiver_id;
            $listingId = $message->listing_id ? (int) $message->listing_id : null;

            // Set tenant context for the queued job — required for HasTenantScope
            // trait and any service that reads TenantContext::getId()
            if ($event->tenantId) {
                \App\Core\TenantContext::setById($event->tenantId);
            }

            /** @var BrokerMessageVisibilityService $visibilityService */
            $visibilityService = app(BrokerMessageVisibilityService::class);

            $copyReason = $visibilityService->shouldCopyMessage($senderId, $receiverId, $listingId);

            if ($copyReason !== null) {
                $visibilityService->copyMessageForBroker($message->id, $copyReason);
            }
        } catch (\Throwable $e) {
            Log::error('CopyMessageForBrokerReview: failed', [
                'message_id' => $event->message->id ?? null,
                'tenant_id' => $event->tenantId ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
