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
 * Fired when a group is deleted locally.
 *
 * Carries only primitive IDs because the group model is already deleted
 * by the time listeners run.
 */
class GroupDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $groupId,
        public readonly int $tenantId,
        public readonly ?string $groupName = null,
    ) {}
}
