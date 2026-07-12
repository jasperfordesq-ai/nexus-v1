<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

use App\Models\EventWaitlistOfferEnvelope;
use JsonSerializable;
use LogicException;

/** One-time in-memory secret handoff; callers must never serialize or log it. */
final readonly class EventWaitlistOfferEnvelopeClaim implements JsonSerializable
{
    public function __construct(
        public EventWaitlistOfferEnvelope $envelope,
        public string $offerToken,
        public string $claimToken,
    ) {}

    /** @return array{envelope_id:int,offer_token:string,claim_token:string} */
    public function __debugInfo(): array
    {
        return [
            'envelope_id' => (int) $this->envelope->getKey(),
            'offer_token' => '[REDACTED]',
            'claim_token' => '[REDACTED]',
        ];
    }

    /** @return never */
    public function __serialize(): array
    {
        throw new LogicException('Waitlist offer claims cannot be serialized.');
    }

    public function jsonSerialize(): never
    {
        throw new LogicException('Waitlist offer claims cannot be JSON serialized.');
    }
}
