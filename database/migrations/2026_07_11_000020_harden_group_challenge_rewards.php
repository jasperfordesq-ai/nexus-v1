<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('group_challenges')) {
            DB::table('group_challenges')
                ->whereNotIn('reward_xp', [0, 25, 50, 100])
                ->update(['reward_xp' => 0]);

            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::statement('ALTER TABLE group_challenges MODIFY reward_xp INT NOT NULL DEFAULT 0');
            } else {
                Schema::table('group_challenges', function (Blueprint $table): void {
                    $table->integer('reward_xp')->default(0)->change();
                });
            }
        }

        if (! Schema::hasTable('group_challenge_rewards')) {
            Schema::create('group_challenge_rewards', function (Blueprint $table): void {
                $table->id();
                $table->integer('tenant_id');
                $table->integer('challenge_id');
                $table->integer('user_id');
                $table->unsignedInteger('reward_xp');
                $table->timestamp('awarded_at');

                $table->unique(['challenge_id', 'user_id'], 'uq_group_challenge_reward_user');
                $table->index(['tenant_id', 'user_id'], 'idx_group_challenge_reward_tenant_user');
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('challenge_id')->references('id')->on('group_challenges')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('group_challenge_rewards');

        if (Schema::hasTable('group_challenges')) {
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::statement('ALTER TABLE group_challenges MODIFY reward_xp INT NOT NULL DEFAULT 100');
            } else {
                Schema::table('group_challenges', function (Blueprint $table): void {
                    $table->integer('reward_xp')->default(100)->change();
                });
            }
        }
    }
};
