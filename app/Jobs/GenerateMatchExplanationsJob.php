<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Jobs;

use App\Core\TenantContext;
use App\Services\Matching\MatchExplanationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Background job: LLM "why this match" explanations (+ bounded re-rank) for
 * one user's top cached matches. Dispatched from the match-cache warm cron
 * for AI-enabled tenants — NEVER inline in a request. Failures leave the
 * algorithmic reasons untouched, so there is no user-visible degradation.
 */
class GenerateMatchExplanationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $userId,
        public readonly int $topN = 5,
    ) {}

    public function handle(MatchExplanationService $explanations): void
    {
        try {
            TenantContext::runForTenant($this->tenantId, function () use ($explanations): void {
                $result = $explanations->generateForUser($this->userId, $this->topN);
                if ($result['explained'] > 0) {
                    Log::info('[GenerateMatchExplanationsJob] explanations generated', [
                        'tenant_id' => $this->tenantId,
                        'user_id' => $this->userId,
                    ] + $result);
                }
            });
        } catch (\Throwable $e) {
            Log::warning('[GenerateMatchExplanationsJob] failed', [
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
