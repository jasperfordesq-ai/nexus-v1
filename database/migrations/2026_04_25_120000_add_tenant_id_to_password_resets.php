<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('password_resets')) {
            return;
        }

        if (!Schema::hasColumn('password_resets', 'tenant_id')) {
            Schema::table('password_resets', function (Blueprint $table) {
                $table->unsignedInteger('tenant_id')->nullable()->after('email');
                $table->index(['tenant_id', 'email'], 'password_resets_tenant_email_idx');
            });
        }

        // Purge any pre-migration tokens (tenant_id IS NULL). Tokens are short-
        // lived (1h) so this loses at most one expiring window, and prevents
        // a NULL token from being redeemed cross-tenant under the backward-
        // compat path in PasswordResetController.
        \Illuminate\Support\Facades\DB::table('password_resets')
            ->whereNull('tenant_id')
            ->delete();
    }

    public function down(): void
    {
        if (!Schema::hasTable('password_resets') || !Schema::hasColumn('password_resets', 'tenant_id')) {
            return;
        }

        Schema::table('password_resets', function (Blueprint $table) {
            $table->dropIndex('password_resets_tenant_email_idx');
            $table->dropColumn('tenant_id');
        });
    }
};
