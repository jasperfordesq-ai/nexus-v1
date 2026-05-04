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
        if (!Schema::hasTable('user_blocks')) {
            return;
        }

        if (!Schema::hasColumn('user_blocks', 'tenant_id')) {
            Schema::table('user_blocks', function (Blueprint $table): void {
                $table->unsignedInteger('tenant_id')->nullable()->after('id');
                $table->index(['tenant_id', 'user_id'], 'idx_user_blocks_tenant_user');
                $table->index(['tenant_id', 'blocked_user_id'], 'idx_user_blocks_tenant_blocked');
            });
        }

        DB::statement(
            'UPDATE user_blocks ub
             INNER JOIN users u ON u.id = ub.user_id
             SET ub.tenant_id = u.tenant_id
             WHERE ub.tenant_id IS NULL'
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('user_blocks') || !Schema::hasColumn('user_blocks', 'tenant_id')) {
            return;
        }

        Schema::table('user_blocks', function (Blueprint $table): void {
            $table->dropIndex('idx_user_blocks_tenant_user');
            $table->dropIndex('idx_user_blocks_tenant_blocked');
            $table->dropColumn('tenant_id');
        });
    }
};
