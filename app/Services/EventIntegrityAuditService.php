<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/** Read-only integrity audit used before Events constraints or writers expand. */
final class EventIntegrityAuditService
{
    /** @return array<string,mixed> */
    public function run(?int $tenantId = null, int $sampleLimit = 100): array
    {
        if ($tenantId !== null && $tenantId <= 0) {
            throw new InvalidArgumentException('Tenant id must be positive.');
        }
        if ($tenantId !== null && !DB::table('tenants')->where('id', $tenantId)->exists()) {
            throw new InvalidArgumentException('Tenant was not found.');
        }

        $sampleLimit = max(1, min($sampleLimit, 1000));
        $issues = [];

        $this->addQueryIssue(
            $issues,
            'event_organizer_tenant_mismatch',
            'critical',
            $this->events($tenantId)
                ->leftJoin('users as organizer', 'organizer.id', '=', 'e.user_id')
                ->where(fn (Builder $q) => $q->whereNull('organizer.id')->orWhereColumn('organizer.tenant_id', '!=', 'e.tenant_id')),
            'e.id',
            $sampleLimit,
        );

        if (Schema::hasColumn('events', 'group_id') && Schema::hasTable('groups')) {
            $this->addQueryIssue(
                $issues,
                'event_group_tenant_mismatch',
                'critical',
                $this->events($tenantId)
                    ->leftJoin('groups as event_group', 'event_group.id', '=', 'e.group_id')
                    ->whereNotNull('e.group_id')
                    ->where(fn (Builder $q) => $q->whereNull('event_group.id')->orWhereColumn('event_group.tenant_id', '!=', 'e.tenant_id')),
                'e.id',
                $sampleLimit,
            );
        }

        if (Schema::hasColumn('events', 'category_id') && Schema::hasTable('categories')) {
            $this->addQueryIssue(
                $issues,
                'event_category_tenant_mismatch',
                'critical',
                $this->events($tenantId)
                    ->leftJoin('categories as category', 'category.id', '=', 'e.category_id')
                    ->whereNotNull('e.category_id')
                    ->where(fn (Builder $q) => $q->whereNull('category.id')->orWhereColumn('category.tenant_id', '!=', 'e.tenant_id')),
                'e.id',
                $sampleLimit,
            );
        }

        if (Schema::hasColumn('events', 'series_id') && Schema::hasTable('event_series')) {
            $this->addQueryIssue(
                $issues,
                'event_series_tenant_mismatch',
                'critical',
                $this->events($tenantId)
                    ->leftJoin('event_series as series', 'series.id', '=', 'e.series_id')
                    ->whereNotNull('e.series_id')
                    ->where(fn (Builder $q) => $q->whereNull('series.id')->orWhereColumn('series.tenant_id', '!=', 'e.tenant_id')),
                'e.id',
                $sampleLimit,
            );
        }

        if (Schema::hasColumn('events', 'parent_event_id')) {
            $this->addQueryIssue(
                $issues,
                'event_parent_tenant_mismatch',
                'critical',
                $this->events($tenantId)
                    ->leftJoin('events as parent_event', 'parent_event.id', '=', 'e.parent_event_id')
                    ->whereNotNull('e.parent_event_id')
                    ->where(fn (Builder $q) => $q->whereNull('parent_event.id')->orWhereColumn('parent_event.tenant_id', '!=', 'e.tenant_id')),
                'e.id',
                $sampleLimit,
            );
            $this->addQueryIssue(
                $issues,
                'event_self_parent',
                'critical',
                $this->events($tenantId)->whereColumn('e.id', 'e.parent_event_id'),
                'e.id',
                $sampleLimit,
            );
        }

        if (Schema::hasColumn('events', 'cancelled_by')) {
            $this->addQueryIssue(
                $issues,
                'event_cancellation_actor_tenant_mismatch',
                'critical',
                $this->events($tenantId)
                    ->leftJoin('users as cancellation_actor', 'cancellation_actor.id', '=', 'e.cancelled_by')
                    ->whereNotNull('e.cancelled_by')
                    ->where(fn (Builder $q) => $q->whereNull('cancellation_actor.id')->orWhereColumn('cancellation_actor.tenant_id', '!=', 'e.tenant_id')),
                'e.id',
                $sampleLimit,
            );
        }

        $this->addQueryIssue(
            $issues,
            'event_invalid_date_range',
            'critical',
            $this->events($tenantId)->whereNotNull('e.end_time')->whereColumn('e.end_time', '<', 'e.start_time'),
            'e.id',
            $sampleLimit,
        );
        $this->addQueryIssue(
            $issues,
            'event_invalid_capacity',
            'warning',
            $this->events($tenantId)->whereNotNull('e.max_attendees')->where('e.max_attendees', '<', 1),
            'e.id',
            $sampleLimit,
        );

        $this->auditTimeAndOccurrenceIdentity($issues, $tenantId, $sampleLimit);

        foreach (['online_link', 'video_url'] as $urlColumn) {
            if (!Schema::hasColumn('events', $urlColumn)) {
                continue;
            }
            $this->addQueryIssue(
                $issues,
                "event_invalid_{$urlColumn}_scheme",
                'warning',
                $this->events($tenantId)
                    ->whereNotNull("e.{$urlColumn}")
                    ->where("e.{$urlColumn}", '!=', '')
                    ->where("e.{$urlColumn}", 'not like', 'https://%')
                    ->where("e.{$urlColumn}", 'not like', 'http://%'),
                'e.id',
                $sampleLimit,
            );
        }

        foreach ([
            'event_rsvps',
            'event_waitlist',
            'event_registrations',
            'event_registration_history',
            'event_waitlist_entries',
            'event_waitlist_entry_history',
            'event_reminders',
            'event_reminder_sent',
            'event_reminder_delivery_claims',
            'event_attendance',
            'event_attendance_activity',
            'event_attendance_credit_claims',
        ] as $childTable) {
            $this->auditChildTable($issues, $childTable, $tenantId, $sampleLimit);
        }

        if (Schema::hasTable('event_waitlist')) {
            $duplicates = DB::table('event_waitlist as w')
                ->when($tenantId !== null, fn (Builder $q) => $q->where('w.tenant_id', $tenantId))
                ->where('w.status', 'waiting')
                ->groupBy('w.tenant_id', 'w.event_id', 'w.position')
                ->havingRaw('COUNT(*) > 1');
            $duplicateCountQuery = (clone $duplicates)
                ->select(['w.tenant_id', 'w.event_id', 'w.position']);
            $count = DB::query()->fromSub($duplicateCountQuery, 'duplicate_waitlist_positions')->count();
            if ($count > 0) {
                $issues[] = [
                    'code' => 'duplicate_waitlist_position',
                    'severity' => 'critical',
                    'count' => $count,
                    'sample_ids' => (clone $duplicates)->limit($sampleLimit)->pluck('w.event_id')->map(fn ($id) => (int) $id)->all(),
                ];
            }
        }

        if (Schema::hasTable('event_attendance') && Schema::hasTable('event_rsvps')) {
            $defaultCapacityPool = trim((string) config(
                'events.registration.default_capacity_pool_key',
                'event',
            ));
            $defaultCapacityPool = $defaultCapacityPool === '' ? 'event' : $defaultCapacityPool;
            $attendanceWithoutProof = DB::table('event_attendance as attendance')
                ->leftJoin('event_rsvps as rsvp', function ($join): void {
                    $join->on('rsvp.event_id', '=', 'attendance.event_id')
                        ->on('rsvp.user_id', '=', 'attendance.user_id')
                        ->on('rsvp.tenant_id', '=', 'attendance.tenant_id');
                })
                ->when($tenantId !== null, fn (Builder $q) => $q->where('attendance.tenant_id', $tenantId));
            if (Schema::hasTable('event_registrations')) {
                $attendanceWithoutProof
                    ->leftJoin('event_registrations as attendance_registration', function ($join) use ($defaultCapacityPool): void {
                        $join->on('attendance_registration.event_id', '=', 'attendance.event_id')
                            ->on('attendance_registration.user_id', '=', 'attendance.user_id')
                            ->on('attendance_registration.tenant_id', '=', 'attendance.tenant_id')
                            ->where('attendance_registration.capacity_pool_key', '=', $defaultCapacityPool);
                    })
                    ->where(function (Builder $proof): void {
                        $proof->where(function (Builder $canonical): void {
                            $canonical->whereNotNull('attendance_registration.id')
                                ->where('attendance_registration.registration_state', '!=', 'confirmed');
                        })->orWhere(function (Builder $legacy): void {
                            $legacy->whereNull('attendance_registration.id')
                                ->where(function (Builder $fallback): void {
                                    $fallback->whereNull('rsvp.id')
                                        ->orWhere('rsvp.status', '!=', 'attended');
                                });
                        });
                    });
            } else {
                $attendanceWithoutProof->where(
                    fn (Builder $q) => $q
                        ->whereNull('rsvp.id')
                        ->orWhere('rsvp.status', '!=', 'attended'),
                );
            }
            $this->addQueryIssue(
                $issues,
                'attendance_without_attended_rsvp',
                'warning',
                $attendanceWithoutProof,
                'attendance.id',
                $sampleLimit,
            );
            $legacyAttendedWithoutAttendance = DB::table('event_rsvps as rsvp')
                ->leftJoin('event_attendance as attendance', function ($join): void {
                    $join->on('attendance.event_id', '=', 'rsvp.event_id')
                        ->on('attendance.user_id', '=', 'rsvp.user_id')
                        ->on('attendance.tenant_id', '=', 'rsvp.tenant_id');
                })
                ->when($tenantId !== null, fn (Builder $q) => $q->where('rsvp.tenant_id', $tenantId))
                ->where('rsvp.status', 'attended')
                ->whereNull('attendance.id');
            if (Schema::hasTable('event_registrations')) {
                $legacyAttendedWithoutAttendance
                    ->leftJoin('event_registrations as attended_registration', function ($join) use ($defaultCapacityPool): void {
                        $join->on('attended_registration.event_id', '=', 'rsvp.event_id')
                            ->on('attended_registration.user_id', '=', 'rsvp.user_id')
                            ->on('attended_registration.tenant_id', '=', 'rsvp.tenant_id')
                            ->where('attended_registration.capacity_pool_key', '=', $defaultCapacityPool);
                    })
                    ->whereNull('attended_registration.id');
            }
            $this->addQueryIssue(
                $issues,
                'attended_rsvp_without_attendance',
                'warning',
                $legacyAttendedWithoutAttendance,
                'rsvp.id',
                $sampleLimit,
            );
        }

        $this->auditTerminalParticipantState($issues, $tenantId, $sampleLimit);
        $this->auditAttendanceLedger($issues, $tenantId, $sampleLimit);
        $this->auditRegistrationAndWaitlistLedger($issues, $tenantId, $sampleLimit);
        $this->auditCalendarFeedTokens($issues, $tenantId, $sampleLimit);

        if (Schema::hasTable('transactions')) {
            $transactionQuery = DB::table('transactions as transaction')
                ->when($tenantId !== null, fn (Builder $q) => $q->where('transaction.tenant_id', $tenantId))
                ->where('transaction.transaction_type', 'event_checkin');
            if (Schema::hasTable('event_attendance_credit_claims')) {
                $transactionQuery
                    ->leftJoin('event_attendance_credit_claims as attendance_claim', function ($join): void {
                        $join->on('attendance_claim.transaction_id', '=', 'transaction.id')
                            ->on('attendance_claim.tenant_id', '=', 'transaction.tenant_id');
                    })
                    ->whereNull('attendance_claim.id');
            }
            $this->addQueryIssue(
                $issues,
                'unmappable_legacy_event_checkin_transaction',
                'critical',
                $transactionQuery,
                'transaction.id',
                $sampleLimit,
            );
        }

        $severityCounts = ['critical' => 0, 'warning' => 0];
        foreach ($issues as $issue) {
            $severityCounts[$issue['severity']] += (int) $issue['count'];
        }

        return [
            'read_only' => true,
            'tenant_id' => $tenantId,
            'generated_at' => now()->toIso8601String(),
            'issue_types' => count($issues),
            'issues_by_severity' => $severityCounts,
            'blocking' => $severityCounts['critical'] > 0,
            'issues' => $issues,
        ];
    }

