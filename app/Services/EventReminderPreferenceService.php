<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/** Strict write/read boundary for Event channel overrides and reminder rules. */
final class EventReminderPreferenceService
{
    /** @var list<string> */
    public const CHANNEL_FIELDS = [
        'email_enabled',
        'in_app_enabled',
        'web_push_enabled',
        'fcm_enabled',
        'realtime_enabled',
    ];

    /** @var list<string> */
    public const CADENCES = ['instant', 'daily', 'monthly', 'off'];

    /** @var list<string> */
    private const OVERRIDE_FIELDS = [
        ...self::CHANNEL_FIELDS,
        'cadence',
        'reminders_enabled',
    ];

    /** @var list<string> */
    private const RULE_FIELDS = [
        'offset_minutes',
        'enabled',
        ...self::CHANNEL_FIELDS,
    ];

    /**
     * @return array{
     *   revision:int,
     *   overrides:array<string,bool|string|null>,
     *   rules:list<array<string,int|bool|null>>,
     *   resolved:array<string,mixed>
     * }
     */
    public function eventPreferences(int $eventId, int $userId): array
    {
        $tenantId = $this->tenantId();
        $this->assertEventAndUser($tenantId, $eventId, $userId, false);

        $preference = DB::table('event_notification_preferences')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('event_id', $eventId)
            ->whereNull('category_id')
            ->first();
        $rules = DB::table('event_reminder_rules')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->where('enabled', true)
            ->orderByDesc('offset_minutes')
            ->orderBy('id')
            ->get();

        return [
            'revision' => (int) ($preference->preference_version ?? 0),
            'overrides' => $this->formatOverrides($preference),
            'rules' => $rules->map(fn (object $rule): array => $this->formatRule($rule))->all(),
            'resolved' => EventNotificationPreferenceResolver::resolveForEvent(
                $userId,
                $tenantId,
                $eventId,
            ),
        ];
    }

