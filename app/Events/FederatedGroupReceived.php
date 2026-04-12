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
 * Fired after an inbound federation group (or group membership change)
 * has been persisted to the `federation_groups` shadow table.
 */
class FederatedGroupReceived
{
    use Dispatchable, SerializesModels;

    /**
     * @param array<string, mixed> $shadowRow
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly int $externalPartnerId,
        public readonly int $localId,
        public readonly array $shadowRow,
        public readonly string $kind = 'group',
    ) {}
}
