<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

final class EventAgendaEnterpriseMigrationTest extends TestCase
{
    public function test_schema_exposes_capacity_resources_and_immutable_registration_evidence(): void
    {
        self::assertTrue(Schema::hasColumn('event_sessions', 'capacity'));
        foreach ([
            'event_session_resources' => [
                'tenant_id', 'event_id', 'session_id', 'resource_type', 'visibility',
                'title', 'url_ciphertext', 'position', 'created_by', 'updated_by',
            ],
            'event_session_registrations' => [
                'tenant_id', 'event_id', 'session_id', 'user_id', 'event_registration_id',
                'version', 'status', 'registered_at', 'withdrawn_at',
            ],
            'event_session_registration_history' => [
                'tenant_id', 'event_id', 'session_id', 'registration_id', 'user_id',
                'event_registration_id', 'actor_user_id', 'registration_version',
                'action', 'idempotency_key', 'request_hash',
            ],
        ] as $table => $columns) {
            self::assertTrue(Schema::hasTable($table), $table);
            foreach ($columns as $column) {
                self::assertTrue(Schema::hasColumn($table, $column), "{$table}.{$column}");
            }
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ([
            'fk_ev_session_resource_event',
            'fk_ev_session_resource_session',
            'fk_ev_session_reg_event',
            'fk_ev_session_reg_session',
            'fk_ev_session_reg_event_reg',
            'fk_ev_session_reg_hist_event',
            'fk_ev_session_reg_hist_session',
            'fk_ev_session_reg_hist_registration',
            'fk_ev_session_reg_hist_event_reg',
        ] as $constraint) {
            self::assertTrue($this->constraintExists($constraint, 'FOREIGN KEY'), $constraint);
        }
        foreach ([
            'chk_ev_session_capacity',
            'chk_ev_session_resource_type',
            'chk_ev_session_resource_visibility',
            'chk_ev_session_resource_media',
            'chk_ev_session_reg_version',
            'chk_ev_session_reg_status',
            'chk_ev_session_reg_state',
            'chk_ev_session_reg_hist_action',
        ] as $constraint) {
            self::assertTrue($this->constraintExists($constraint, 'CHECK'), $constraint);
        }
        foreach ([
            'trg_ev_session_reg_hist_no_update',
            'trg_ev_session_reg_hist_no_delete',
            'trg_ev_session_reg_validate_insert',
            'trg_ev_session_reg_validate_update',
        ] as $trigger) {
            self::assertTrue(DB::table('information_schema.TRIGGERS')
                ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
                ->where('TRIGGER_NAME', $trigger)
                ->exists(), $trigger);
        }
    }

    public function test_foundation_migration_refuses_out_of_order_rollback_with_enterprise_dependents(): void
    {
        $migration = require database_path(
            'migrations/2026_07_11_000053_create_event_agenda_sessions.php',
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('event_agenda_rollback_refused_dependents_exist');
        $migration->down();
    }

    private function constraintExists(string $constraint, string $type): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', $type)
            ->exists();
    }
}
