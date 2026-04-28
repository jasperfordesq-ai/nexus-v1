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
        if (!Schema::hasTable('caring_caregiver_links')) {
            Schema::create('caring_caregiver_links', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id')->index();
                $table->unsignedInteger('caregiver_id');
                $table->unsignedInteger('cared_for_id');
                $table->enum('relationship_type', ['family', 'friend', 'neighbour', 'professional'])->default('family');
                $table->boolean('is_primary')->default(false);
                $table->date('start_date');
                $table->text('notes')->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->unsignedInteger('approved_by')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'caregiver_id']);
                $table->index(['tenant_id', 'cared_for_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('caring_caregiver_links');
    }
};
