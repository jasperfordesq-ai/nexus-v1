<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Contracts\EventFederationTransport;
use App\Support\Events\EventFederationReceiptContract;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Nexus-to-Nexus transport for the strict event lifecycle contract. */
final class NexusEventFederationTransport implements EventFederationTransport
{
    private const ENDPOINT = '/ingest/events';

    public function deliver(int $tenantId, int $externalPartnerId, array $payload): array
    {
        $partner = DB::table('federation_external_partners')
            ->where('id', $externalPartnerId)
            ->where('tenant_id', $tenantId)
            ->first(['status', 'protocol_type', 'allow_events']);
        if ($partner === null || (string) $partner->protocol_type !== 'nexus') {
            return $this->failed('PARTNER_PROTOCOL_UNSUPPORTED', 'event_federation_partner_protocol_unsupported');
        }
        if ((string) $partner->status !== 'active') {
            return $this->failed('PARTNER_UNAVAILABLE', 'event_federation_partner_unavailable');
        }
        if (! (bool) $partner->allow_events && (string) ($payload['action'] ?? '') !== 'tombstone') {
            return $this->failed('EVENT_FEDERATION_DISABLED', 'event_federation_partner_events_disabled');
        }

        $response = FederationExternalApiClient::post($externalPartnerId, self::ENDPOINT, $payload);
        if (! (bool) ($response['success'] ?? false)) {
            $status = max(0, (int) ($response['status_code'] ?? 0));

            return $this->failed(
                $status > 0 ? 'REMOTE_HTTP_' . $status : 'REMOTE_UNAVAILABLE',
                (string) ($response['error'] ?? 'event_federation_remote_delivery_failed'),
            );
        }

        $receipt = $response['data'] ?? null;
        if (! is_array($receipt)) {
            return $this->failed('REMOTE_RECEIPT_INVALID', 'event_federation_remote_receipt_missing');
        }
        try {
            EventFederationReceiptContract::assertMatchesDelivery($receipt, $payload);
        } catch (Throwable $exception) {
            return $this->failed('REMOTE_RECEIPT_INVALID', $exception->getMessage());
        }

        return ['success' => true, 'receipt' => $receipt];
    }

    /** @return array{success:false,error_code:string,error:string} */
    private function failed(string $code, string $error): array
    {
        return ['success' => false, 'error_code' => $code, 'error' => $error];
    }
}