    private function events(?int $tenantId): Builder
    {
        return DB::table('events as e')
            ->when($tenantId !== null, fn (Builder $query) => $query->where('e.tenant_id', $tenantId));
    }

    /** @param list<array<string,mixed>> $issues */
    private function auditTimeAndOccurrenceIdentity(
        array &$issues,
        ?int $tenantId,
        int $sampleLimit,
    ): void {
        if (Schema::hasColumn('events', 'timezone')) {
            $this->addQueryIssue(
                $issues,
                'event_timezone_missing',
                'critical',
                $this->events($tenantId)
                    ->where(fn (Builder $q) => $q->whereNull('e.timezone')->orWhere('e.timezone', '')),
                'e.id',
                $sampleLimit,
            );
            $this->addInvalidTimezoneIssue($issues, $tenantId, $sampleLimit);
        }

        if (Schema::hasColumn('events', 'timezone_source')) {
            $this->addQueryIssue(
                $issues,
                'event_timezone_source_missing',
                'critical',
                $this->events($tenantId)
                    ->where(fn (Builder $q) => $q->whereNull('e.timezone_source')->orWhere('e.timezone_source', '')),
                'e.id',
                $sampleLimit,
            );
            $this->addQueryIssue(
                $issues,
                'event_timezone_fallback_provenance',
                'warning',
                $this->events($tenantId)
                    ->whereNotNull('e.timezone_source')
                    ->where('e.timezone_source', '!=', '')
                    ->whereNotIn('e.timezone_source', ['tenant_setting', 'explicit']),
                'e.id',
                $sampleLimit,
            );
        }

        if (Schema::hasColumn('events', 'all_day')) {
            $this->addQueryIssue(
                $issues,
                'event_all_day_semantics_missing',
                'critical',
                $this->events($tenantId)->whereNull('e.all_day'),
                'e.id',
                $sampleLimit,
            );
            $this->addQueryIssue(
                $issues,
                'event_all_day_end_missing',
                'critical',
                $this->events($tenantId)->where('e.all_day', 1)->whereNull('e.end_time'),
                'e.id',
                $sampleLimit,
            );
        }

        if (! Schema::hasColumn('events', 'occurrence_key')) {
            return;
        }

        $concreteEvents = $this->events($tenantId);
        if (Schema::hasColumn('events', 'is_recurring_template')) {
            $concreteEvents->where('e.is_recurring_template', 0);
        }
        $this->addQueryIssue(
            $issues,
            'event_concrete_occurrence_key_missing',
            'critical',
            $concreteEvents
                ->where(fn (Builder $q) => $q->whereNull('e.occurrence_key')->orWhere('e.occurrence_key', '')),
            'e.id',
            $sampleLimit,
        );

        if (Schema::hasColumn('events', 'is_recurring_template')) {
            $this->addQueryIssue(
                $issues,
                'event_recurrence_template_has_occurrence_key',
                'critical',
                $this->events($tenantId)
                    ->where('e.is_recurring_template', 1)
                    ->whereNotNull('e.occurrence_key')
                    ->where('e.occurrence_key', '!=', ''),
                'e.id',
                $sampleLimit,
            );
        }

        $duplicateKeys = $this->events($tenantId)
            ->whereNotNull('e.occurrence_key')
            ->where('e.occurrence_key', '!=', '')
            ->groupBy('e.tenant_id', 'e.occurrence_key')
            ->havingRaw('COUNT(*) > 1');
        $duplicateCountQuery = (clone $duplicateKeys)->select(['e.tenant_id', 'e.occurrence_key']);
        $duplicateCount = DB::query()->fromSub($duplicateCountQuery, 'duplicate_occurrence_keys')->count();
        if ($duplicateCount > 0) {
            $sampleQuery = (clone $duplicateKeys)->selectRaw('MIN(e.id) AS sample_id');
            $issues[] = [
                'code' => 'event_duplicate_occurrence_key',
                'severity' => 'critical',
                'count' => $duplicateCount,
                'sample_ids' => DB::query()
                    ->fromSub($sampleQuery, 'duplicate_occurrence_samples')
                    ->limit($sampleLimit)
                    ->pluck('sample_id')
                    ->map(fn ($id): int => (int) $id)
                    ->all(),
            ];
        }

        $hasEngine = Schema::hasColumn('events', 'recurrence_engine');
        $hasVersion = Schema::hasColumn('events', 'recurrence_engine_version');
        if ($hasEngine && $hasVersion) {
            $this->addQueryIssue(
                $issues,
                'event_partial_recurrence_engine_metadata',
                'critical',
                $this->events($tenantId)->where(function (Builder $q): void {
                    $q->where(function (Builder $presentEngineMissingVersion): void {
                        $presentEngineMissingVersion
                            ->whereNotNull('e.recurrence_engine')
                            ->where('e.recurrence_engine', '!=', '')
                            ->where(fn (Builder $missing) => $missing
                                ->whereNull('e.recurrence_engine_version')
                                ->orWhere('e.recurrence_engine_version', ''));
                    })->orWhere(function (Builder $missingEnginePresentVersion): void {
                        $missingEnginePresentVersion
                            ->where(fn (Builder $missing) => $missing
                                ->whereNull('e.recurrence_engine')
                                ->orWhere('e.recurrence_engine', ''))
                            ->whereNotNull('e.recurrence_engine_version')
                            ->where('e.recurrence_engine_version', '!=', '');
                    });
                }),
                'e.id',
                $sampleLimit,
            );

            if (Schema::hasColumn('events', 'parent_event_id')
                && Schema::hasColumn('events', 'is_recurring_template')) {
                $this->addQueryIssue(
                    $issues,
                    'event_recurrence_engine_metadata_missing',
                    'critical',
                    $this->events($tenantId)
                        ->where(fn (Builder $q) => $q
                            ->where('e.is_recurring_template', 1)
                            ->orWhereNotNull('e.parent_event_id'))
                        ->where(function (Builder $q): void {
                            $q->whereNull('e.recurrence_engine')
                                ->orWhere('e.recurrence_engine', '')
                                ->orWhereNull('e.recurrence_engine_version')
                                ->orWhere('e.recurrence_engine_version', '');
                        }),
                    'e.id',
                    $sampleLimit,
                );
            }
        }

        if (Schema::hasColumn('events', 'is_recurring_template')) {
            foreach ([
                'event_rsvps',
                'event_waitlist',
                'event_registrations',
                'event_registration_history',
                'event_waitlist_entries',
                'event_waitlist_entry_history',
                'event_waitlist_offer_envelopes',
                'event_waitlist_offer_envelope_access',
                'event_reminders',
                'event_reminder_sent',
                'event_reminder_delivery_claims',
                'event_attendance',
                'event_attendance_activity',
                'event_attendance_credit_claims',
            ] as $childTable) {
                if (! Schema::hasTable($childTable)) {
                    continue;
                }
                $this->addQueryIssue(
                    $issues,
                    "{$childTable}_attached_to_recurrence_template",
                    'critical',
                    DB::table("{$childTable} as concrete_child")
                        ->join('events as recurrence_template', 'recurrence_template.id', '=', 'concrete_child.event_id')
                        ->when($tenantId !== null, fn (Builder $q) => $q->where('concrete_child.tenant_id', $tenantId))
                        ->where('recurrence_template.is_recurring_template', 1),
                    'concrete_child.id',
                    $sampleLimit,
                );
            }
        }
    }

