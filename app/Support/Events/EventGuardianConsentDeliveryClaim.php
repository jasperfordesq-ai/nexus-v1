<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

use App\Models\EventGuardianConsentDeliveryEnvelope;
use JsonSerializable;
use LogicException;

/** One-time in-memory secret handoff which cannot be serialized or logged. */
final readonly class EventGuardianConsentDeliveryClaim implements JsonSerializable
{
    public function __construct(
        public EventGuardianConsentDeliveryEnvelope $envelope,
        public string $guardianToken,
        public string $claimToken,
    ) {}

    /** @return array{envelope_id:int,guardian_token:string,claim_token:string} */
    public function __debugInfo(): array
    {
        return [
            'envelope_id' => (int) $this->envelope->getKey(),
            'guardian_token' => '[REDACTED]',
            'claim_token' => '[REDACTED]',
        ];
    }

    /** @return never */
    public function __serialize(): array
    {
        throw new LogicException('Guardian delivery claims cannot be serialized.');
    }

    public function jsonSerialize(): never
    {
        throw new LogicException('Guardian delivery claims cannot be JSON serialized.');
    }
}
