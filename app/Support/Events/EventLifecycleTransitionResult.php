<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

use App\Models\Event;

/** Result returned for both real transitions and idempotent replays. */
final readonly class EventLifecycleTransitionResult
{
    public function __construct(
        public Event $event,
        public bool $changed,
        public ?int $historyId,
        public ?int $outboxId,
        /** @var list<int> */
        public array $affectedRecipientUserIds = [],
        /** @var array{reminders_cancelled:int,waitlist_cancelled:int,registrations_cancelled:int} */
        public array $cascade = [
            'reminders_cancelled' => 0,
            'waitlist_cancelled' => 0,
            'registrations_cancelled' => 0,
        ],
        public bool $publicationBecamePublished = false,
        public ?string $deliveryMode = null,
    ) {
    }
}
