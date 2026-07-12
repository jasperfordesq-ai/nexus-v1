<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

/** Optional durable context for a lifecycle transition performed as part of a series operation. */
final readonly class EventLifecycleTransitionContext
{
    public function __construct(
        public ?int $seriesRootEventId = null,
        public bool $suppressNotifications = false,
        /** @var array<string,mixed> */
        public array $metadata = [],
    ) {
    }

    /** @return array<string,mixed> */
    public function metadataFor(int $eventId): array
    {
        $metadata = $this->metadata;
        if ($this->seriesRootEventId !== null && $this->seriesRootEventId > 0) {
            $metadata['series'] = [
                'root_event_id' => $this->seriesRootEventId,
                'member_type' => $eventId === $this->seriesRootEventId ? 'template' : 'occurrence',
            ];
        }
        if ($this->suppressNotifications) {
            $metadata['notifications_suppressed'] = true;
        }

        return $metadata;
    }
}
