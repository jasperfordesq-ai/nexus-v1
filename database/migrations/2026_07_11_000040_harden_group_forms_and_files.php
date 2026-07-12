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
    public function up(): void
    {
        // Templates and API contracts have long advertised `secret`; make the
        // canonical group column capable of preserving it instead of silently
        // coercing a selected secret template to public/private.
        DB::statement(
            "ALTER TABLE `groups` MODIFY `visibility` ENUM('public','private','secret') NOT NULL DEFAULT 'public'",
        );

        if (! Schema::hasColumn('group_files', 'updated_at')) {
            Schema::table('group_files', function (Blueprint $table): void {
                $table->dateTime('updated_at')->nullable()->after('created_at');
            });
            DB::table('group_files')->whereNull('updated_at')->update([
                'updated_at' => DB::raw('created_at'),
            ]);
        }
    }

    public function down(): void
    {
        // Expand-only migration: both additions are now durable data contracts.
        // Rewriting `secret` groups as `private` loses their access semantics,
        // while narrowing the enum would make a later re-apply unable to recover
        // the original values. Dropping group_files.updated_at would likewise
        // discard persisted metadata. Refuse rollback so Laravel also retains
        // the migration ledger row instead of treating a no-op as rolled back.
        throw new LogicException(
            'Migration 2026_07_11_000040 is expand-only and cannot be rolled back safely.',
        );
    }
};
