<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * M7: Drop the duplicate empty `activity_logs` table.
 *
 * The canonical table is `activity_log` (singular). `activity_logs` (plural)
 * was created as a stub with only (id, created_at) columns and contains no
 * data. Keeping both causes confusion and risks future code accidentally
 * writing to the wrong table.
 *
 * Safety: Count rows before dropping. If any rows exist, skip the drop and
 * log a warning so a human can investigate.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('activity_logs')) {
            return;
        }

        $count = (int) DB::table('activity_logs')->count();

        if ($count > 0) {
            Log::warning('M7: Skipped dropping activity_logs — table is non-empty', [
                'row_count' => $count,
                'action_required' => 'Investigate where these rows came from before dropping. Table was supposed to be an abandoned duplicate.',
            ]);
            return;
        }

        Schema::dropIfExists('activity_logs');
        Log::info('M7: Dropped empty duplicate table activity_logs (canonical table is activity_log)');
    }

    public function down(): void
    {
        // Intentionally empty — this is a cleanup of an abandoned duplicate table.
        // If a rollback is ever required, create a new migration that recreates
        // the stub table from the schema dump.
    }
};
