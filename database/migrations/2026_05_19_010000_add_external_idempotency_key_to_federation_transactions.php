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
        if (!Schema::hasTable('federation_transactions')) {
            return;
        }

        if (!Schema::hasColumn('federation_transactions', 'external_idempotency_key')) {
            Schema::table('federation_transactions', function (Blueprint $table): void {
                $table->string('external_idempotency_key', 191)
                    ->nullable()
                    ->after('external_transaction_id')
                    ->comment('Stable key for idempotent external webhook transaction claims');
            });
        }

        $this->addIndexIfMissing(
            'federation_transactions',
            'uk_fed_tx_external_idempotency',
            'CREATE UNIQUE INDEX `uk_fed_tx_external_idempotency` ON `federation_transactions` (`external_idempotency_key`)'
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('federation_transactions')) {
            return;
        }

        $this->dropIndexIfExists('federation_transactions', 'uk_fed_tx_external_idempotency');

        if (Schema::hasColumn('federation_transactions', 'external_idempotency_key')) {
            Schema::table('federation_transactions', function (Blueprint $table): void {
                $table->dropColumn('external_idempotency_key');
            });
        }
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
