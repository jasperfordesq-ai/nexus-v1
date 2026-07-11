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
        if (!Schema::hasColumn('prerender_jobs', 'fence_state')) {
            Schema::table('prerender_jobs', function (Blueprint $table): void {
                // Pending authoritative intents use the existing, non-claimable
                // status='failed' for old blue/green workers and expose their
                // real lifecycle through this additive state. This avoids an
                // in-place ENUM modification while the old color is live.
                $table->string('fence_state', 16)
                    ->default('ready')
                    ->after('queued_at');
            });
        }

        if (!Schema::hasColumn('prerender_jobs', 'fence_ready_at')) {
            Schema::table('prerender_jobs', function (Blueprint $table): void {
                // CURRENT_TIMESTAMP keeps rows inserted by the still-live old
                // blue/green color claimable during rolling migration. New
                // authoritative outbox rows explicitly write NULL.
                $table->timestamp('fence_ready_at')
                    ->nullable()
                    ->useCurrent()
                    ->after('fence_state');
            });
            // Every pre-migration row was already eligible under the old
            // queue contract. Only new authoritative intents start pending.
            DB::table('prerender_jobs')->whereNull('fence_ready_at')->update([
                'fence_ready_at' => DB::raw('queued_at'),
            ]);
        }

        if (!Schema::hasIndex('prerender_jobs', 'idx_prerender_jobs_fence_claim')) {
            Schema::table('prerender_jobs', function (Blueprint $table): void {
                $table->index(
                    ['status', 'fence_state', 'fence_ready_at', 'priority', 'queued_at'],
                    'idx_prerender_jobs_fence_claim'
                );
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('prerender_jobs')) return;
        if (Schema::hasColumn('prerender_jobs', 'fence_state')) {
            DB::table('prerender_jobs')->where('fence_state', 'pending')->update([
                'status' => 'cancelled',
                'finished_at' => now(),
                'error_message' => 'cancelled during fence migration rollback',
            ]);
        }
        if (Schema::hasIndex('prerender_jobs', 'idx_prerender_jobs_fence_claim')) {
            Schema::table('prerender_jobs', function (Blueprint $table): void {
                $table->dropIndex('idx_prerender_jobs_fence_claim');
            });
        }
        if (Schema::hasColumn('prerender_jobs', 'fence_ready_at')) {
            Schema::table('prerender_jobs', function (Blueprint $table): void {
                $table->dropColumn('fence_ready_at');
            });
        }
        if (Schema::hasColumn('prerender_jobs', 'fence_state')) {
            Schema::table('prerender_jobs', function (Blueprint $table): void {
                $table->dropColumn('fence_state');
            });
        }
    }
};
