<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $uniqueIndexName = 'uq_caring_nudges_dispatch_key';
    private string $consentIndexName = 'idx_caring_research_consent_status_user';

    public function up(): void
    {
        if (Schema::hasTable('caring_smart_nudges')) {
            if (! Schema::hasColumn('caring_smart_nudges', 'dispatch_key')) {
                Schema::table('caring_smart_nudges', function (Blueprint $table): void {
                    $table->string('dispatch_key', 96)->nullable()->after('source_type');
                });
            }

            if (! $this->indexExists('caring_smart_nudges', $this->uniqueIndexName)) {
                Schema::table('caring_smart_nudges', function (Blueprint $table): void {
                    $table->unique(['tenant_id', 'dispatch_key'], $this->uniqueIndexName);
                });
            }
        }

        if (Schema::hasTable('caring_research_consents')
            && ! $this->indexExists('caring_research_consents', $this->consentIndexName)
        ) {
            Schema::table('caring_research_consents', function (Blueprint $table): void {
                $table->index(['tenant_id', 'consent_status', 'user_id'], 'idx_caring_research_consent_status_user');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('caring_research_consents')
            && $this->indexExists('caring_research_consents', $this->consentIndexName)
        ) {
            Schema::table('caring_research_consents', function (Blueprint $table): void {
                $table->dropIndex('idx_caring_research_consent_status_user');
            });
        }

        if (! Schema::hasTable('caring_smart_nudges')) {
            return;
        }

        if ($this->indexExists('caring_smart_nudges', $this->uniqueIndexName)) {
            Schema::table('caring_smart_nudges', function (Blueprint $table): void {
                $table->dropUnique('uq_caring_nudges_dispatch_key');
            });
        }

        if (Schema::hasColumn('caring_smart_nudges', 'dispatch_key')) {
            Schema::table('caring_smart_nudges', function (Blueprint $table): void {
                $table->dropColumn('dispatch_key');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $index],
        ) !== [];
    }
};
