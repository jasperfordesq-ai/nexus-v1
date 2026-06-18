<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Group conversations send messages via GroupConversationService::sendGroupMessage,
 * which writes messages.receiver_id = 0 as a sentinel (a group message has no single
 * receiver, and the column is NOT NULL). The legacy `messages_ibfk_3` foreign key
 * (receiver_id -> users.id) rejects that sentinel, so EVERY group message — and any
 * feature built on group conversations (reactions, group sends) — 500s on both the
 * React and accessible frontends. This FK predates group messaging and is
 * incompatible with the shipped service design; drop it (guarded + idempotent).
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::selectOne(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages'
               AND CONSTRAINT_NAME = 'messages_ibfk_3' AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
        );

        if ($exists !== null) {
            DB::statement('ALTER TABLE `messages` DROP FOREIGN KEY `messages_ibfk_3`');
        }
    }

    public function down(): void
    {
        // Intentionally NOT re-added: the receiver_id = 0 group sentinel rows that
        // exist once group messaging is used would make re-adding the FK fail.
    }
};
