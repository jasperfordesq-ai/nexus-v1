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
        if (Schema::hasTable('goal_progress_history')) {
            return;
        }

        Schema::create('goal_progress_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('goal_id');
            $table->unsignedInteger('tenant_id');
            $table->enum('event_type', [
                'progress_update',
                'checkin',
                'milestone',
                'buddy_joined',
                'completed',
                'created',
            ]);
            $table->text('description');
            $table->json('data')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['goal_id', 'id']);
            $table->index('tenant_id');
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_progress_history');
    }
};
