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
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'is_online')) {
                $table->boolean('is_online')->default(false)->after('max_attendees');
            }
            if (! Schema::hasColumn('events', 'online_link')) {
                $table->string('online_link', 512)->nullable()->after('is_online');
            }
            if (! Schema::hasColumn('events', 'image_url')) {
                $table->string('image_url', 512)->nullable()->after('online_link');
            }
            if (! Schema::hasColumn('events', 'video_url')) {
                $table->string('video_url', 512)->nullable()->after('image_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['is_online', 'online_link', 'image_url', 'video_url']);
        });
    }
};