    /** @param list<array<string,mixed>> $issues */
    private function auditAttendanceLedger(
        array &$issues,
        ?int $tenantId,
        int $sampleLimit,
    ): void {
        if (Schema::hasTable('event_attendance_activity')) {
            $this->addQueryIssue(
                $issues,
                'event_attendance_activity_actor_tenant_mismatch',
                'critical',
                DB::table('event_attendance_activity as activity')
                    ->leftJoin('users as activity_actor', 'activity_actor.id', '=', 'activity.actor_user_id')
                    ->when($tenantId !== null, fn (Builder $q) => $q->where('activity.tenant_id', $tenantId))
                    ->where(fn (Builder $q) => $q
                        ->whereNull('activity_actor.id')
                        ->orWhereColumn('activity_actor.tenant_id', '!=', 'activity.tenant_id')),
                'activity.id',
                $sampleLimit,
            );
            $this->addQueryIssue(
                $issues,
                'event_attendance_activity_fact_mismatch',
                'critical',
                DB::table('event_attendance_activity as activity')
                    ->leftJoin('event_attendance as attendance', 'attendance.id', '=', 'activity.attendance_id')
                    ->when($tenantId !== null, fn (Builder $q) => $q->where('activity.tenant_id', $tenantId))
                    ->where(fn (Builder $q) => $q
                        ->whereNull('attendance.id')
                        ->orWhereColumn('attendance.tenant_id', '!=', 'activity.tenant_id')
                        ->orWhereColumn('attendance.event_id', '!=', 'activity.event_id')
                        ->orWhereColumn('attendance.user_id', '!=', 'activity.user_id')),
                'activity.id',
                $sampleLimit,
            );
        }

        if (! Schema::hasTable('event_attendance_credit_claims')) {
            return;
        }

        $this->addQueryIssue(
            $issues,
            'event_attendance_credit_claim_fact_mismatch',
            'critical',
            DB::table('event_attendance_credit_claims as claim')
                ->leftJoin('event_attendance as attendance', 'attendance.id', '=', 'claim.attendance_id')
                ->when($tenantId !== null, fn (Builder $q) => $q->where('claim.tenant_id', $tenantId))
                ->where(fn (Builder $q) => $q
                    ->whereNull('attendance.id')
                    ->orWhereColumn('attendance.tenant_id', '!=', 'claim.tenant_id')
                    ->orWhereColumn('attendance.event_id', '!=', 'claim.event_id')
                    ->orWhereColumn('attendance.user_id', '!=', 'claim.user_id')),
            'claim.id',
            $sampleLimit,
        );

        foreach (['payer_user_id', 'payee_user_id'] as $participantColumn) {
            $alias = $participantColumn === 'payer_user_id' ? 'claim_payer' : 'claim_payee';
            $this->addQueryIssue(
                $issues,
                "event_attendance_credit_claim_{$participantColumn}_tenant_mismatch",
                'critical',
                DB::table('event_attendance_credit_claims as claim')
                    ->leftJoin("users as {$alias}", "{$alias}.id", '=', "claim.{$participantColumn}")
                    ->when($tenantId !== null, fn (Builder $q) => $q->where('claim.tenant_id', $tenantId))
                    ->whereNotNull("claim.{$participantColumn}")
                    ->where(fn (Builder $q) => $q
                        ->whereNull("{$alias}.id")
                        ->orWhereColumn("{$alias}.tenant_id", '!=', 'claim.tenant_id')),
                'claim.id',
                $sampleLimit,
            );
        }

        if (Schema::hasTable('transactions')) {
            $this->addQueryIssue(
                $issues,
                'event_attendance_completed_claim_transaction_mismatch',
                'critical',
                DB::table('event_attendance_credit_claims as claim')
                    ->leftJoin('transactions as claim_transaction', 'claim_transaction.id', '=', 'claim.transaction_id')
                    ->when($tenantId !== null, fn (Builder $q) => $q->where('claim.tenant_id', $tenantId))
                    ->where('claim.status', 'completed')
                    ->where(fn (Builder $q) => $q
                        ->whereNull('claim.transaction_id')
                        ->orWhereNull('claim_transaction.id')
                        ->orWhereColumn('claim_transaction.tenant_id', '!=', 'claim.tenant_id')),
                'claim.id',
                $sampleLimit,
            );
        }

        if (Schema::hasTable('event_attendance')) {
            $this->addQueryIssue(
                $issues,
                'event_attendance_credited_without_completed_claim',
                'critical',
                DB::table('event_attendance as attendance')
                    ->leftJoin('event_attendance_credit_claims as claim', function ($join): void {
                        $join->on('claim.attendance_id', '=', 'attendance.id')
                            ->on('claim.tenant_id', '=', 'attendance.tenant_id')
                            ->where('claim.status', '=', 'completed');
                    })
                    ->when($tenantId !== null, fn (Builder $q) => $q->where('attendance.tenant_id', $tenantId))
                    ->whereNotNull('attendance.hours_credited')
                    ->whereNull('claim.id'),
                'attendance.id',
                $sampleLimit,
            );
        }
    }

