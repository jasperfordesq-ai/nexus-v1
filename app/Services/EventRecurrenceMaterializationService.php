<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\TenantContext;
use App\Enums\EventOperationalState;
use App\Enums\EventPublicationState;
use App\Exceptions\EventRecurrenceTraversalLimitException;
use App\Exceptions\EventRecurrenceDefinitionBlueprintException;
use App\Models\Event;
use App\Models\User;
use App\Policies\EventPolicy;
use App\Support\Authorization\AdminTier;
use App\Support\Events\EventLifecycleTransitionContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Throwable;

/** Bounded, tenant-safe rolling materializer for v2 never-ending Event rules. */
class EventRecurrenceMaterializationService
{
    private const ERROR_ACTOR_UNAVAILABLE = 'event_recurrence_actor_unavailable';
    private const ERROR_RULE_INVALID = 'event_recurrence_rule_invalid';
    private const ERROR_SEEK_LIMIT = 'event_recurrence_seek_limit_exceeded';
    private const ERROR_INTERNAL = 'event_recurrence_materialization_failed';

    public function __construct(
        private readonly EventRecurrenceService $recurrence,
        private readonly EventRecurrenceOccurrenceWriter $occurrences,
        private readonly EventLifecycleService $lifecycle,
        private readonly EventPolicy $policy,
    ) {
    }

    /**
     * @return array{
     *   read_only:false,
     *   payload_free:true,
     *   enabled:bool,
     *   configuration_valid:bool,
     *   schema_available:bool,
     *   examined:int,
     *   succeeded:int,
     *   failed:int,
     *   paused:int,
     *   not_due:int,
     *   occurrences_inserted:int,
     *   occurrences_replayed:int,
     *   truncated:int
     * }
     */
    public function materialize(?int $tenantId = null, ?int $limit = null): array
    {
        $configuration = $this->configuration();
        $summary = [
            'read_only' => false,
            'payload_free' => true,
            'enabled' => $configuration['enabled'],
            'configuration_valid' => $configuration['valid'],
            'schema_available' => $this->schemaAvailable(),
            'examined' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'paused' => 0,
            'not_due' => 0,
            'occurrences_inserted' => 0,
            'occurrences_replayed' => 0,
            'truncated' => 0,
        ];
        if (! $summary['enabled'] || ! $configuration['engine_v2_writer_enabled']) {
            return $summary;
        }
        if (! $summary['configuration_valid'] || ! $summary['schema_available']) {
            $summary['failed'] = 1;

            return $summary;
        }
        if ($tenantId !== null && $tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant id must be positive.');
        }

        $limit ??= $configuration['series_limit'];
        $limit = max(1, min($limit, $configuration['series_limit']));
        $target = CarbonImmutable::now('UTC')->addDays($configuration['lookahead_days']);
        $dueBefore = $target->subDays($configuration['refresh_margin_days']);
        $retryBefore = CarbonImmutable::now('UTC')->subMinutes($configuration['retry_grace_minutes']);

        $rules = DB::table('event_recurrence_rules as rule')
            ->join('events as root', function ($join): void {
                $join->on('root.id', '=', 'rule.event_id')
                    ->on('root.tenant_id', '=', 'rule.tenant_id');
            })
            ->join('tenants as tenant', 'tenant.id', '=', 'rule.tenant_id')
            ->where('tenant.is_active', 1)
            ->where('rule.recurrence_engine', EventRecurrenceService::ENGINE)
            ->where('rule.recurrence_engine_version', EventRecurrenceService::ENGINE_VERSION)
            ->where('rule.ends_type', 'never')
            ->where('root.is_recurring_template', 1)
            ->whereIn('root.publication_status', [
                EventPublicationState::Draft->value,
                EventPublicationState::Published->value,
            ])
            // Postponed roots are an intentional pause. Excluding them before
            // the bounded LIMIT prevents a large paused set starving due work.
            ->where('root.operational_status', EventOperationalState::Scheduled->value)
            ->when($tenantId !== null, static fn (Builder $query) => $query->where('rule.tenant_id', $tenantId))
            ->where(function (Builder $due) use ($dueBefore): void {
                $due->whereNotNull('rule.materialization_resume_at')
                    ->orWhereNull('rule.materialized_through_at')
                    ->orWhere('rule.materialized_through_at', '<', $dueBefore);
            })
            ->where(function (Builder $retry) use ($retryBefore): void {
                $retry->whereNull('rule.materialization_error_code')
                    ->orWhereNull('rule.materialization_last_attempted_at')
                    ->orWhere('rule.materialization_last_attempted_at', '<=', $retryBefore);
            })
            ->orderByRaw('rule.materialization_resume_at IS NULL')
            ->orderBy('rule.materialized_through_at')
            ->orderBy('rule.tenant_id')
            ->orderBy('rule.id')
            ->limit($limit)
            ->get(['rule.id', 'rule.tenant_id', 'rule.event_id']);

        foreach ($rules as $candidate) {
            $summary['examined']++;
            $result = $this->materializeRule(
                (int) $candidate->tenant_id,
                (int) $candidate->event_id,
                (int) $candidate->id,
                $configuration,
                $target,
                $dueBefore,
                $retryBefore,
            );
            $status = $result['status'];
            if (array_key_exists($status, $summary)) {
                $summary[$status]++;
            }
            $summary['occurrences_inserted'] += $result['inserted'];
            $summary['occurrences_replayed'] += $result['replayed'];
            if ($result['truncated']) {
                $summary['truncated']++;
            }
        }

        return $summary;
    }

