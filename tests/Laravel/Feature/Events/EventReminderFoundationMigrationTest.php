<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

final class EventReminderFoundationMigrationTest extends TestCase
{
    use DatabaseTransactions;

    private const PREFERENCES = '2026_07_11_000050_create_event_notification_preferences_and_reminder_rules.php';
    private const SCHEDULES = '2026_07_11_000051_create_versioned_event_reminder_schedules.php';
    private const BACKFILL = '2026_07_11_000052_backfill_event_reminder_rules_and_schedules.php';

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_schema_exposes_scoped_preferences_versioned_rules_and_due_indexes(): void
    {
        $this->migration(self::PREFERENCES)->up();
        $this->migration(self::SCHEDULES)->up();

        foreach ([
            'event_notification_preferences' => [
                'tenant_id', 'user_id', 'event_id', 'category_id', 'email_enabled',
                'in_app_enabled', 'web_push_enabled', 'fcm_enabled', 'realtime_enabled',
                'cadence', 'reminders_enabled', 'preference_version',
            ],
            'event_reminder_rules' => [
                'tenant_id', 'event_id', 'user_id', 'offset_minutes', 'enabled',
                'email_enabled', 'in_app_enabled', 'web_push_enabled', 'fcm_enabled',
                'realtime_enabled', 'rule_version',
            ],
            'event_reminder_schedules' => [
                'tenant_id', 'event_id', 'user_id', 'rule_id', 'registration_id',
                'offset_minutes', 'rule_version', 'registration_version',
                'event_calendar_sequence', 'schedule_version', 'scheduled_for',
                'deliver_until', 'status', 'reason_code', 'outbox_id', 'queued_at',
                'delivered_at', 'cancelled_at', 'superseded_at',
            ],
        ] as $table => $columns) {
            self::assertTrue(Schema::hasTable($table), "Missing {$table}");
            foreach ($columns as $column) {
                self::assertTrue(Schema::hasColumn($table, $column), "Missing {$table}.{$column}");
            }
        }

        self::assertSame(
            ['tenant_id', 'user_id', 'event_id'],
            $this->indexColumns('event_notification_preferences', 'uq_event_notification_preference_event'),
        );
        self::assertSame(
            ['tenant_id', 'user_id', 'category_id'],
            $this->indexColumns('event_notification_preferences', 'uq_event_notification_preference_category'),
        );
        self::assertSame(
            ['tenant_id', 'event_id', 'user_id', 'offset_minutes'],
            $this->indexColumns('event_reminder_rules', 'uq_event_reminder_rule_offset'),
        );
        self::assertSame(
            ['tenant_id', 'event_id', 'user_id', 'offset_minutes', 'schedule_version'],
            $this->indexColumns('event_reminder_schedules', 'uq_event_reminder_schedule_version'),
        );
        self::assertSame(
            ['tenant_id', 'status', 'scheduled_for', 'id'],
            $this->indexColumns('event_reminder_schedules', 'idx_event_reminder_schedule_due'),
        );
        foreach ([
            'event_notification_preferences' => [
                'chk_event_notification_preference_scope',
                'chk_event_notification_preference_cadence',
            ],
            'event_reminder_rules' => [
                'chk_event_reminder_rule_offset',
            ],
            'event_reminder_schedules' => [
                'chk_event_reminder_schedule_offset',
                'chk_event_reminder_schedule_status',
                'chk_event_reminder_schedule_terminal_timestamps',
            ],
        ] as $table => $expectedChecks) {
            $actual = $this->checkConstraints($table);
            foreach ($expectedChecks as $check) {
                self::assertSame(1, count(array_keys($actual, $check, true)), "Missing or duplicate {$check}");
            }
        }
    }

