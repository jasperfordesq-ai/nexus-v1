<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores previous password hashes per user so recent passwords cannot be
 * reused (IT-Sec-07 style password-history control). Only hashes are stored,
 * never plaintext; rows beyond the configured history depth are pruned by
 * PasswordHistoryService. Guarded with Schema::hasTable for idempotency.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_password_history')) {
            return;
        }

        Schema::create('user_password_history', function (Blueprint $table) {
            $table->id();
            // users.id / users.tenant_id are signed int(11) — match the type
            // so future FK additions stay possible.
            $table->integer('tenant_id');
            $table->integer('user_id');
            $table->string('password_hash');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'user_id', 'created_at'], 'upw_hist_tenant_user_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_password_history');
    }
};