    /** @param list<array<string,mixed>> $issues */
    private function auditRegistrationAndWaitlistLedger(
        array &$issues,
        ?int $tenantId,
        int $sampleLimit,
    ): void {
        if (Schema::hasTable('event_registrations')) {
            $this->addQueryIssue(
                $issues,
                'event_registration_state_invalid',
                'critical',
                DB::table('event_registrations as registration')
                    ->when($tenantId !== null, fn (Builder $q) => $q->where('registration.tenant_id', $tenantId))
                    ->whereNotIn('registration.registration_state', [
                        'invited',
                        'pending',
                        'confirmed',
                        'declined',
                        'cancelled',
                    ]),
                'registration.id',
                $sampleLimit,
            );
            $this->addQueryIssue(
                $issues,
                'event_registration_version_invalid',
                'critical',
                DB::table('event_registrations as registration')
                    ->when($tenantId !== null, fn (Builder $q) => $q->where('registration.tenant_id', $tenantId))
                    ->where('registration.registration_version', '<', 1),
                'registration.id',
                $sampleLimit,
            );
            $this->auditActorTenant(
                $issues,
                'event_registrations',
                'registration',
                'state_changed_by',
                $tenantId,
                $sampleLimit,
            );

            foreach ([
                'invited' => 'invited_at',
                'pending' => 'pending_at',
                'confirmed' => 'confirmed_at',
                'declined' => 'declined_at',
                'cancelled' => 'cancelled_at',
            ] as $state => $timestamp) {
                $this->addQueryIssue(
                    $issues,
                    "event_registration_{$state}_timestamp_missing",
                    'critical',
                    DB::table('event_registrations as registration')
                        ->when($tenantId !== null, fn (Builder $q) => $q->where('registration.tenant_id', $tenantId))
                        ->where('registration.registration_state', $state)
                        ->whereNull("registration.{$timestamp}"),
                    'registration.id',
                    $sampleLimit,
                );
            }

            if (Schema::hasTable('event_registration_history')) {
                $this->addQueryIssue(
                    $issues,
                    'event_registration_history_fact_mismatch',
                    'critical',
                    DB::table('event_registration_history as history')
                        ->leftJoin(
                            'event_registrations as registration',
                            'registration.id',
                            '=',
                            'history.registration_id',
                        )
                        ->when($tenantId !== null, fn (Builder $q) => $q->where('history.tenant_id', $tenantId))
                        ->where(fn (Builder $q) => $q
                            ->whereNull('registration.id')
                            ->orWhereColumn('registration.tenant_id', '!=', 'history.tenant_id')
                            ->orWhereColumn('registration.event_id', '!=', 'history.event_id')
                            ->orWhereColumn('registration.user_id', '!=', 'history.user_id')
                            ->orWhereColumn('registration.capacity_pool_key', '!=', 'history.capacity_pool_key')),
                    'history.id',
                    $sampleLimit,
                );
                $this->addQueryIssue(
                    $issues,
                    'event_registration_fact_history_missing_or_stale',
                    'critical',
                    DB::table('event_registrations as registration')
                        ->leftJoin('event_registration_history as history', function ($join): void {
                            $join->on('history.registration_id', '=', 'registration.id')
                                ->on('history.tenant_id', '=', 'registration.tenant_id')
                                ->on('history.registration_version', '=', 'registration.registration_version');
                        })
                        ->when($tenantId !== null, fn (Builder $q) => $q->where('registration.tenant_id', $tenantId))
                        ->where(fn (Builder $q) => $q
                            ->whereNull('history.id')
                            ->orWhereColumn('history.to_state', '!=', 'registration.registration_state')),
                    'registration.id',
                    $sampleLimit,
                );
                $this->auditActorTenant(
                    $issues,
                    'event_registration_history',
                    'history',
                    'actor_user_id',
                    $tenantId,
                    $sampleLimit,
                );
            }
        }

        if (Schema::hasTable('event_waitlist_entries')) {
            $this->addQueryIssue(
                $issues,
                'event_waitlist_queue_state_invalid',
                'critical',
                DB::table('event_waitlist_entries as entry')
                    ->when($tenantId !== null, fn (Builder $q) => $q->where('entry.tenant_id', $tenantId))
                    ->whereNotIn('entry.queue_state', [
                        'waiting',
                        'offered',
                        'accepted',
                        'expired',
                        'cancelled',
                    ]),
                'entry.id',
                $sampleLimit,
            );
            $this->addQueryIssue(
                $issues,
                'event_waitlist_queue_identity_invalid',
                'critical',
                DB::table('event_waitlist_entries as entry')
                    ->when($tenantId !== null, fn (Builder $q) => $q->where('entry.tenant_id', $tenantId))
                    ->where(fn (Builder $q) => $q
                        ->where('entry.queue_version', '<', 1)
                        ->orWhere('entry.queue_sequence', '<', 1)),
                'entry.id',
                $sampleLimit,
            );
            $this->auditActorTenant(
                $issues,
                'event_waitlist_entries',
                'entry',
                'state_changed_by',
                $tenantId,
                $sampleLimit,
            );
            $this->addQueryIssue(
                $issues,
                'event_waitlist_offer_evidence_missing',
                'critical',
                DB::table('event_waitlist_entries as entry')
                    ->when($tenantId !== null, fn (Builder $q) => $q->where('entry.tenant_id', $tenantId))
                    ->where('entry.queue_state', 'offered')
                    ->where(fn (Builder $q) => $q
                        ->whereNull('entry.offered_at')
                        ->orWhereNull('entry.offer_expires_at')
                        ->orWhereNull('entry.offer_token_hash')
                        ->orWhereRaw('CHAR_LENGTH(entry.offer_token_hash) <> 64')
                        ->orWhereRaw("entry.offer_token_hash NOT REGEXP '^[0-9a-fA-F]{64}$'")),
                'entry.id',
                $sampleLimit,
            );
            $this->addQueryIssue(
                $issues,
                'event_waitlist_offer_window_invalid',
                'critical',
                DB::table('event_waitlist_entries as entry')
                    ->when($tenantId !== null, fn (Builder $q) => $q->where('entry.tenant_id', $tenantId))
                    ->whereNotNull('entry.offered_at')
                    ->whereNotNull('entry.offer_expires_at')
                    ->whereColumn('entry.offer_expires_at', '<=', 'entry.offered_at'),
                'entry.id',
                $sampleLimit,
            );
            $this->addQueryIssue(
                $issues,
                'event_waitlist_offer_expiry_overdue',
                'warning',
                DB::table('event_waitlist_entries as entry')
                    ->when($tenantId !== null, fn (Builder $q) => $q->where('entry.tenant_id', $tenantId))
                    ->where('entry.queue_state', 'offered')
                    ->whereNotNull('entry.offer_expires_at')
                    ->where('entry.offer_expires_at', '<=', now()),
                'entry.id',
                $sampleLimit,
            );
            $this->addQueryIssue(
                $issues,
                'event_waitlist_accepted_evidence_missing',
                'critical',
                DB::table('event_waitlist_entries as entry')
                    ->when($tenantId !== null, fn (Builder $q) => $q->where('entry.tenant_id', $tenantId))
                    ->where('entry.queue_state', 'accepted')
                    ->where(fn (Builder $q) => $q
                        ->whereNull('entry.offered_at')
                        ->orWhereNull('entry.offer_expires_at')
                        ->orWhereNull('entry.offer_token_hash')
                        ->orWhereNull('entry.accepted_at')
                        ->orWhereNull('entry.offer_token_used_at')
                        ->orWhereNull('entry.accepted_registration_id')),
                'entry.id',
                $sampleLimit,
            );
            foreach (['expired' => 'expired_at', 'cancelled' => 'cancelled_at'] as $state => $timestamp) {
                $this->addQueryIssue(
                    $issues,
                    "event_waitlist_{$state}_timestamp_missing",
                    'critical',
                    DB::table('event_waitlist_entries as entry')
                        ->when($tenantId !== null, fn (Builder $q) => $q->where('entry.tenant_id', $tenantId))
                        ->where('entry.queue_state', $state)
                        ->whereNull("entry.{$timestamp}"),
                    'entry.id',
                    $sampleLimit,
                );
            }
            $this->addQueryIssue(
                $issues,
                'event_waitlist_token_used_outside_acceptance',
                'critical',
                DB::table('event_waitlist_entries as entry')
                    ->when($tenantId !== null, fn (Builder $q) => $q->where('entry.tenant_id', $tenantId))
                    ->whereNotNull('entry.offer_token_used_at')
                    ->where('entry.queue_state', '!=', 'accepted'),
                'entry.id',
                $sampleLimit,
            );

            if (Schema::hasTable('event_registrations')) {
                $this->addQueryIssue(
                    $issues,
                    'event_waitlist_accepted_registration_mismatch',
                    'critical',
                    DB::table('event_waitlist_entries as entry')
                        ->leftJoin(
                            'event_registrations as registration',
                            'registration.id',
                            '=',
                            'entry.accepted_registration_id',
                        )
                        ->when($tenantId !== null, fn (Builder $q) => $q->where('entry.tenant_id', $tenantId))
                        ->where('entry.queue_state', 'accepted')
                        ->where(fn (Builder $q) => $q
                            ->whereNull('registration.id')
                            ->orWhereColumn('registration.tenant_id', '!=', 'entry.tenant_id')
                            ->orWhereColumn('registration.event_id', '!=', 'entry.event_id')
                            ->orWhereColumn('registration.user_id', '!=', 'entry.user_id')
                            ->orWhereColumn('registration.capacity_pool_key', '!=', 'entry.capacity_pool_key')
                            ->orWhere('registration.registration_state', '!=', 'confirmed')),
                    'entry.id',
                    $sampleLimit,
                );
            }

            if (Schema::hasTable('event_waitlist_entry_history')) {
                $this->addQueryIssue(
                    $issues,
                    'event_waitlist_history_fact_mismatch',
                    'critical',
                    DB::table('event_waitlist_entry_history as history')
                        ->leftJoin('event_waitlist_entries as entry', 'entry.id', '=', 'history.waitlist_entry_id')
                        ->when($tenantId !== null, fn (Builder $q) => $q->where('history.tenant_id', $tenantId))
                        ->where(fn (Builder $q) => $q
                            ->whereNull('entry.id')
                            ->orWhereColumn('entry.tenant_id', '!=', 'history.tenant_id')
                            ->orWhereColumn('entry.event_id', '!=', 'history.event_id')
                            ->orWhereColumn('entry.user_id', '!=', 'history.user_id')
                            ->orWhereColumn('entry.capacity_pool_key', '!=', 'history.capacity_pool_key')),
                    'history.id',
                    $sampleLimit,
                );
                $this->addQueryIssue(
                    $issues,
                    'event_waitlist_fact_history_missing_or_stale',
                    'critical',
                    DB::table('event_waitlist_entries as entry')
                        ->leftJoin('event_waitlist_entry_history as history', function ($join): void {
                            $join->on('history.waitlist_entry_id', '=', 'entry.id')
                                ->on('history.tenant_id', '=', 'entry.tenant_id')
                                ->on('history.queue_version', '=', 'entry.queue_version');
                        })
                        ->when($tenantId !== null, fn (Builder $q) => $q->where('entry.tenant_id', $tenantId))
                        ->where(fn (Builder $q) => $q
                            ->whereNull('history.id')
                            ->orWhereColumn('history.to_state', '!=', 'entry.queue_state')
                            ->orWhereColumn('history.queue_sequence', '!=', 'entry.queue_sequence')),
                    'entry.id',
                    $sampleLimit,
                );
                $this->auditActorTenant(
                    $issues,
                    'event_waitlist_entry_history',
                    'history',
                    'actor_user_id',
                    $tenantId,
                    $sampleLimit,
                );
            }
        }

        $this->auditWaitlistOfferEnvelopes($issues, $tenantId, $sampleLimit);
        $this->auditCapacityClaims($issues, $tenantId, $sampleLimit);
    }

