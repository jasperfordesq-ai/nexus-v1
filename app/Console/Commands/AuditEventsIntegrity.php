<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EventIntegrityAuditService;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class AuditEventsIntegrity extends Command
{
    protected $signature = 'events:integrity-audit
        {--tenant= : Restrict the audit to one tenant id}
        {--json : Emit machine-readable JSON}
        {--dry-run : Explicitly assert the command must remain read-only}
        {--chunk=100 : Maximum sample ids retained per issue (1-1000)}';

    protected $description = 'Read-only Events integrity audit required before constraints or financial writers expand';

    public function handle(EventIntegrityAuditService $audit): int
    {
        $tenantOption = $this->option('tenant');
        $tenantId = $tenantOption === null || $tenantOption === '' ? null : (int) $tenantOption;
        $sampleLimit = max(1, min((int) $this->option('chunk'), 1000));

        try {
            $result = $audit->run($tenantId, $sampleLimit);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::INVALID;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $scope = $tenantId === null ? 'all tenants' : "tenant {$tenantId}";
            $this->info("Events integrity audit completed for {$scope} (read-only).");
            $this->line(sprintf(
                'Critical rows: %d; warning rows: %d; issue types: %d',
                $result['issues_by_severity']['critical'],
                $result['issues_by_severity']['warning'],
                $result['issue_types'],
            ));
            foreach ($result['issues'] as $issue) {
                $this->line(sprintf(
                    '[%s] %s count=%d samples=%s',
                    strtoupper((string) $issue['severity']),
                    $issue['code'],
                    $issue['count'],
                    implode(',', $issue['sample_ids']),
                ));
            }
        }

        return $result['blocking'] ? self::FAILURE : self::SUCCESS;
    }
}
