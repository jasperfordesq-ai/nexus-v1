<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add an assigned_to column to the gdpr_requests table so GDPR requests
 * can be assigned to a specific admin/staff member for processing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gdpr_requests')) {
            return;
        }

        if (Schema::hasColumn('gdpr_requests', 'assigned_to')) {
            return;
        }

        Schema::table('gdpr_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_to')->nullable()->after('processed_by');
            $table->index('assigned_to');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('gdpr_requests')) {
            return;
        }

        if (! Schema::hasColumn('gdpr_requests', 'assigned_to')) {
            return;
        }

        Schema::table('gdpr_requests', function (Blueprint $table) {
            $table->dropIndex(['assigned_to']);
            $table->dropColumn('assigned_to');
        });
    }
};
