<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_reminders')
            || ! Schema::hasTable('event_reminder_rules')
            || ! Schema::hasTable('event_reminder_schedules')
            || ! Schema::hasTable('event_notification_preferences')) {
            return;
        }

        $skipped = 0;
        DB::table('event_reminders')
            ->select(['tenant_id', 'event_id', 'user_id', 'remind_before_minutes'])
            ->distinct()
            ->orderBy('tenant_id')
            ->orderBy('event_id')
            ->orderBy('user_id')
            ->orderBy('remind_before_minutes')
            ->chunk(250, function ($subjects) use (&$skipped): void {
                foreach ($subjects as $subject) {
                    $tenantId = (int) $subject->tenant_id;
                    $eventId = (int) $subject->event_id;
                    $userId = (int) $subject->user_id;
                    $offset = (int) $subject->remind_before_minutes;
                    $event = $this->scopedEvent($tenantId, $eventId);
                    if ($event === null || ! $this->scopedUserExists($tenantId, $userId) || $offset <= 0) {
                        $skipped++;
                        continue;
                    }

                    $legacyRows = DB::table('event_reminders')
                        ->where('tenant_id', $tenantId)
                        ->where('event_id', $eventId)
                        ->where('user_id', $userId)
                        ->where('remind_before_minutes', $offset)
                        ->orderByRaw('COALESCE(updated_at, created_at) ASC')
                        ->orderBy('id')
                        ->get();
                    if ($legacyRows->isEmpty()) {
                        continue;
                    }

                    $latest = $legacyRows->last();
                    if ($latest === null) {
                        continue;
                    }
                    $ruleChannels = $this->legacyChannels((string) $latest->reminder_type);
                    $ruleVersion = max(1, $legacyRows->count());
                    $ruleValues = [
                        ...$ruleChannels,
                        'enabled' => (string) $latest->status !== 'cancelled',
                        'rule_version' => $ruleVersion,
                        'updated_at' => $latest->updated_at ?? $latest->created_at ?? now(),
                    ];
                    $ruleQuery = DB::table('event_reminder_rules')
                        ->where('tenant_id', $tenantId)
                        ->where('event_id', $eventId)
                        ->where('user_id', $userId)
                        ->where('offset_minutes', $offset);
                    $ruleId = (int) $ruleQuery->value('id');
                    if ($ruleId > 0) {
                        DB::table('event_reminder_rules')
                            ->where('tenant_id', $tenantId)
                            ->where('id', $ruleId)
                            ->update($ruleValues);
                    } else {
                        $ruleId = (int) DB::table('event_reminder_rules')->insertGetId([
                            'tenant_id' => $tenantId,
                            'event_id' => $eventId,
                            'user_id' => $userId,
                            'offset_minutes' => $offset,
                            ...$ruleValues,
                            'created_at' => $latest->created_at ?? now(),
                        ]);
                    }

                    $registration = $this->registration($tenantId, $eventId, $userId);
                    foreach ($legacyRows->values() as $index => $legacy) {
                        $status = $this->legacyStatus((string) $legacy->status);
                        $scheduledFor = CarbonImmutable::parse((string) $legacy->scheduled_for, 'UTC');
                        $scheduleVersion = $index + 1;
                        DB::table('event_reminder_schedules')->insertOrIgnore([
                            'tenant_id' => $tenantId,
                            'event_id' => $eventId,
                            'user_id' => $userId,
                            'rule_id' => $ruleId > 0 ? $ruleId : null,
                            'registration_id' => $registration?->id,
                            'offset_minutes' => $offset,
                            'rule_version' => min($ruleVersion, $scheduleVersion),
                            'registration_version' => (int) ($registration?->registration_version ?? 0),
                            'event_calendar_sequence' => (int) ($event->calendar_sequence ?? 0),
                            'schedule_version' => $scheduleVersion,
                            'scheduled_for' => $scheduledFor->utc(),
                            'deliver_until' => $this->deliverUntil($scheduledFor, $event->start_time),
                            'status' => $status,
                            'reason_code' => 'legacy_reminder_backfill',
                            'delivered_at' => $status === 'delivered' ? ($legacy->sent_at ?? $legacy->updated_at ?? now()) : null,
                            'cancelled_at' => $status === 'cancelled' ? ($legacy->updated_at ?? now()) : null,
                            'created_at' => $legacy->created_at ?? now(),
                            'updated_at' => $legacy->updated_at ?? $legacy->created_at ?? now(),
                        ]);
                    }
                }
            });

        $this->backfillEventReminderIntent();
        $this->backfillFixedSentEvidence($skipped);

        if ($skipped > 0) {
            Log::warning('[EventReminderBackfill] Legacy rows were not canonicalized', [
                'skipped_count' => $skipped,
                'reason_code' => 'event_reminder_legacy_scope_ambiguous',
            ]);
        }
    }

    public function down(): void
    {
        // Expand/backfill data is removed by the two preceding schema rollbacks.
    }

    private function backfillEventReminderIntent(): void
    {
        DB::table('event_reminders')
            ->select(['tenant_id', 'event_id', 'user_id'])
            ->distinct()
            ->orderBy('tenant_id')
            ->orderBy('event_id')
            ->orderBy('user_id')
            ->chunk(250, function ($subjects): void {
                foreach ($subjects as $subject) {
                    $tenantId = (int) $subject->tenant_id;
                    $eventId = (int) $subject->event_id;
                    $userId = (int) $subject->user_id;
                    if ($this->scopedEvent($tenantId, $eventId) === null
                        || ! $this->scopedUserExists($tenantId, $userId)) {
                        continue;
                    }
                    $hasEnabled = DB::table('event_reminder_rules')
                        ->where('tenant_id', $tenantId)
                        ->where('event_id', $eventId)
                        ->where('user_id', $userId)
                        ->where('enabled', true)
                        ->exists();

                    DB::table('event_notification_preferences')->insertOrIgnore([
                        'tenant_id' => $tenantId,
                        'user_id' => $userId,
                        'event_id' => $eventId,
                        'category_id' => null,
                        'reminders_enabled' => $hasEnabled,
                        'preference_version' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    private function backfillFixedSentEvidence(int &$skipped): void
    {
        if (! Schema::hasTable('event_reminder_sent')) {
            return;
        }

        DB::table('event_reminder_sent')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$skipped): void {
                foreach ($rows as $row) {
                    $tenantId = (int) $row->tenant_id;
                    $eventId = (int) $row->event_id;
                    $userId = (int) $row->user_id;
                    $offset = $this->legacyOffset((string) $row->reminder_type);
                    $event = $this->scopedEvent($tenantId, $eventId);
                    if ($event === null || ! $this->scopedUserExists($tenantId, $userId) || $offset === null) {
                        $skipped++;
                        continue;
                    }
                    if (DB::table('event_reminder_schedules')
                        ->where('tenant_id', $tenantId)
                        ->where('event_id', $eventId)
                        ->where('user_id', $userId)
                        ->where('offset_minutes', $offset)
                        ->where('status', 'delivered')
                        ->exists()) {
                        continue;
                    }

                    $registration = $this->registration($tenantId, $eventId, $userId);
                    $start = CarbonImmutable::parse((string) $event->start_time, 'UTC');
                    $scheduledFor = $start->subMinutes($offset);
                    $scheduleVersion = (int) DB::table('event_reminder_schedules')
                        ->where('tenant_id', $tenantId)
                        ->where('event_id', $eventId)
                        ->where('user_id', $userId)
                        ->where('offset_minutes', $offset)
                        ->max('schedule_version') + 1;

                    DB::table('event_reminder_schedules')->insertOrIgnore([
                        'tenant_id' => $tenantId,
                        'event_id' => $eventId,
                        'user_id' => $userId,
                        'rule_id' => null,
                        'registration_id' => $registration?->id,
                        'offset_minutes' => $offset,
                        'rule_version' => 0,
                        'registration_version' => (int) ($registration?->registration_version ?? 0),
                        'event_calendar_sequence' => (int) ($event->calendar_sequence ?? 0),
                        'schedule_version' => max(1, $scheduleVersion),
                        'scheduled_for' => $scheduledFor,
                        'deliver_until' => $this->deliverUntil($scheduledFor, $event->start_time),
                        'status' => 'delivered',
                        'reason_code' => 'legacy_fixed_sent_backfill',
                        'delivered_at' => $row->sent_at,
                        'created_at' => $row->sent_at,
                        'updated_at' => $row->sent_at,
                    ]);
                }
            });
    }

    /** @return array<string,bool> */
    private function legacyChannels(string $type): array
    {
        $email = in_array($type, ['email', 'both'], true);
        $platform = in_array($type, ['platform', 'both'], true);

        return [
            'email_enabled' => $email,
            'in_app_enabled' => $platform,
            'web_push_enabled' => $platform,
            'fcm_enabled' => $platform,
            'realtime_enabled' => $platform,
        ];
    }

    private function legacyStatus(string $status): string
    {
        return match ($status) {
            'sent' => 'delivered',
            'failed' => 'failed_terminal',
            'cancelled' => 'cancelled',
            default => 'pending',
        };
    }

    private function legacyOffset(string $type): ?int
    {
        return match ($type) {
            '7d' => 10080,
            '24h' => 1440,
            '1h' => 60,
            default => null,
        };
    }

    private function scopedEvent(int $tenantId, int $eventId): ?object
    {
        return DB::table('events')
            ->where('tenant_id', $tenantId)
            ->where('id', $eventId)
            ->first(['id', 'start_time', 'calendar_sequence']);
    }

    private function scopedUserExists(int $tenantId, int $userId): bool
    {
        return DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('id', $userId)
            ->exists();
    }

    private function registration(int $tenantId, int $eventId, int $userId): ?object
    {
        return DB::table('event_registrations')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->orderByDesc('registration_version')
            ->orderByDesc('id')
            ->first(['id', 'registration_version']);
    }

    private function deliverUntil(CarbonImmutable $scheduledFor, mixed $eventStart): CarbonImmutable
    {
        $start = CarbonImmutable::parse((string) $eventStart, 'UTC');
        $horizon = max(1, (int) config('events.reminders.catch_up_horizon_minutes', 1440));
        $horizonEnd = $scheduledFor->addMinutes($horizon);

        return $horizonEnd->lessThan($start) ? $horizonEnd : $start;
    }
};
