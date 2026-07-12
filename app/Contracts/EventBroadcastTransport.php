<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\EventBroadcastChannel;
use App\Support\Events\EventBroadcastRenderedMessage;
use App\Support\Events\EventBroadcastTransportResult;

interface EventBroadcastTransport
{
    public function send(
        EventBroadcastChannel $channel,
        int $tenantId,
        int $eventId,
        object $recipient,
        EventBroadcastRenderedMessage $message,
        string $deliveryKey,
        string $emailCadence,
    ): EventBroadcastTransportResult;
}
