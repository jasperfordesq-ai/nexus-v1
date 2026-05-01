<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the standalone single-column tenant_id index on caring_municipality_feedback —
 * already covered by composite indexes leading with tenant_id.
 */
return new class extends Migration
{
    private string $indexName = 'caring_municipality_feedback_tenant_id_index';

    public function up(): void
    {
        if (! Schema::hasTable('caring_municipality_feedback')) {
            return;
        }

        try {
            Schema::table('caring_municipality_feedback', function (Blueprint $table): void {
                $table->dropIndex($this->indexName);
            });
        } catch (\Throwable $e) {
            Log::info('caring_municipality_feedback tenant_id index already absent — skipping drop', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('caring_municipality_feedback')) {
            return;
        }

        try {
            Schema::table('caring_municipality_feedback', function (Blueprint $table): void {
                $table->index('tenant_id', $this->indexName);
            });
        } catch (\Throwable $e) {
            Log::info('caring_municipality_feedback tenant_id index already present — skipping add', [
                'error' => $e->getMessage(),
            ]);
        }
    }
};
