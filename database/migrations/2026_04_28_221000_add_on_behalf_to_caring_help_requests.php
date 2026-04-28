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
        if (Schema::hasTable('caring_help_requests') && !Schema::hasColumn('caring_help_requests', 'is_on_behalf')) {
            Schema::table('caring_help_requests', function (Blueprint $table) {
                $table->boolean('is_on_behalf')->default(false)->after('category_id');
                $table->unsignedInteger('requested_by_id')->nullable()->after('is_on_behalf');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('caring_help_requests')) {
            Schema::table('caring_help_requests', function (Blueprint $table) {
                if (Schema::hasColumn('caring_help_requests', 'requested_by_id')) {
                    $table->dropColumn('requested_by_id');
                }
                if (Schema::hasColumn('caring_help_requests', 'is_on_behalf')) {
                    $table->dropColumn('is_on_behalf');
                }
            });
        }
    }
};
