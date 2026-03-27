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
        Schema::table('job_offers', function (Blueprint $table) {
            if (!Schema::hasColumn('job_offers', 'responded_at')) {
                $table->timestamp('responded_at')->nullable()->after('expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            if (Schema::hasColumn('job_offers', 'responded_at')) {
                $table->dropColumn('responded_at');
            }
        });
    }
};
