<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a member explicitly asks a coordinator/broker to help arrange contact
 * with a member they cannot message directly because of safeguarding rules.
 *
 * Distinct from {@see SafeguardingContactAttemptBlocked}: that fires when a direct
 * message is blocked; this fires when the member deliberately requests mediated
 * contact via the safeguarding panel. Opening the conversation alone fires neither.
 */
class SafeguardingCoordinationRequested
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param string $reasonCode The underlying restriction (SAFEGUARDING_CONTACT_RESTRICTED|VETTING_REQUIRED)
     * @param string[] $requiredVettingTypes
     * @param string[] $requiredVettingLabels
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly int $senderId,
        public readonly int $recipientId,
        public readonly string $reasonCode,
        public readonly array $requiredVettingTypes = [],
        public readonly array $requiredVettingLabels = [],
    ) {}
}
