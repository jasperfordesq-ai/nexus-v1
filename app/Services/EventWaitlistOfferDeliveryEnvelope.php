<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\EventWaitlistException;
use App\Support\Events\EventWaitlistOfferEnvelopeClaim;
use Illuminate\Support\Facades\DB;

/** Narrow boundary through which the notification worker receives an offer secret in memory. */
final class EventWaitlistOfferDeliveryEnvelope
{
    public const CONSUMER = 'event-notification-outbox';

    public function __construct(
        private readonly ?EventWaitlistOfferEnvelopeService $envelopes = null,
    ) {}

    public function claimOrResume(int $outboxId, string $deliveryKey): EventWaitlistOfferEnvelopeClaim
    {
        $service = $this->envelopes ?? new EventWaitlistOfferEnvelopeService();

        try {
            return $service->claimForDelivery($outboxId, self::CONSUMER, $deliveryKey);
        } catch (EventWaitlistException $exception) {
            if ($exception->reasonCode !== 'event_waitlist_offer_envelope_already_claimed') {
                throw $exception;
            }

            return $service->resumeClaimForDelivery($outboxId, self::CONSUMER, $deliveryKey);
        }
    }

    public function complete(EventWaitlistOfferEnvelopeClaim $claim, string $deliveryKey): void
    {
        ($this->envelopes ?? new EventWaitlistOfferEnvelopeService())->completeHandoff(
            (int) $claim->envelope->getKey(),
            $claim->claimToken,
            self::CONSUMER,
            $deliveryKey . ':handoff',
        );
    }

    public function completeAfterDelivery(int $outboxId, string $deliveryKey): void
    {
        $status = DB::table('event_waitlist_offer_envelopes')
            ->where('outbox_id', $outboxId)
            ->value('status');
        if ($status === 'handed_off') {
            return;
        }

        $this->complete($this->claimOrResume($outboxId, $deliveryKey), $deliveryKey);
    }
}
