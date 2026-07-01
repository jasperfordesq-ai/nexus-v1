<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add 'postmark' to the email_log.provider enum.
 *
 * The provider column was enum('sendgrid','gmail_api','smtp'); once platform
 * email routes through Postmark, Mailer::logEmail() writes provider='postmark'.
 * Under MySQL/MariaDB non-strict mode an out-of-range enum value is silently
 * coerced to '' — so Postmark sends were logged with an empty provider, which
 * broke PostmarkWebhookController row matching (WHERE provider='postmark') and
 * therefore delivery/open/bounce status updates on email_log.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('email_log') || !Schema::hasColumn('email_log', 'provider')) {
            return;
        }

        $col = DB::selectOne("SHOW COLUMNS FROM email_log WHERE Field = 'provider'");
        if ($col && is_string($col->Type ?? null) && !str_contains($col->Type, "'postmark'")) {
            DB::statement("ALTER TABLE email_log MODIFY provider ENUM('sendgrid','gmail_api','smtp','postmark') NULL DEFAULT NULL");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('email_log') || !Schema::hasColumn('email_log', 'provider')) {
            return;
        }

        // Enum-shrink is lossy; only revert when no rows depend on 'postmark'.
        $inUse = DB::table('email_log')->where('provider', 'postmark')->exists();
        if (!$inUse) {
            DB::statement("ALTER TABLE email_log MODIFY provider ENUM('sendgrid','gmail_api','smtp') NULL DEFAULT NULL");
        }
    }
};
