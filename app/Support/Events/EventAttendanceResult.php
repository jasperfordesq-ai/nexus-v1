<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

use App\Models\EventAttendance;

/** Result of an idempotent attendance recording attempt. */
final readonly class EventAttendanceResult
{
    public function __construct(
        public EventAttendance $attendance,
        public string $outcome,
        public string $creditStatus,
        public ?int $activityId,
        public ?int $outboxId,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'attendance_id' => (int) $this->attendance->getKey(),
            'event_id' => (int) $this->attendance->event_id,
            'user_id' => (int) $this->attendance->user_id,
            'outcome' => $this->outcome,
            'checked_in' => in_array($this->outcome, ['checked_in', 'already_checked_in'], true),
            'checked_in_at' => $this->attendance->checked_in_at?->toIso8601String(),
            'credit_status' => $this->creditStatus,
            'hours_credited' => $this->attendance->hours_credited !== null
                ? (float) $this->attendance->hours_credited
                : null,
            'attendance_version' => (int) ($this->attendance->attendance_version ?? 0),
        ];
    }
}
