<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Events;

use App\Models\Event as CommunityEventModel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a new community event is created locally.
 *
 * Carries the event model + tenant id so queued listeners (notably
 * PushCommunityEventToFederatedPartners) can restore tenant context and
 * broadcast the event to federated partners.
 */
class CommunityEventCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly CommunityEventModel $event,
        public readonly int $tenantId,
    ) {}
}
