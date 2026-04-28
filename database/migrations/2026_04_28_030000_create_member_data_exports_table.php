<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * member_data_exports — audit trail of GDPR/FADP member data export requests.
 *
 * Each row records one personal-data archive download initiated by a member.
 * Supports both rate-limiting (5/day per user) and forensic visibility into
 * who exported what and when (e.g. for incident response or supervisor review).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('member_data_exports')) {
            return;
        }

        Schema::create('member_data_exports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('format', ['json', 'zip'])->default('json');
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id'], 'idx_mde_tenant_user');
            $table->index(['tenant_id', 'requested_at'], 'idx_mde_tenant_requested');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_data_exports');
    }
};
