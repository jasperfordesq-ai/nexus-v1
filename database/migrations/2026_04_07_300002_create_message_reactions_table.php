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
        // message_reactions table already exists from legacy. This migration ensures
        // it has a tenant_id column for proper tenant scoping. The legacy table
        // scopes via JOIN to messages.tenant_id; we add an explicit column for
        // direct queries.
        if (!Schema::hasTable('message_reactions')) {
            Schema::create('message_reactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('message_id');
                $table->unsignedBigInteger('user_id');
                $table->string('emoji', 10);
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['tenant_id', 'message_id', 'user_id', 'emoji'], 'unique_reaction_tenant');
                $table->index('message_id', 'idx_mr_message_id');
                $table->index('user_id', 'idx_mr_user_id');
            });
        } else {
            // Add tenant_id if missing
            if (!Schema::hasColumn('message_reactions', 'tenant_id')) {
                Schema::table('message_reactions', function (Blueprint $table) {
                    $table->unsignedBigInteger('tenant_id')->default(0)->after('id');
                    $table->index('tenant_id', 'idx_mr_tenant_id');
                });

                // Backfill tenant_id from messages table
                \Illuminate\Support\Facades\DB::statement('
                    UPDATE message_reactions mr
                    INNER JOIN messages m ON mr.message_id = m.id
                    SET mr.tenant_id = m.tenant_id
                    WHERE mr.tenant_id = 0
                ');
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('message_reactions', 'tenant_id')) {
            Schema::table('message_reactions', function (Blueprint $table) {
                $table->dropIndex('idx_mr_tenant_id');
                $table->dropColumn('tenant_id');
            });
        }
    }
};
