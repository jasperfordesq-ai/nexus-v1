<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('event_domain_outbox')
            || Schema::hasColumn('event_domain_outbox', 'aggregate_stream')) {
            return;
        }

        Schema::table('event_domain_outbox', function (Blueprint $table): void {
            // Existing rows remain conservatively ordered as one event stream.
            // New writers persist bounded lifecycle/registration/etc. streams.
            $table->string('aggregate_stream', 191)
                ->default('event')
                ->after('event_id');
            $table->index(
                ['tenant_id', 'event_id', 'aggregate_stream', 'aggregate_version', 'id'],
                'idx_event_outbox_stream',
            );
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('event_domain_outbox')
            || !Schema::hasColumn('event_domain_outbox', 'aggregate_stream')) {
            return;
        }

        $hasStreamIndex = Schema::hasIndex('event_domain_outbox', 'idx_event_outbox_stream');
        Schema::table('event_domain_outbox', function (Blueprint $table) use ($hasStreamIndex): void {
            if ($hasStreamIndex) {
                $table->dropIndex('idx_event_outbox_stream');
            }
            $table->dropColumn('aggregate_stream');
        });
    }
};
