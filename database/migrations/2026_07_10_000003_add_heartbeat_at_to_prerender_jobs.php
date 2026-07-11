<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preserve the true job start timestamp while maintaining a renewable lease.
 *
 * `started_at` is historical operator evidence and must not move throughout a
 * long render. `heartbeat_at` is the mutable lease checked by the stale-job
 * reaper and health dashboard. It is nullable for rows created before this
 * migration; runtime checks use COALESCE(heartbeat_at, started_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('prerender_jobs', 'heartbeat_at')) {
            Schema::table('prerender_jobs', function (Blueprint $table): void {
                $table->timestamp('heartbeat_at')->nullable()->after('started_at')
                    ->comment('Renewable worker lease; started_at remains immutable');
            });
        }
        if (!Schema::hasIndex('prerender_jobs', 'idx_prerender_jobs_running_lease')) {
            Schema::table('prerender_jobs', function (Blueprint $table): void {
                $table->index(
                    ['status', 'heartbeat_at', 'started_at'],
                    'idx_prerender_jobs_running_lease'
                );
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('prerender_jobs')) {
            return;
        }

        if (Schema::hasIndex('prerender_jobs', 'idx_prerender_jobs_running_lease')) {
            Schema::table('prerender_jobs', function (Blueprint $table): void {
                $table->dropIndex('idx_prerender_jobs_running_lease');
            });
        }
        if (Schema::hasColumn('prerender_jobs', 'heartbeat_at')) {
            Schema::table('prerender_jobs', function (Blueprint $table): void {
                $table->dropColumn('heartbeat_at');
            });
        }
    }
};