    /**
     * @return array{
     *   enabled:bool,
     *   engine_v2_writer_enabled:bool,
     *   valid:bool,
     *   lookahead_days:int,
     *   refresh_margin_days:int,
     *   overdue_grace_hours:int,
     *   retry_grace_minutes:int,
     *   repair_lookback_days:int,
     *   series_limit:int,
     *   occurrence_limit:int,
     *   scan_limit:int
     * }
     */
    public function configuration(): array
    {
        $enabled = config('events.recurrence.materialization.enabled', false);
        $writerEnabled = config('events.recurrence.engine_v2_enabled', false);
        $settings = [
            'enabled' => is_bool($enabled) ? $enabled : false,
            'engine_v2_writer_enabled' => is_bool($writerEnabled) ? $writerEnabled : false,
            'lookahead_days' => (int) config('events.recurrence.materialization.lookahead_days', 365),
            'refresh_margin_days' => (int) config('events.recurrence.materialization.refresh_margin_days', 30),
            'overdue_grace_hours' => (int) config('events.recurrence.materialization.overdue_grace_hours', 6),
            'retry_grace_minutes' => (int) config('events.recurrence.materialization.retry_grace_minutes', 15),
            'repair_lookback_days' => (int) config('events.recurrence.materialization.repair_lookback_days', 30),
            'series_limit' => (int) config('events.recurrence.materialization.series_limit', 50),
            'occurrence_limit' => (int) config('events.recurrence.materialization.occurrence_limit', 500),
            'scan_limit' => (int) config('events.recurrence.materialization.scan_limit', 2000),
        ];
        $settings['valid'] = is_bool($enabled)
            && is_bool($writerEnabled)
            && $settings['lookahead_days'] >= 30
            && $settings['lookahead_days'] <= 3650
            && $settings['refresh_margin_days'] >= 1
            && $settings['refresh_margin_days'] < $settings['lookahead_days']
            && $settings['overdue_grace_hours'] >= 1
            && $settings['overdue_grace_hours'] <= 168
            && $settings['retry_grace_minutes'] >= 1
            && $settings['retry_grace_minutes'] <= 1440
            && $settings['repair_lookback_days'] >= 1
            && $settings['repair_lookback_days'] <= 365
            && $settings['series_limit'] >= 1
            && $settings['series_limit'] <= 500
            && $settings['occurrence_limit'] >= 1
            && $settings['occurrence_limit'] <= 5000
            && $settings['scan_limit'] > $settings['occurrence_limit']
            && $settings['scan_limit'] <= 100_000;

        /** @var array{enabled:bool,engine_v2_writer_enabled:bool,valid:bool,lookahead_days:int,refresh_margin_days:int,overdue_grace_hours:int,retry_grace_minutes:int,repair_lookback_days:int,series_limit:int,occurrence_limit:int,scan_limit:int} $settings */
        return $settings;
    }

