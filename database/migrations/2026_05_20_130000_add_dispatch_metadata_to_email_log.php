<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_log')) {
            return;
        }

        Schema::table('email_log', function (Blueprint $table): void {
            if (!Schema::hasColumn('email_log', 'source')) {
                $table->string('source', 160)->nullable()->after('category')->index();
            }
            if (!Schema::hasColumn('email_log', 'idempotency_key')) {
                $table->string('idempotency_key', 191)->nullable()->after('source')->index();
            }
            if (!Schema::hasColumn('email_log', 'dispatch_id')) {
                $table->string('dispatch_id', 64)->nullable()->after('idempotency_key')->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('email_log')) {
            return;
        }

        Schema::table('email_log', function (Blueprint $table): void {
            foreach (['dispatch_id', 'idempotency_key', 'source'] as $column) {
                if (Schema::hasColumn('email_log', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
