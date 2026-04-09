<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widen reactions.emoji column from varchar(10) to varchar(50).
 *
 * The original varchar(10) silently truncated reaction types like
 * 'time_credit' (11 chars) to 'time_credi', breaking toggle logic.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reactions')) {
            return;
        }
        Schema::table('reactions', function (Blueprint $table) {
            $table->string('emoji', 50)->change();
        });
    }

    public function down(): void
    {
        Schema::table('reactions', function (Blueprint $table) {
            $table->string('emoji', 10)->change();
        });
    }
};