    /** @param list<array<string,mixed>> $issues */
    private function auditWaitlistOfferEnvelopes(
        array &$issues,
        ?int $tenantId,
        int $sampleLimit,
    ): void {
        if (! Schema::hasTable('event_waitlist_offer_envelopes')) {
            return;
        }

        $this->addQueryIssue(
            $issues,
            'event_waitlist_offer_envelope_fact_mismatch',
            'critical',
            DB::table('event_waitlist_offer_envelopes as envelope')
                ->leftJoin('event_waitlist_entries as entry', 'entry.id', '=', 'envelope.waitlist_entry_id')
                ->leftJoin('event_domain_outbox as outbox', 'outbox.id', '=', 'envelope.outbox_id')
                ->when($tenantId !== null, fn (Builder $q) => $q->where('envelope.tenant_id', $tenantId))
                ->where(fn (Builder $q) => $q
                    ->whereNull('entry.id')
                    ->orWhereNull('outbox.id')
                    ->orWhereColumn('entry.tenant_id', '!=', 'envelope.tenant_id')
                    ->orWhereColumn('entry.event_id', '!=', 'envelope.event_id')
                    ->orWhereColumn('entry.queue_version', '!=', 'envelope.queue_version')
                    ->orWhereColumn('outbox.tenant_id', '!=', 'envelope.tenant_id')
                    ->orWhereColumn('outbox.event_id', '!=', 'envelope.event_id')
                    ->orWhereColumn('outbox.action', '!=', 'envelope.action')),
            'envelope.id',
            $sampleLimit,
        );
        $this->addQueryIssue(
            $issues,
            'event_waitlist_offer_envelope_state_invalid',
            'critical',
            DB::table('event_waitlist_offer_envelopes as envelope')
                ->when($tenantId !== null, fn (Builder $q) => $q->where('envelope.tenant_id', $tenantId))
                ->where(fn (Builder $q) => $q
                    ->whereNotIn('envelope.status', [
                        'sealed',
                        'claimed',
                        'handed_off',
                        'erased',
                        'expired',
                    ])
                    ->orWhere('envelope.envelope_version', '<', 1)),
            'envelope.id',
            $sampleLimit,
        );
        $this->addQueryIssue(
            $issues,
            'event_waitlist_offer_envelope_crypto_evidence_invalid',
            'critical',
            DB::table('event_waitlist_offer_envelopes as envelope')
                ->when($tenantId !== null, fn (Builder $q) => $q->where('envelope.tenant_id', $tenantId))
                ->where(fn (Builder $q) => $q
                    ->whereNull('envelope.cipher_version')
                    ->orWhere('envelope.cipher_version', '')
                    ->orWhereNull('envelope.key_version')
                    ->orWhere('envelope.key_version', '')
                    ->orWhereRaw('CHAR_LENGTH(envelope.key_fingerprint) <> 64')
                    ->orWhereRaw('CHAR_LENGTH(envelope.aad_hash) <> 64')),
            'envelope.id',
            $sampleLimit,
        );
        $this->addQueryIssue(
            $issues,
            'event_waitlist_offer_envelope_live_secret_invalid',
            'critical',
            DB::table('event_waitlist_offer_envelopes as envelope')
                ->when($tenantId !== null, fn (Builder $q) => $q->where('envelope.tenant_id', $tenantId))
                ->where(function (Builder $q): void {
                    $q->where(function (Builder $sealed): void {
                        $sealed->where('envelope.status', 'sealed')
                            ->where(fn (Builder $invalid) => $invalid
                                ->whereNull('envelope.token_ciphertext')
                                ->orWhereNotNull('envelope.claim_token_hash')
                                ->orWhereNotNull('envelope.claimed_by')
                                ->orWhereNotNull('envelope.claimed_at'));
                    })->orWhere(function (Builder $claimed): void {
                        $claimed->where('envelope.status', 'claimed')
                            ->where(fn (Builder $invalid) => $invalid
                                ->whereNull('envelope.token_ciphertext')
                                ->orWhereNull('envelope.claim_token_hash')
                                ->orWhereRaw('CHAR_LENGTH(envelope.claim_token_hash) <> 64')
                                ->orWhereNull('envelope.claimed_by')
                                ->orWhereNull('envelope.claimed_at'));
                    });
                }),
            'envelope.id',
            $sampleLimit,
        );
        $this->addQueryIssue(
            $issues,
            'event_waitlist_offer_envelope_terminal_secret_retained',
            'critical',
            DB::table('event_waitlist_offer_envelopes as envelope')
                ->when($tenantId !== null, fn (Builder $q) => $q->where('envelope.tenant_id', $tenantId))
                ->whereIn('envelope.status', ['handed_off', 'erased', 'expired'])
                ->where(fn (Builder $q) => $q
                    ->whereNotNull('envelope.token_ciphertext')
                    ->orWhereNotNull('envelope.claim_token_hash')
                    ->orWhereNull('envelope.erased_at')
                    ->orWhere(fn (Builder $handedOff) => $handedOff
                        ->where('envelope.status', 'handed_off')
                        ->whereNull('envelope.handed_off_at'))),
            'envelope.id',
            $sampleLimit,
        );
        $this->addQueryIssue(
            $issues,
            'event_waitlist_offer_envelope_missing_for_active_offer',
            'critical',
            DB::table('event_waitlist_entries as entry')
                ->leftJoin('event_waitlist_offer_envelopes as envelope', function ($join): void {
                    $join->on('envelope.waitlist_entry_id', '=', 'entry.id')
                        ->on('envelope.tenant_id', '=', 'entry.tenant_id')
                        ->on('envelope.queue_version', '=', 'entry.queue_version');
                })
                ->when($tenantId !== null, fn (Builder $q) => $q->where('entry.tenant_id', $tenantId))
                ->where('entry.queue_state', 'offered')
                ->whereNull('envelope.id'),
            'entry.id',
            $sampleLimit,
        );

        if (! Schema::hasTable('event_waitlist_offer_envelope_access')) {
            return;
        }
        $this->addQueryIssue(
            $issues,
            'event_waitlist_offer_envelope_access_fact_mismatch',
            'critical',
            DB::table('event_waitlist_offer_envelope_access as access')
                ->leftJoin('event_waitlist_offer_envelopes as envelope', 'envelope.id', '=', 'access.envelope_id')
                ->when($tenantId !== null, fn (Builder $q) => $q->where('access.tenant_id', $tenantId))
                ->where(fn (Builder $q) => $q
                    ->whereNull('envelope.id')
                    ->orWhereColumn('envelope.tenant_id', '!=', 'access.tenant_id')
                    ->orWhereColumn('envelope.event_id', '!=', 'access.event_id')
                    ->orWhereColumn('envelope.waitlist_entry_id', '!=', 'access.waitlist_entry_id')
                    ->orWhereColumn('envelope.outbox_id', '!=', 'access.outbox_id')
                    ->orWhereColumn('envelope.queue_version', '!=', 'access.queue_version')),
            'access.id',
            $sampleLimit,
        );

        $latestAccess = DB::table('event_waitlist_offer_envelope_access')
            ->selectRaw('tenant_id, envelope_id, MAX(id) AS access_id')
            ->groupBy('tenant_id', 'envelope_id');
        $this->addQueryIssue(
            $issues,
            'event_waitlist_offer_envelope_access_missing_or_stale',
            'critical',
            DB::table('event_waitlist_offer_envelopes as envelope')
                ->leftJoinSub($latestAccess, 'latest_access', function ($join): void {
                    $join->on('latest_access.envelope_id', '=', 'envelope.id')
                        ->on('latest_access.tenant_id', '=', 'envelope.tenant_id');
                })
                ->leftJoin('event_waitlist_offer_envelope_access as access', 'access.id', '=', 'latest_access.access_id')
                ->when($tenantId !== null, fn (Builder $q) => $q->where('envelope.tenant_id', $tenantId))
                ->where(fn (Builder $q) => $q
                    ->whereNull('access.id')
                    ->orWhereColumn('access.to_status', '!=', 'envelope.status')),
            'envelope.id',
            $sampleLimit,
        );
    }

