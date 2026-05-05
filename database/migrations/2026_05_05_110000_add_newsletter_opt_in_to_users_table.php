<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        if (!Schema::hasColumn('users', 'newsletter_opt_in')) {
            $afterNotificationPreferences = Schema::hasColumn('users', 'notification_preferences');

            Schema::table('users', function (Blueprint $table) use ($afterNotificationPreferences) {
                $column = $table->boolean('newsletter_opt_in')->default(false);

                if ($afterNotificationPreferences) {
                    $column->after('notification_preferences');
                }
            });
        }

        if (!$this->indexExists('users', 'idx_users_newsletter_opt_in')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['tenant_id', 'newsletter_opt_in'], 'idx_users_newsletter_opt_in');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        if ($this->indexExists('users', 'idx_users_newsletter_opt_in')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('idx_users_newsletter_opt_in');
            });
        }

        if (Schema::hasColumn('users', 'newsletter_opt_in')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('newsletter_opt_in');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->exists();
    }
};
