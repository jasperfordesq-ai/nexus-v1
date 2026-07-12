<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

use App\Enums\EventFederationAction;
use App\Enums\EventFederationInboundDecision;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** Strict, versioned acknowledgement returned by the Event federation boundary. */
final class EventFederationReceiptContract
{
    public const SCHEMA = 'nexus.event.federation.receipt';
    public const SCHEMA_VERSION = 1;

    private const KEYS = [
        'contract',
        'contract_version',
        'decision',
        'action',
        'event_aggregate_version',
        'event_calendar_version',
        'received_at',
    ];

    /** @return array<string,int|string> */
    public static function fromResult(EventFederationInboundResult $result): array
    {
        return [
            'contract' => self::SCHEMA,
            'contract_version' => self::SCHEMA_VERSION,
            'decision' => $result->decision->value,
            'action' => $result->action->value,
            'event_aggregate_version' => $result->aggregateVersion,
            'event_calendar_version' => $result->calendarVersion,
            'received_at' => now()->utc()->toIso8601String(),
        ];
    }

    /** @param array<string,mixed> $receipt */
    public static function assertValid(array $receipt): void
    {
        $keys = array_keys($receipt);
        sort($keys);
        $expected = self::KEYS;
        sort($expected);
        if ($keys !== $expected) {
            throw new InvalidArgumentException('event_federation_receipt_shape_invalid');
        }
        if ($receipt['contract'] !== self::SCHEMA
            || $receipt['contract_version'] !== self::SCHEMA_VERSION
            || EventFederationInboundDecision::tryFrom((string) $receipt['decision']) === null
            || EventFederationAction::tryFrom((string) $receipt['action']) === null
            || ! is_int($receipt['event_aggregate_version'])
            || $receipt['event_aggregate_version'] <= 0
            || ! is_int($receipt['event_calendar_version'])
            || $receipt['event_calendar_version'] < 0) {
            throw new InvalidArgumentException('event_federation_receipt_values_invalid');
        }

        try {
            CarbonImmutable::parse((string) $receipt['received_at']);
        } catch (\Throwable) {
            throw new InvalidArgumentException('event_federation_receipt_time_invalid');
        }
    }

    /** @param array<string,mixed> $receipt */
    public static function assertMatchesDelivery(array $receipt, array $payload): void
    {
        self::assertValid($receipt);
        if ((string) $receipt['action'] !== (string) ($payload['action'] ?? '')
            || (int) $receipt['event_aggregate_version'] !== (int) ($payload['event_aggregate_version'] ?? -1)
            || (int) $receipt['event_calendar_version'] !== (int) ($payload['event_calendar_version'] ?? -1)) {
            throw new InvalidArgumentException('event_federation_receipt_delivery_mismatch');
        }
    }
}
