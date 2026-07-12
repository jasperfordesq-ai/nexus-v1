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
    public function up(): void
    {
        if (Schema::hasTable('events')
            && ! Schema::hasColumn('events', 'calendar_sequence')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->unsignedBigInteger('calendar_sequence')
                    ->default(0)
                    ->after('lifecycle_version')
                    ->comment('Monotonic revision for calendar reconciliation');
            });
        }

        if (! Schema::hasTable('event_calendar_feed_tokens')) {
            Schema::create('event_calendar_feed_tokens', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->integer('tenant_id');
                $table->integer('user_id');
                $table->char('token_hash', 64);
                $table->string('token_prefix', 16);
                $table->string('label', 100)->nullable();
                $table->string('locale', 10)->default('en');
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->unique('token_hash', 'uq_event_calendar_feed_token_hash');
                $table->index(
                    ['tenant_id', 'user_id', 'revoked_at', 'id'],
                    'idx_event_calendar_feed_token_owner',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_calendar_feed_tokens');

        if (Schema::hasTable('events')
            && Schema::hasColumn('events', 'calendar_sequence')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->dropColumn('calendar_sequence');
            });
        }
    }
};
