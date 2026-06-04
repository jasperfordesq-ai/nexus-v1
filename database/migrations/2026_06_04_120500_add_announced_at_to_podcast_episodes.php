<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('podcast_episodes')) {
            return;
        }
        if (!Schema::hasColumn('podcast_episodes', 'announced_at')) {
            Schema::table('podcast_episodes', function (Blueprint $table): void {
                // Timestamp the subscribers were notified + the feed activity posted
                // for this episode going live. NULL = not yet announced; the
                // `podcasts:release-due` scheduler announces future-scheduled episodes
                // once their scheduled_for arrives. Prevents premature/duplicate
                // "new episode" notifications for embargoed episodes.
                $table->timestamp('announced_at')->nullable()->after('published_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('podcast_episodes', 'announced_at')) {
            Schema::table('podcast_episodes', function (Blueprint $table): void {
                $table->dropColumn('announced_at');
            });
        }
    }
};
