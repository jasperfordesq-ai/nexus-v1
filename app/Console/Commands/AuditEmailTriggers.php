<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EmailTriggerAuditService;
use Illuminate\Console\Command;

class AuditEmailTriggers extends Command
{
    protected $signature = 'email:audit-triggers {--tenant= : Tenant id to audit} {--hours=24 : Lookback window, 1-168 hours} {--json : Output JSON}';

    protected $description = 'Audit tenant-aware email triggers against email_log attempts';

    public function handle(EmailTriggerAuditService $audit): int
    {
        $tenantOption = $this->option('tenant');
        $tenantId = $tenantOption !== null && $tenantOption !== ''
            ? (int) $tenantOption
            : null;
        $hours = max(1, min((int) $this->option('hours'), 168));

        $result = $audit->run($tenantId, $hours);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return ((int) ($result['issues_by_severity']['critical'] ?? 0)) > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->info("Email trigger audit score: {$result['score']}/1000");
        $this->line("Window: {$result['window_hours']}h; matrix entries: {$result['matrix_count']}; issues: {$result['issue_count']}");

        foreach ($result['issues'] as $issue) {
            $tenant = $issue['tenant_id'] !== null ? "tenant {$issue['tenant_id']}" : 'platform';
            $params = $issue['params'] ?? [];
            $count = isset($params['count']) ? " count={$params['count']}" : '';
            $this->line(sprintf(
                '[%s] %s %s/%s %s%s',
                strtoupper((string) $issue['severity']),
                $tenant,
                $issue['module'],
                $issue['event'],
                $issue['code'],
                $count
            ));
        }

        return ((int) ($result['issues_by_severity']['critical'] ?? 0)) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
