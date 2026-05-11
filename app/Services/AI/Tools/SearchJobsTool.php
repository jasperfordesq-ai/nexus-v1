<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services\AI\Tools;

use App\Core\TenantContext;
use App\Services\TenantFeatureConfig;
use Illuminate\Support\Facades\DB;

class SearchJobsTool extends AbstractTool
{
    public function name(): string
    {
        return 'search_jobs';
    }

    public function description(): string
    {
        return 'Find open job vacancies on the community job board. Use when the user is looking for paid work, internships, or volunteering opportunities advertised by employers. Returns up to 8 active jobs.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Free-text query — role title, skill, or industry.'],
                'location' => ['type' => 'string', 'description' => 'Optional location filter.'],
                'is_remote' => ['type' => 'boolean', 'description' => 'If true, only remote roles.'],
                'limit' => ['type' => 'integer', 'description' => 'Max results (1-8, default 5).'],
            ],
            'required' => [],
        ];
    }

    public function isAvailable(int $userId): bool
    {
        $tenant = TenantContext::get() ?: [];
        $features = $tenant['features'] ?? null;
        if (is_string($features)) {
            $features = json_decode($features, true) ?: [];
        }
        $merged = TenantFeatureConfig::mergeFeatures(is_array($features) ? $features : []);
        return !empty($merged['job_vacancies']);
    }

    public function execute(array $arguments, int $userId): array
    {
        $tenantId = $this->tenantId();
        $query = $this->stringArg($arguments, 'query');
        $location = $this->stringArg($arguments, 'location');
        $isRemote = $arguments['is_remote'] ?? null;
        $limit = $this->intArg($arguments, 'limit', 5, 1, 8);

        $q = DB::table('job_vacancies')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('moderation_status')->orWhere('moderation_status', 'approved');
            })
            ->where(function ($q) {
                $q->whereNull('expired_at')->orWhere('expired_at', '>', now());
            });

        if ($query !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $query) . '%';
            $q->where(function ($q) use ($like) {
                $q->where('title', 'LIKE', $like)
                  ->orWhere('description', 'LIKE', $like)
                  ->orWhere('skills_required', 'LIKE', $like);
            });
        }
        if ($location !== '') {
            $locLike = '%' . str_replace(['%', '_'], ['\%', '\_'], $location) . '%';
            $q->where('location', 'LIKE', $locLike);
        }
        if (is_bool($isRemote) && $isRemote === true) {
            $q->where('is_remote', true);
        }

        $rows = $q->orderByDesc('is_featured')
            ->orderByDesc('renewed_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'title', 'tagline', 'description', 'location', 'is_remote', 'type', 'commitment', 'salary_min', 'salary_max', 'salary_currency', 'deadline']);

        if ($rows->isEmpty()) {
            return $this->ok('No matching open jobs found.', [], 'job');
        }

        $slugPrefix = TenantContext::getSlugPrefix();
        $results = $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'title' => (string) $r->title,
            'tagline' => $r->tagline,
            'location' => $r->location,
            'is_remote' => (bool) $r->is_remote,
            'type' => $r->type,
            'commitment' => $r->commitment,
            'salary' => $this->formatSalary($r),
            'deadline' => $r->deadline,
            'excerpt' => mb_substr(strip_tags((string) ($r->description ?? '')), 0, 160),
            'url' => $slugPrefix . '/jobs/' . (int) $r->id,
        ])->all();

        return $this->ok(sprintf('Found %d open job(s).', count($results)), $results, 'job');
    }

    private function formatSalary($r): ?string
    {
        if (!$r->salary_min && !$r->salary_max) {
            return null;
        }
        $cur = $r->salary_currency ?: 'EUR';
        if ($r->salary_min && $r->salary_max) {
            return "{$cur} {$r->salary_min}-{$r->salary_max}";
        }
        return "{$cur} " . ($r->salary_min ?: $r->salary_max);
    }
}
