<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

final readonly class EventNotificationOutboxHandleResult
{
    public function __construct(
        public int $recipients,
        public int $delivered,
        public int $suppressed,
        public bool $skipped = false,
    ) {}
}
