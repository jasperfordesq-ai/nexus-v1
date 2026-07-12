<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Event;
use App\Models\EventTemplateMaterialization;
use DateTimeInterface;

/** Privacy-safe provenance projection for a newly materialized event draft. */
final class EventTemplateMaterializationResource
{
    /** @return array<string,mixed> */
    public static function fromResult(
        Event $event,
        EventTemplateMaterialization $materialization,
        bool $created,
    ): array {
        return [
            'created_event' => [
                'id' => (int) $event->id,
                'title' => (string) $event->title,
                'publication_status' => (string) $event->getRawOriginal('publication_status'),
                'operational_status' => (string) $event->getRawOriginal('operational_status'),
                'edit_path' => '/events/' . (int) $event->id . '/edit',
            ],
            'provenance' => [
                'id' => (int) $materialization->id,
                'template_id' => (int) $materialization->template_id,
                'template_version' => (int) $materialization->template_version_number,
                'source_event_id' => (int) $materialization->source_event_id,
                'schema_version' => (int) $materialization->schema_version,
                'schedule' => [
                    'start_at' => self::timestamp($materialization->schedule_start_utc),
                    'end_at' => self::timestamp($materialization->schedule_end_utc),
                    'timezone' => (string) $materialization->schedule_timezone,
                    'all_day' => (bool) $materialization->schedule_all_day,
                ],
                'override_fields' => is_array($materialization->override_fields)
                    ? array_values(array_filter($materialization->override_fields, 'is_string'))
                    : [],
                'federation_normalized' => (bool) $materialization->federation_normalized,
                'created_at' => self::timestamp($materialization->created_at),
                'immutable' => true,
            ],
            'changed' => $created,
            'idempotent_replay' => ! $created,
            'workflow' => [
                'fresh_draft' => true,
                'published' => false,
                'registrations_copied' => false,
                'notifications_sent' => false,
                'federated' => false,
            ],
        ];
    }

    private static function timestamp(mixed $value): ?string
    {
        return $value instanceof DateTimeInterface
            ? $value->format(DATE_ATOM)
            : (is_string($value) && $value !== '' ? $value : null);
    }
}
