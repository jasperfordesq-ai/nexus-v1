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
        Schema::table('polls', function (Blueprint $table) {
            if (!Schema::hasColumn('polls', 'event_id')) {
                $table->unsignedInteger('event_id')->nullable()->after('user_id');
                $table->index('event_id', 'polls_event_id_index');
                $table->foreign('event_id')
                    ->references('id')
                    ->on('events')
                    ->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('polls', function (Blueprint $table) {
            if (Schema::hasColumn('polls', 'event_id')) {
                $table->dropForeign(['event_id']);
                $table->dropIndex('polls_event_id_index');
                $table->dropColumn('event_id');
            }
        });
    }
};
