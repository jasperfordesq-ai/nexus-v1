<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

use App\Enums\EventOperationalState;
use App\Enums\EventPublicationState;

/** Optional intent guard evaluated under the same row lock as a transition. */
final readonly class EventLifecycleTransitionGuard
{
    /**
     * @param list<EventPublicationState>|null $publicationSources
     * @param list<EventOperationalState>|null $operationalSources
     */
    public function __construct(
        public ?array $publicationSources = null,
        public ?array $operationalSources = null,
    ) {
    }

    public function allowsPublication(EventPublicationState $state): bool
    {
        return $this->publicationSources === null
            || in_array($state, $this->publicationSources, true);
    }

    public function allowsOperational(EventOperationalState $state): bool
    {
        return $this->operationalSources === null
            || in_array($state, $this->operationalSources, true);
    }
}
