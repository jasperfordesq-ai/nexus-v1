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
        Schema::table('newsletter_queue', function (Blueprint $table) {
            if (!Schema::hasColumn('newsletter_queue', 'name')) {
                $table->string('name', 255)->default('')->after('email');
            }
            if (!Schema::hasColumn('newsletter_queue', 'first_name')) {
                $table->string('first_name', 100)->default('')->after('name');
            }
            if (!Schema::hasColumn('newsletter_queue', 'last_name')) {
                $table->string('last_name', 100)->default('')->after('first_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_queue', function (Blueprint $table) {
            $table->dropColumn(['name', 'first_name', 'last_name']);
        });
    }
};
