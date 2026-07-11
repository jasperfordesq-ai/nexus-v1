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
        if (! Schema::hasTable('tenant_safeguarding_settings')) {
            Schema::create('tenant_safeguarding_settings', function (Blueprint $table): void {
                $table->integer('tenant_id')->primary();
                $table->string('jurisdiction', 40);
                $table->string('policy_version', 64)->default('1');
                $table->integer('configured_by')->nullable();
                $table->dateTime('configured_at');
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('configured_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('member_vetting_attestations')) {
            Schema::create('member_vetting_attestations', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->integer('tenant_id');
                $table->integer('user_id');
                $table->string('scheme_code', 64);
                $table->string('attestation_code', 64);
                $table->string('purpose_code', 64);
                $table->string('scope_type', 32)->default('tenant');
                $table->string('scope_identifier', 191)->default('');
                $table->string('decision', 20);
                $table->integer('confirmed_by')->nullable();
                $table->dateTime('confirmed_at')->nullable();
                $table->integer('revoked_by')->nullable();
                $table->dateTime('revoked_at')->nullable();
                $table->string('revocation_reason_code', 64)->nullable();
                $table->string('policy_version', 64)->default('1');
                $table->timestamps();

                $table->unique(
                    ['tenant_id', 'user_id', 'scheme_code', 'attestation_code', 'purpose_code', 'scope_type', 'scope_identifier'],
                    'uq_member_vetting_attestation_scope'
                );
                $table->index(
                    ['tenant_id', 'user_id', 'attestation_code', 'purpose_code', 'decision'],
                    'idx_member_vetting_policy_status'
                );

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('confirmed_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('revoked_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('member_vetting_attestation_events')) {
            Schema::create('member_vetting_attestation_events', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('attestation_id');
                $table->integer('tenant_id');
                $table->integer('user_id');
                $table->string('scheme_code', 64);
                $table->string('attestation_code', 64);
                $table->string('purpose_code', 64);
                $table->string('scope_type', 32);
                $table->string('scope_identifier', 191)->default('');
                $table->string('event_type', 32);
                $table->string('decision_before', 20)->nullable();
                $table->string('decision_after', 20);
                $table->string('reason_code', 64)->nullable();
                $table->integer('actor_user_id')->nullable();
                $table->string('policy_version', 64);
                $table->dateTime('created_at');

                $table->index(['tenant_id', 'user_id', 'created_at'], 'idx_vetting_event_member_history');
                $table->index(['tenant_id', 'actor_user_id', 'created_at'], 'idx_vetting_event_actor_history');

                $table->foreign('attestation_id')->references('id')->on('member_vetting_attestations')->cascadeOnDelete();
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('safeguarding_vetting_review_requests')) {
            Schema::create('safeguarding_vetting_review_requests', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->integer('tenant_id');
                $table->integer('user_id');
                $table->string('jurisdiction', 40);
                $table->string('scheme_code', 64);
                $table->string('attestation_code', 64);
                $table->string('purpose_code', 64);
                $table->string('scope_type', 32)->default('tenant');
                $table->string('scope_identifier', 191)->default('');
                $table->string('policy_version', 64);
                $table->string('status', 20)->default('pending');
                $table->string('request_source', 32)->default('member_request');
                $table->integer('requested_by')->nullable();
                $table->dateTime('requested_at');
                $table->integer('handled_by')->nullable();
                $table->dateTime('handled_at')->nullable();
                $table->string('resolution_code', 64)->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status', 'requested_at'], 'idx_vetting_review_queue');
                $table->index(['tenant_id', 'user_id', 'purpose_code'], 'idx_vetting_review_member');
                $table->unique(
                    ['tenant_id', 'user_id', 'purpose_code', 'scope_type', 'scope_identifier'],
                    'uq_vetting_review_member_scope'
                );

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('handled_by')->references('id')->on('users')->nullOnDelete();
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

        // These legacy offer attributes are member-selected public claims and
        // are not backed by the broker attestation. Keep historical rows for
        // referential integrity, but stop presenting them as trust signals.
        if (Schema::hasTable('attributes')) {
            DB::table('attributes')
                ->where('target_type', 'offer')
                ->whereIn('name', ['Background Checked', 'Garda Vetted'])
                ->update(['is_active' => false, 'updated_at' => now()]);
        }

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
    }

    public function down(): void
    {
        if (Schema::hasTable('user_safeguarding_preferences')) {
            Schema::table('user_safeguarding_preferences', function (Blueprint $table): void {
                foreach (['policy_review_reason_code', 'policy_review_required_at'] as $column) {
                    if (Schema::hasColumn('user_safeguarding_preferences', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
        Schema::dropIfExists('safeguarding_policy_rotation_events');
        Schema::dropIfExists('safeguarding_vetting_review_requests');
        Schema::dropIfExists('member_vetting_attestation_events');
        Schema::dropIfExists('member_vetting_attestations');
        Schema::dropIfExists('tenant_safeguarding_settings');
    }

};
