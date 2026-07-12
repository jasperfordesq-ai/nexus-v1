<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\EventTemplateVersion;
use DateTimeInterface;

/** Explicit, allowlist-only representation of one immutable template version. */
final class EventTemplateVersionResource
{
    /** @return array<string, mixed> */
    public static function fromModel(EventTemplateVersion $version): array
    {
        return [
            'id' => (int) $version->id,
            'number' => (int) $version->version_number,
            'schema_version' => (int) $version->schema_version,
            'configuration' => self::configuration(
                is_array($version->payload) ? $version->payload : [],
            ),
            'snapshot' => [
                'hash' => (string) $version->payload_hash,
                'source_lifecycle_version' => (int) $version->source_lifecycle_version,
                'source_calendar_sequence' => (int) $version->source_calendar_sequence,
                'source_updated_at' => self::timestamp($version->source_updated_at),
                'immutable' => true,
            ],
            'copied_fields' => self::stringList($version->copied_fields),
            'skipped_fields' => self::stringList($version->skipped_fields),
            'captured_at' => self::timestamp($version->created_at),
        ];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public static function configuration(array $payload): array
    {
        return [
            'title' => (string) ($payload['title'] ?? ''),
            'description' => (string) ($payload['description'] ?? ''),
            'category_id' => self::nullableInt($payload['category_id'] ?? null),
            'group_id' => self::nullableInt($payload['group_id'] ?? null),
            'location' => self::nullableString($payload['location'] ?? null),
            'latitude' => self::nullableFloat($payload['latitude'] ?? null),
            'longitude' => self::nullableFloat($payload['longitude'] ?? null),
            'max_attendees' => self::nullableInt($payload['max_attendees'] ?? null),
            'is_online' => (bool) ($payload['is_online'] ?? false),
            'allow_remote_attendance' => (bool) ($payload['allow_remote_attendance'] ?? false),
            'timezone' => (string) ($payload['timezone'] ?? 'UTC'),
            'all_day' => (bool) ($payload['all_day'] ?? false),
            'federated_visibility' => (string) ($payload['federated_visibility'] ?? 'none'),
        ];
    }

    /** @return list<string> */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => is_string($item) ? $item : '', $value),
            static fn (string $item): bool => $item !== '',
        ));
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private static function nullableFloat(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function timestamp(mixed $value): ?string
    {
        return $value instanceof DateTimeInterface
            ? $value->format(DATE_ATOM)
            : (is_string($value) && $value !== '' ? $value : null);
    }
}
