<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Events;

use App\Models\Connection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when the receiver of a connection request accepts it.
 *
 * ConnectionRequested fires at request time; this is the counterpart for the
 * acceptance transition.  Federation listeners use this to propagate the
 * accepted relationship to external partners.
 */
class ConnectionAccepted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Connection $connectionModel,
        public readonly int $tenantId,
    ) {}
}
