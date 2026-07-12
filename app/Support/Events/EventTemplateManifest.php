<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

use App\Exceptions\EventTemplateException;
use App\Models\Event;
use DateTimeInterface;

/** Canonical, explicit allowlist/denylist boundary for event template snapshots. */
final class EventTemplateManifest
{
    public const SCHEMA_VERSION = 2;

    /** @var list<string> */
    public const COPIED_FIELDS = [
        'title',
        'description',
        'category_id',
        'group_id',
        'location',
        'venue_accessibility',
        'latitude',
        'longitude',
        'max_attendees',
        'is_online',
        'allow_remote_attendance',
        'timezone',
        'all_day',
        'federated_visibility',
    ];

    /**
     * The manifest is deliberately exhaustive at the aggregate-family level.
     * Dotted values describe related operational/private records, never JSON paths
     * that are read from the source event.
     *
     * @var list<string>
     */
    public const SKIPPED_FIELDS = [
        'online_link',
        'video_url',
        'image_url',
        'cover_image',
        'start_time',
        'end_time',
        'id',
        'tenant_id',
        'user_id',
        'created_at',
        'updated_at',
        'status',
        'publication_status',
        'operational_status',
        'lifecycle_version',
        'calendar_sequence',
        'publication_status_changed_at',
        'publication_status_changed_by',
        'operational_status_changed_at',
        'operational_status_changed_by',
        'moderation_submitted_at',
        'moderation_submitted_by',
        'moderated_at',
        'moderated_by',
        'moderation_reason',
        'lifecycle_reason',
        'published_at',
        'published_by',
        'postponed_at',
        'cancelled_at',
        'completed_at',
        'archived_at',
        'archived_by',
        'is_recurring_template',
        'parent_event_id',
        'occurrence_date',
        'occurrence_key',
        'recurrence_engine',
        'recurrence_engine_version',
        'series_id',
        'federated_event_id',
        'federated_tenant_id',
        'federation_id',
        'federation_state',
        'related.participants',
        'related.rsvps',
        'related.registrations',
        'related.waitlist',
        'related.invitations',
        'related.registration_forms',
        'related.private_answers',
        'related.guests',
        'related.staff',
        'related.roles',
        'related.attendance',
        'related.offline_checkin',
        'related.tickets',
        'related.credits',
        'related.wallet_transactions',
        'related.sessions',
        'related.resources',
        'related.reminders',
        'related.notification_outbox',
        'related.notification_deliveries',
        'related.federation_deliveries',
        'related.analytics',
        'related.audit_history',
    ];

    /** Federation is policy-normalized by materialization, not caller-overridable. */
    private const SAFE_OVERRIDE_FIELDS = [
        'title',
        'description',
        'category_id',
        'group_id',
        'location',
        'latitude',
        'longitude',
        'max_attendees',
        'is_online',
        'allow_remote_attendance',
        'timezone',
        'all_day',
    ];

