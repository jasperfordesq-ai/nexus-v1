<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

use App\Enums\EventFederationAction;
use App\Enums\EventFederationInboundDecision;

final readonly class EventFederationInboundResult
{
    public function __construct(
        public EventFederationInboundDecision $decision,
        public int $projectionId,
        public EventFederationAction $action,
        public int $aggregateVersion,
        public int $calendarVersion,
        public string $payloadHash,
    ) {}

    /** @return array<string,int|string> */
    public function toArray(): array
    {
        return [
            'decision' => $this->decision->value,
            'projection_id' => $this->projectionId,
            'action' => $this->action->value,
            'aggregate_version' => $this->aggregateVersion,
            'calendar_version' => $this->calendarVersion,
            'payload_hash' => $this->payloadHash,
        ];
    }
}
