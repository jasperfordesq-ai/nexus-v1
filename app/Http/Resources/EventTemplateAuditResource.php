<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\EventTemplateAuditAction;
use App\Models\EventTemplateAudit;
use BackedEnum;
use DateTimeInterface;

/** Allowlisted audit projection that excludes actors, request hashes and private data. */
final class EventTemplateAuditResource
{
    /** @return array<string,mixed> */
    public static function fromModel(EventTemplateAudit $audit): array
    {
        $action = $audit->action instanceof BackedEnum
            ? (string) $audit->action->value
            : (string) $audit->getRawOriginal('action');

        return [
            'id' => (int) $audit->id,
            'action' => $action,
            'template_version' => (int) $audit->template_version_number,
            'source_event_id' => (int) $audit->source_event_id,
            'materialized_event_id' => $audit->materialized_event_id === null
                ? null
                : (int) $audit->materialized_event_id,
            'evidence' => self::evidence(
                $action,
                is_array($audit->metadata) ? $audit->metadata : [],
            ),
            'created_at' => self::timestamp($audit->created_at),
            'immutable' => true,
        ];
    }

    /** @param array<string,mixed> $metadata @return array<string,mixed> */
    private static function evidence(string $action, array $metadata): array
    {
        return match ($action) {
            EventTemplateAuditAction::Captured->value,
            EventTemplateAuditAction::Revised->value => [
                'schema_version' => (int) ($metadata['schema_version'] ?? 0),
                'snapshot_hash' => (string) ($metadata['payload_hash'] ?? ''),
                'copied_fields' => self::stringList($metadata['copied_fields'] ?? []),
                'skipped_fields' => self::stringList($metadata['skipped_fields'] ?? []),
            ],
            EventTemplateAuditAction::Archived->value => [
                'archive_reason_recorded' => (bool) ($metadata['archive_reason_recorded'] ?? false),
            ],
            EventTemplateAuditAction::Materialized->value => [
                'materialization_id' => (int) ($metadata['materialization_id'] ?? 0),
                'effective_snapshot_hash' => (string) ($metadata['effective_payload_hash'] ?? ''),
                'override_fields' => self::stringList($metadata['override_fields'] ?? []),
                'federation_normalized' => (bool) ($metadata['federation_normalized'] ?? false),
                'publication_workflow' => (string) ($metadata['publication_workflow'] ?? ''),
            ],
            default => [],
        };
    }

    /** @return list<string> */
    private static function stringList(mixed $value): array
    {
        return is_array($value)
            ? array_values(array_filter($value, 'is_string'))
            : [];
    }

    private static function timestamp(mixed $value): ?string
    {
        return $value instanceof DateTimeInterface
            ? $value->format(DATE_ATOM)
            : (is_string($value) && $value !== '' ? $value : null);
    }
}
