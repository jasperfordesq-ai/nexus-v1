<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

use App\Models\EventCheckinCredential;

/** The bearer secret is populated only for the transaction that created it. */
final readonly class EventCheckinCredentialIssueResult
{
    public function __construct(
        public EventCheckinCredential $credential,
        public ?string $secret,
        public bool $issued,
        public int $manifestVersion,
    ) {
    }
}
