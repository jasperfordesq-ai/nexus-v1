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
        if (Schema::hasTable('notification_queue')) {
            DB::statement("ALTER TABLE notification_queue MODIFY frequency ENUM('instant','daily','weekly','monthly') DEFAULT 'daily'");
            DB::table('notification_queue')
                ->where('frequency', 'weekly')
                ->update(['frequency' => 'monthly']);
        }

        if (Schema::hasTable('notification_settings')) {
            DB::statement("ALTER TABLE notification_settings MODIFY frequency ENUM('instant','daily','weekly','monthly','off') NOT NULL DEFAULT 'instant'");
            DB::table('notification_settings')
                ->where('frequency', 'weekly')
                ->update(['frequency' => 'monthly']);
        }

        if (Schema::hasTable('match_preferences')) {
            DB::statement("ALTER TABLE match_preferences MODIFY notification_frequency ENUM('daily','weekly','monthly','fortnightly','never') DEFAULT 'monthly'");
            DB::table('match_preferences')
                ->where('notification_frequency', 'weekly')
                ->update(['notification_frequency' => 'monthly']);
        }

        if (Schema::hasTable('tenant_settings')) {
            DB::table('tenant_settings')
                ->where('setting_key', 'caring.civic_digest.tenant_default_cadence')
                ->where('setting_value', 'weekly')
                ->update(['setting_value' => 'monthly']);

            $rows = DB::table('tenant_settings')
                ->where('setting_key', 'like', 'caring.civic_digest.user_prefs.%')
                ->where('setting_value', 'like', '%"cadence":"weekly"%')
                ->select(['id', 'setting_value'])
                ->get();

            foreach ($rows as $row) {
                $decoded = json_decode((string) $row->setting_value, true);
                if (!is_array($decoded) || ($decoded['cadence'] ?? null) !== 'weekly') {
                    continue;
                }
                $decoded['cadence'] = 'monthly';
                DB::table('tenant_settings')
                    ->where('id', $row->id)
                    ->update([
                        'setting_value' => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'updated_at' => now(),
                    ]);
            }
        }

        if (Schema::hasTable('tenants') && Schema::hasColumn('tenants', 'configuration')) {
            $tenants = DB::table('tenants')
                ->where('configuration', 'like', '%"default_frequency":"weekly"%')
                ->select(['id', 'configuration'])
                ->get();

            foreach ($tenants as $tenant) {
                $decoded = json_decode((string) $tenant->configuration, true);
                if (!is_array($decoded) || ($decoded['notifications']['default_frequency'] ?? null) !== 'weekly') {
                    continue;
                }
                $decoded['notifications']['default_frequency'] = 'monthly';
                DB::table('tenants')
                    ->where('id', $tenant->id)
                    ->update(['configuration' => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notification_queue')) {
            DB::table('notification_queue')
                ->where('frequency', 'monthly')
                ->update(['frequency' => 'weekly']);
            DB::statement("ALTER TABLE notification_queue MODIFY frequency ENUM('instant','daily','weekly') DEFAULT 'daily'");
        }

        if (Schema::hasTable('notification_settings')) {
            DB::table('notification_settings')
                ->where('frequency', 'monthly')
                ->update(['frequency' => 'weekly']);
            DB::statement("ALTER TABLE notification_settings MODIFY frequency ENUM('instant','daily','weekly','off') NOT NULL DEFAULT 'instant'");
        }

        if (Schema::hasTable('match_preferences')) {
            DB::table('match_preferences')
                ->where('notification_frequency', 'monthly')
                ->update(['notification_frequency' => 'weekly']);
            DB::statement("ALTER TABLE match_preferences MODIFY notification_frequency ENUM('daily','weekly','fortnightly','never') DEFAULT 'fortnightly'");
        }
    }
};
