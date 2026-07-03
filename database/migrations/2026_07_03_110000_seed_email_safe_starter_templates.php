<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use App\Services\EmailHtmlSanitizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed a gallery of professionally-designed, email-safe starter templates into
 * every active tenant's newsletter_templates (category='starter',
 * content_format='html'). Gives the new template gallery real designs to start
 * from — Announcement, Community Digest, Event Invite, Welcome, Re-engagement,
 * Simple Letter.
 *
 * Template bodies live in database/seeders/data/email_starter_templates.php so
 * they can be reused by a future seeder/refresh. Each is run through the
 * email-safe sanitizer at insert as defense-in-depth.
 *
 * Idempotent: skips any (tenant, name, category='starter') that already exists,
 * so re-running is a safe no-op and it won't clobber a tenant's edited copy.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('newsletter_templates') || !Schema::hasTable('tenants')) {
            return;
        }
        if (!Schema::hasColumn('newsletter_templates', 'content_format')) {
            return; // multi-format migration hasn't run yet — nothing to seed into
        }

        $path = database_path('seeders/data/email_starter_templates.php');
        if (!is_file($path)) {
            return;
        }

        /** @var array<int, array<string, mixed>> $templates */
        $templates = require $path;
        if (!is_array($templates) || $templates === []) {
            return;
        }

        // Sanitize each body once (not per-tenant).
        foreach ($templates as &$tpl) {
            $tpl['content'] = EmailHtmlSanitizer::sanitizeForFormat(
                (string) ($tpl['content'] ?? ''),
                (string) ($tpl['content_format'] ?? 'html')
            );
        }
        unset($tpl);

        $tenantIds = DB::table('tenants')->where('is_active', 1)->pluck('id');

        $inserted = 0;
        foreach ($tenantIds as $tenantId) {
            foreach ($templates as $tpl) {
                $name = (string) ($tpl['name'] ?? '');
                if ($name === '') {
                    continue;
                }

                $exists = DB::table('newsletter_templates')
                    ->where('tenant_id', $tenantId)
                    ->where('name', $name)
                    ->where('category', 'starter')
                    ->exists();
                if ($exists) {
                    continue;
                }

                try {
                    DB::table('newsletter_templates')->insert([
                        'tenant_id' => $tenantId,
                        'name' => $name,
                        'description' => (string) ($tpl['description'] ?? ''),
                        'category' => 'starter',
                        'subject' => (string) ($tpl['subject'] ?? ''),
                        'preview_text' => (string) ($tpl['preview_text'] ?? ''),
                        'content' => (string) ($tpl['content'] ?? ''),
                        'content_format' => (string) ($tpl['content_format'] ?? 'html'),
                        'thumbnail' => $tpl['thumbnail'] ?? null,
                        'is_active' => 1,
                        'created_by' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $inserted++;
                } catch (\Throwable $e) {
                    // Best-effort: tolerate per-row failures (e.g. a column the
                    // schema dropped) rather than aborting the whole deploy.
                }
            }
        }

        if ($inserted > 0 && app()->runningInConsole()) {
            fwrite(STDOUT, "  Seeded {$inserted} email starter templates across "
                . count($tenantIds) . " tenants.\n");
        }
    }

    public function down(): void
    {
        // Non-destructive: a tenant admin may have customised a starter copy.
    }
};
