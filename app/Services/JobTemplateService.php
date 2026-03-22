<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\JobTemplate;
use Illuminate\Support\Facades\Log;

class JobTemplateService
{
    public static function list(int $userId): array
    {
        $tenantId = TenantContext::getId();
        try {
            return JobTemplate::where('tenant_id', $tenantId)
                ->where(function ($q) use ($userId) {
                    $q->where('user_id', $userId)->orWhere('is_public', true);
                })
                ->orderByDesc('use_count')
                ->orderByDesc('updated_at')
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            Log::error('JobTemplateService::list failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public static function create(int $userId, array $data): array|false
    {
        $tenantId = TenantContext::getId();
        try {
            $template = JobTemplate::create([
                'tenant_id'       => $tenantId,
                'user_id'         => $userId,
                'name'            => trim($data['name'] ?? ''),
                'description'     => $data['description'] ?? null,
                'type'            => $data['type'] ?? 'paid',
                'commitment'      => $data['commitment'] ?? 'flexible',
                'category'        => $data['category'] ?? null,
                'skills_required' => $data['skills_required'] ?? null,
                'is_remote'       => (bool) ($data['is_remote'] ?? false),
                'salary_type'     => $data['salary_type'] ?? null,
                'salary_currency' => $data['salary_currency'] ?? 'EUR',
                'salary_min'      => isset($data['salary_min']) ? (float) $data['salary_min'] : null,
                'salary_max'      => isset($data['salary_max']) ? (float) $data['salary_max'] : null,
                'hours_per_week'  => isset($data['hours_per_week']) ? (float) $data['hours_per_week'] : null,
                'time_credits'    => isset($data['time_credits']) ? (float) $data['time_credits'] : null,
                'benefits'        => $data['benefits'] ?? null,
                'tagline'         => $data['tagline'] ?? null,
                'is_public'       => (bool) ($data['is_public'] ?? false),
            ]);
            return $template->toArray();
        } catch (\Throwable $e) {
            Log::error('JobTemplateService::create failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public static function get(int $templateId, int $userId): ?array
    {
        $tenantId = TenantContext::getId();
        try {
            $t = JobTemplate::where('tenant_id', $tenantId)
                ->where('id', $templateId)
                ->where(function ($q) use ($userId) {
                    $q->where('user_id', $userId)->orWhere('is_public', true);
                })
                ->first();
            if (!$t) return null;
            // Increment use_count when fetched for use
            $t->increment('use_count');
            return $t->toArray();
        } catch (\Throwable $e) {
            Log::error('JobTemplateService::get failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public static function delete(int $templateId, int $userId): bool
    {
        $tenantId = TenantContext::getId();
        try {
            return JobTemplate::where('tenant_id', $tenantId)
                ->where('id', $templateId)
                ->where('user_id', $userId)
                ->delete() > 0;
        } catch (\Throwable $e) {
            Log::error('JobTemplateService::delete failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
