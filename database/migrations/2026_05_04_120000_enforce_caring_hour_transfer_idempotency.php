<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $uniqueIndexName = 'uq_caring_hour_xfer_remote_idem_tenant';

    public function up(): void
    {
        if (! Schema::hasTable('caring_hour_transfers')) {
            return;
        }

        if (! Schema::hasColumn('caring_hour_transfers', 'remote_idempotency_key')) {
            Schema::table('caring_hour_transfers', function (Blueprint $table): void {
                $table->string('remote_idempotency_key', 160)->nullable()->after('linked_transfer_id');
            });
        }

        if (! Schema::hasColumn('caring_hour_transfers', 'is_remote')) {
            Schema::table('caring_hour_transfers', function (Blueprint $table): void {
                $table->boolean('is_remote')->default(false)->after('remote_idempotency_key');
            });
        }

        if ($this->indexExists($this->uniqueIndexName)) {
            return;
        }

        $this->normaliseDuplicateRemoteKeys();

        Schema::table('caring_hour_transfers', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'remote_idempotency_key'], $this->uniqueIndexName);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('caring_hour_transfers')) {
            return;
        }

        if ($this->indexExists($this->uniqueIndexName)) {
            Schema::table('caring_hour_transfers', function (Blueprint $table): void {
                $table->dropUnique($this->uniqueIndexName);
            });
        }
    }

    private function indexExists(string $name): bool
    {
        return collect(DB::select(
            'SHOW INDEX FROM caring_hour_transfers WHERE Key_name = ?',
            [$name]
        ))->isNotEmpty();
    }

    private function normaliseDuplicateRemoteKeys(): void
    {
        DB::table('caring_hour_transfers')
            ->where('remote_idempotency_key', '')
            ->update(['remote_idempotency_key' => null]);

        $duplicates = DB::table('caring_hour_transfers')
            ->select([
                'tenant_id',
                'remote_idempotency_key',
                DB::raw('MIN(id) as keep_id'),
                DB::raw('COUNT(*) as duplicate_count'),
            ])
            ->whereNotNull('remote_idempotency_key')
            ->where('remote_idempotency_key', '<>', '')
            ->groupBy('tenant_id', 'remote_idempotency_key')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('caring_hour_transfers')
                ->where('tenant_id', (int) $duplicate->tenant_id)
                ->where('remote_idempotency_key', (string) $duplicate->remote_idempotency_key)
                ->where('id', '<>', (int) $duplicate->keep_id)
                ->update([
                    'remote_idempotency_key' => DB::raw("CONCAT('duplicate:', id)"),
                    'updated_at' => now(),
                ]);
        }
    }
};