    public function schemaAvailable(): bool
    {
        if (! Schema::hasTable('events')
            || ! Schema::hasTable('event_recurrence_rules')
            || ! Schema::hasTable('event_recurrence_revisions')
            || ! Schema::hasTable('event_recurrence_occurrence_ledger')) {
            return false;
        }

        foreach ([
            'recurrence_id',
            'is_recurrence_exception',
            'recurrence_override_fields',
            'recurrence_override_version',
        ] as $eventColumn) {
            if (! Schema::hasColumn('events', $eventColumn)) {
                return false;
            }
        }

        foreach ([
            'materialized_through_at',
            'materialization_resume_at',
            'materialization_last_attempted_at',
            'materialization_last_succeeded_at',
            'materialization_last_failed_at',
            'materialization_error_code',
            'materialization_truncated',
            'effective_revision_version',
            'materialized_set_version',
        ] as $column) {
            if (! Schema::hasColumn('event_recurrence_rules', $column)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{enabled:bool,engine_v2_writer_enabled:bool,valid:bool,lookahead_days:int,refresh_margin_days:int,overdue_grace_hours:int,retry_grace_minutes:int,repair_lookback_days:int,series_limit:int,occurrence_limit:int,scan_limit:int} $configuration
     * @return array{status:string,inserted:int,replayed:int,truncated:bool}
     */
    private function materializeRule(
        int $tenantId,
        int $rootId,
        int $ruleId,
        array $configuration,
        CarbonImmutable $target,
        CarbonImmutable $dueBefore,
        CarbonImmutable $retryBefore,
    ): array {
        try {
            return TenantContext::runForTenant(
                $tenantId,
                fn (): array => DB::transaction(function () use (
                    $tenantId,
                    $rootId,
                    $ruleId,
                    $configuration,
                    $target,
                    $dueBefore,
                    $retryBefore,
                ): array {
                    $root = DB::table('events')
                        ->where('tenant_id', $tenantId)
                        ->where('id', $rootId)
                        ->lockForUpdate()
                        ->first();
                    $rule = DB::table('event_recurrence_rules')
                        ->where('tenant_id', $tenantId)
                        ->where('event_id', $rootId)
                        ->where('id', $ruleId)
                        ->lockForUpdate()
                        ->first();
                    if ($root === null || $rule === null || ! (bool) $root->is_recurring_template) {
                        throw new LogicException(self::ERROR_RULE_INVALID);
                    }
                    $activeTenant = DB::table('tenants')
                        ->where('id', $tenantId)
                        ->where('is_active', 1)
                        ->exists();
                    if (! $activeTenant || ! TenantContext::hasFeature('events')) {
                        return ['status' => 'paused', 'inserted' => 0, 'replayed' => 0, 'truncated' => false];
                    }
                    if ((string) $root->recurrence_engine !== EventRecurrenceService::ENGINE
                        || (string) $root->recurrence_engine_version !== EventRecurrenceService::ENGINE_VERSION
                        || (string) $rule->recurrence_engine !== EventRecurrenceService::ENGINE
                        || (string) $rule->recurrence_engine_version !== EventRecurrenceService::ENGINE_VERSION
                        || (string) $rule->ends_type !== 'never'
                        || trim((string) $rule->rrule) === '') {
                        throw new LogicException(self::ERROR_RULE_INVALID);
                    }
                    if ((string) $root->operational_status === EventOperationalState::Postponed->value) {
                        return ['status' => 'paused', 'inserted' => 0, 'replayed' => 0, 'truncated' => false];
                    }
                    if (! in_array((string) $root->publication_status, [
                        EventPublicationState::Draft->value,
                        EventPublicationState::Published->value,
                    ], true) || (string) $root->operational_status !== EventOperationalState::Scheduled->value) {
                        return ['status' => 'paused', 'inserted' => 0, 'replayed' => 0, 'truncated' => false];
                    }
                    if (! $this->isDue($rule, $dueBefore, $retryBefore)) {
                        return ['status' => 'not_due', 'inserted' => 0, 'replayed' => 0, 'truncated' => false];
                    }

                    $attemptedAt = CarbonImmutable::now('UTC');
                    DB::table('event_recurrence_rules')
                        ->where('tenant_id', $tenantId)
                        ->where('id', $ruleId)
                        ->update(['materialization_last_attempted_at' => $attemptedAt]);

                    /** @var Event|null $rootModel */
                    $rootModel = Event::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->whereKey($rootId)
                        ->first();
                    if ($rootModel === null) {
                        throw new LogicException(self::ERROR_RULE_INVALID);
                    }
                    $actor = $this->permissionedActor($rootModel);
                    if ($actor === null) {
                        throw new LogicException(self::ERROR_ACTOR_UNAVAILABLE);
                    }

                    $utc = new \DateTimeZone('UTC');
                    $rootStart = new \DateTimeImmutable((string) $root->start_time, $utc);
                    $rootEnd = $root->end_time !== null
                        ? new \DateTimeImmutable((string) $root->end_time, $utc)
                        : null;
                    $windowStart = $rule->materialization_resume_at !== null
                        ? new \DateTimeImmutable((string) $rule->materialization_resume_at, $utc)
                        : ($rule->materialized_through_at !== null
                            ? (new \DateTimeImmutable((string) $rule->materialized_through_at, $utc))
                                ->modify('-' . $configuration['refresh_margin_days'] . ' days')
                            // A migrated rule without a watermark must not
                            // recreate an unbounded historical archive. Recent
                            // holes are repairable; older gaps remain visible to
                            // the integrity/ledger audit for deliberate repair.
                            : CarbonImmutable::now('UTC')
                                ->subDays($configuration['repair_lookback_days'])
                                ->toDateTimeImmutable());
                    if ($windowStart < $rootStart) {
                        $windowStart = $rootStart;
                    }

                    if ($rootStart > $target) {
                        $window = [
                            'occurrences' => [],
                            'truncated' => false,
                            'evaluated_through_utc' => $target->format('Y-m-d H:i:s'),
                            'resume_at_utc' => null,
                        ];
                    } else {
                        $window = $this->recurrence->expandWindow(
                            $rootStart,
                            $rootEnd,
                            (string) ($root->timezone ?: 'UTC'),
                            (string) $rule->rrule,
                            $windowStart,
                            $target,
                            $this->decodeDateList($rule->exdates ?? null),
                            $this->decodeDateList($rule->rdates ?? null),
                            $configuration['occurrence_limit'],
                            $configuration['scan_limit'],
                        );
                    }

                    $inserted = 0;
                    $replayed = 0;
                    foreach ($window['occurrences'] as $occurrence) {
                        $write = $this->occurrences->insert(
                            $root,
                            $occurrence,
                            (int) ($rule->effective_revision_version ?? 0) ?: null,
                        );
                        if (! $write['inserted']) {
                            $replayed++;
                            continue;
                        }
                        $inserted++;
                        $publication = EventPublicationState::from((string) $root->publication_status);
                        if ($publication === EventPublicationState::Draft) {
                            continue;
                        }
                        $this->lifecycle->transition(
                            $write['id'],
                            $actor,
                            $publication,
                            null,
                            null,
                            null,
                            new EventLifecycleTransitionContext(
                                $rootId,
                                true,
                                [
                                    'materialization' => [
                                        'source' => 'rolling_recurrence',
                                        'recurrence_id' => $occurrence['recurrence_id'],
                                    ],
                                ],
                            ),
                        );
                    }

                    DB::table('event_recurrence_rules')
                        ->where('tenant_id', $tenantId)
                        ->where('id', $ruleId)
                        ->update([
                            'materialized_through_at' => $window['evaluated_through_utc'],
                            'materialization_resume_at' => $window['resume_at_utc'],
                            'materialization_last_succeeded_at' => CarbonImmutable::now('UTC'),
                            'materialization_error_code' => null,
                            'materialization_truncated' => $window['truncated'] ? 1 : 0,
                            'updated_at' => CarbonImmutable::now('UTC'),
                        ]);

                    return [
                        'status' => 'succeeded',
                        'inserted' => $inserted,
                        'replayed' => $replayed,
                        'truncated' => (bool) $window['truncated'],
                    ];
                }, 3),
            );
        } catch (Throwable $exception) {
            $errorCode = $this->safeErrorCode($exception);
            $this->recordFailure($tenantId, $rootId, $ruleId, $errorCode);
            Log::error('Event recurrence rolling materialization failed', [
                'tenant_id' => $tenantId,
                'root_event_id' => $rootId,
                'rule_id' => $ruleId,
                'reason_code' => $errorCode,
                'exception' => $exception::class,
            ]);

            return ['status' => 'failed', 'inserted' => 0, 'replayed' => 0, 'truncated' => false];
        }
    }

    private function isDue(object $rule, CarbonImmutable $dueBefore, CarbonImmutable $retryBefore): bool
    {
        if ($rule->materialization_error_code !== null
            && $rule->materialization_last_attempted_at !== null
            && CarbonImmutable::parse((string) $rule->materialization_last_attempted_at, 'UTC') > $retryBefore) {
            return false;
        }
        if ($rule->materialization_resume_at !== null) {
            return true;
        }
        if ($rule->materialized_through_at === null) {
            return true;
        }

        return CarbonImmutable::parse((string) $rule->materialized_through_at, 'UTC') < $dueBefore;
    }

    private function permissionedActor(Event $root): ?User
    {
        $tenantId = (int) $root->getAttribute('tenant_id');
        $candidateIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            [
                $root->getAttribute('user_id'),
                $root->getAttribute('publication_status_changed_by'),
                $root->getAttribute('operational_status_changed_by'),
            ],
        ), static fn (int $id): bool => $id > 0)));
        $users = User::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereIn('id', $candidateIds)
            ->get();
        $admins = User::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereNotIn('role', AdminTier::OPERATIONAL_ROLES)
            ->where(function ($admin): void {
                $admin->whereIn('role', AdminTier::ROLES)
                    ->orWhere('is_admin', 1)
                    ->orWhere('is_super_admin', 1)
                    ->orWhere('is_tenant_super_admin', 1)
                    ->orWhere('is_god', 1);
            })
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->filter(static fn (User $user): bool => AdminTier::allows($user));

