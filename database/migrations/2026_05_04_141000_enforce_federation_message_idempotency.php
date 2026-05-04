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
        if (
            !Schema::hasTable('federation_messages')
            || !Schema::hasColumn('federation_messages', 'external_partner_id')
            || !Schema::hasColumn('federation_messages', 'external_message_id')
            || !Schema::hasColumn('federation_messages', 'receiver_tenant_id')
        ) {
            return;
        }

        DB::statement("
            DELETE fm FROM federation_messages fm
            INNER JOIN federation_messages keep
                ON keep.receiver_tenant_id = fm.receiver_tenant_id
               AND keep.external_partner_id = fm.external_partner_id
               AND keep.external_message_id = fm.external_message_id
               AND keep.id < fm.id
            WHERE fm.external_partner_id IS NOT NULL
              AND fm.external_message_id IS NOT NULL
        ");

        if (!$this->indexExists('federation_messages', 'uniq_fed_messages_external_idempotency')) {
            Schema::table('federation_messages', function (Blueprint $table): void {
                $table->unique(
                    ['receiver_tenant_id', 'external_partner_id', 'external_message_id'],
                    'uniq_fed_messages_external_idempotency'
                );
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('federation_messages')) {
            return;
        }

        if ($this->indexExists('federation_messages', 'uniq_fed_messages_external_idempotency')) {
            Schema::table('federation_messages', function (Blueprint $table): void {
                $table->dropUnique('uniq_fed_messages_external_idempotency');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        return collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->pluck('Key_name')
            ->contains($index);
    }
};
