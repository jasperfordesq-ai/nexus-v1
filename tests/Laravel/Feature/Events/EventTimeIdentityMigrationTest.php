<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

final class EventTimeIdentityMigrationTest extends TestCase
{
    use DatabaseTransactions;

    private const MIGRATION = '2026_07_11_000013_add_event_time_identity_foundation.php';

    public function test_expand_only_columns_are_nullable_and_occurrence_index_is_tenant_scoped(): void
    {
        foreach ([
            'timezone',
            'timezone_source',
            'all_day',
            'occurrence_key',
            'recurrence_engine',
            'recurrence_engine_version',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('events', $column), "Missing events.{$column}");
            $definition = DB::selectOne('SHOW COLUMNS FROM `events` WHERE Field = ?', [$column]);
            $this->assertNotNull($definition);
            $this->assertSame('YES', $definition->{'Null'}, "events.{$column} must remain nullable during expansion");
        }

        $indexRows = DB::select("SHOW INDEX FROM `events` WHERE Key_name = 'uq_events_tenant_occurrence'");
        $this->assertCount(2, $indexRows);
        $this->assertSame(
            ['tenant_id', 'occurrence_key'],
            array_map(static fn (object $row): string => (string) $row->Column_name, $indexRows),
        );
        $this->assertSame([0, 0], array_map(static fn (object $row): int => (int) $row->Non_unique, $indexRows));
    }

    public function test_backfill_preserves_ids_and_distinguishes_templates_from_concrete_rows(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'general.timezone'],
            ['setting_value' => 'Europe/Paris', 'setting_type' => 'string'],
        );
        $organizer = User::factory()->forTenant($this->testTenantId)->create();

        $standaloneId = $this->insertEvent((int) $organizer->id, ['title' => 'Standalone']);
        $templateId = $this->insertEvent((int) $organizer->id, [
            'title' => 'Legacy template',
            'is_recurring_template' => 1,
        ]);
        $occurrenceId = $this->insertEvent((int) $organizer->id, [
            'title' => 'Legacy concrete occurrence',
            'parent_event_id' => $templateId,
            'occurrence_date' => now()->addWeeks(2)->toDateString(),
        ]);

        $this->migration()->up();

        $standalone = DB::table('events')->where('id', $standaloneId)->first();
        $template = DB::table('events')->where('id', $templateId)->first();
        $occurrence = DB::table('events')->where('id', $occurrenceId)->first();

        $this->assertSame($standaloneId, (int) $standalone->id);
        $this->assertSame("legacy:event:{$standaloneId}", $standalone->occurrence_key);
        $this->assertSame('Europe/Paris', $standalone->timezone);
        $this->assertSame('tenant_setting', $standalone->timezone_source);
        $this->assertSame(0, (int) $standalone->all_day);
        $this->assertNull($standalone->recurrence_engine);
        $this->assertNull($standalone->recurrence_engine_version);

        $this->assertSame($templateId, (int) $template->id);
        $this->assertNull($template->occurrence_key);
        $this->assertSame('legacy', $template->recurrence_engine);
        $this->assertSame('1', $template->recurrence_engine_version);

        $this->assertSame($occurrenceId, (int) $occurrence->id);
        $this->assertSame("legacy:event:{$occurrenceId}", $occurrence->occurrence_key);
        $this->assertSame('legacy', $occurrence->recurrence_engine);
        $this->assertSame('1', $occurrence->recurrence_engine_version);
    }

    public function test_invalid_or_missing_tenant_timezone_uses_config_with_auditable_status(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'general.timezone'],
            ['setting_value' => 'Mars/Olympus', 'setting_type' => 'string'],
        );
        DB::table('tenant_settings')
            ->where('tenant_id', 999)
            ->where('setting_key', 'general.timezone')
            ->delete();

        $invalidTenantOrganizer = User::factory()->forTenant($this->testTenantId)->create();
        $missingTenantOrganizer = User::factory()->forTenant(999)->create();
        $invalidId = $this->insertEvent((int) $invalidTenantOrganizer->id, ['title' => 'Invalid zone']);
        $missingId = $this->insertEvent(
            (int) $missingTenantOrganizer->id,
            ['tenant_id' => 999, 'title' => 'Missing zone'],
        );

        config()->set('app.timezone', 'America/New_York');
        $this->migration()->up();

        $invalid = DB::table('events')->where('id', $invalidId)->first();
        $missing = DB::table('events')->where('id', $missingId)->first();

        $this->assertSame('America/New_York', $invalid->timezone);
        $this->assertSame('app_config_invalid_tenant_setting', $invalid->timezone_source);
        $this->assertSame('America/New_York', $missing->timezone);
        $this->assertSame('app_config_missing_tenant_setting', $missing->timezone_source);
    }

    public function test_invalid_tenant_and_application_timezones_fall_back_to_utc_explicitly(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'general.timezone'],
            ['setting_value' => 'Mars/Olympus', 'setting_type' => 'string'],
        );
        $organizer = User::factory()->forTenant($this->testTenantId)->create();
        $eventId = $this->insertEvent((int) $organizer->id, ['title' => 'UTC fallback']);

        config()->set('app.timezone', 'also/not-a-zone');
        $this->migration()->up();

        $event = DB::table('events')->where('id', $eventId)->first();
        $this->assertSame('UTC', $event->timezone);
        $this->assertSame('utc_fallback_invalid_tenant_setting', $event->timezone_source);
    }

    public function test_unique_occurrence_key_allows_cross_tenant_identity_but_rejects_same_tenant_duplicate(): void
    {
        $organizer = User::factory()->forTenant($this->testTenantId)->create();
        $foreignOrganizer = User::factory()->forTenant(999)->create();
        $sharedKey = 'contract:test-cross-tenant-key';

        $this->insertEvent((int) $organizer->id, ['occurrence_key' => $sharedKey]);
        $this->insertEvent((int) $foreignOrganizer->id, [
            'tenant_id' => 999,
            'occurrence_key' => $sharedKey,
        ]);

        $this->expectException(QueryException::class);
        $this->insertEvent((int) $organizer->id, ['occurrence_key' => $sharedKey]);
    }

    private function migration(): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/' . self::MIGRATION);

        return $migration;
    }

    /** @param array<string,mixed> $overrides */
    private function insertEvent(int $organizerId, array $overrides = []): int
    {
        $start = now()->addWeek();

        return (int) DB::table('events')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'user_id' => $organizerId,
            'title' => 'Time identity fixture',
            'description' => 'Event time and occurrence identity migration fixture.',
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'timezone' => null,
            'timezone_source' => null,
            'all_day' => null,
            'occurrence_key' => null,
            'recurrence_engine' => null,
            'recurrence_engine_version' => null,
            'is_recurring_template' => 0,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
