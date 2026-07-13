<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Jobs;

use App\Core\TenantContext;
use App\Services\PodcastMediaCleanupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** Retry one durable podcast media cleanup ledger entry. */
class CleanupPodcastMedia implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(
        public int $tenantId,
        public int $taskId,
    ) {
    }

    public function handle(PodcastMediaCleanupService $cleanup): void
    {
        TenantContext::runForTenant($this->tenantId, function () use ($cleanup): void {
            $cleanup->process($this->taskId);
        });
    }

    public function failed(Throwable $exception): void
    {
        try {
            TenantContext::runForTenant($this->tenantId, function () use ($exception): void {
                app(PodcastMediaCleanupService::class)->releaseAfterTerminalFailure(
                    $this->taskId,
                    $exception,
                );
            });
        } catch (Throwable) {
            // Failed-job handlers must never mask the original queue failure.
        }
    }
}
