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
        if (Schema::hasTable('job_templates') && !Schema::hasColumn('job_templates', 'template_type')) {
            Schema::table('job_templates', function (Blueprint $table) {
                $table->string('template_type', 20)->default('job_posting')->after('user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('job_templates', 'template_type')) {
            Schema::table('job_templates', function (Blueprint $table) {
                $table->dropColumn('template_type');
            });
        }
    }
};
