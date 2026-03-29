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
        // Add new columns to existing badges table for tier/class/threshold system
        Schema::table('badges', function (Blueprint $table) {
            if (! Schema::hasColumn('badges', 'badge_tier')) {
                $table->enum('badge_tier', ['core', 'template', 'custom'])
                    ->default('template')
                    ->after('is_active')
                    ->comment('core=always enabled, template=tenant-configurable, custom=tenant-created');
            }
            if (! Schema::hasColumn('badges', 'badge_class')) {
                $table->enum('badge_class', ['quantity', 'quality', 'special', 'verification'])
                    ->default('quantity')
                    ->after('badge_tier')
                    ->comment('quantity=threshold counter, quality=behavioral, special=one-off, verification=trust');
            }
            if (! Schema::hasColumn('badges', 'threshold')) {
                $table->unsignedInteger('threshold')->default(0)->after('badge_class');
            }
            if (! Schema::hasColumn('badges', 'threshold_type')) {
                $table->string('threshold_type', 50)->nullable()->after('threshold')
                    ->comment('count, rate, duration_months, ratio');
            }
            if (! Schema::hasColumn('badges', 'evaluation_method')) {
                $table->string('evaluation_method', 100)->nullable()->after('threshold_type')
                    ->comment('PHP method name for badge check logic');
            }
            if (! Schema::hasColumn('badges', 'is_enabled')) {
                $table->boolean('is_enabled')->default(true)->after('evaluation_method')
                    ->comment('Global default enabled state (tenant overrides in tenant_badge_overrides)');
            }
            if (! Schema::hasColumn('badges', 'config_json')) {
                $table->text('config_json')->nullable()->after('is_enabled')
                    ->comment('Flexible config for quality badges (thresholds, time windows, etc.)');
            }
        });

        // Add indexes for the new columns (using raw SQL to check existence — no Doctrine in Laravel 12)
        $existingIndexes = collect(DB::select("SHOW INDEX FROM badges"))
            ->pluck('Key_name')
            ->unique()
            ->toArray();

        Schema::table('badges', function (Blueprint $table) use ($existingIndexes) {
            if (! in_array('idx_badge_tier', $existingIndexes)) {
                $table->index('badge_tier', 'idx_badge_tier');
            }
            if (! in_array('idx_badge_class', $existingIndexes)) {
                $table->index('badge_class', 'idx_badge_class');
            }
        });

        // Create tenant_badge_overrides table for per-tenant customization
        if (! Schema::hasTable('tenant_badge_overrides')) {
            Schema::create('tenant_badge_overrides', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->string('badge_key', 100);
                $table->boolean('is_enabled')->default(true);
                $table->unsignedInteger('custom_threshold')->nullable();
                $table->string('custom_name', 200)->nullable();
                $table->string('custom_description')->nullable();
                $table->string('custom_icon', 100)->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'badge_key'], 'uniq_tenant_badge_override');
                $table->index('tenant_id', 'idx_tbo_tenant');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_badge_overrides');

        Schema::table('badges', function (Blueprint $table) {
            $columns = ['badge_tier', 'badge_class', 'threshold', 'threshold_type',
                        'evaluation_method', 'is_enabled', 'config_json'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('badges', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