    /** @param list<array<string,mixed>> $issues */
    private function auditTerminalParticipantState(
        array &$issues,
        ?int $tenantId,
        int $sampleLimit,
    ): void {
        if (Schema::hasTable('event_registrations')) {
            $this->addQueryIssue(
                $issues,
                'terminal_event_active_canonical_registration',
                'critical',
                DB::table('event_registrations as terminal_registration')
                    ->join('events as terminal_event', function ($join): void {
                        $join->on('terminal_event.id', '=', 'terminal_registration.event_id')
                            ->on('terminal_event.tenant_id', '=', 'terminal_registration.tenant_id');
                    })
                    ->when($tenantId !== null, fn (Builder $q) => $q->where('terminal_registration.tenant_id', $tenantId))
                    ->whereIn('terminal_registration.registration_state', ['invited', 'pending', 'confirmed'])
                    ->where(fn (Builder $q) => $this->whereTerminalEvent($q, 'terminal_event')),
                'terminal_registration.id',
                $sampleLimit,
            );
        }
        if (Schema::hasTable('event_waitlist_entries')) {
            $this->addQueryIssue(
                $issues,
                'terminal_event_active_canonical_waitlist',
                'critical',
                DB::table('event_waitlist_entries as terminal_waitlist')
                    ->join('events as terminal_event', function ($join): void {
                        $join->on('terminal_event.id', '=', 'terminal_waitlist.event_id')
                            ->on('terminal_event.tenant_id', '=', 'terminal_waitlist.tenant_id');
                    })
                    ->when($tenantId !== null, fn (Builder $q) => $q->where('terminal_waitlist.tenant_id', $tenantId))
                    ->whereIn('terminal_waitlist.queue_state', ['waiting', 'offered'])
                    ->where(fn (Builder $q) => $this->whereTerminalEvent($q, 'terminal_event')),
                'terminal_waitlist.id',
                $sampleLimit,
            );
        }
        if (Schema::hasTable('event_waitlist_offer_envelopes')) {
            $this->addQueryIssue(
                $issues,
                'terminal_event_live_offer_envelope',
                'critical',
                DB::table('event_waitlist_offer_envelopes as terminal_envelope')
                    ->join('events as terminal_event', function ($join): void {
                        $join->on('terminal_event.id', '=', 'terminal_envelope.event_id')
                            ->on('terminal_event.tenant_id', '=', 'terminal_envelope.tenant_id');
                    })
                    ->when($tenantId !== null, fn (Builder $q) => $q->where('terminal_envelope.tenant_id', $tenantId))
                    ->whereIn('terminal_envelope.status', ['sealed', 'claimed'])
                    ->whereNotNull('terminal_envelope.token_ciphertext')
                    ->where(fn (Builder $q) => $this->whereTerminalEvent($q, 'terminal_event')),
                'terminal_envelope.id',
                $sampleLimit,
            );
        }
        if (Schema::hasTable('event_reminders')) {
            $this->addQueryIssue(
                $issues,
                'terminal_event_pending_reminder',
                'critical',
                DB::table('event_reminders as terminal_reminder')
                    ->join('events as terminal_event', function ($join): void {
                        $join->on('terminal_event.id', '=', 'terminal_reminder.event_id')
                            ->on('terminal_event.tenant_id', '=', 'terminal_reminder.tenant_id');
                    })
                    ->when($tenantId !== null, fn (Builder $q) => $q->where('terminal_reminder.tenant_id', $tenantId))
                    ->where('terminal_reminder.status', 'pending')
                    ->where(fn (Builder $q) => $this->whereTerminalEvent($q, 'terminal_event')),
                'terminal_reminder.id',
                $sampleLimit,
            );
        }

        if (Schema::hasTable('event_rsvps')) {
            $this->addQueryIssue(
                $issues,
                'terminal_event_active_legacy_registration',
                'critical',
                DB::table('event_rsvps as terminal_rsvp')
                    ->join('events as terminal_event', function ($join): void {
                        $join->on('terminal_event.id', '=', 'terminal_rsvp.event_id')
                            ->on('terminal_event.tenant_id', '=', 'terminal_rsvp.tenant_id');
                    })
                    ->when($tenantId !== null, fn (Builder $q) => $q->where('terminal_rsvp.tenant_id', $tenantId))
                    ->whereIn('terminal_rsvp.status', ['going', 'interested', 'maybe', 'invited', 'waitlisted'])
                    ->where(fn (Builder $q) => $this->whereLegacyTerminalEvent($q, 'terminal_event')),
                'terminal_rsvp.id',
                $sampleLimit,
            );
        }
        if (Schema::hasTable('event_waitlist')) {
            $this->addQueryIssue(
                $issues,
                'terminal_event_active_legacy_waitlist',
                'critical',
                DB::table('event_waitlist as terminal_legacy_waitlist')
                    ->join('events as terminal_event', function ($join): void {
                        $join->on('terminal_event.id', '=', 'terminal_legacy_waitlist.event_id')
                            ->on('terminal_event.tenant_id', '=', 'terminal_legacy_waitlist.tenant_id');
                    })
                    ->when($tenantId !== null, fn (Builder $q) => $q->where('terminal_legacy_waitlist.tenant_id', $tenantId))
                    ->where('terminal_legacy_waitlist.status', 'waiting')
                    ->where(fn (Builder $q) => $this->whereLegacyTerminalEvent($q, 'terminal_event')),
                'terminal_legacy_waitlist.id',
                $sampleLimit,
            );
        }
    }

