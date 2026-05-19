<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vol_donations')) {
            return;
        }

        Schema::table('vol_donations', function (Blueprint $table): void {
            if (!Schema::hasColumn('vol_donations', 'receipt_email_sent_at')) {
                $table->timestamp('receipt_email_sent_at')->nullable()->after('stripe_payment_intent_id');
            }
            if (!Schema::hasColumn('vol_donations', 'receipt_email_failed_at')) {
                $table->timestamp('receipt_email_failed_at')->nullable()->after('receipt_email_sent_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('vol_donations')) {
            return;
        }

        Schema::table('vol_donations', function (Blueprint $table): void {
            if (Schema::hasColumn('vol_donations', 'receipt_email_failed_at')) {
                $table->dropColumn('receipt_email_failed_at');
            }
            if (Schema::hasColumn('vol_donations', 'receipt_email_sent_at')) {
                $table->dropColumn('receipt_email_sent_at');
            }
        });
    }
};
