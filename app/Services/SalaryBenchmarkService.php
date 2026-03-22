<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\SalaryBenchmark;
use Illuminate\Support\Facades\Log;

class SalaryBenchmarkService
{
    /**
     * Find the best-matching benchmark for a job title.
     * Tries tenant-specific first, then global.
     * Returns null if no match found.
     */
    public static function findForTitle(string $title): ?array
    {
        $tenantId = TenantContext::getId();
        $title    = strtolower($title);

        try {
            $benchmarks = SalaryBenchmark::orderByRaw('tenant_id IS NULL ASC')
                ->where(function ($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                })
                ->get();

            foreach ($benchmarks as $b) {
                if (str_contains($title, strtolower($b->role_keyword))) {
                    return $b->toArray();
                }
            }

            return null;
        } catch (\Throwable $e) {
            Log::error('SalaryBenchmarkService::findForTitle failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * List all benchmarks visible to this tenant (own + global).
     */
    public static function list(): array
    {
        $tenantId = TenantContext::getId();
        try {
            return SalaryBenchmark::where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            })
            ->orderBy('role_keyword')
            ->get()
            ->toArray();
        } catch (\Throwable $e) {
            Log::error('SalaryBenchmarkService::list failed', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
