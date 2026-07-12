<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Enums\EventStaffRole;
use App\Exceptions\EventRoleAssignmentException;
use App\Models\User;
use App\Services\EventRoleService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

final class EventRoleMigrationTest extends TestCase
{
    private const MIGRATION = '2026_07_11_000016_add_event_staff_role_foundation.php';

    public function test_schema_exposes_versioned_assignments_and_immutable_history(): void
    {
        foreach ([
            'tenant_id',
            'event_id',
            'user_id',
            'role',
            'status',
            'assignment_version',
            'granted_at',
            'granted_by',
            'revoked_at',
            'revoked_by',
            'expires_at',
        ] as $column) {
            self::assertTrue(Schema::hasColumn('event_staff_assignments', $column));
        }
        foreach ([
            'assignment_id',
            'actor_user_id',
            'assignment_version',
            'action',
            'idempotency_key',
            'from_status',
            'to_status',
            'previous_expires_at',
            'new_expires_at',
            'metadata',
        ] as $column) {
            self::assertTrue(Schema::hasColumn('event_staff_assignment_history', $column));
        }

        $unique = DB::select("SHOW INDEX FROM `event_staff_assignments` WHERE Key_name = 'uq_event_staff_assignment_subject'");
        self::assertSame(
            ['tenant_id', 'event_id', 'user_id', 'role'],
            array_map(static fn (object $row): string => (string) $row->Column_name, $unique),
        );
        $idempotencyUnique = DB::select(
            "SHOW INDEX FROM `event_staff_assignment_history` "
            . "WHERE Key_name = 'uq_event_staff_history_idempotency'",
        );
        self::assertSame(
            ['tenant_id', 'idempotency_key'],
            array_map(static fn (object $row): string => (string) $row->Column_name, $idempotencyUnique),
        );

        if (DB::getDriverName() === 'mysql') {
            foreach (['trg_event_staff_history_no_update', 'trg_event_staff_history_no_delete'] as $trigger) {
                self::assertTrue(DB::table('information_schema.TRIGGERS')
                    ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
                    ->where('TRIGGER_NAME', $trigger)
                    ->exists());
            }
        }
    }

    public function test_empty_migration_roundtrip_and_old_schema_service_compatibility(): void
    {
        self::assertSame(0, DB::table('event_staff_assignment_history')->count());
        self::assertSame(0, DB::table('event_staff_assignments')->count());

        $migration = $this->migration();
        $migration->down();

        try {
            self::assertFalse(Schema::hasTable('event_staff_assignments'));
            self::assertFalse(Schema::hasTable('event_staff_assignment_history'));

            TenantContext::setById($this->testTenantId);
            $service = new EventRoleService();
            $actor = new User();
            $actor->forceFill(['id' => 1, 'tenant_id' => $this->testTenantId]);

            self::assertSame([], $service->capabilitiesForUser(1, 1));
            self::assertTrue($service->list(1, $actor)->isEmpty());

            try {
                $service->grant(1, 1, EventStaffRole::CheckInStaff, $actor);
                self::fail('Mutation must fail closed while the role schema is unavailable.');
            } catch (EventRoleAssignmentException $exception) {
                self::assertSame('event_staff_role_schema_unavailable', $exception->reasonCode);
            }
        } finally {
            $migration->up();
        }

        self::assertTrue(Schema::hasTable('event_staff_assignments'));
        self::assertTrue(Schema::hasTable('event_staff_assignment_history'));
    }

    private function migration(): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/' . self::MIGRATION);

        return $migration;
    }
}