    /**
     * Replace one event-scoped preference aggregate with optimistic concurrency.
     * Missing overrides mean inheritance; missing rules disable prior rules.
     *
     * @param array<string,mixed> $overrides
     * @param list<array<string,mixed>> $rules
     * @return array{
     *   revision:int,
     *   overrides:array<string,bool|string|null>,
     *   rules:list<array<string,int|bool|null>>,
     *   resolved:array<string,mixed>
     * }
     */
    public function replaceEventPreferences(
        int $eventId,
        int $userId,
        array $overrides,
        array $rules,
        ?int $expectedRevision = null,
    ): array {
        $tenantId = $this->tenantId();
        $normalizedOverrides = $this->normalizeOverrides($overrides);
        $normalizedRules = $this->normalizeRules($rules);
        $this->assertExpectedRevision($expectedRevision);

        DB::transaction(function () use (
            $tenantId,
            $eventId,
            $userId,
            $normalizedOverrides,
            $normalizedRules,
            $expectedRevision,
        ): void {
            $this->assertEventAndUser($tenantId, $eventId, $userId, true);
            $preference = DB::table('event_notification_preferences')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('event_id', $eventId)
                ->whereNull('category_id')
                ->lockForUpdate()
                ->first();
            $currentRevision = (int) ($preference->preference_version ?? 0);
            if ($expectedRevision !== null && $expectedRevision !== $currentRevision) {
                throw new RuntimeException('event_reminder_preference_version_conflict');
            }

            $overrideChanged = $this->formatOverrides($preference) !== $normalizedOverrides;
            $ruleChanged = $this->replaceRulesLocked(
                $tenantId,
                $eventId,
                $userId,
                $normalizedRules,
            );
            if (! $overrideChanged && ! $ruleChanged) {
                return;
            }

            $now = now();
            if ($preference === null) {
                DB::table('event_notification_preferences')->insert([
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'event_id' => $eventId,
                    'category_id' => null,
                    ...$normalizedOverrides,
                    'preference_version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                return;
            }

            DB::table('event_notification_preferences')
                ->where('id', (int) $preference->id)
                ->where('tenant_id', $tenantId)
                ->where('preference_version', $currentRevision)
                ->update([
                    ...$normalizedOverrides,
                    'preference_version' => $currentRevision + 1,
                    'updated_at' => $now,
                ]);
        }, 3);

        return $this->eventPreferences($eventId, $userId);
    }

    /** Reset event-scoped overrides/rules to inherited tenant/category defaults. */
    public function deleteEventPreferences(
        int $eventId,
        int $userId,
        int $expectedRevision,
    ): array {
        $tenantId = $this->tenantId();
        $this->assertExpectedRevision($expectedRevision);

        DB::transaction(function () use ($tenantId, $eventId, $userId, $expectedRevision): void {
            $this->assertEventAndUser($tenantId, $eventId, $userId, true);
            $preference = DB::table('event_notification_preferences')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('event_id', $eventId)
                ->whereNull('category_id')
                ->lockForUpdate()
                ->first();
            $currentRevision = (int) ($preference->preference_version ?? 0);
            if ($expectedRevision !== $currentRevision) {
                throw new RuntimeException('event_reminder_preference_version_conflict');
            }
            $now = now();
            DB::table('event_reminder_rules')
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('user_id', $userId)
                ->where('enabled', true)
                ->update([
                    'enabled' => false,
                    'rule_version' => DB::raw('rule_version + 1'),
                    'updated_at' => $now,
                ]);
            if ($preference !== null) {
                DB::table('event_notification_preferences')
                    ->where('tenant_id', $tenantId)
                    ->where('id', (int) $preference->id)
                    ->delete();
            }
        }, 3);

        return $this->eventPreferences($eventId, $userId);
    }

    /**
     * @param array<string,mixed> $overrides
     * @return array{revision:int,overrides:array<string,bool|string|null>}
     */
    public function replaceCategoryPreference(
        int $categoryId,
        int $userId,
        array $overrides,
        ?int $expectedRevision = null,
    ): array {
        $tenantId = $this->tenantId();
        $normalized = $this->normalizeOverrides($overrides);
        $this->assertExpectedRevision($expectedRevision);

        DB::transaction(function () use (
            $tenantId,
            $categoryId,
            $userId,
            $normalized,
            $expectedRevision,
        ): void {
            $this->assertUser($tenantId, $userId, true);
            $category = DB::table('categories')
                ->where('tenant_id', $tenantId)
                ->where('id', $categoryId)
                ->whereIn('type', ['event', 'events'])
                ->lockForUpdate()
                ->first(['id']);
            if ($category === null) {
                throw new InvalidArgumentException('event_notification_category_not_found');
            }

            $preference = DB::table('event_notification_preferences')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('category_id', $categoryId)
                ->whereNull('event_id')
                ->lockForUpdate()
                ->first();
            $currentRevision = (int) ($preference->preference_version ?? 0);
            if ($expectedRevision !== null && $expectedRevision !== $currentRevision) {
                throw new RuntimeException('event_notification_preference_version_conflict');
            }
            if ($this->formatOverrides($preference) === $normalized) {
                return;
            }

            $now = now();
            if ($preference === null) {
                DB::table('event_notification_preferences')->insert([
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'event_id' => null,
                    'category_id' => $categoryId,
                    ...$normalized,
                    'preference_version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                return;
            }

            DB::table('event_notification_preferences')
                ->where('id', (int) $preference->id)
                ->where('tenant_id', $tenantId)
                ->where('preference_version', $currentRevision)
                ->update([
                    ...$normalized,
                    'preference_version' => $currentRevision + 1,
                    'updated_at' => $now,
                ]);
        }, 3);

        $row = DB::table('event_notification_preferences')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('category_id', $categoryId)
            ->whereNull('event_id')
            ->first();

        return [
            'revision' => (int) ($row->preference_version ?? 0),
            'overrides' => $this->formatOverrides($row),
        ];
    }

    /**
     * @return list<array<string,int|bool|null>>
     */
    public function activeRules(int $eventId, int $userId, bool $lockForUpdate = false): array
    {
        $tenantId = $this->tenantId();
        $query = DB::table('event_reminder_rules')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->where('enabled', true)
            ->orderByDesc('offset_minutes')
            ->orderBy('id');
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get()->map(fn (object $rule): array => $this->formatRule($rule))->all();
    }

    /**
     * @param list<array<string,int|bool|null>> $rules
     */
    private function replaceRulesLocked(
        int $tenantId,
        int $eventId,
        int $userId,
        array $rules,
    ): bool {
        $existing = DB::table('event_reminder_rules')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->get()
            ->keyBy('offset_minutes');
        $desired = collect($rules)->keyBy('offset_minutes');
        $changed = false;
        $now = now();

        foreach ($existing as $offset => $row) {
            $next = $desired->get((int) $offset);
            if ($next === null) {
                if ((bool) $row->enabled) {
                    DB::table('event_reminder_rules')
                        ->where('id', (int) $row->id)
                        ->where('tenant_id', $tenantId)
                        ->update([
                            'enabled' => false,
                            'rule_version' => (int) $row->rule_version + 1,
                            'updated_at' => $now,
                        ]);
                    $changed = true;
                }
                continue;
            }

            $current = $this->formatRule($row);
            if ($this->ruleComparable($current) !== $this->ruleComparable($next)) {
                DB::table('event_reminder_rules')
                    ->where('id', (int) $row->id)
                    ->where('tenant_id', $tenantId)
                    ->update([
                        ...$this->ruleWriteValues($next),
                        'rule_version' => (int) $row->rule_version + 1,
                        'updated_at' => $now,
                    ]);
                $changed = true;
            }
            $desired->forget((int) $offset);
        }

        foreach ($desired->values() as $rule) {
            DB::table('event_reminder_rules')->insert([
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'user_id' => $userId,
                ...$this->ruleWriteValues($rule),
                'rule_version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $changed = true;
        }

        return $changed;
    }

    /** @param array<string,mixed> $overrides @return array<string,bool|string|null> */
    private function normalizeOverrides(array $overrides): array
    {
        $unknown = array_diff(array_keys($overrides), self::OVERRIDE_FIELDS);
        if ($unknown !== []) {
            throw new InvalidArgumentException('event_notification_preference_field_invalid');
        }

        $normalized = [];
        foreach (self::CHANNEL_FIELDS as $field) {
            $normalized[$field] = $this->nullableBoolean($overrides[$field] ?? null, $field);
        }
        $cadence = $overrides['cadence'] ?? null;
        if ($cadence !== null && (! is_string($cadence) || ! in_array($cadence, self::CADENCES, true))) {
            throw new InvalidArgumentException('event_notification_preference_cadence_invalid');
        }
        $normalized['cadence'] = $cadence;
        $normalized['reminders_enabled'] = $this->nullableBoolean(
            $overrides['reminders_enabled'] ?? null,
            'reminders_enabled',
        );

        return $normalized;
    }

    /** @param array<mixed> $rules @return list<array<string,int|bool|null>> */
    private function normalizeRules(array $rules): array
    {
        if (! array_is_list($rules)) {
            throw new InvalidArgumentException('event_reminder_rules_must_be_list');
        }
        $minimum = max(1, (int) config('events.reminders.minimum_offset_minutes', 5));
        $maximum = max($minimum, (int) config('events.reminders.maximum_offset_minutes', 525600));
        $maxRules = max(1, min(50, (int) config('events.reminders.max_rules_per_event', 10)));
        if (count($rules) > $maxRules) {
            throw new InvalidArgumentException('event_reminder_rule_limit_exceeded');
        }
        $seen = [];
        $normalized = [];

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                throw new InvalidArgumentException('event_reminder_rule_invalid');
            }
            if (array_diff(array_keys($rule), self::RULE_FIELDS) !== []) {
                throw new InvalidArgumentException('event_reminder_rule_field_invalid');
            }
            $offset = $rule['offset_minutes'] ?? null;
            if (! is_int($offset) || $offset < $minimum || $offset > $maximum) {
                throw new InvalidArgumentException('event_reminder_rule_offset_invalid');
            }
            if (isset($seen[$offset])) {
                throw new InvalidArgumentException('event_reminder_rule_offset_duplicate');
            }
            $seen[$offset] = true;

            $enabled = $rule['enabled'] ?? true;
            if (! is_bool($enabled)) {
                throw new InvalidArgumentException('event_reminder_rule_enabled_invalid');
            }
            $next = ['offset_minutes' => $offset, 'enabled' => $enabled];
            foreach (self::CHANNEL_FIELDS as $field) {
                $next[$field] = $this->nullableBoolean($rule[$field] ?? null, $field);
            }
            $normalized[] = $next;
        }

        usort(
            $normalized,
            static fn (array $left, array $right): int => $right['offset_minutes'] <=> $left['offset_minutes'],
        );

        return $normalized;
    }

    private function nullableBoolean(mixed $value, string $field): ?bool
    {
        if ($value === null || is_bool($value)) {
            return $value;
        }

        throw new InvalidArgumentException("event_notification_preference_{$field}_invalid");
    }

    /** @return array<string,bool|string|null> */
    private function formatOverrides(?object $row): array
    {
        $formatted = [];
        foreach (self::CHANNEL_FIELDS as $field) {
            $value = $row?->{$field};
            $formatted[$field] = $value === null ? null : (bool) $value;
        }
        $formatted['cadence'] = $row?->cadence === null ? null : (string) $row->cadence;
        $value = $row?->reminders_enabled;
        $formatted['reminders_enabled'] = $value === null ? null : (bool) $value;

        return $formatted;
    }

    /** @return array<string,int|bool|null> */
    private function formatRule(object $rule): array
    {
        $formatted = [
            'id' => (int) $rule->id,
            'offset_minutes' => (int) $rule->offset_minutes,
            'enabled' => (bool) $rule->enabled,
            'rule_version' => (int) $rule->rule_version,
        ];
        foreach (self::CHANNEL_FIELDS as $field) {
            $value = $rule->{$field} ?? null;
            $formatted[$field] = $value === null ? null : (bool) $value;
        }

        return $formatted;
    }

    /** @param array<string,int|bool|null> $rule @return array<string,int|bool|null> */
    private function ruleComparable(array $rule): array
    {
        unset($rule['id'], $rule['rule_version']);
        return $rule;
    }

    /** @param array<string,int|bool|null> $rule @return array<string,int|bool|null> */
    private function ruleWriteValues(array $rule): array
    {
        return [
            'offset_minutes' => (int) $rule['offset_minutes'],
            'enabled' => (bool) $rule['enabled'],
            'email_enabled' => $rule['email_enabled'],
            'in_app_enabled' => $rule['in_app_enabled'],
            'web_push_enabled' => $rule['web_push_enabled'],
            'fcm_enabled' => $rule['fcm_enabled'],
            'realtime_enabled' => $rule['realtime_enabled'],
        ];
    }

    private function assertEventAndUser(int $tenantId, int $eventId, int $userId, bool $lock): void
    {
        $eventQuery = DB::table('events')
            ->where('tenant_id', $tenantId)
            ->where('id', $eventId)
            ->where('is_recurring_template', false);
        if ($lock) {
            $eventQuery->lockForUpdate();
        }
        if ($eventQuery->first(['id']) === null) {
            throw new InvalidArgumentException('event_reminder_concrete_event_not_found');
        }
        $this->assertUser($tenantId, $userId, $lock);
    }

    private function assertUser(int $tenantId, int $userId, bool $lock): void
    {
        $query = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('id', $userId)
            ->where('status', 'active')
            ->whereNull('deleted_at');
        if ($lock) {
            $query->lockForUpdate();
        }
        if ($query->first(['id']) === null) {
            throw new InvalidArgumentException('event_reminder_user_not_found');
        }
    }

    private function assertExpectedRevision(?int $expectedRevision): void
    {
        if ($expectedRevision !== null && $expectedRevision < 0) {
            throw new InvalidArgumentException('event_notification_preference_version_invalid');
        }
    }

    private function tenantId(): int
    {
        $tenantId = TenantContext::currentId();
        if ($tenantId === null || $tenantId <= 0) {
            throw new RuntimeException('event_reminder_tenant_context_missing');
        }

        return $tenantId;
    }
}
