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
        Schema::table('group_media', function (Blueprint $table): void {
            if (! Schema::hasColumn('group_media', 'original_name')) {
                $table->string('original_name', 255)->nullable()->after('media_type');
            }
            if (! Schema::hasColumn('group_media', 'mime_type')) {
                $table->string('mime_type', 100)->nullable()->after('original_name');
            }
            if (! Schema::hasColumn('group_media', 'updated_at')) {
                $table->dateTime('updated_at')->nullable()->after('created_at');
            }
        });

        DB::table('group_media')->whereNull('updated_at')->update([
            'updated_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('group_media', function (Blueprint $table): void {
            foreach (['updated_at', 'mime_type', 'original_name'] as $column) {
                if (Schema::hasColumn('group_media', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
