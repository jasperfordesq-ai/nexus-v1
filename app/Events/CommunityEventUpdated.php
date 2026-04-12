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
 * Fired when an existing community event is updated.
 */
class CommunityEventUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly CommunityEventModel $event,
        public readonly int $tenantId,
    ) {}
}
