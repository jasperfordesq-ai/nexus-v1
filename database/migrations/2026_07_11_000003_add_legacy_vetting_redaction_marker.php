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
        if (! Schema::hasTable('vetting_records')
            || Schema::hasColumn('vetting_records', 'legacy_sensitive_metadata_redacted')) {
            return;
        }

        $hasNotesColumn = Schema::hasColumn('vetting_records', 'notes');
        Schema::table('vetting_records', function (Blueprint $table) use ($hasNotesColumn): void {
            // Content-free operational disposition only. This deliberately
            // records neither certificate data nor which fields once existed.
            $column = $table->boolean('legacy_sensitive_metadata_redacted')->default(false);
            if ($hasNotesColumn) {
                $column->after('notes');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('vetting_records')
            || ! Schema::hasColumn('vetting_records', 'legacy_sensitive_metadata_redacted')) {
            return;
        }

        Schema::table('vetting_records', function (Blueprint $table): void {
            $table->dropColumn('legacy_sensitive_metadata_redacted');
        });
    }
};
