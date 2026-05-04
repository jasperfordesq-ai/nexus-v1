<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The user_blocks table already exists from legacy. This migration ensures
        // it has the expected columns and adds a 'reason' column if missing.
        if (!Schema::hasTable('user_blocks')) {
            Schema::create('user_blocks', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id')->nullable();
                $table->unsignedInteger('user_id')->comment('User who blocked');
                $table->unsignedInteger('blocked_user_id')->comment('User who was blocked');
                $table->text('reason')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['user_id', 'blocked_user_id'], 'unique_block');
                $table->index(['tenant_id', 'user_id'], 'idx_user_blocks_tenant_user');
                $table->index(['tenant_id', 'blocked_user_id'], 'idx_user_blocks_tenant_blocked');
                $table->index('user_id', 'idx_user');
                $table->index('blocked_user_id', 'idx_blocked');
            });
        } else {
            Schema::table('user_blocks', function (Blueprint $table) {
                if (!Schema::hasColumn('user_blocks', 'tenant_id')) {
                    $table->unsignedInteger('tenant_id')->nullable()->after('id');
                    $table->index(['tenant_id', 'user_id'], 'idx_user_blocks_tenant_user');
                    $table->index(['tenant_id', 'blocked_user_id'], 'idx_user_blocks_tenant_blocked');
                }
                if (!Schema::hasColumn('user_blocks', 'reason')) {
                    $table->text('reason')->nullable()->after('blocked_user_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_blocks', 'reason')) {
            Schema::table('user_blocks', function (Blueprint $table) {
                $table->dropColumn('reason');
            });
        }
    }
};
