<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a user joins a group (membership becomes active).
 *
 * Carries only IDs because membership pivots are not first-class models.
 */
class GroupMemberJoined
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $groupId,
        public readonly int $userId,
        public readonly int $tenantId,
    ) {}
}
