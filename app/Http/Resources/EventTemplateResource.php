<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\EventTemplateVersion;
use BackedEnum;
use DateTimeInterface;

/** Public manager projection of a tenant-scoped event template aggregate. */
final class EventTemplateResource
{
    /**
     * @param array{template:EventTemplate,version:EventTemplateVersion,source:Event,capabilities:array<string,bool>} $record
     * @return array<string,mixed>
     */
    public static function fromRecord(array $record): array
    {
        $template = $record['template'];
        $source = $record['source'];
        $status = $template->status instanceof BackedEnum
            ? (string) $template->status->value
            : (string) $template->getRawOriginal('status');

        return [
            'id' => (int) $template->id,
            'public_id' => (string) $template->public_id,
            'status' => $status,
            'current_version' => (int) $template->current_version,
            'source_event' => [
                'id' => (int) $source->id,
                'title' => (string) $source->title,
                'updated_at' => self::timestamp($source->updated_at),
            ],
            'version' => EventTemplateVersionResource::fromModel($record['version']),
            'usage' => [
                'materialization_count' => max(0, (int) ($template->materializations_count ?? 0)),
                'audit_entry_count' => max(0, (int) ($template->audits_count ?? 0)),
            ],
            'archive' => [
                'reason' => $status === 'archived'
                    ? self::nullableString($template->archive_reason)
                    : null,
                'archived_at' => $status === 'archived'
                    ? self::timestamp($template->archived_at)
                    : null,
            ],
            'capabilities' => [
                'view' => (bool) ($record['capabilities']['view'] ?? false),
                'revise' => (bool) ($record['capabilities']['revise'] ?? false),
                'archive' => (bool) ($record['capabilities']['archive'] ?? false),
                'materialize' => (bool) ($record['capabilities']['materialize'] ?? false),
                'view_audit' => (bool) ($record['capabilities']['view_audit'] ?? false),
            ],
            'created_at' => self::timestamp($template->created_at),
            'updated_at' => self::timestamp($template->updated_at),
        ];
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
