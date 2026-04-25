<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Jobs;

use App\Services\CronJobRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunAdminCronJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    /** Retry on transient DB / network failures. */
    public int $tries = 3;

    /** Exponential-style backoff (seconds). */
    public array $backoff = [30, 120, 300];

    public function __construct(private readonly string $method) {}

    public function handle(CronJobRunner $runner): void
    {
        $method = $this->method;
        $runner->$method();
    }

    public function failed(\Throwable $e): void
    {
        Log::error('RunAdminCronJob failed permanently', [
            'method' => $this->method,
            'error'  => $e->getMessage(),
        ]);
    }
}
