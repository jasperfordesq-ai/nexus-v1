<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Enums;

enum EventNotificationDeliveryMode: string
{
    case Direct = 'direct';
    case ShadowOutbox = 'shadow_outbox';
    case OutboxAuthoritative = 'outbox_authoritative';

    public function initialOutboxStatus(): string
    {
        return match ($this) {
            self::Direct => 'direct',
            self::ShadowOutbox => 'shadow',
            self::OutboxAuthoritative => 'pending',
        };
    }

    public function isClaimable(): bool
    {
        return $this === self::OutboxAuthoritative;
    }
}
