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
        Schema::table('groups', function (Blueprint $table): void {
            if (! Schema::hasColumn('groups', 'template_id')) {
                $table->unsignedInteger('template_id')->nullable()->after('type_id');
                $table->index(['tenant_id', 'template_id'], 'idx_groups_tenant_template');
            }
            if (! Schema::hasColumn('groups', 'template_features')) {
                $table->json('template_features')->nullable()->after('template_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table): void {
            if (Schema::hasColumn('groups', 'template_features')) {
                $table->dropColumn('template_features');
            }
            if (Schema::hasColumn('groups', 'template_id')) {
                $table->dropIndex('idx_groups_tenant_template');
                $table->dropColumn('template_id');
            }
        });
    }
};