    private function whereTerminalEvent(Builder $query, string $alias): void
    {
        $query->where("{$alias}.operational_status", 'cancelled')
            ->orWhere("{$alias}.publication_status", 'archived')
            ->orWhere(fn (Builder $legacy) => $this->whereLegacyTerminalEvent($legacy, $alias));
    }

    private function whereLegacyTerminalEvent(Builder $query, string $alias): void
    {
        $query->where(fn (Builder $publication) => $publication
            ->whereNull("{$alias}.publication_status")
            ->orWhere("{$alias}.publication_status", ''))
            ->where(fn (Builder $operational) => $operational
                ->whereNull("{$alias}.operational_status")
                ->orWhere("{$alias}.operational_status", ''))
            ->where("{$alias}.status", 'cancelled');
    }

    /** @param list<array<string,mixed>> $issues */
    private function auditActorTenant(
        array &$issues,
        string $table,
        string $alias,
        string $actorColumn,
        ?int $tenantId,
        int $sampleLimit,
    ): void {
        $this->addQueryIssue(
            $issues,
            "{$table}_actor_tenant_mismatch",
            'critical',
            DB::table("{$table} as {$alias}")
                ->leftJoin('users as ledger_actor', 'ledger_actor.id', '=', "{$alias}.{$actorColumn}")
                ->when($tenantId !== null, fn (Builder $q) => $q->where("{$alias}.tenant_id", $tenantId))
                ->whereNotNull("{$alias}.{$actorColumn}")
                ->where(fn (Builder $q) => $q
                    ->whereNull('ledger_actor.id')
                    ->orWhereColumn('ledger_actor.tenant_id', '!=', "{$alias}.tenant_id")),
            "{$alias}.id",
            $sampleLimit,
        );
    }

