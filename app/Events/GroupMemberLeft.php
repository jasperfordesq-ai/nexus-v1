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
 * Fired when a user leaves (or is removed from) a group.
 */
class GroupMemberLeft
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $groupId,
        public readonly int $userId,
        public readonly int $tenantId,
    ) {}
}
