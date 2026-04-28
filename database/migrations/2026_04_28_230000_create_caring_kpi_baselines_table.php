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
        if (Schema::hasTable('caring_kpi_baselines')) {
            return;
        }

        Schema::create('caring_kpi_baselines', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id')->index();
            $table->string('label');
            $table->json('baseline_period'); // { "start": "2025-01-01", "end": "2025-12-31" }
            $table->timestamp('captured_at');
            $table->json('metrics');         // { volunteer_hours, member_count, recipient_count, avg_response_hours, engagement_rate_pct, active_relationships, total_exchanges }
            $table->text('notes')->nullable();
            $table->unsignedInteger('captured_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caring_kpi_baselines');
    }
};
