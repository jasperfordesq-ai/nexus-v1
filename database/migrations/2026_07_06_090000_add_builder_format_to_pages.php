<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store the authoring mode and GrapesJS project state for tenant CMS pages.
 *
 * The published body remains in `content`, so existing page rendering and menu
 * behaviour keep working. `design_json` is only for reopening the visual editor
 * without losing canvas components, CSS rules, devices, or block state.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pages')) {
            return;
        }

        Schema::table('pages', function (Blueprint $table) {
            if (!Schema::hasColumn('pages', 'content_format')) {
                // Keep default on the same line for the migration safety scanner.
                $table->enum('content_format', ['plaintext', 'richtext', 'html', 'builder'])->default('richtext')
                    ->after('content');
            }
            if (!Schema::hasColumn('pages', 'design_json')) {
                $table->longText('design_json')->nullable()->after('content_format');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('pages')) {
            return;
        }

        Schema::table('pages', function (Blueprint $table) {
            if (Schema::hasColumn('pages', 'design_json')) {
                $table->dropColumn('design_json');
            }
            if (Schema::hasColumn('pages', 'content_format')) {
                $table->dropColumn('content_format');
            }
        });
    }
};
