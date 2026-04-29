<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Jobs;

use App\Services\TenantProvisioning\TenantProvisioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * AG44 — Asynchronously runs the provisioning pipeline so the
 * super-admin's HTTP request returns immediately.
 */
class ProvisionTenantJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(
        public readonly int $requestId,
        public readonly int $reviewerId,
    ) {}

    public function handle(): void
    {
        try {
            TenantProvisioningService::approveAndProvision($this->requestId, $this->reviewerId);
        } catch (Throwable $e) {
            Log::error('ProvisionTenantJob failed', [
                'request_id' => $this->requestId,
                'error'      => $e->getMessage(),
            ]);
            // Don't rethrow — the service has already marked the request as failed
            // and written to provisioning_log so the admin can retry.
        }
    }
}
