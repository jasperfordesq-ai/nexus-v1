<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands\Tenant;

use App\Services\TenantProvisioning\TenantProvisioningService;
use Illuminate\Console\Command;
use Throwable;

/**
 * AG44 — Manual provisioning trigger.
 *
 *   php artisan tenant:provision <request_id> [--reviewer=<user_id>]
 *
 * Useful for retrying a failed provisioning, or for running approval
 * outside of the admin UI (e.g. from a deploy script).
 */
class ProvisionTenant extends Command
{
    protected $signature = 'tenant:provision
        {request_id : ID in tenant_provisioning_requests}
        {--reviewer=1 : Reviewer user ID to record on the request}';

    protected $description = 'Run the tenant provisioning pipeline for a given request id';

    public function handle(): int
    {
        $id       = (int) $this->argument('request_id');
        $reviewer = (int) $this->option('reviewer');

        if ($id <= 0) {
            $this->error('request_id must be > 0');
            return self::FAILURE;
        }

        if (! TenantProvisioningService::isAvailable()) {
            $this->error('tenant_provisioning_requests table not found — run migrations first.');
            return self::FAILURE;
        }

        try {
            $row = TenantProvisioningService::approveAndProvision($id, $reviewer);
            $this->info('Provisioning complete: tenant_id=' . ($row['provisioned_tenant_id'] ?? 'n/a'));
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Provisioning failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
