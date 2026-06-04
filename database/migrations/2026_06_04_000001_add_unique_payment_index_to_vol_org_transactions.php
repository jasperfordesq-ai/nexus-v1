<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Defense-in-depth against double-paying a volunteer for the same approved
 * hours log. A unique index on (tenant_id, vol_log_id, type) guarantees at most
 * one row per (tenant, log, type). Because 'volunteer_payment' is the only
 * transaction type that sets vol_log_id (deposits, withdrawals, admin
 * adjustments and manual pays leave it NULL, and MySQL treats each NULL as
 * distinct), this enforces exactly one auto-payment per approved hours log for
 * EVERY caller — VolunteerService::verifyHours, AdminVolunteerController::verifyHours,
 * and any future code path — even if an application-level guard is bypassed.
 */
return new class extends Migration
{
    private string $indexName = 'uq_vot_log_payment';

    public function up(): void
    {
        if (!Schema::hasTable('vol_org_transactions') || $this->indexExists()) {
            return;
        }

        try {
            DB::statement(
                "ALTER TABLE vol_org_transactions
                 ADD UNIQUE INDEX `{$this->indexName}` (`tenant_id`, `vol_log_id`, `type`)"
            );
        } catch (\Throwable $e) {
            // Pre-existing duplicate volunteer_payment rows (evidence of a past
            // double-pay) would make this fail. Do NOT break the deploy — the
            // application-level guards (conditional status UPDATE + existing-payment
            // check + dedupe lock) already prevent NEW double-pays. Log the affected
            // logs for manual reconciliation, then this migration can be re-run.
            Log::error(
                '[migration] Could not add unique index ' . $this->indexName
                . ' on vol_org_transactions — likely pre-existing duplicate volunteer_payment rows. '
                . 'Application-level double-pay guards remain in force. Reconcile duplicates and re-run.',
                ['error' => $e->getMessage(), 'duplicates' => $this->findDuplicatePayments()]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vol_org_transactions') && $this->indexExists()) {
            try {
                DB::statement("ALTER TABLE vol_org_transactions DROP INDEX `{$this->indexName}`");
            } catch (\Throwable $e) {
                // best effort
            }
        }
    }

    private function indexExists(): bool
    {
        return DB::selectOne(
            "SELECT 1 AS found FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = 'vol_org_transactions' AND index_name = ?",
            [$this->indexName]
        ) !== null;
    }

    /** @return array<int, array{tenant_id:int, vol_log_id:int, type:string, n:int}> */
    private function findDuplicatePayments(): array
    {
        try {
            return array_map(static fn ($r) => (array) $r, DB::select(
                "SELECT tenant_id, vol_log_id, type, COUNT(*) AS n
                 FROM vol_org_transactions
                 WHERE vol_log_id IS NOT NULL
                 GROUP BY tenant_id, vol_log_id, type
                 HAVING n > 1
                 LIMIT 50"
            ));
        } catch (\Throwable $e) {
            return [];
        }
    }
};
