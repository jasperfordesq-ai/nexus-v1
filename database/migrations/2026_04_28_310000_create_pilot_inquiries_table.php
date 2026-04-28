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
 * AG71 — Pilot Region Inquiry & Qualification Funnel
 *
 * Creates the pilot_inquiries table which stores Gemeinde submissions
 * from the "Jetzt Pilotregion werden!" CTA on agoris.ch.
 *
 * Each row tracks a municipality's journey from initial interest through
 * to going live on the NEXUS platform.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pilot_inquiries')) {
            Schema::create('pilot_inquiries', function (Blueprint $table) {
                $table->bigIncrements('id');

                // The NEXUS tenant where this inquiry was submitted (platform admin tenant)
                $table->unsignedBigInteger('tenant_id')->index();

                // Municipality details
                $table->string('municipality_name', 255);
                $table->string('region', 255)->nullable();       // canton / county
                $table->char('country', 2)->default('CH');
                $table->unsignedInteger('population')->nullable();

                // Contact
                $table->string('contact_name', 255);
                $table->string('contact_email', 255);
                $table->string('contact_phone', 50)->nullable();
                $table->string('contact_role', 100)->nullable(); // e.g. "Gemeindeschreiber"

                // Qualification signals
                $table->tinyInteger('has_kiss_cooperative')->default(0);
                $table->tinyInteger('has_existing_digital_tool')->default(0);
                $table->string('existing_tool_name', 255)->nullable();

                // 0=ASAP, 6=6 months, 12=1 year, 24=2 years, 99=just exploring
                $table->unsignedInteger('timeline_months')->nullable();

                // Modules of interest: ["time_banking","caring_community","local_marketplace","municipal_announcements"]
                $table->json('interest_modules')->nullable();

                // 'under_5k' | '5k_10k' | '10k_25k' | '25k_plus' | 'unknown'
                $table->string('budget_indication', 50)->nullable();

                $table->text('notes')->nullable();

                // Fit-score (0.0–100.0) computed at submission time
                $table->decimal('fit_score', 4, 1)->nullable();
                $table->json('fit_breakdown')->nullable(); // {"kiss_cooperative":30,"population":20,...}

                // Pipeline stage
                $table->enum('stage', [
                    'new',
                    'qualified',
                    'proposal_sent',
                    'pilot_agreed',
                    'live',
                    'rejected',
                    'dormant',
                ])->default('new');

                // Sales assignment
                $table->unsignedBigInteger('assigned_to')->nullable(); // user_id of sales contact

                // Stage timestamps
                $table->dateTime('proposal_sent_at')->nullable();
                $table->dateTime('pilot_agreed_at')->nullable();
                $table->dateTime('went_live_at')->nullable();

                $table->text('rejection_reason')->nullable();
                $table->text('internal_notes')->nullable(); // admin-only

                // Traffic source
                $table->string('source', 50)->nullable(); // 'website_cta'|'direct'|'referral'|'event'

                $table->timestamps();

                $table->index(['tenant_id', 'stage']);
                $table->index('contact_email');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pilot_inquiries');
    }
};
