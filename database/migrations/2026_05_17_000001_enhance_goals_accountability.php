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
        if (Schema::hasTable('goals')) {
            Schema::table('goals', function (Blueprint $table) {
                if (!Schema::hasColumn('goals', 'streak_count')) {
                    $table->unsignedInteger('streak_count')->default(0)->after('last_checkin_at');
                }
                if (!Schema::hasColumn('goals', 'best_streak_count')) {
                    $table->unsignedInteger('best_streak_count')->default(0)->after('streak_count');
                }
            });

            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE goals MODIFY checkin_frequency ENUM('none','daily','weekly','biweekly','monthly') NOT NULL DEFAULT 'none'");
            }
        }

        if (DB::getDriverName() === 'mysql' && Schema::hasTable('goal_progress_history')) {
            DB::statement("ALTER TABLE goal_progress_history MODIFY event_type ENUM('progress_update','checkin','milestone','buddy_joined','buddy_action','completed','created') NOT NULL");
        }

        if (!Schema::hasTable('goal_milestones')) {
            Schema::create('goal_milestones', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('goal_id');
                $table->unsignedInteger('tenant_id');
                $table->string('title');
                $table->decimal('target_percent', 5, 2)->nullable();
                $table->decimal('target_value', 10, 2)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['goal_id', 'sort_order']);
                $table->index('tenant_id');
                $table->index('completed_at');
            });
        }

        if (!Schema::hasTable('goal_buddy_notes')) {
            Schema::create('goal_buddy_notes', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('goal_id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('buddy_id');
                $table->unsignedInteger('owner_id');
                $table->enum('type', ['nudge', 'encouragement', 'offer_help', 'celebration', 'note'])->default('encouragement');
                $table->text('message')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['goal_id', 'created_at']);
                $table->index(['owner_id', 'read_at']);
                $table->index('tenant_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_buddy_notes');
        Schema::dropIfExists('goal_milestones');

        if (Schema::hasTable('goals')) {
            Schema::table('goals', function (Blueprint $table) {
                if (Schema::hasColumn('goals', 'best_streak_count')) {
                    $table->dropColumn('best_streak_count');
                }
                if (Schema::hasColumn('goals', 'streak_count')) {
                    $table->dropColumn('streak_count');
                }
            });

            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE goals MODIFY checkin_frequency ENUM('none','weekly','biweekly') NOT NULL DEFAULT 'none'");
            }
        }

        if (DB::getDriverName() === 'mysql' && Schema::hasTable('goal_progress_history')) {
            DB::statement("ALTER TABLE goal_progress_history MODIFY event_type ENUM('progress_update','checkin','milestone','buddy_joined','completed','created') NOT NULL");
        }
    }
};
