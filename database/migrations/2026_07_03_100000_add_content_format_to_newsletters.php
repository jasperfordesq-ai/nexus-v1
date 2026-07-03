<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-format newsletter authoring (enterprise HTML email upgrade, Phase 1).
 *
 * content_format tells the render pipeline how to treat `content`:
 *  - richtext  (default) — Lexical editor HTML, wrapped in the branded shell (legacy behavior)
 *  - html                — pasted/designed raw HTML, injected verbatim (+ unsubscribe/tracking)
 *  - plaintext           — raw text, escaped at render, sent text/plain-first
 *  - builder             — drag-and-drop builder export (Phase 2); renders like html
 *
 * design_json stores the visual builder's project state (Phase 2 — GrapesJS/MJML)
 * so builder designs reopen losslessly. Added now to avoid a second migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['newsletters', 'newsletter_templates'] as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'content_format')) {
                    $table->enum('content_format', ['plaintext', 'richtext', 'html', 'builder'])
                        ->default('richtext')
                        ->after('content');
                }
                if (!Schema::hasColumn($tableName, 'design_json')) {
                    $table->longText('design_json')->nullable()->after('content_format');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['newsletters', 'newsletter_templates'] as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'design_json')) {
                    $table->dropColumn('design_json');
                }
                if (Schema::hasColumn($tableName, 'content_format')) {
                    $table->dropColumn('content_format');
                }
            });
        }
    }
};
