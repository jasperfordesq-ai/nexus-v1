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
        Schema::table('federation_partnerships', function (Blueprint $table) {
            if (!Schema::hasColumn('federation_partnerships', 'suspended_by')) {
                $table->unsignedInteger('suspended_by')->nullable()->default(null)->after('termination_reason')
                    ->comment('User ID of the user who suspended the partnership');
            }
            if (!Schema::hasColumn('federation_partnerships', 'suspended_by_tenant_id')) {
                $table->unsignedInteger('suspended_by_tenant_id')->nullable()->default(null)->after('suspended_by')
                    ->comment('Tenant ID of the party that suspended the partnership');
            }
        });
    }

    public function down(): void
    {
        Schema::table('federation_partnerships', function (Blueprint $table) {
            if (Schema::hasColumn('federation_partnerships', 'suspended_by_tenant_id')) {
                $table->dropColumn('suspended_by_tenant_id');
            }
            if (Schema::hasColumn('federation_partnerships', 'suspended_by')) {
                $table->dropColumn('suspended_by');
            }
        });
    }
};
