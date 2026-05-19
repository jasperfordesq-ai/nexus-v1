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
    private string $indexName = 'marketplace_payments_pi_unique';

    public function up(): void
    {
        if (!Schema::hasTable('marketplace_payments')) {
            return;
        }

        DB::statement(
            'DELETE newer FROM marketplace_payments newer
             INNER JOIN marketplace_payments older
               ON newer.stripe_payment_intent_id = older.stripe_payment_intent_id
              AND newer.stripe_payment_intent_id IS NOT NULL
              AND newer.id > older.id'
        );

        if (!$this->hasIndex('marketplace_payments', $this->indexName)) {
            Schema::table('marketplace_payments', function (Blueprint $table): void {
                $table->unique('stripe_payment_intent_id', $this->indexName);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('marketplace_payments') && $this->hasIndex('marketplace_payments', $this->indexName)) {
            Schema::table('marketplace_payments', function (Blueprint $table): void {
                $table->dropUnique($this->indexName);
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $indexName);
    }
};
