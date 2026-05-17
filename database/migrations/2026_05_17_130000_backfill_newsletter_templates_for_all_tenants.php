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
 * Backfill: copy every "starter" newsletter_templates row from tenant 2 to
 * every other active tenant so re-engagement / nurture / digest blasts work
 * uniformly across the platform.
 *
 * Why: when SendGrid + the newsletter feature were rolled out, the seed
 * templates were created only for tenant 2 (`hour-timebank`) via
 * migrations/2026_01_19_*.sql. Other tenants had no rows in
 * `newsletter_templates`, so the admin "New newsletter" UI was empty for
 * everyone except tenant 2, and any code path that looks up a template by
 * `category='starter'` per-tenant returned nothing.
 *
 * Idempotent: skips any (tenant, template name, category) combination that
 * already exists, so re-running is a safe no-op.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('newsletter_templates') || !Schema::hasTable('tenants')) {
            return;
        }

        $sources = DB::table('newsletter_templates')
            ->where('tenant_id', 2)
            ->where('category', 'starter')
            ->get();

        if ($sources->isEmpty()) {
            return;
        }

        $targets = DB::table('tenants')
            ->where('is_active', 1)
            ->where('id', '!=', 2)
            ->pluck('id');

        $inserted = 0;
        foreach ($targets as $tenantId) {
            foreach ($sources as $tpl) {
                $exists = DB::table('newsletter_templates')
                    ->where('tenant_id', $tenantId)
                    ->where('name', $tpl->name)
                    ->where('category', $tpl->category)
                    ->exists();
                if ($exists) {
                    continue;
                }
                $row = (array) $tpl;
                unset($row['id']);
                $row['tenant_id'] = $tenantId;
                $row['created_at'] = $row['created_at'] ?? now();
                $row['updated_at'] = now();
                try {
                    DB::table('newsletter_templates')->insert($row);
                    $inserted++;
                } catch (\Throwable $e) {
                    // Per-row failures (e.g. UNIQUE conflicts on a different
                    // column) are tolerated; the migration is best-effort.
                }
            }
        }

        // No-op if no work to do — but emit a console hint when running
        // with -v so operators see the backfill count.
        if ($inserted > 0 && app()->runningInConsole()) {
            fwrite(STDOUT, "  Backfilled {$inserted} newsletter template rows across "
                . count($targets) . " tenants.\n");
        }
    }

    public function down(): void
    {
        // Non-destructive: we don't delete the copies on rollback because
        // a tenant admin may have edited their copy in the meantime.
    }
};
