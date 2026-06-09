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
        if (!Schema::hasTable('regional_analytics_subscriptions')) {
            return;
        }

        if (!Schema::hasColumn('regional_analytics_subscriptions', 'subscription_token_hash')) {
            Schema::table('regional_analytics_subscriptions', function (Blueprint $table): void {
                $table->string('subscription_token_hash', 64)->nullable()->unique('regional_analytics_token_hash_unique')->after('subscription_token');
            });
        }

        $rows = DB::table('regional_analytics_subscriptions')
            ->whereNull('subscription_token_hash')
            ->whereNotNull('subscription_token')
            ->get(['id', 'subscription_token']);

        foreach ($rows as $row) {
            $hash = hash('sha256', (string) $row->subscription_token);
            DB::table('regional_analytics_subscriptions')
                ->where('id', $row->id)
                ->update([
                    'subscription_token_hash' => $hash,
                    'subscription_token' => 'token-ref-' . $row->id . '-' . substr($hash, 0, 16),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('regional_analytics_subscriptions')
            || !Schema::hasColumn('regional_analytics_subscriptions', 'subscription_token_hash')) {
            return;
        }

        Schema::table('regional_analytics_subscriptions', function (Blueprint $table): void {
            $table->dropUnique('regional_analytics_token_hash_unique');
            $table->dropColumn('subscription_token_hash');
        });
    }
};
