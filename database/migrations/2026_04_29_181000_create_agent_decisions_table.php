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
 * AG61 — KI-Agenten: agent_decisions audit table.
 *
 * Every approve/reject/edit on an agent_proposals row is logged here for
 * compliance and traceability. We need this distinct from agent_proposals
 * because a single proposal can be edited multiple times before approval.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('agent_decisions')) {
            return;
        }

        Schema::create('agent_decisions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('proposal_id');
            $table->unsignedBigInteger('tenant_id');
            $table->enum('decision', ['approve', 'reject', 'edit']);
            $table->unsignedBigInteger('decided_by');
            $table->text('decision_note')->nullable();
            $table->json('edited_payload')->nullable();
            $table->dateTime('decided_at');
            $table->timestamps();

            $table->index(['proposal_id', 'decided_at']);
            $table->index(['tenant_id', 'decided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_decisions');
    }
};
