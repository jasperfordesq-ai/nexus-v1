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
        if (Schema::hasTable('reviews')) {
            if (!Schema::hasColumn('reviews', 'review_type')) {
                Schema::table('reviews', function (Blueprint $table) {
                    $table->string('review_type', 20)->default('peer')->after('status');
                });
            }
            if (!Schema::hasColumn('reviews', 'dimensions')) {
                Schema::table('reviews', function (Blueprint $table) {
                    $table->json('dimensions')->nullable()->after('review_type');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('reviews')) {
            Schema::table('reviews', function (Blueprint $table) {
                if (Schema::hasColumn('reviews', 'review_type')) {
                    $table->dropColumn('review_type');
                }
                if (Schema::hasColumn('reviews', 'dimensions')) {
                    $table->dropColumn('dimensions');
                }
            });
        }
    }
};
