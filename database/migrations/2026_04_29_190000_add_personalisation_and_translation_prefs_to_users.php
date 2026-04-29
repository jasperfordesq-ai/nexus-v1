<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AG35 + AG38 — add per-user prefs for personalised feed/listings sort and
 * UGC auto-translation.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'prefers_chronological_feed')) {
                $table->boolean('prefers_chronological_feed')->default(false)->after('preferred_language');
            }
            if (!Schema::hasColumn('users', 'auto_translate_ugc')) {
                $table->boolean('auto_translate_ugc')->default(false)->after('prefers_chronological_feed');
            }
            if (!Schema::hasColumn('users', 'auto_translate_target_locale')) {
                $table->string('auto_translate_target_locale', 5)->nullable()->after('auto_translate_ugc');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            foreach (['prefers_chronological_feed', 'auto_translate_ugc', 'auto_translate_target_locale'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
