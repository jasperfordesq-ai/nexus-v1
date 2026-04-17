<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Events;

use App\Models\Group;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a group is updated locally (name, description, visibility,
 * federated_visibility, etc.).
 */
class GroupUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Group $group,
        public readonly int $tenantId,
    ) {}
}