    public function test_database_checks_reject_invalid_cadence_offsets_status_and_terminal_evidence(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            self::markTestSkipped('Database CHECK enforcement is verified on MariaDB/MySQL.');
        }
        $user = User::factory()->forTenant($this->testTenantId)->create();
        $event = Event::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'is_recurring_template' => false,
        ]);
        $now = now();

        $this->assertQueryRejected(static fn () => DB::table('event_notification_preferences')->insert([
            'tenant_id' => $event->tenant_id,
            'user_id' => $user->id,
            'event_id' => $event->id,
            'category_id' => null,
            'cadence' => 'weekly',
            'preference_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]));
        $this->assertQueryRejected(static fn () => DB::table('event_reminder_rules')->insert([
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'user_id' => $user->id,
            'offset_minutes' => 0,
            'enabled' => true,
            'rule_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        $schedule = [
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'user_id' => $user->id,
            'rule_id' => null,
            'registration_id' => null,
            'offset_minutes' => 60,
            'rule_version' => 0,
            'registration_version' => 0,
            'event_calendar_sequence' => 0,
            'scheduled_for' => $now->copy()->addHour(),
            'deliver_until' => $now->copy()->addHours(2),
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $this->assertQueryRejected(static fn () => DB::table('event_reminder_schedules')->insert([
            ...$schedule,
            'schedule_version' => 1,
            'status' => 'retrying',
        ]));
        $this->assertQueryRejected(static fn () => DB::table('event_reminder_schedules')->insert([
            ...$schedule,
            'schedule_version' => 2,
            'status' => 'delivered',
            'delivered_at' => null,
        ]));
        $this->assertQueryRejected(static fn () => DB::table('event_reminder_schedules')->insert([
            ...$schedule,
            'offset_minutes' => 0,
            'schedule_version' => 3,
            'status' => 'pending',
        ]));
    }

    public function test_preference_scope_check_rejects_zero_or_two_scopes(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            self::markTestSkipped('Database CHECK enforcement is verified on MariaDB/MySQL.');
        }
        $user = User::factory()->forTenant($this->testTenantId)->create();
        $categoryId = (int) DB::table('categories')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'name' => 'Preference scope ' . uniqid(),
            'slug' => 'preference-scope-' . uniqid(),
            'type' => 'event',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $event = Event::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'is_recurring_template' => false,
        ]);

        foreach ([
            ['event_id' => null, 'category_id' => null],
            ['event_id' => $event->id, 'category_id' => $categoryId],
        ] as $scope) {
            try {
                DB::table('event_notification_preferences')->insert([
                    'tenant_id' => $this->testTenantId,
                    'user_id' => $user->id,
                    ...$scope,
                    'preference_version' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                self::fail('Invalid event notification preference scope was accepted.');
            } catch (QueryException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_backfill_maps_legacy_channels_intent_and_delivery_evidence_idempotently(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create();
        $event = Event::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'start_time' => now()->addDays(3),
            'end_time' => now()->addDays(3)->addHour(),
            'calendar_sequence' => 7,
            'is_recurring_template' => false,
        ]);
        $registrationId = (int) DB::table('event_registrations')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $event->id,
            'user_id' => $user->id,
            'capacity_pool_key' => 'event',
            'registration_state' => 'confirmed',
            'registration_version' => 3,
            'state_changed_at' => now(),
            'state_changed_by' => $user->id,
            'confirmed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('event_reminders')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $event->id,
            'user_id' => $user->id,
            'remind_before_minutes' => 10080,
            'reminder_type' => 'both',
            'scheduled_for' => now()->addDay(),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->backfill()->up();
        $this->backfill()->up();

        self::assertDatabaseHas('event_reminder_rules', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $event->id,
            'user_id' => $user->id,
            'offset_minutes' => 10080,
            'email_enabled' => 1,
            'in_app_enabled' => 1,
            'web_push_enabled' => 1,
            'fcm_enabled' => 1,
            'realtime_enabled' => 1,
            'enabled' => 1,
        ]);
        self::assertDatabaseHas('event_notification_preferences', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $event->id,
            'user_id' => $user->id,
            'reminders_enabled' => 1,
        ]);
        self::assertDatabaseHas('event_reminder_schedules', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $event->id,
            'user_id' => $user->id,
            'registration_id' => $registrationId,
            'registration_version' => 3,
            'event_calendar_sequence' => 7,
            'schedule_version' => 1,
            'status' => 'pending',
            'reason_code' => 'legacy_reminder_backfill',
        ]);
        self::assertSame(1, DB::table('event_reminder_rules')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->count());
        self::assertSame(1, DB::table('event_reminder_schedules')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->count());
    }

    private function backfill(): Migration
    {
        return $this->migration(self::BACKFILL);
    }

    private function migration(string $file): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/' . $file);
        return $migration;
    }

    /** @param callable():mixed $write */
    private function assertQueryRejected(callable $write): void
    {
        try {
            $write();
            self::fail('Invalid direct reminder write was accepted.');
        } catch (QueryException) {
            self::assertTrue(true);
        }
    }

    /** @return list<string> */
    private function checkConstraints(string $table): array
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_TYPE', 'CHECK')
            ->orderBy('CONSTRAINT_NAME')
            ->pluck('CONSTRAINT_NAME')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /** @return list<string> */
    private function indexColumns(string $table, string $index): array
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->orderBy('SEQ_IN_INDEX')
            ->pluck('COLUMN_NAME')
            ->map(static fn (mixed $column): string => (string) $column)
            ->all();
    }
}