    /**
     * Audit calendar capabilities without ever selecting or sampling a token
     * hash/prefix. Findings expose only aggregate counts and internal row IDs.
     *
     * @param list<array<string,mixed>> $issues
     */
    private function auditCalendarFeedTokens(
        array &$issues,
        ?int $tenantId,
        int $sampleLimit,
    ): void {
        if (! Schema::hasTable('event_calendar_feed_tokens')) {
            return;
        }

        $this->addQueryIssue(
            $issues,
            'event_calendar_feed_token_owner_mismatch',
            'critical',
            DB::table('event_calendar_feed_tokens as calendar_token')
                ->leftJoin('tenants as token_tenant', 'token_tenant.id', '=', 'calendar_token.tenant_id')
                ->leftJoin('users as token_user', 'token_user.id', '=', 'calendar_token.user_id')
                ->when($tenantId !== null, fn (Builder $q) => $q->where('calendar_token.tenant_id', $tenantId))
                ->where(fn (Builder $q) => $q
                    ->whereNull('token_tenant.id')
                    ->orWhereNull('token_user.id')
                    ->orWhereColumn('token_user.tenant_id', '!=', 'calendar_token.tenant_id')),
            'calendar_token.id',
            $sampleLimit,
        );
        $this->addQueryIssue(
            $issues,
            'event_calendar_feed_token_evidence_invalid',
            'critical',
            DB::table('event_calendar_feed_tokens as calendar_token')
                ->when($tenantId !== null, fn (Builder $q) => $q->where('calendar_token.tenant_id', $tenantId))
                ->where(fn (Builder $q) => $q
                    ->whereRaw('CHAR_LENGTH(calendar_token.token_hash) <> 64')
                    ->orWhereRaw("calendar_token.token_hash NOT REGEXP '^[0-9a-f]{64}$'")
                    ->orWhereRaw('CHAR_LENGTH(calendar_token.token_prefix) <> 12')
                    ->orWhereRaw("calendar_token.token_prefix NOT REGEXP '^nxc_[0-9a-f]{8}$'")
                    ->orWhereNotIn('calendar_token.locale', [
                        'en', 'ga', 'de', 'fr', 'it', 'pt', 'es', 'nl', 'pl', 'ja', 'ar',
                    ])),
            'calendar_token.id',
            $sampleLimit,
        );

        $maximum = max(1, min(100, (int) config(
            'events.calendar.max_active_feed_tokens',
            10,
        )));
        $overLimit = DB::table('event_calendar_feed_tokens as calendar_token')
            ->select(['calendar_token.tenant_id', 'calendar_token.user_id'])
            ->when($tenantId !== null, fn (Builder $q) => $q->where('calendar_token.tenant_id', $tenantId))
            ->whereNull('calendar_token.revoked_at')
            ->groupBy('calendar_token.tenant_id', 'calendar_token.user_id')
            ->havingRaw('COUNT(*) > ?', [$maximum]);
        $count = DB::query()->fromSub(clone $overLimit, 'calendar_token_over_limit')->count();
        if ($count > 0) {
            $issues[] = [
                'code' => 'event_calendar_feed_token_active_limit_exceeded',
                'severity' => 'critical',
                'count' => $count,
                'sample_ids' => (clone $overLimit)
                    ->limit($sampleLimit)
                    ->pluck('calendar_token.user_id')
                    ->map(fn ($id): int => (int) $id)
                    ->all(),
            ];
        }
    }

    /** @param list<array<string,mixed>> $issues */
    private function auditCapacityClaims(array &$issues, ?int $tenantId, int $sampleLimit): void
    {
        if (! Schema::hasTable('event_registrations')) {
            return;
        }

        $claims = DB::table('event_registrations as registration')
            ->select([
                'registration.tenant_id',
                'registration.event_id',
                'registration.capacity_pool_key',
                'registration.user_id',
            ])
            ->where('registration.registration_state', 'confirmed');
        if (Schema::hasTable('event_rsvps')) {
            $defaultPool = trim((string) config(
                'events.registration.default_capacity_pool_key',
                'event',
            ));
            $defaultPool = $defaultPool === '' ? 'event' : $defaultPool;
            $legacy = DB::table('event_rsvps as rsvp')
                ->selectRaw(
                    'rsvp.tenant_id, rsvp.event_id, ? as capacity_pool_key, rsvp.user_id',
                    [$defaultPool],
                )
                ->whereIn('rsvp.status', ['going', 'attended']);
            $claims->union($legacy);
        }
        if (Schema::hasTable('event_waitlist_entries')) {
            $offers = DB::table('event_waitlist_entries as offered')
                ->select([
                    'offered.tenant_id',
                    'offered.event_id',
                    'offered.capacity_pool_key',
                    'offered.user_id',
                ])
                ->where('offered.queue_state', 'offered')
                ->where('offered.offer_expires_at', '>', now());
            $claims->union($offers);
        }

        $overbooked = DB::query()
            ->fromSub($claims, 'capacity_claim')
            ->join('events as capacity_event', function ($join): void {
                $join->on('capacity_event.id', '=', 'capacity_claim.event_id')
                    ->on('capacity_event.tenant_id', '=', 'capacity_claim.tenant_id');
            })
            ->when($tenantId !== null, fn (Builder $q) => $q->where('capacity_claim.tenant_id', $tenantId))
            ->whereNotNull('capacity_event.max_attendees')
            ->where('capacity_event.max_attendees', '>', 0)
            ->groupBy(
                'capacity_claim.tenant_id',
                'capacity_claim.event_id',
                'capacity_claim.capacity_pool_key',
                'capacity_event.max_attendees',
            )
            ->havingRaw('COUNT(DISTINCT capacity_claim.user_id) > capacity_event.max_attendees');
        $count = DB::query()
            ->fromSub((clone $overbooked)->selectRaw('MIN(capacity_claim.event_id) AS sample_id'), 'overbooked')
            ->count();
        if ($count === 0) {
            return;
        }
        $issues[] = [
            'code' => 'event_capacity_overbooked',
            'severity' => 'critical',
            'count' => $count,
            'sample_ids' => (clone $overbooked)
                ->selectRaw('MIN(capacity_claim.event_id) AS sample_id')
                ->limit($sampleLimit)
                ->pluck('sample_id')
                ->map(fn ($id): int => (int) $id)
                ->all(),
        ];
    }

    /** @param list<array<string,mixed>> $issues */
    private function addInvalidTimezoneIssue(array &$issues, ?int $tenantId, int $sampleLimit): void
    {
        $validTimezones = array_fill_keys(DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true);
        $validTimezones['UTC'] = true;
        $count = 0;
        $sampleIds = [];

        foreach ($this->events($tenantId)
            ->select(['e.id', 'e.timezone'])
            ->whereNotNull('e.timezone')
            ->where('e.timezone', '!=', '')
            ->orderBy('e.id')
            ->cursor() as $event) {
            if (isset($validTimezones[(string) $event->timezone])) {
                continue;
            }
            $count++;
            if (count($sampleIds) < $sampleLimit) {
                $sampleIds[] = (int) $event->id;
            }
        }

        if ($count > 0) {
            $issues[] = [
                'code' => 'event_timezone_invalid',
                'severity' => 'critical',
                'count' => $count,
                'sample_ids' => $sampleIds,
            ];
        }
    }

    /** @param list<array<string,mixed>> $issues */
    private function auditChildTable(array &$issues, string $table, ?int $tenantId, int $sampleLimit): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        $alias = 'child';
        $base = DB::table("{$table} as {$alias}")
            ->leftJoin('events as child_event', 'child_event.id', '=', "{$alias}.event_id")
            ->leftJoin('users as child_user', 'child_user.id', '=', "{$alias}.user_id")
            ->when($tenantId !== null, fn (Builder $q) => $q->where("{$alias}.tenant_id", $tenantId));

        $this->addQueryIssue(
            $issues,
            "{$table}_orphan_or_tenant_mismatch",
            'critical',
            $base->where(function (Builder $q) use ($alias): void {
                $q->whereNull('child_event.id')
                    ->orWhereNull('child_user.id')
                    ->orWhereColumn('child_event.tenant_id', '!=', "{$alias}.tenant_id")
                    ->orWhereColumn('child_user.tenant_id', '!=', "{$alias}.tenant_id");
            }),
            "{$alias}.id",
            $sampleLimit,
        );
    }

    /** @param list<array<string,mixed>> $issues */
    private function addQueryIssue(
        array &$issues,
        string $code,
        string $severity,
        Builder $query,
        string $idColumn,
        int $sampleLimit,
    ): void {
        $count = (clone $query)->distinct()->count($idColumn);
        if ($count === 0) {
            return;
        }

        $issues[] = [
            'code' => $code,
            'severity' => $severity,
            'count' => $count,
            'sample_ids' => (clone $query)
                ->select($idColumn)
                ->distinct()
                ->limit($sampleLimit)
                ->pluck($idColumn)
                ->map(fn ($id) => (int) $id)
                ->all(),
        ];
    }
}
