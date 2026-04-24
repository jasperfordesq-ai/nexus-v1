<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clear cached NexusScore data so insights regenerate with fixed PHP translations.
        if (Schema::hasTable('nexus_score_cache')) {
            DB::table('nexus_score_cache')->truncate();
        }
    }

    public function down(): void {}
};
