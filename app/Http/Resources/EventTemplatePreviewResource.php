<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Resources;

/** Explicit preview resources; no model or arbitrary service array is serialized. */
final class EventTemplatePreviewResource
{
    /** @param array<string,mixed> $preview @return array<string,mixed> */
    public static function capture(array $preview): array
    {
        return [
            'kind' => 'capture',
            'schema_version' => (int) ($preview['schema_version'] ?? 0),
            'source_event_id' => (int) ($preview['source_event_id'] ?? 0),
            'source_lifecycle_version' => max(0, (int) ($preview['source_lifecycle_version'] ?? 0)),
            'source_calendar_sequence' => max(0, (int) ($preview['source_calendar_sequence'] ?? 0)),
            'configuration' => EventTemplateVersionResource::configuration(
                is_array($preview['payload'] ?? null) ? $preview['payload'] : [],
            ),
            'snapshot_hash' => (string) ($preview['payload_hash'] ?? ''),
            'copied_fields' => self::stringList($preview['copied_fields'] ?? []),
            'skipped_fields' => self::stringList($preview['skipped_fields'] ?? []),
            'checklist' => self::checklist($preview['checklist'] ?? []),
        ];
    }

    /** @param array<string,mixed> $preview @return array<string,mixed> */
    public static function materialization(array $preview): array
    {
        $schedule = is_array($preview['schedule'] ?? null) ? $preview['schedule'] : [];
        $willCreate = is_array($preview['will_create'] ?? null) ? $preview['will_create'] : [];

        return [
            'kind' => 'materialization',
            'template_id' => (int) ($preview['template_id'] ?? 0),
            'template_version_id' => (int) ($preview['template_version_id'] ?? 0),
            'template_version' => (int) ($preview['template_version_number'] ?? 0),
            'source_event_id' => (int) ($preview['source_event_id'] ?? 0),
            'schema_version' => (int) ($preview['schema_version'] ?? 0),
            'template_snapshot_hash' => (string) ($preview['template_payload_hash'] ?? ''),
            'effective_snapshot_hash' => (string) ($preview['effective_payload_hash'] ?? ''),
            'configuration' => EventTemplateVersionResource::configuration(
                is_array($preview['effective_payload'] ?? null)
                    ? $preview['effective_payload']
                    : [],
            ),
            'schedule' => [
                'start_at' => (string) ($schedule['start_utc'] ?? ''),
                'end_at' => isset($schedule['end_utc']) ? (string) $schedule['end_utc'] : null,
                'timezone' => (string) ($schedule['timezone'] ?? 'UTC'),
                'all_day' => (bool) ($schedule['all_day'] ?? false),
            ],
            'copied_fields' => self::stringList($preview['copied_fields'] ?? []),
            'skipped_fields' => self::stringList($preview['skipped_fields'] ?? []),
            'override_fields' => self::stringList($preview['override_fields'] ?? []),
            'checklist' => self::checklist($preview['checklist'] ?? []),
            'will_create' => [
                'publication_status' => (string) ($willCreate['publication_status'] ?? 'draft'),
                'operational_status' => (string) ($willCreate['operational_status'] ?? 'scheduled'),
                'recurring' => (bool) ($willCreate['recurring'] ?? false),
                'publish' => (bool) ($willCreate['publish'] ?? false),
                'register' => (bool) ($willCreate['register'] ?? false),
                'notify' => (bool) ($willCreate['notify'] ?? false),
                'federate' => (bool) ($willCreate['federate'] ?? false),
            ],
        ];
    }

    /** @return list<array{code:string,passed:bool}> */
    private static function checklist(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $items = [];
        foreach ($value as $item) {
            if (is_array($item) && is_string($item['code'] ?? null)) {
                $items[] = [
                    'code' => $item['code'],
                    'passed' => (bool) ($item['passed'] ?? false),
                ];
            }
        }

        return $items;
    }

    /** @return list<string> */
    private static function stringList(mixed $value): array
    {
        return is_array($value)
            ? array_values(array_filter($value, 'is_string'))
            : [];
    }
}
