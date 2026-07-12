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
use LogicException;
use Tests\Laravel\TestCase;

final class EventWaitlistOfferEnvelopeMigrationTest extends TestCase
{
    use DatabaseTransactions;

    private const MIGRATION = '2026_07_11_000021_add_event_waitlist_offer_delivery_envelopes.php';

    public function test_schema_exposes_versioned_ciphertext_claim_and_immutable_access_evidence(): void
    {
        foreach ([
            'event_waitlist_offer_envelopes' => [
                'tenant_id', 'event_id', 'waitlist_entry_id', 'outbox_id', 'queue_version',
                'action', 'cipher_version', 'key_version', 'key_fingerprint', 'aad_hash',
                'token_ciphertext', 'status', 'envelope_version', 'claim_token_hash',
                'claimed_by', 'claimed_at', 'handed_off_at', 'erased_at', 'expires_at',
            ],
            'event_waitlist_offer_envelope_access' => [
                'tenant_id', 'event_id', 'envelope_id', 'waitlist_entry_id', 'outbox_id',
                'queue_version', 'operation', 'consumer', 'claim_id_hash', 'from_status',
                'to_status', 'idempotency_key', 'metadata', 'created_at',
            ],
        ] as $table => $columns) {
            self::assertTrue(Schema::hasTable($table), "Missing {$table}");
            foreach ($columns as $column) {
                self::assertTrue(Schema::hasColumn($table, $column), "Missing {$table}.{$column}");
            }
        }

        self::assertSame(
            ['tenant_id', 'outbox_id'],
            $this->indexColumns(
                'event_waitlist_offer_envelopes',
                'uq_event_waitlist_offer_envelope_outbox',
            ),
        );
        self::assertSame(
            ['tenant_id', 'waitlist_entry_id', 'queue_version'],
            $this->indexColumns(
                'event_waitlist_offer_envelopes',
                'uq_event_waitlist_offer_envelope_version',
            ),
        );
        self::assertSame(
            ['tenant_id', 'idempotency_key'],
            $this->indexColumns(
                'event_waitlist_offer_envelope_access',
                'uq_event_waitlist_envelope_access_key',
            ),
        );

        if (DB::getDriverName() === 'mysql') {
            foreach ([
                'trg_event_waitlist_envelope_access_no_update',
                'trg_event_waitlist_envelope_access_no_delete',
            ] as $trigger) {
                self::assertTrue(DB::table('information_schema.TRIGGERS')
                    ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
                    ->where('TRIGGER_NAME', $trigger)
                    ->exists(), "Missing trigger {$trigger}");
            }
        }
    }

    public function test_access_evidence_is_immutable_for_update_and_delete(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            self::markTestSkipped('The immutable ledger triggers are MySQL-specific.');
        }
        $accessId = $this->insertEnvelopeAccessFixture();

        foreach (['update', 'delete'] as $operation) {
            try {
                if ($operation === 'update') {
                    DB::table('event_waitlist_offer_envelope_access')
                        ->where('id', $accessId)
                        ->update(['operation' => 'tampered']);
                } else {
                    DB::table('event_waitlist_offer_envelope_access')
                        ->where('id', $accessId)
                        ->delete();
                }
                self::fail("{$operation} bypassed envelope access immutability");
            } catch (QueryException $exception) {
                self::assertStringContainsString(
                    'event_waitlist_offer_envelope_access_immutable',
                    $exception->getMessage(),
                );
            }
        }
    }

    public function test_rollback_refuses_operational_envelope_or_access_records(): void
    {
        $this->insertEnvelopeAccessFixture();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'Event waitlist offer delivery-envelope records exist and cannot be rolled back.',
        );
        $this->migration()->down();
    }

    private function insertEnvelopeAccessFixture(): int
    {
        $suffix = random_int(100000, 999999);
        $now = now();
        $envelopeId = (int) DB::table('event_waitlist_offer_envelopes')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $suffix,
            'waitlist_entry_id' => $suffix,
            'outbox_id' => $suffix,
            'queue_version' => 1,
            'action' => 'event.waitlist.offered',
            'cipher_version' => 'aes-256-gcm-v1',
            'key_version' => 'test-v1',
            'key_fingerprint' => hash('sha256', 'migration-key'),
            'aad_hash' => hash('sha256', 'migration-aad'),
            'token_ciphertext' => '{"v":1}',
            'status' => 'sealed',
            'envelope_version' => 1,
            'expires_at' => $now->copy()->addMinutes(15),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) DB::table('event_waitlist_offer_envelope_access')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $suffix,
            'envelope_id' => $envelopeId,
            'waitlist_entry_id' => $suffix,
            'outbox_id' => $suffix,
            'queue_version' => 1,
            'operation' => 'sealed',
            'to_status' => 'sealed',
            'idempotency_key' => hash('sha256', "migration-envelope-{$suffix}"),
            'metadata' => json_encode(['schema_version' => 1], JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);
    }

    /** @return list<string> */
    private function indexColumns(string $table, string $index): array
    {
        $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);

        return array_map(static fn (object $row): string => (string) $row->Column_name, $rows);
    }

    private function migration(): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/' . self::MIGRATION);

        return $migration;
    }
}
