<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

use App\Enums\EventFederationAction;
use App\Enums\EventFederationTombstoneReason;
use App\Enums\EventOperationalState;
use App\Enums\EventPublicationState;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;

final class EventFederationPayloadContract
{
    public const SCHEMA = 'nexus.event.federation';
    public const SCHEMA_VERSION = 1;

    /** @var list<string> */
    private const BASE_KEYS = [
        'payload_schema',
        'payload_schema_version',
        'action',
        'source_identity',
        'source_platform',
        'source_tenant_id',
        'external_id',
        'event_aggregate_version',
        'event_calendar_version',
        'occurred_at',
    ];

    /** @var list<string> */
    private const UPSERT_KEYS = [
        'title',
        'starts_at',
        'ends_at',
        'timezone',
        'all_day',
        'location',
        'latitude',
        'longitude',
        'is_online',
        'publication_status',
        'operational_status',
        'visibility',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    private const TOMBSTONE_KEYS = [
        'tombstone_reason',
        'publication_status',
        'operational_status',
        'visibility',
    ];

    /**
     * Validate the versioned, privacy-minimised Nexus event federation payload.
     * Unknown fields fail closed so later domain changes cannot accidentally add
     * rosters, contact details, private notes, or meeting credentials.
     *
     * @param array<string,mixed> $payload
     */
    public static function assertValid(
        array $payload,
        ?int $expectedSourceTenantId = null,
        ?int $expectedEventId = null,
    ): void {
        if (($payload['payload_schema'] ?? null) !== self::SCHEMA
            || ($payload['payload_schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('event_federation_payload_schema_invalid');
        }

        $action = EventFederationAction::tryFrom((string) ($payload['action'] ?? ''));
        if ($action === null) {
            throw new InvalidArgumentException('event_federation_payload_action_invalid');
        }
        self::assertExactKeys($payload, $action);

        $sourceTenantId = self::positiveInt($payload['source_tenant_id'] ?? null, 'source_tenant_id');
        $eventId = self::positiveInt($payload['external_id'] ?? null, 'external_id');
        if ($expectedSourceTenantId !== null && $sourceTenantId !== $expectedSourceTenantId) {
            throw new InvalidArgumentException('event_federation_payload_tenant_mismatch');
        }
        if ($expectedEventId !== null && $eventId !== $expectedEventId) {
            throw new InvalidArgumentException('event_federation_payload_event_mismatch');
        }
        if (($payload['source_platform'] ?? null) !== 'nexus') {
            throw new InvalidArgumentException('event_federation_payload_platform_invalid');
        }
        $identity = 'urn:nexus:event:' . $sourceTenantId . ':' . $eventId;
        if (($payload['source_identity'] ?? null) !== $identity) {
            throw new InvalidArgumentException('event_federation_payload_identity_invalid');
        }

        self::positiveInt($payload['event_aggregate_version'] ?? null, 'event_aggregate_version');
        self::nonNegativeInt($payload['event_calendar_version'] ?? null, 'event_calendar_version');
        self::dateTime($payload['occurred_at'] ?? null, 'occurred_at');

        if ($action === EventFederationAction::Tombstone) {
            if (EventFederationTombstoneReason::tryFrom((string) ($payload['tombstone_reason'] ?? '')) === null) {
                throw new InvalidArgumentException('event_federation_tombstone_reason_invalid');
            }
            self::optionalLifecycleFields($payload);

            return;
        }

        self::boundedString($payload['title'] ?? null, 'title', 500, false);
        self::assertPublicText((string) $payload['title'], 'title');
        $startsAt = self::dateTime($payload['starts_at'] ?? null, 'starts_at');
        $endsAt = self::dateTime($payload['ends_at'] ?? null, 'ends_at');
        if ($endsAt <= $startsAt) {
            throw new InvalidArgumentException('event_federation_payload_time_range_invalid');
        }
        $timezone = self::boundedString($payload['timezone'] ?? null, 'timezone', 64, false);
        try {
            new DateTimeZone($timezone);
        } catch (\Throwable) {
            throw new InvalidArgumentException('event_federation_payload_timezone_invalid');
        }
        foreach (['all_day', 'is_online'] as $boolean) {
            if (! is_bool($payload[$boolean] ?? null)) {
                throw new InvalidArgumentException("event_federation_payload_{$boolean}_invalid");
            }
        }
        if ($payload['location'] !== null) {
            self::boundedString($payload['location'], 'location', 500, false);
            self::assertPublicText((string) $payload['location'], 'location');
        }
        self::coordinate($payload['latitude'], -90, 90, 'latitude');
        self::coordinate($payload['longitude'], -180, 180, 'longitude');
        self::requiredLifecycleFields($payload);
        if ((string) $payload['publication_status'] !== EventPublicationState::Published->value
            || (string) $payload['operational_status'] === EventOperationalState::Cancelled->value) {
            throw new InvalidArgumentException('event_federation_payload_not_public');
        }
        if (! in_array((string) $payload['visibility'], ['listed', 'joinable'], true)) {
            throw new InvalidArgumentException('event_federation_payload_visibility_invalid');
        }
        self::dateTime($payload['created_at'] ?? null, 'created_at');
        self::dateTime($payload['updated_at'] ?? null, 'updated_at');
    }

    /** @param array<string,mixed> $payload */
    public static function hash(array $payload): string
    {
        return hash('sha256', self::canonicalJson($payload));
    }

    /** @param array<string,mixed> $payload */
    public static function canonicalJson(array $payload): string
    {
        try {
            return json_encode(
                self::canonicalize($payload),
                JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('event_federation_payload_json_invalid', 0, $exception);
        }
    }

    /** @param array<string,mixed> $payload */
    public static function deliveryIdempotencyKey(
        int $tenantId,
        int $eventId,
        int $partnerId,
        array $payload,
    ): string {
        return hash('sha256', implode('|', [
            'event-federation-delivery-v1',
            $tenantId,
            $eventId,
            $partnerId,
            (int) ($payload['payload_schema_version'] ?? 0),
            (int) ($payload['event_aggregate_version'] ?? 0),
            (int) ($payload['event_calendar_version'] ?? 0),
        ]));
    }

    /** @param array<string,mixed> $payload */
    private static function assertExactKeys(array $payload, EventFederationAction $action): void
    {
        $allowed = array_fill_keys([
            ...self::BASE_KEYS,
            ...($action === EventFederationAction::Upsert ? self::UPSERT_KEYS : self::TOMBSTONE_KEYS),
        ], true);
        foreach (array_keys($payload) as $key) {
            if (! is_string($key) || ! isset($allowed[$key])) {
                throw new InvalidArgumentException('event_federation_payload_field_not_allowed');
            }
        }
        foreach (self::BASE_KEYS as $key) {
            if (! array_key_exists($key, $payload)) {
                throw new InvalidArgumentException('event_federation_payload_field_missing');
            }
        }
        if ($action === EventFederationAction::Upsert) {
            foreach (self::UPSERT_KEYS as $key) {
                if (! array_key_exists($key, $payload)) {
                    throw new InvalidArgumentException('event_federation_payload_field_missing');
                }
            }
        } elseif (! array_key_exists('tombstone_reason', $payload)) {
            throw new InvalidArgumentException('event_federation_payload_field_missing');
        }
    }

    /** @param array<string,mixed> $payload */
    private static function requiredLifecycleFields(array $payload): void
    {
        if (EventPublicationState::tryFrom((string) ($payload['publication_status'] ?? '')) === null
            || EventOperationalState::tryFrom((string) ($payload['operational_status'] ?? '')) === null) {
            throw new InvalidArgumentException('event_federation_payload_lifecycle_invalid');
        }
    }

    /** @param array<string,mixed> $payload */
    private static function optionalLifecycleFields(array $payload): void
    {
        foreach (['publication_status', 'operational_status', 'visibility'] as $field) {
            if (array_key_exists($field, $payload) && ! is_string($payload[$field])) {
                throw new InvalidArgumentException('event_federation_payload_lifecycle_invalid');
            }
        }
        if (array_key_exists('publication_status', $payload)
            && EventPublicationState::tryFrom((string) $payload['publication_status']) === null) {
            throw new InvalidArgumentException('event_federation_payload_lifecycle_invalid');
        }
        if (array_key_exists('operational_status', $payload)
            && EventOperationalState::tryFrom((string) $payload['operational_status']) === null) {
            throw new InvalidArgumentException('event_federation_payload_lifecycle_invalid');
        }
        if (array_key_exists('visibility', $payload)
            && ! in_array((string) $payload['visibility'], ['none', 'listed', 'joinable'], true)) {
            throw new InvalidArgumentException('event_federation_payload_visibility_invalid');
        }
    }

    private static function positiveInt(mixed $value, string $field): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        if ($int === false || $int <= 0) {
            throw new InvalidArgumentException("event_federation_payload_{$field}_invalid");
        }

        return $int;
    }

    private static function nonNegativeInt(mixed $value, string $field): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        if ($int === false || $int < 0) {
            throw new InvalidArgumentException("event_federation_payload_{$field}_invalid");
        }

        return $int;
    }

    private static function boundedString(mixed $value, string $field, int $max, bool $nullable): string
    {
        if ($value === null && $nullable) {
            return '';
        }
        if (! is_string($value) || trim($value) === '' || mb_strlen($value) > $max) {
            throw new InvalidArgumentException("event_federation_payload_{$field}_invalid");
        }

        return $value;
    }

    private static function dateTime(mixed $value, string $field): DateTimeImmutable
    {
        if (! is_string($value)
            || preg_match(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+\-]\d{2}:\d{2})$/D',
                $value,
            ) !== 1) {
            throw new InvalidArgumentException("event_federation_payload_{$field}_invalid");
        }
        try {
            $date = new DateTimeImmutable($value);
            $errors = DateTimeImmutable::getLastErrors();
            if (is_array($errors)
                && ((int) $errors['warning_count'] > 0 || (int) $errors['error_count'] > 0)) {
                throw new InvalidArgumentException("event_federation_payload_{$field}_invalid");
            }

            return $date;
        } catch (\Throwable) {
            throw new InvalidArgumentException("event_federation_payload_{$field}_invalid");
        }
    }

