<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a member updates their profile.
 *
 * `changedFields` is an ordered list of field names that were actually
 * modified (after dirty-check) so that the federation listener can build
 * a minimal sync payload and avoid pushing data the partner already has.
 */
class MemberProfileUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param list<string> $changedFields
     */
    public function __construct(
        public readonly User $user,
        public readonly array $changedFields,
        public readonly int $tenantId,
    ) {}
}
