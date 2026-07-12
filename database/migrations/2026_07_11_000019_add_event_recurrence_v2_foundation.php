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
    /**
     * Expand-only recurrence metadata. Legacy writers can continue omitting
     * every new column while the v2 engine is disabled.
     */
    public function up(): void
    {
        if (! Schema::hasTable('event_recurrence_rules')) {
            return;
        }

        $addExdates = ! Schema::hasColumn('event_recurrence_rules', 'exdates');
        $addRdates = ! Schema::hasColumn('event_recurrence_rules', 'rdates');
        $addEngine = ! Schema::hasColumn('event_recurrence_rules', 'recurrence_engine');
        $addVersion = ! Schema::hasColumn('event_recurrence_rules', 'recurrence_engine_version');
        $addHash = ! Schema::hasColumn('event_recurrence_rules', 'rule_hash');

        if ($addExdates || $addRdates || $addEngine || $addVersion || $addHash) {
            Schema::table('event_recurrence_rules', function (Blueprint $table) use (
                $addExdates,
                $addRdates,
                $addEngine,
                $addVersion,
                $addHash,
            ): void {
                if ($addExdates) {
                    $table->json('exdates')->nullable()->after('rrule');
                }
                if ($addRdates) {
                    $table->json('rdates')->nullable()->after('exdates');
                }
                if ($addEngine) {
                    $table->string('recurrence_engine', 32)->nullable()->after('rdates');
                }
                if ($addVersion) {
                    $table->string('recurrence_engine_version', 32)->nullable()->after('recurrence_engine');
                }
                if ($addHash) {
                    $table->char('rule_hash', 64)->nullable()->after('recurrence_engine_version');
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_recurrence_rules')) {
            return;
        }

        $columns = array_values(array_filter([
            'exdates',
            'rdates',
            'recurrence_engine',
            'recurrence_engine_version',
            'rule_hash',
        ], static fn (string $column): bool => Schema::hasColumn('event_recurrence_rules', $column)));

        if ($columns !== []) {
            Schema::table('event_recurrence_rules', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
