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
        Schema::table('member_vetting_attestations', function (Blueprint $table): void {
            $table->json('certification_codes')->nullable()->after('attestation_code');
            $table->text('scope_summary_encrypted')->nullable()->after('scope_identifier');
            $table->text('private_notes_encrypted')->nullable()->after('scope_summary_encrypted');
            $table->date('review_due_at')->nullable()->after('private_notes_encrypted');
            $table->date('authority_expires_at')->nullable()->after('review_due_at');
            $table->dateTime('renewal_reminder_90_sent_at')->nullable()->after('authority_expires_at');
            $table->dateTime('renewal_reminder_30_sent_at')->nullable()->after('renewal_reminder_90_sent_at');
            $table->dateTime('renewal_reminder_7_sent_at')->nullable()->after('renewal_reminder_30_sent_at');
            $table->dateTime('renewal_due_notified_at')->nullable()->after('renewal_reminder_7_sent_at');
            $table->dateTime('expiry_notified_at')->nullable()->after('renewal_due_notified_at');

            $table->index(
                ['tenant_id', 'decision', 'review_due_at'],
                'idx_vetting_review_due'
            );
            $table->index(
                ['tenant_id', 'decision', 'authority_expires_at'],
                'idx_vetting_authority_expiry'
            );
        });
    }

    public function down(): void
    {
        Schema::table('member_vetting_attestations', function (Blueprint $table): void {
            $table->dropIndex('idx_vetting_review_due');
            $table->dropIndex('idx_vetting_authority_expiry');
            $table->dropColumn([
                'certification_codes',
                'scope_summary_encrypted',
                'private_notes_encrypted',
                'review_due_at',
                'authority_expires_at',
                'renewal_reminder_90_sent_at',
                'renewal_reminder_30_sent_at',
                'renewal_reminder_7_sent_at',
                'renewal_due_notified_at',
                'expiry_notified_at',
            ]);
        });
    }
};
