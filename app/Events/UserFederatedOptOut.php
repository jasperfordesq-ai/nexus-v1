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
 * Fired when a user opts out of federation or their account is deleted.
 * Triggers PushFederationDataRetraction to notify all federated partners.
 */
class UserFederatedOptOut
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly int $tenantId,
        /** 'opt_out' or 'account_deleted' */
        public readonly string $reason = 'opt_out',
    ) {}
}
