<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

use App\Models\EventRegistration;
use App\Models\EventWaitlistEntry;

/** Result for queue joins, offers, acceptance, expiry, and withdrawal. */
final readonly class EventWaitlistTransitionResult
{
    public function __construct(
        public EventWaitlistEntry $entry,
        public bool $changed,
        public bool $replayed,
        public ?int $historyId,
        public ?int $outboxId,
        public ?string $offerToken = null,
        public ?EventRegistration $registration = null,
        public ?EventWaitlistEntry $nextOfferedEntry = null,
        public ?string $nextOfferToken = null,
    ) {
    }
}
