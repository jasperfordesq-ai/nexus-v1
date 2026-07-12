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
    private const INDEX = 'idx_notification_queue_event_context';

    public function up(): void
    {
        if (! Schema::hasTable('notification_queue')) {
            throw new LogicException(
                'event_notification_queue_context_prerequisite_missing:notification_queue',
            );
        }

        if (! Schema::hasColumn('notification_queue', 'event_id')) {
            Schema::table('notification_queue', function (Blueprint $table): void {
                // Intentionally no FK: Events use an integer global primary key
                // without a unique (tenant_id, id) key, and queue evidence must
                // survive event deletion long enough to be suppressed/audited.
                $table->integer('event_id')->nullable();
            });
        }

        if (! Schema::hasIndex('notification_queue', self::INDEX)) {
            Schema::table('notification_queue', function (Blueprint $table): void {
                $table->index(
                    ['tenant_id', 'event_id', 'status', 'frequency'],
                    self::INDEX,
                );
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('notification_queue')
            || ! Schema::hasColumn('notification_queue', 'event_id')) {
            return;
        }

        if (DB::table('notification_queue')->whereNotNull('event_id')->exists()) {
            throw new \RuntimeException(
                'event_notification_queue_context_rollback_refused_evidence_exists',
            );
        }

        $hasIndex = Schema::hasIndex('notification_queue', self::INDEX);
        Schema::table('notification_queue', function (Blueprint $table) use ($hasIndex): void {
            if ($hasIndex) {
                $table->dropIndex(self::INDEX);
            }
            $table->dropColumn('event_id');
        });
    }
};
