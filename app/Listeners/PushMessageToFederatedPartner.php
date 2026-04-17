<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\MessageSent;
use App\Services\FederationExternalApiClient;
use App\Services\FederationFeatureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PushMessageToFederatedPartner — forwards a locally-sent message to an
 * external federation partner when the recipient is federated.
 *
 * Only triggers when the underlying Message row has is_federated=1 AND the
 * receiver user is linked to an external partner. Queued so it never blocks
 * the local conversation UI. The V2 controller's explicit sendMessage()
 * endpoint remains the primary path; this listener is a safety net for
 * local writes that flag a message as federated.
 */
class PushMessageToFederatedPartner implements ShouldQueue
{
    /** Process on the high-priority federation queue to minimise message latency. */
    public string $queue = 'federation-high';

    public function __construct(
        private readonly FederationFeatureService $federationFeatureService,
    ) {}

    public function handle(MessageSent $event): void
    {
        try {
            TenantContext::setById($event->tenantId);

            if (!TenantContext::hasFeature('federation')) {
                return;
            }

            if (!$this->federationFeatureService->isTenantFederationEnabled($event->tenantId)) {
                return;
            }

            $message = $event->message;

            // Only forward messages explicitly flagged as federated
            if (empty($message->is_federated)) {
                return;
            }

            // Resolve receiver's federated partner — by convention, a federated
            // recipient has a row in federation_external_user_map or the message
            // itself carries external_partner_id. We check the message first,
            // then fall back to looking up any external partner whose directory
            // maps the receiver_id.
            $partnerId = (int) ($message->external_partner_id ?? 0);
            $externalReceiverId = $message->external_receiver_id ?? null;

            if ($partnerId <= 0 || !$externalReceiverId) {
                // Try the federation_messages shadow row
                $row = DB::table('federation_messages')
                    ->where('reference_message_id', $message->id)
                    ->where('direction', 'outbound')
                    ->whereNotNull('external_partner_id')
                    ->first();

                if ($row) {
                    $partnerId = (int) $row->external_partner_id;
                    $externalReceiverId = $row->external_receiver_id ?? $row->receiver_user_id;
                }
            }

            if ($partnerId <= 0) {
                // Nothing to push — this is a purely local message flagged
                // federated (rare). Not an error.
                return;
            }

            $payload = [
                'sender_id'         => $event->sender->id,
                'sender_tenant_id'  => $event->tenantId,
                'receiver_id'       => $externalReceiverId,
                'subject'           => $message->subject ?? '',
                'body'              => $message->body ?? '',
                'message_id'        => $message->id,
                'conversation_id'   => $event->conversationId,
                'created_at'        => $message->created_at?->toISOString(),
            ];

            $result = FederationExternalApiClient::sendMessage($partnerId, $payload);

            if (empty($result['success'])) {
                Log::warning('PushMessageToFederatedPartner: partner rejected message', [
                    'partner_id' => $partnerId,
                    'tenant_id'  => $event->tenantId,
                    'message_id' => $message->id,
                    'error'      => $result['error'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('PushMessageToFederatedPartner listener failed', [
                'tenant_id'  => $event->tenantId ?? null,
                'message_id' => $event->message->id ?? null,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
