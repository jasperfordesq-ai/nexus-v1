<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\CaringCommunity;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

class KissTreffenService
{
    private const TABLE = 'caring_kiss_treffen';
    private const TYPES = [
        'monthly_stamm',
        'annual_general_assembly',
        'governance_circle',
        'cooperative_workshop',
        'other',
    ];

    public function isAvailable(): bool
    {
        return Schema::hasTable(self::TABLE);
    }

    public function list(int $tenantId, int $limit = 20): array
    {
        $this->assertAvailable();

        $rows = DB::table(self::TABLE . ' as kt')
            ->join('events as e', function ($join) {
                $join->on('e.id', '=', 'kt.event_id')
                    ->on('e.tenant_id', '=', 'kt.tenant_id');
            })
            ->leftJoin('users as u', function ($join) {
                $join->on('u.id', '=', 'e.user_id')
                    ->on('u.tenant_id', '=', 'e.tenant_id');
            })
            ->where('kt.tenant_id', $tenantId)
            ->whereIn('e.status', ['active', 'draft'])
            ->orderBy('e.start_time')
            ->limit(max(1, min($limit, 100)))
            ->get($this->selectColumns());

        return $rows->map(fn ($row) => $this->format($row))->all();
    }

    public function getByEventId(int $tenantId, int $eventId): array
    {
        $this->assertAvailable();

        $row = $this->baseQuery($tenantId)
            ->where('kt.event_id', $eventId)
            ->first($this->selectColumns());

        if (!$row) {
            throw new RuntimeException(__('api.caring_kiss_treffen_not_found'));
        }

        return $this->format($row);
    }

    public function upsert(int $tenantId, int $eventId, array $input): array
    {
        $this->assertAvailable();
        $this->assertEventExists($tenantId, $eventId);

        $type = (string) ($input['treffen_type'] ?? 'monthly_stamm');
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException(__('api.caring_kiss_treffen_type_invalid'));
        }

        $quorumRequired = $this->nullablePositiveInt($input['quorum_required'] ?? null, 'quorum_required');
        $now = now();

        DB::table(self::TABLE)->updateOrInsert(
            ['tenant_id' => $tenantId, 'event_id' => $eventId],
            [
                'treffen_type' => $type,
                'members_only' => array_key_exists('members_only', $input) ? (bool) $input['members_only'] : true,
                'quorum_required' => $quorumRequired,
                'fondation_header' => $this->optionalString($input['fondation_header'] ?? null, 255),
                'minutes_document_url' => $this->optionalString($input['minutes_document_url'] ?? null, 512),
                'coordinator_notes' => $this->optionalString($input['coordinator_notes'] ?? null, 2000),
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        return $this->getByEventId($tenantId, $eventId);
    }

    public function recordMinutes(int $tenantId, int $eventId, int $actorId, array $input): array
    {
        $this->assertAvailable();

        $minutesUrl = $this->optionalString($input['minutes_document_url'] ?? null, 512);
        if ($minutesUrl === null) {
            throw new InvalidArgumentException(__('api.caring_kiss_treffen_minutes_required'));
        }

        $updated = DB::table(self::TABLE)
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->update([
                'minutes_document_url' => $minutesUrl,
                'minutes_uploaded_at' => now(),
                'minutes_uploaded_by' => $actorId,
                'coordinator_notes' => $this->optionalString($input['coordinator_notes'] ?? null, 2000),
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            throw new RuntimeException(__('api.caring_kiss_treffen_not_found'));
        }

        return $this->getByEventId($tenantId, $eventId);
    }

    private function baseQuery(int $tenantId)
    {
        return DB::table(self::TABLE . ' as kt')
            ->join('events as e', function ($join) {
                $join->on('e.id', '=', 'kt.event_id')
                    ->on('e.tenant_id', '=', 'kt.tenant_id');
            })
            ->leftJoin('users as u', function ($join) {
                $join->on('u.id', '=', 'e.user_id')
                    ->on('u.tenant_id', '=', 'e.tenant_id');
            })
            ->where('kt.tenant_id', $tenantId);
    }

    private function selectColumns(): array
    {
        return [
            'kt.*',
            'e.title',
            'e.description',
            'e.location',
            'e.start_time',
            'e.end_time',
            'e.status as event_status',
            'u.name as organizer_name',
            DB::raw("(SELECT COUNT(*) FROM event_rsvps r WHERE r.tenant_id = kt.tenant_id AND r.event_id = kt.event_id AND r.status IN ('going', 'attended')) as quorum_count"),
        ];
    }

    private function format(object $row): array
    {
        $required = $row->quorum_required !== null ? (int) $row->quorum_required : null;
        $count = (int) ($row->quorum_count ?? 0);

        return [
            'id' => (int) $row->id,
            'tenant_id' => (int) $row->tenant_id,
            'event_id' => (int) $row->event_id,
            'treffen_type' => (string) $row->treffen_type,
            'members_only' => (bool) $row->members_only,
            'fondation_header' => $row->fondation_header,
            'minutes_document_url' => $row->minutes_document_url,
            'minutes_uploaded_at' => $row->minutes_uploaded_at,
            'minutes_uploaded_by' => $row->minutes_uploaded_by ? (int) $row->minutes_uploaded_by : null,
            'coordinator_notes' => $row->coordinator_notes,
            'event' => [
                'id' => (int) $row->event_id,
                'title' => (string) $row->title,
                'description' => (string) $row->description,
                'location' => $row->location,
                'start_time' => $row->start_time,
                'end_time' => $row->end_time,
                'status' => (string) $row->event_status,
                'organizer_name' => $row->organizer_name,
            ],
            'quorum' => [
                'required' => $required,
                'current' => $count,
                'met' => $required !== null ? $count >= $required : null,
            ],
        ];
    }

    private function assertEventExists(int $tenantId, int $eventId): void
    {
        $exists = DB::table('events')
            ->where('tenant_id', $tenantId)
            ->where('id', $eventId)
            ->exists();

        if (!$exists) {
            throw new RuntimeException(__('api.event_not_found'));
        }
    }

    private function assertAvailable(): void
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException(__('api.caring_kiss_treffen_unavailable'));
        }
    }

    private function nullablePositiveInt(mixed $value, string $field): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value) || (int) $value < 0) {
            throw new InvalidArgumentException(__('api.invalid_integer_field', ['field' => $field]));
        }

        return (int) $value;
    }

    private function optionalString(mixed $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);
        return $trimmed !== '' ? mb_substr($trimmed, 0, $maxLength) : null;
    }
}