    /** @return array<string,mixed> */
    public function capture(Event $event): array
    {
        $value = static fn (string $field): mixed => $event->getRawOriginal($field);

        return [
            'title' => trim((string) $value('title')),
            'description' => trim((string) $value('description')),
            'category_id' => $this->nullableInteger($value('category_id')),
            'group_id' => $this->nullableInteger($value('group_id')),
            'location' => $this->nullableTrimmedString($value('location')),
            'venue_accessibility' => [
                'step_free_access' => $this->nullableBoolean($value('accessibility_step_free')),
                'accessible_toilet' => $this->nullableBoolean($value('accessibility_toilet')),
                'hearing_loop' => $this->nullableBoolean($value('accessibility_hearing_loop')),
                'quiet_space' => $this->nullableBoolean($value('accessibility_quiet_space')),
                'seating_available' => $this->nullableBoolean($value('accessibility_seating')),
                'accessible_parking' => $this->nullableBoolean($value('accessibility_parking')),
                'parking_details' => $this->nullableTrimmedString($value('accessibility_parking_details')),
                'transit_details' => $this->nullableTrimmedString($value('accessibility_transit_details')),
                'assistance_contact' => $this->nullableTrimmedString($value('accessibility_assistance_contact')),
                'notes' => $this->nullableTrimmedString($value('accessibility_notes')),
            ],
            'latitude' => $this->nullableFloat($value('latitude')),
            'longitude' => $this->nullableFloat($value('longitude')),
            'max_attendees' => $this->nullableInteger($value('max_attendees')),
            'is_online' => (bool) $value('is_online'),
            'allow_remote_attendance' => (bool) $value('allow_remote_attendance'),
            'timezone' => trim((string) ($value('timezone') ?: 'UTC')),
            'all_day' => (bool) $value('all_day'),
            'federated_visibility' => $this->federationVisibility($value('federated_visibility')),
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $overrides
     * @return array{payload:array<string,mixed>,override_fields:list<string>}
     */
    public function materializationPayload(array $payload, array $overrides): array
    {
        $this->assertCanonicalPayload($payload);
        $unknown = array_diff(array_keys($overrides), self::SAFE_OVERRIDE_FIELDS);
        if ($unknown !== []) {
            throw new EventTemplateException('event_template_override_field_forbidden');
        }

        $normalized = [];
        foreach ($overrides as $field => $value) {
            $normalized[$field] = $this->normalizeOverride($field, $value);
        }
        $effective = [...$payload, ...$normalized];
        $effective['federated_visibility'] = 'none';
        $this->assertCanonicalPayload($effective);

        return [
            'payload' => $effective,
            'override_fields' => array_values(array_filter(
                self::SAFE_OVERRIDE_FIELDS,
                static fn (string $field): bool => array_key_exists($field, $normalized),
            )),
        ];
    }

    /** @param array<string,mixed> $payload */
    public function payloadHash(array $payload): string
    {
        $this->assertCanonicalPayload($payload);

        return $this->hash([
            'schema_version' => self::SCHEMA_VERSION,
            'payload' => $payload,
        ]);
    }

    /** @param array<string|int,mixed> $payload */
    public function hash(array $payload): string
    {
        return hash('sha256', json_encode(
            $this->canonicalize($payload),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    /** @param array<string,mixed> $payload */
    public function assertCanonicalPayload(array $payload): void
    {
        if (array_keys($payload) !== self::COPIED_FIELDS) {
            throw new EventTemplateException('event_template_payload_manifest_invalid');
        }
    }

    private function normalizeOverride(string $field, mixed $value): mixed
    {
        return match ($field) {
            'title', 'description' => is_string($value)
                ? trim($value)
                : throw new EventTemplateException('event_template_override_value_invalid'),
            'location' => $this->nullableTrimmedString($value),
            'category_id', 'group_id', 'max_attendees' => $this->nullableIntegerStrict($value),
            'latitude', 'longitude' => $this->nullableFloatStrict($value),
            'is_online', 'allow_remote_attendance', 'all_day' => $this->boolean($value),
            'timezone' => is_string($value) && trim($value) !== ''
                ? trim($value)
                : throw new EventTemplateException('event_template_override_value_invalid'),
            default => throw new EventTemplateException('event_template_override_field_forbidden'),
        };
    }

    private function nullableInteger(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function nullableIntegerStrict(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1)) {
            return (int) $value;
        }

        throw new EventTemplateException('event_template_override_value_invalid');
    }

    private function nullableFloat(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    private function nullableFloatStrict(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }

        throw new EventTemplateException('event_template_override_value_invalid');
    }

    private function nullableBoolean(mixed $value): ?bool
    {
        return $value === null || $value === '' ? null : (bool) $value;
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value)) {
            throw new EventTemplateException('event_template_override_value_invalid');
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function boolean(mixed $value): bool
    {
        if (! is_bool($value)
            && ! (is_int($value) && in_array($value, [0, 1], true))
            && ! (is_string($value)
                && in_array(strtolower(trim($value)), ['0', '1', 'false', 'true'], true))) {
            throw new EventTemplateException('event_template_override_value_invalid');
        }
        $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($filtered === null) {
            throw new EventTemplateException('event_template_override_value_invalid');
        }

        return $filtered;
    }

    private function federationVisibility(mixed $value): string
    {
        $visibility = trim((string) $value);

        return in_array($visibility, ['none', 'listed', 'joinable'], true)
            ? $visibility
            : 'none';
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            if ($value instanceof DateTimeInterface) {
                return $value->format('Y-m-d\TH:i:s.uP');
            }

            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
