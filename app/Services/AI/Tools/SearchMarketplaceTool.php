<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services\AI\Tools;

use App\Core\TenantContext;
use App\Services\TenantFeatureConfig;
use Illuminate\Support\Facades\DB;

class SearchMarketplaceTool extends AbstractTool
{
    public function name(): string
    {
        return 'search_marketplace';
    }

    public function description(): string
    {
        return 'Search the community marketplace for items being sold or given away (separate from listings — listings are services, marketplace is physical or digital goods). Returns up to 8 active marketplace items.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Free-text query for the item.'],
                'location' => ['type' => 'string', 'description' => 'Optional location filter.'],
                'limit' => ['type' => 'integer', 'description' => 'Max results (1-8, default 5).'],
            ],
            'required' => ['query'],
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
        return !empty($merged['marketplace']);
    }

    public function execute(array $arguments, int $userId): array
    {
        $tenantId = $this->tenantId();
        $query = $this->stringArg($arguments, 'query');
        $location = $this->stringArg($arguments, 'location');
        $limit = $this->intArg($arguments, 'limit', 5, 1, 8);

        if ($query === '') {
            return $this->err('A non-empty query is required.');
        }
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $query) . '%';

        $q = DB::table('marketplace_listings')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('moderation_status')->orWhere('moderation_status', 'approved');
            })
            ->where(function ($q) use ($like) {
                $q->where('title', 'LIKE', $like)
                  ->orWhere('tagline', 'LIKE', $like)
                  ->orWhere('description', 'LIKE', $like);
            });

        if ($location !== '') {
            $locLike = '%' . str_replace(['%', '_'], ['\%', '\_'], $location) . '%';
            $q->where('location', 'LIKE', $locLike);
        }

        $rows = $q->orderByDesc('renewed_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'title', 'tagline', 'description', 'condition', 'price', 'price_currency', 'price_type', 'time_credit_price', 'location']);

        if ($rows->isEmpty()) {
            return $this->ok('No marketplace items matched "' . $query . '".', [], 'marketplace');
        }

        $slugPrefix = TenantContext::getSlugPrefix();
        $results = $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'title' => (string) $r->title,
            'tagline' => $r->tagline,
            'condition' => $r->condition,
            'price' => $r->price !== null ? ($r->price_currency ?: 'EUR') . ' ' . $r->price : null,
            'time_credit_price' => $r->time_credit_price,
            'price_type' => $r->price_type,
            'location' => $r->location,
            'excerpt' => mb_substr(strip_tags((string) ($r->description ?? '')), 0, 160),
            'url' => $slugPrefix . '/marketplace/' . (int) $r->id,
        ])->all();

        return $this->ok(sprintf('Found %d marketplace item(s).', count($results)), $results, 'marketplace');
    }
}
