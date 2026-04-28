<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('safeguarding_report_actions')) {
            return;
        }

        Schema::create('safeguarding_report_actions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('tenant_id');
            $table->unsignedBigInteger('report_id');
            $table->unsignedInteger('actor_user_id');
            $table->enum('action', [
                'created',
                'triaged',
                'assigned',
                'escalated',
                'status_changed',
                'note_added',
                'resolved',
                'dismissed',
            ]);
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'report_id', 'created_at'], 'idx_safeguard_action_tenant_report');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('safeguarding_report_actions');
    }
};