    private static function coordinate(mixed $value, float $minimum, float $maximum, string $field): void
    {
        if ($value === null) {
            return;
        }
        if (! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException("event_federation_payload_{$field}_invalid");
        }
        $coordinate = (float) $value;
        if (! is_finite($coordinate) || $coordinate < $minimum || $coordinate > $maximum) {
            throw new InvalidArgumentException("event_federation_payload_{$field}_invalid");
        }
    }

    private static function assertPublicText(string $value, string $field): void
    {
        $sensitivePatterns = [
            '/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i',
            '/\b(?:https?:\/\/|www\.)\S+/i',
            '/\b(?:token|password|secret|credential|meeting[_ -]?id|passcode)\s*[:=]/i',
            '/(?<!\w)(?:\+?\d[\s().-]*){8,}(?!\w)/',
            '/\beyJ[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]{10,}\b/',
        ];
        foreach ($sensitivePatterns as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                throw new InvalidArgumentException("event_federation_payload_{$field}_sensitive");
            }
        }
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            if (is_object($value) || is_resource($value)) {
                throw new InvalidArgumentException('event_federation_payload_value_invalid');
            }

            return $value;
        }
        if (array_is_list($value)) {
            return array_map(self::canonicalize(...), $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $child) {
            $value[$key] = self::canonicalize($child);
        }

        return $value;
    }
}
