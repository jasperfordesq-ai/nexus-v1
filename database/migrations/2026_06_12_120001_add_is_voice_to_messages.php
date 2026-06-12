<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MessageService sets is_voice on every voice-message send (it is in
 * Message::$fillable and $casts) but the column was never added to the
 * messages table — the INSERT threw "unknown column" and EVERY voice
 * message send returned 400 UPLOAD_FAILED since 2026-03-28. Readers
 * (MessageSent broadcast, NotifyMessageReceived, GroupConversationService)
 * already expect the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('messages', 'is_voice')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            $table->boolean('is_voice')->default(false)->after('audio_url');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('messages', 'is_voice')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropColumn('is_voice');
            });
        }
    }
};
