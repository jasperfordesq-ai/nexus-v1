<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\Feature\Events\Concerns\BuildsEventTicketingFixtures;
use Tests\Laravel\TestCase;

final class EventTicketingMigrationTest extends TestCase
{
    use DatabaseTransactions;
    use BuildsEventTicketingFixtures;

    private const MIGRATION = '2026_07_11_000057_create_event_ticketing_foundation.php';

    public function test_schema_is_idempotent_composite_scoped_and_contains_no_money_or_reward_fields(): void
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/' . self::MIGRATION);
        $migration->up();
        foreach ([
            'event_ticket_types',
            'event_ticket_type_history',
            'event_ticket_entitlements',
            'event_ticket_entitlement_history',
            'event_ticket_inventory_history',
        ] as $table) {
            self::assertTrue(Schema::hasTable($table), $table);
        }
        foreach ([
            'kind',
            'unit_price_credits',
            'allocation_limit',
            'sales_opens_at_utc',
            'sales_closes_at_utc',
            'eligibility_policy',
            'refund_cutoff_at_utc',
            'organizer_cancel_refundable',
        ] as $column) {
            self::assertTrue(Schema::hasColumn('event_ticket_types', $column), $column);
        }
        foreach ([
            'currency',
            'currency_code',
            'amount_cents',
            'monetary_price',
            'attendance_reward',
            'attendance_credits',
            'wallet_transaction_id',
        ] as $column) {
            self::assertFalse(Schema::hasColumn('event_ticket_types', $column), $column);
            self::assertFalse(Schema::hasColumn('event_ticket_entitlements', $column), $column);
        }
        self::assertSame(
            ['tenant_id', 'event_id', 'ticket_type_id', 'registration_id', 'user_id', 'id'],
            $this->indexColumns(
                'event_ticket_entitlements',
                'uq_event_ticket_entitlement_identity',
            ),
        );
        if (DB::getDriverName() === 'mysql') {
            foreach ([
                'fk_event_ticket_type_event',
                'fk_event_ticket_entitlement_registration',
                'fk_event_ticket_ent_hist_entitlement',
                'fk_event_ticket_inv_hist_entitlement',
            ] as $constraint) {
                self::assertTrue($this->constraintExists($constraint, 'FOREIGN KEY'), $constraint);
            }
            foreach ([
                'chk_event_ticket_type_kind',
                'chk_event_ticket_type_price',
                'chk_event_ticket_type_hist_reason',
                'chk_event_ticket_ent_free_only',
                'chk_event_ticket_ent_lifecycle',
                'chk_event_ticket_ent_hist_total',
                'chk_event_ticket_inv_hist_version',
                'chk_event_ticket_inv_hist_action',
            ] as $constraint) {
                self::assertTrue($this->constraintExists($constraint, 'CHECK'), $constraint);
            }
            foreach ([
                'trg_event_ticket_type_validate_insert',
                'trg_event_ticket_type_no_delete',
                'trg_event_ticket_type_hist_no_update',
                'trg_event_ticket_entitlement_validate_insert',
                'trg_event_ticket_entitlement_no_delete',
                'trg_event_ticket_inv_hist_no_delete',
            ] as $trigger) {
                self::assertTrue($this->triggerExists($trigger), $trigger);
            }
            $allocationTrigger = $this->triggerStatement(
                'trg_event_ticket_entitlement_validate_insert',
            );
            self::assertStringContainsString('UTC_TIMESTAMP(6)', $allocationTrigger);
            self::assertStringNotContainsString('NOW()', $allocationTrigger);
        }
    }

    public function test_direct_sql_rejects_monetary_kinds_price_mismatches_and_history_rewrites(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            self::markTestSkipped('MariaDB constraints are production-driver invariants.');
        }
        $owner = $this->ticketUser();
        [$eventId, $start] = $this->ticketEvent((int) $owner->id);
        $created = (new \App\Services\EventTicketTypeService())->create(
            $eventId,
            $owner,
            $this->ticketTypePayload($start),
            'migration-ticket-create',
        );
        $typeId = (int) $created['ticket_type']->id;
        $this->assertQueryFails(
            fn () => DB::table('event_ticket_types')->where('id', $typeId)->update([
                'kind' => 'money',
                'ticket_version' => 2,
            ]),
            'chk_event_ticket_type_kind',
        );
        $this->assertQueryFails(
            fn () => DB::table('event_ticket_types')->where('id', $typeId)->update([
                'unit_price_credits' => '1.00',
                'ticket_version' => 2,
            ]),
            'chk_event_ticket_type_price',
        );
        $historyId = (int) DB::table('event_ticket_type_history')->value('id');
        $this->assertQueryFails(
            fn () => DB::table('event_ticket_type_history')
                ->where('id', $historyId)
                ->update(['action' => 'updated']),
            'event_ticket_type_history_immutable',
        );
        $this->assertQueryFails(
            fn () => DB::table('event_ticket_types')->where('id', $typeId)->delete(),
            'event_ticket_type_delete_forbidden',
        );
    }

    public function test_rollback_refuses_even_draft_ticket_type_evidence(): void
    {
        $owner = $this->ticketUser();
        [$eventId, $start] = $this->ticketEvent((int) $owner->id);
        (new \App\Services\EventTicketTypeService())->create(
            $eventId,
            $owner,
            $this->ticketTypePayload($start),
            'rollback-ticket-create',
        );
        /** @var Migration $migration */
        $migration = require database_path('migrations/' . self::MIGRATION);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('event_ticketing_rollback_refused_durable_evidence');
        $migration->down();
    }

    private function constraintExists(string $name, string $type): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('CONSTRAINT_NAME', $name)
            ->where('CONSTRAINT_TYPE', $type)
            ->exists();
    }

    private function triggerExists(string $name): bool
    {
        return DB::table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
            ->where('TRIGGER_NAME', $name)
            ->exists();
    }

    private function triggerStatement(string $name): string
    {
        return (string) DB::table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
            ->where('TRIGGER_NAME', $name)
            ->value('ACTION_STATEMENT');
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

    /** @param callable():mixed $operation */
    private function assertQueryFails(callable $operation, string $needle): void
    {
        try {
            $operation();
            self::fail("Expected {$needle}.");
        } catch (QueryException $exception) {
            self::assertStringContainsString($needle, $exception->getMessage());
        }
    }
}
