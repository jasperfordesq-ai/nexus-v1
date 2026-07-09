<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Events;

use App\Models\VolOpportunity;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a volunteer opportunity is updated.
 */
class VolunteerOpportunityUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly VolOpportunity $opportunity,
        public readonly int $tenantId,
        /**
         * The opportunity's federated_visibility BEFORE this update, so the
         * federation push listener can detect a share -> un-share transition
         * (listed -> none) and retract the opportunity from partners. Null when
         * the prior value is unknown / irrelevant (e.g. the delete path, where
         * retraction is driven by is_active=0 while visibility stays 'listed').
         */
        public readonly ?string $previousFederatedVisibility = null,
    ) {}
}
