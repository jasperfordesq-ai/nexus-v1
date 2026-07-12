<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

use App\Models\EventRegistration;
use App\Models\EventWaitlistEntry;

/** Result for a real, replayed, or already-satisfied registration intent. */
final readonly class EventRegistrationTransitionResult
{
    public function __construct(
        public EventRegistration $registration,
        public bool $changed,
        public bool $replayed,
        public ?int $historyId,
        public ?int $outboxId,
        public bool $releasedCapacity = false,
        public ?EventWaitlistEntry $offeredEntry = null,
        public ?string $offerToken = null,
    ) {
    }
}
