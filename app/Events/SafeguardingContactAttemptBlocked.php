<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when safeguarding rules block a direct member-to-member message.
 */
class SafeguardingContactAttemptBlocked
{
    use Dispatchable, InteractsWithSockets;

    /**
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
