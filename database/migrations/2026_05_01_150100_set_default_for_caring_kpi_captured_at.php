<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make captured_at / generated_at timestamps default to CURRENT_TIMESTAMP so
 * inserts that omit them don't fail under MySQL strict mode.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('caring_kpi_baselines') && Schema::hasColumn('caring_kpi_baselines', 'captured_at')) {
            DB::statement('ALTER TABLE caring_kpi_baselines MODIFY COLUMN captured_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP');
        }

        if (Schema::hasTable('caring_research_dataset_exports') && Schema::hasColumn('caring_research_dataset_exports', 'generated_at')) {
            DB::statement('ALTER TABLE caring_research_dataset_exports MODIFY COLUMN generated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('caring_kpi_baselines') && Schema::hasColumn('caring_kpi_baselines', 'captured_at')) {
            DB::statement('ALTER TABLE caring_kpi_baselines MODIFY COLUMN captured_at TIMESTAMP NOT NULL');
        }

        if (Schema::hasTable('caring_research_dataset_exports') && Schema::hasColumn('caring_research_dataset_exports', 'generated_at')) {
            DB::statement('ALTER TABLE caring_research_dataset_exports MODIFY COLUMN generated_at TIMESTAMP NOT NULL');
        }
    }
};
