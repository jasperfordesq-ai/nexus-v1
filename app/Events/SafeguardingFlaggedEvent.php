<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a member selects safeguarding options that have notify_admin triggers.
 *
 * This event triggers immediate in-app + email notifications to all admin/broker
 * users for the tenant, alerting them that a member has self-identified
 * safeguarding needs during onboarding (or settings update).
 */
class SafeguardingFlaggedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly int $tenantId,
        public readonly array $triggers,
    ) {}
}
