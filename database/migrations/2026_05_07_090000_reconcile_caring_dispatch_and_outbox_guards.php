<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->reconcileSmartNudgeDispatchKey();
        $this->reconcileRemoteHourTransferOutbox();
    }

    public function down(): void
    {
        // Hardening-only reconciliation. Do not drop idempotency or outbox
        // guards on rollback because earlier migrations may also own them.
    }

    private function reconcileSmartNudgeDispatchKey(): void
    {
        if (! Schema::hasTable('caring_smart_nudges')
            || ! Schema::hasColumn('caring_smart_nudges', 'dispatch_key')
        ) {
            return;
        }

        if ($this->indexExists('caring_smart_nudges', 'uq_caring_nudges_dispatch_key')) {
            return;
        }

        DB::table('caring_smart_nudges')
            ->where('dispatch_key', '')
            ->update(['dispatch_key' => null]);

        $duplicates = DB::table('caring_smart_nudges')
            ->select([
                'tenant_id',
                'dispatch_key',
                DB::raw('MIN(id) as keep_id'),
            ])
            ->whereNotNull('dispatch_key')
            ->where('dispatch_key', '<>', '')
            ->groupBy('tenant_id', 'dispatch_key')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('caring_smart_nudges')
                ->where('tenant_id', (int) $duplicate->tenant_id)
                ->where('dispatch_key', (string) $duplicate->dispatch_key)
                ->where('id', '<>', (int) $duplicate->keep_id)
                ->update([
                    'dispatch_key' => DB::raw("CONCAT('duplicate:', id)"),
                    'updated_at' => now(),
                ]);
        }

        DB::statement(
            'ALTER TABLE caring_smart_nudges ADD UNIQUE KEY uq_caring_nudges_dispatch_key (tenant_id, dispatch_key)'
        );
    }

    private function reconcileRemoteHourTransferOutbox(): void
    {
        if (! Schema::hasTable('caring_hour_transfers')) {
            return;
        }

        $required = [
            'tenant_id',
            'role',
            'is_remote',
            'status',
            'remote_delivery_status',
            'remote_delivery_next_retry_at',
        ];
        foreach ($required as $column) {
            if (! Schema::hasColumn('caring_hour_transfers', $column)) {
                return;
            }
        }

        DB::table('caring_hour_transfers')
            ->where('role', 'source')
            ->where('is_remote', 1)
            ->where('status', 'sent')
            ->whereNull('remote_delivery_status')
            ->update([
                'remote_delivery_status' => 'pending',
                'remote_delivery_next_retry_at' => DB::raw('COALESCE(remote_delivery_next_retry_at, updated_at, created_at, NOW())'),
                'updated_at' => now(),
            ]);

        if (! $this->indexExists('caring_hour_transfers', 'idx_caring_hour_remote_outbox_due')) {
            DB::statement(
                'ALTER TABLE caring_hour_transfers ADD INDEX idx_caring_hour_remote_outbox_due (tenant_id, role, is_remote, status, remote_delivery_next_retry_at)'
            );
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $index],
        ) !== [];
    }
};
