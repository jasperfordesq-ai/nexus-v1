<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_settings')) {
            return;
        }

        DB::table('email_settings')
            ->where('tenant_id', 11)
            ->whereIn('setting_key', [
                'sendgrid_api_key',
                'sendgrid_from_email',
                'sendgrid_from_name',
            ])
            ->delete();

        DB::table('email_settings')->updateOrInsert(
            [
                'tenant_id' => 11,
                'setting_key' => 'email_provider',
            ],
            [
                'setting_value' => 'platform_default',
                'is_encrypted' => 0,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        // Intentionally not reversible: deleted tenant-specific SendGrid secrets
        // must never be reconstructed or reintroduced by rollback.
    }
};
