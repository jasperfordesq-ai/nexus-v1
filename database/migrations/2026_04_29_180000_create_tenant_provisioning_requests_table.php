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
 * AG44 — Self-service regional node provisioning.
 *
 * Stores public form submissions where a canton/cooperative/municipality
 * applies to have a NEXUS tenant provisioned for them. A super-admin
 * reviews each request, approves it, and the platform spins up a tenant
 * (slug, admin user, default seeds, caring-community preset if applicable)
 * without manual SSH/DB work.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('tenant_provisioning_requests')) {
            Schema::create('tenant_provisioning_requests', function (Blueprint $table) {
                $table->bigIncrements('id');

                // Applicant
                $table->string('applicant_name', 255);
                $table->string('applicant_email', 255);
                $table->string('applicant_phone', 50)->nullable();

                // Requested community
                $table->string('org_name', 255);
                $table->char('country_code', 2)->default('CH');
                $table->string('region_or_canton', 255)->nullable();
                $table->string('requested_slug', 80)->unique();
                $table->string('requested_subdomain', 120)->nullable()->unique();
                $table->string('tenant_category', 50)->default('community');

                // Localisation
                $table->json('languages')->nullable();
                $table->string('default_language', 10)->default('en');

                // Sizing & intent
                $table->string('expected_member_count_bucket', 30)->nullable();
                $table->text('intended_use')->nullable();

                // Anti-abuse
                $table->string('captcha_token', 500)->nullable();
                $table->string('ip_hash', 64)->nullable();

                // Public status token (used for /pilot-apply/status/:token)
                $table->string('status_token', 64)->nullable()->unique();

                // Workflow
                $table->enum('status', [
                    'pending',
                    'under_review',
                    'approved',
                    'provisioned',
                    'rejected',
                    'failed',
                ])->default('pending');

                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->dateTime('reviewed_at')->nullable();
                $table->text('rejection_reason')->nullable();

                // Provisioning result
                $table->unsignedBigInteger('provisioned_tenant_id')->nullable();
                $table->json('provisioning_log')->nullable();

                $table->timestamps();

                $table->index('status');
                $table->index('applicant_email');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_provisioning_requests');
    }
};
