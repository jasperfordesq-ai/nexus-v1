<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

use App\Models\EventCheckinDevice;

/** Device secret delivery is one-shot; idempotent replays never recover it from storage. */
final readonly class EventCheckinDeviceRegistrationResult
{
    public function __construct(
        public EventCheckinDevice $device,
        public ?string $secret,
        public bool $issued,
        public int $manifestVersion,
    ) {
    }
}
