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
        if (!Schema::hasTable('podcast_episodes')) {
            return;
        }

        Schema::table('podcast_episodes', function (Blueprint $table) {
            // Why processing/scanning failed ('not_audio', 'infected',
            // 'source_missing', 'processing_error') — surfaced to admins so
            // stuck media is diagnosable instead of a bare 'failed'.
            if (!Schema::hasColumn('podcast_episodes', 'media_failure_reason')) {
                $table->string('media_failure_reason', 200)->nullable()->after('media_duration_source');
            }
        });

        // The podcasts:release-due scheduler scans cross-tenant:
        //   status='published' AND moderation_status='approved'
        //   AND announced_at IS NULL AND scheduled_for <= now()
        // No tenant_id prefix — the query runs withoutGlobalScopes().
        if (!Schema::hasIndex('podcast_episodes', 'pod_eps_release_due_idx')) {
            Schema::table('podcast_episodes', function (Blueprint $table) {
                $table->index(['status', 'moderation_status', 'announced_at', 'scheduled_for'], 'pod_eps_release_due_idx');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('podcast_episodes')) {
            return;
        }

        if (Schema::hasIndex('podcast_episodes', 'pod_eps_release_due_idx')) {
            Schema::table('podcast_episodes', function (Blueprint $table) {
                $table->dropIndex('pod_eps_release_due_idx');
            });
        }

        if (Schema::hasColumn('podcast_episodes', 'media_failure_reason')) {
            Schema::table('podcast_episodes', function (Blueprint $table) {
                $table->dropColumn('media_failure_reason');
            });
        }
    }
};
