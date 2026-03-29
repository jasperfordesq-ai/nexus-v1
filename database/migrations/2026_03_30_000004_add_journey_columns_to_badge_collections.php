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
        Schema::table('badge_collections', function (Blueprint $table) {
            if (! Schema::hasColumn('badge_collections', 'collection_type')) {
                $table->enum('collection_type', ['journey', 'collection'])
                    ->default('collection')
                    ->after('collection_key')
                    ->comment('journey=ordered step-by-step path, collection=unordered badge group');
            }
            if (! Schema::hasColumn('badge_collections', 'is_ordered')) {
                $table->boolean('is_ordered')
                    ->default(false)
                    ->after('collection_type')
                    ->comment('Whether steps must be completed in sequence');
            }
            if (! Schema::hasColumn('badge_collections', 'estimated_duration')) {
                $table->string('estimated_duration', 50)
                    ->nullable()
                    ->after('is_ordered')
                    ->comment('Human-readable estimate, e.g. "2 weeks", "1 month"');
            }
        });
    }

    public function down(): void
    {
        Schema::table('badge_collections', function (Blueprint $table) {
            $columns = ['collection_type', 'is_ordered', 'estimated_duration'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('badge_collections', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
