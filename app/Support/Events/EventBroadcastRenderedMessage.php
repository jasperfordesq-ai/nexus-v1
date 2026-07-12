<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

final readonly class EventBroadcastRenderedMessage
{
    public function __construct(
        public string $subject,
        public string $message,
        public string $html,
        public string $path,
        public string $notificationType,
    ) {
    }
}
