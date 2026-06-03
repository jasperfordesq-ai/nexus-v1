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
        if (Schema::hasTable('podcast_shows')) {
            Schema::table('podcast_shows', function (Blueprint $table) {
                if (!Schema::hasColumn('podcast_shows', 'author_name')) {
                    $table->string('author_name', 200)->nullable()->after('category');
                }
                if (!Schema::hasColumn('podcast_shows', 'owner_email')) {
                    $table->string('owner_email', 320)->nullable()->after('author_name');
                }
                if (!Schema::hasColumn('podcast_shows', 'copyright')) {
                    $table->string('copyright', 300)->nullable()->after('owner_email');
                }
                if (!Schema::hasColumn('podcast_shows', 'funding_url')) {
                    $table->string('funding_url', 1000)->nullable()->after('copyright');
                }
                if (!Schema::hasColumn('podcast_shows', 'explicit')) {
                    $table->boolean('explicit')->default(false)->after('funding_url');
                }
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('podcast_shows')) {
            return;
        }

        Schema::table('podcast_shows', function (Blueprint $table) {
            foreach (['explicit', 'funding_url', 'copyright', 'owner_email', 'author_name'] as $column) {
                if (Schema::hasColumn('podcast_shows', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
