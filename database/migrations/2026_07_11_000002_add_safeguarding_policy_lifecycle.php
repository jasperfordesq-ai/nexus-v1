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
        if (Schema::hasTable('user_safeguarding_preferences')) {
            Schema::table('user_safeguarding_preferences', function (Blueprint $table): void {
                if (! Schema::hasColumn('user_safeguarding_preferences', 'policy_review_required_at')) {
                    $table->dateTime('policy_review_required_at')->nullable()->after('review_escalated_at');
                }
                if (! Schema::hasColumn('user_safeguarding_preferences', 'policy_review_reason_code')) {
                    $table->string('policy_review_reason_code', 64)->nullable()->after('policy_review_required_at');
                }
            });
        }

        if (! Schema::hasTable('safeguarding_policy_rotation_events')) {
            Schema::create('safeguarding_policy_rotation_events', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->integer('tenant_id');
                $table->string('jurisdiction', 40);
                $table->string('scheme_code', 64);
                $table->string('attestation_code', 64);
                $table->string('purpose_code', 64);
                $table->string('scope_type', 32);
                $table->string('scope_identifier', 191)->default('');
                $table->string('previous_policy_version', 64);
                $table->string('new_policy_version', 64);
                $table->string('reason_code', 64);
                $table->integer('actor_user_id')->nullable();
                $table->unsignedInteger('affected_member_count')->default(0);
                $table->dateTime('created_at');

                $table->index(['tenant_id', 'created_at'], 'idx_safeguarding_policy_rotation_tenant');
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('safeguarding_policy_rotation_events');

        if (Schema::hasTable('user_safeguarding_preferences')) {
            Schema::table('user_safeguarding_preferences', function (Blueprint $table): void {
                foreach (['policy_review_reason_code', 'policy_review_required_at'] as $column) {
                    if (Schema::hasColumn('user_safeguarding_preferences', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