        foreach ($users->concat($admins)->unique('id') as $candidate) {
            if ($candidate instanceof User && $this->policy->manage($candidate, $root)) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function decodeDateList(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_string($value)) {
            $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        }
        if (! is_array($value)) {
            throw new LogicException(self::ERROR_RULE_INVALID);
        }

        $dates = [];
        foreach ($value as $date) {
            if (! is_string($date)) {
                throw new LogicException(self::ERROR_RULE_INVALID);
            }
            $dates[] = $date;
        }

        return $dates;
    }

    private function safeErrorCode(Throwable $exception): string
    {
        if ($exception instanceof EventRecurrenceTraversalLimitException) {
            return self::ERROR_SEEK_LIMIT;
        }
        if ($exception instanceof EventRecurrenceDefinitionBlueprintException) {
            return $exception->reasonCode;
        }
        if ($exception instanceof LogicException && in_array($exception->getMessage(), [
            self::ERROR_ACTOR_UNAVAILABLE,
            self::ERROR_RULE_INVALID,
        ], true)) {
            return $exception->getMessage();
        }

        return self::ERROR_INTERNAL;
    }

    private function recordFailure(int $tenantId, int $rootId, int $ruleId, string $errorCode): void
    {
        if (! $this->schemaAvailable()) {
            return;
        }
        DB::table('event_recurrence_rules')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $rootId)
            ->where('id', $ruleId)
            ->update([
                'materialization_last_attempted_at' => CarbonImmutable::now('UTC'),
                'materialization_last_failed_at' => CarbonImmutable::now('UTC'),
                'materialization_error_code' => substr($errorCode, 0, 64),
                'updated_at' => CarbonImmutable::now('UTC'),
            ]);
    }
}
