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
        if (!Schema::hasTable('reviews')) {
            return;
        }

        Schema::table('reviews', function (Blueprint $table): void {
            if (!Schema::hasColumn('reviews', 'external_partner_id')) {
                $table->unsignedBigInteger('external_partner_id')
                    ->nullable()
                    ->after('federation_transaction_id');
            }

            if (!Schema::hasColumn('reviews', 'external_id')) {
                $table->string('external_id', 128)
                    ->nullable()
                    ->after('external_partner_id');
            }

            if (!Schema::hasColumn('reviews', 'notification_sent_at')) {
                $table->timestamp('notification_sent_at')
                    ->nullable()
                    ->after('show_cross_tenant');
            }

            if (!Schema::hasColumn('reviews', 'notification_claimed_at')) {
                $table->timestamp('notification_claimed_at')
                    ->nullable()
                    ->after('notification_sent_at');
            }

            if (!Schema::hasColumn('reviews', 'email_sent_at')) {
                $table->timestamp('email_sent_at')
                    ->nullable()
                    ->after('notification_claimed_at');
            }

            if (!Schema::hasColumn('reviews', 'email_claimed_at')) {
                $table->timestamp('email_claimed_at')
                    ->nullable()
                    ->after('email_sent_at');
            }

            if (!Schema::hasColumn('reviews', 'email_skipped_at')) {
                $table->timestamp('email_skipped_at')
                    ->nullable()
                    ->after('email_claimed_at');
            }

            if (!Schema::hasColumn('reviews', 'email_failed_at')) {
                $table->timestamp('email_failed_at')
                    ->nullable()
                    ->after('email_skipped_at');
            }

            if (!Schema::hasColumn('reviews', 'email_last_error')) {
                $table->text('email_last_error')
                    ->nullable()
                    ->after('email_failed_at');
            }
        });

        $this->addIndexIfMissing(
            'reviews',
            'uk_reviews_tenant_partner_external',
            'CREATE UNIQUE INDEX `uk_reviews_tenant_partner_external` ON `reviews` (`tenant_id`, `external_partner_id`, `external_id`)'
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('reviews')) {
            return;
        }

        $this->dropIndexIfExists('reviews', 'uk_reviews_tenant_partner_external');

        Schema::table('reviews', function (Blueprint $table): void {
            foreach (['email_last_error', 'email_failed_at', 'email_skipped_at', 'email_claimed_at', 'email_sent_at', 'notification_claimed_at', 'notification_sent_at', 'external_id', 'external_partner_id'] as $column) {
                if (Schema::hasColumn('reviews', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function addIndexIfMissing(string $table, string $index, string $sql): void
    {
        $database = DB::connection()->getDatabaseName();
        $exists = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index]
        );

        if ((int) ($exists->c ?? 0) === 0) {
            DB::statement($sql);
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        $database = DB::connection()->getDatabaseName();
        $exists = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index]
        );

        if ((int) ($exists->c ?? 0) > 0) {
            DB::statement("DROP INDEX `{$index}` ON `{$table}`");
        }
    }
};
