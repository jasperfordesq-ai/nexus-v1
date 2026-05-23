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
        Schema::table('regional_analytics_subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('regional_analytics_subscriptions', 'contact_language')) {
                $table->string('contact_language')->nullable()->default(null)->after('contact_email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('regional_analytics_subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('regional_analytics_subscriptions', 'contact_language')) {
                $table->dropColumn('contact_language');
            }
        });
    }
};
