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
        // Add balance column to vol_organizations for org wallet
        if (!Schema::hasColumn('vol_organizations', 'balance')) {
            Schema::table('vol_organizations', function (Blueprint $table) {
                $table->decimal('balance', 10, 2)->default(0.00)->after('auto_pay_enabled');
                $table->index(['tenant_id', 'balance'], 'idx_vol_org_balance');
            });
        }

        // Create vol_org_transactions table for wallet audit trail
        if (!Schema::hasTable('vol_org_transactions')) {
            Schema::create('vol_org_transactions', function (Blueprint $table) {
                $table->id();
                $table->integer('tenant_id');
                $table->integer('vol_organization_id');
                $table->integer('user_id')->nullable()->comment('Volunteer or admin who triggered this');
                $table->integer('vol_log_id')->nullable()->comment('Links to approved hours entry');
                $table->enum('type', ['deposit', 'withdrawal', 'volunteer_payment', 'admin_adjustment']);
                $table->decimal('amount', 10, 2)->comment('Positive=credit to org, Negative=debit from org');
                $table->decimal('balance_after', 10, 2)->comment('Org balance after this transaction');
                $table->text('description')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index('tenant_id', 'idx_vot_tenant');
                $table->index('vol_organization_id', 'idx_vot_org');
                $table->index('user_id', 'idx_vot_user');
                $table->index('vol_log_id', 'idx_vot_log');
                $table->index('created_at', 'idx_vot_date');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vol_org_transactions');

        if (Schema::hasColumn('vol_organizations', 'balance')) {
            Schema::table('vol_organizations', function (Blueprint $table) {
                $table->dropIndex('idx_vol_org_balance');
                $table->dropColumn('balance');
            });
        }
    }
};
