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
    /**
     * Add video_url column to marketplace_listings for optional listing video.
     * Guarded with hasColumn for idempotency.
     */
    public function up(): void
    {
        if (Schema::hasColumn('marketplace_listings', 'video_url')) {
            return;
        }

        Schema::table('marketplace_listings', function (Blueprint $table) {
            $table->string('video_url', 500)->nullable()->after('template_data');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('marketplace_listings', 'video_url')) {
            return;
        }

        Schema::table('marketplace_listings', function (Blueprint $table) {
            $table->dropColumn('video_url');
        });
    }
};
