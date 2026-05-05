<?php

// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Purge old broker message copies per retention policy.
 *
 * Per-tenant retention: each tenant's broker_config.retention_days controls
 * how long REVIEWED, non-flagged copies are kept. The CLI --days option is
 * a fallback for tenants that haven't set their own value (and a hard
 * default for any tenant whose config is missing or unparseable).
 *
 * Flagged copies are retained for the longer fixed period (default 365 days)
 * to satisfy legal/audit retention requirements that are uniform across
 * tenants and not user-tunable.
 */
class PurgeBrokerMessageCopiesCommand extends Command
{
    private const MIN_REVIEWED_RETENTION_DAYS = 7;
    private const MIN_FLAGGED_RETENTION_DAYS = 365;

    protected $signature = 'safeguarding:purge-message-copies
                            {--days=90 : Default retention days when a tenant has no broker_config.retention_days set}
                            {--flagged-days=365 : Retention days for flagged copies (uniform across tenants)}';
    protected $description = 'Purge old broker message copies per retention policy (per-tenant retention from broker_config)';

    public function handle(): int
    {
        $defaultDays = max(self::MIN_REVIEWED_RETENTION_DAYS, (int) $this->option('days'));
        $flaggedDays = max(self::MIN_FLAGGED_RETENTION_DAYS, (int) $this->option('flagged-days'));
        $now = now();

        // Build a per-tenant retention map from broker_config so each tenant
        // can choose its own retention window. Missing or invalid values fall
        // back to the CLI default.
        $tenantRetention = $this->buildTenantRetentionMap($defaultDays);

        $reviewedDeleted = 0;

        // Per-tenant pass on reviewed/non-flagged rows so each tenant's
        // retention_days actually applies. The unique index on
        // (tenant_id, original_message_id) means rows are tenant-clean.
        foreach ($tenantRetention as $tenantId => $days) {
            $cutoff = $now->copy()->subDays($days);
            $reviewedDeleted += DB::table('broker_message_copies')
                ->where('tenant_id', $tenantId)
                ->whereNotNull('reviewed_at')
                ->where('flagged', false)
                ->where('sent_at', '<', $cutoff)
                ->delete();
        }

        // Tenants with broker_message_copies rows but no broker_config row
        // fall back to the CLI default. Safety net so a misconfigured tenant
        // never grows unbounded.
        $unhandledDeleted = DB::table('broker_message_copies')
            ->whereNotIn('tenant_id', array_keys($tenantRetention))
            ->whereNotNull('reviewed_at')
            ->where('flagged', false)
            ->where('sent_at', '<', $now->copy()->subDays($defaultDays))
            ->delete();

        $reviewedDeleted += $unhandledDeleted;

        // Flagged copies retained uniformly — these survive on legal grounds
        // even if the tenant later shortens its retention_days.
        $flaggedDeleted = DB::table('broker_message_copies')
            ->whereNotNull('reviewed_at')
            ->where('flagged', true)
            ->where('sent_at', '<', $now->copy()->subDays($flaggedDays))
            ->delete();

        $total = $reviewedDeleted + $flaggedDeleted;

        if ($total > 0) {
            Log::info('Purged broker message copies', [
                'reviewed_deleted' => $reviewedDeleted,
                'flagged_deleted'  => $flaggedDeleted,
                'tenants_with_custom_retention' => count($tenantRetention),
            ]);
        }

        $this->info("Purged {$reviewedDeleted} reviewed + {$flaggedDeleted} flagged copies.");

        return self::SUCCESS;
    }

    /**
     * Read each tenant's broker_config.retention_days; fall back to $default
     * for missing/invalid values. Returns [tenant_id => days].
     */
    private function buildTenantRetentionMap(int $default): array
    {
        $rows = DB::table('tenant_settings')
            ->where('setting_key', 'broker_config')
            ->select(['tenant_id', 'setting_value'])
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $tenantId = (int) $row->tenant_id;
            $config = json_decode($row->setting_value ?? '', true);
            $days = is_array($config) && isset($config['retention_days'])
                ? (int) $config['retention_days']
                : $default;

            // Hard floor of 7 days — a tenant accidentally setting
            // retention_days to 0 would otherwise wipe their entire
            // broker review queue on the next purge run.
            $map[$tenantId] = max(self::MIN_REVIEWED_RETENTION_DAYS, $days);
        }

        return $map;
    }
}
