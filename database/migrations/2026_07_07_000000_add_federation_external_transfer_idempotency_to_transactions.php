<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('transactions')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table): void {
            if (!Schema::hasColumn('transactions', 'federation_idempotency_key')) {
                $table->string('federation_idempotency_key', 191)
                    ->nullable()
                    ->after('receiver_tenant_id')
                    ->comment('Stable client/content idempotency fingerprint for federated transfer submissions');
            }

            if (!Schema::hasColumn('transactions', 'federation_idempotency_payload_hash')) {
                $table->string('federation_idempotency_payload_hash', 64)
                    ->nullable()
                    ->after('federation_idempotency_key')
                    ->comment('Payload hash used to reject mismatched replays for the same federation idempotency key');
            }

            if (!Schema::hasColumn('transactions', 'federation_partner_idempotency_key')) {
                $table->string('federation_partner_idempotency_key', 191)
                    ->nullable()
                    ->after('federation_idempotency_payload_hash')
                    ->comment('Outbound partner idempotency key sent to external federation protocols');
            }

            if (!Schema::hasColumn('transactions', 'external_transaction_id')) {
                $table->string('external_transaction_id', 191)
                    ->nullable()
                    ->after('federation_partner_idempotency_key')
                    ->comment('Remote partner transaction identifier when an external federation transfer is accepted');
            }
        });

        $this->addIndexIfMissing(
            'transactions',
            'uk_txn_federation_idempotency',
            'CREATE UNIQUE INDEX `uk_txn_federation_idempotency` ON `transactions` (`tenant_id`, `federation_idempotency_key`)'
        );

        $this->addIndexIfMissing(
            'transactions',
            'idx_txn_federation_partner_idempotency',
            'CREATE INDEX `idx_txn_federation_partner_idempotency` ON `transactions` (`tenant_id`, `federation_partner_idempotency_key`)'
        );

        $this->addIndexIfMissing(
            'transactions',
            'idx_txn_external_transaction_id',
            'CREATE INDEX `idx_txn_external_transaction_id` ON `transactions` (`tenant_id`, `external_transaction_id`)'
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('transactions')) {
            return;
        }

        $this->dropIndexIfExists('transactions', 'idx_txn_external_transaction_id');
        $this->dropIndexIfExists('transactions', 'idx_txn_federation_partner_idempotency');
        $this->dropIndexIfExists('transactions', 'uk_txn_federation_idempotency');

        Schema::table('transactions', function (Blueprint $table): void {
            foreach ([
                'external_transaction_id',
                'federation_partner_idempotency_key',
                'federation_idempotency_payload_hash',
                'federation_idempotency_key',
            ] as $column) {
                if (Schema::hasColumn('transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function addIndexIfMissing(string $table, string $index, string $sql): void
    {
        $database = DB::connection()->getDatabaseName();
        $exists = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index]
        );

        if ((int) ($exists->c ?? 0) === 0) {
            DB::statement($sql);
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        $database = DB::connection()->getDatabaseName();
        $exists = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index]
        );

        if ((int) ($exists->c ?? 0) > 0) {
            DB::statement("DROP INDEX `{$index}` ON `{$table}`");
        }
    }
};
