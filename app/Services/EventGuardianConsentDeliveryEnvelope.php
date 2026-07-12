<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\EventSafetyException;
use App\Support\Events\EventGuardianConsentDeliveryClaim;
use Illuminate\Support\Facades\DB;

/** Narrow worker-only boundary for receiving a guardian token in memory. */
final class EventGuardianConsentDeliveryEnvelope
{
    public const CONSUMER = 'event-notification-outbox';

    public function __construct(
        private readonly ?EventGuardianConsentDeliveryEnvelopeService $envelopes = null,
    ) {}

    public function claimOrResume(int $outboxId, string $deliveryKey): EventGuardianConsentDeliveryClaim
    {
        $service = $this->envelopes ?? new EventGuardianConsentDeliveryEnvelopeService();
        try {
            return $service->claimForDelivery($outboxId, self::CONSUMER, $deliveryKey);
        } catch (EventSafetyException $exception) {
            if ($exception->reasonCode === 'event_guardian_delivery_envelope_expired') {
                $service->expireForOutbox($outboxId, 'delivery_expired');
                throw $exception;
            }
            if ($exception->reasonCode !== 'event_guardian_delivery_envelope_already_claimed') {
                throw $exception;
            }
            try {
                return $service->resumeClaimForDelivery($outboxId, self::CONSUMER, $deliveryKey);
            } catch (EventSafetyException $resumeException) {
                if ($resumeException->reasonCode === 'event_guardian_delivery_envelope_expired') {
                    $service->expireForOutbox($outboxId, 'delivery_expired');
                }
                throw $resumeException;
            }
        }
    }

    public function complete(EventGuardianConsentDeliveryClaim $claim, string $deliveryKey): void
    {
        ($this->envelopes ?? new EventGuardianConsentDeliveryEnvelopeService())->completeHandoff(
            (int) $claim->envelope->getKey(),
            $claim->claimToken,
            self::CONSUMER,
            $deliveryKey . ':handoff',
        );
    }

    public function completeAfterDelivery(int $outboxId, string $deliveryKey): void
    {
        $status = DB::table('event_guardian_consent_delivery_envelopes')
            ->where('outbox_id', $outboxId)
            ->value('status');
        if ($status === 'handed_off') {
            return;
        }
        $this->complete($this->claimOrResume($outboxId, $deliveryKey), $deliveryKey);
    }
}
