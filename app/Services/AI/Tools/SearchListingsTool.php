<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services\AI\Tools;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Search published listings (offers and requests) for the current tenant.
 *
 * Listings are the primary timebanking exchange surface: people offer or
 * request skills, paid in time credits.
 */
class SearchListingsTool extends AbstractTool
{
    public function name(): string
    {
        return 'search_listings';
    }

    public function description(): string
    {
        return 'Search the community\'s listings (offers and requests of skills paid in time credits). Use this when the user asks for help with something, wants to find a service, or asks what is on offer. Returns up to 8 listings with title, type (offer/request), location, and a short excerpt.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Free-text query — service name, skill, or topic (e.g. "gardening", "Polish lessons", "lift to hospital").',
                ],
                'type' => [
                    'type' => 'string',
                    'enum' => ['offer', 'request', 'any'],
                    'description' => 'Filter by listing type. "offer" = members offering help, "request" = members asking for help, "any" = both.',
                ],
                'location' => [
                    'type' => 'string',
                    'description' => 'Optional location filter (city, town, or area).',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Max results (1-8, default 5).',
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function execute(array $arguments, int $userId): array
    {
        $tenantId = $this->tenantId();
        $query = $this->stringArg($arguments, 'query');
        $type = $this->stringArg($arguments, 'type', 'any');
        $location = $this->stringArg($arguments, 'location');
        $limit = $this->intArg($arguments, 'limit', 5, 1, 8);

        if ($query === '') {
            return $this->err('A non-empty query is required.');
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $query) . '%';

        $q = DB::table('listings')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('moderation_status')
                  ->orWhere('moderation_status', 'approved');
            })
            ->where(function ($q) use ($like) {
                $q->where('title', 'LIKE', $like)
                  ->orWhere('description', 'LIKE', $like);
            });

        if (in_array($type, ['offer', 'request'], true)) {
            $q->where('type', $type);
        }
        if ($location !== '') {
            $locLike = '%' . str_replace(['%', '_'], ['\%', '\_'], $location) . '%';
            $q->where('location', 'LIKE', $locLike);
        }

        $rows = $q->orderByDesc('is_featured')
            ->orderByDesc('renewed_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'title', 'description', 'type', 'location', 'hours_estimate', 'user_id', 'created_at']);

        if ($rows->isEmpty()) {
            return $this->ok(
                'No active listings matched "' . $query . '".',
                [],
                'listing'
            );
        }

        $slugPrefix = TenantContext::getSlugPrefix();
        $results = $rows->map(function ($r) use ($slugPrefix) {
            return [
                'id' => (int) $r->id,
                'title' => (string) $r->title,
                'type' => (string) $r->type,
                'location' => $r->location,
                'hours_estimate' => $r->hours_estimate,
                'excerpt' => mb_substr(strip_tags((string) $r->description), 0, 180),
                'url' => $slugPrefix . '/listings/' . (int) $r->id,
            ];
        })->all();

        $summary = sprintf('Found %d listing(s) matching "%s".', count($results), $query);
        return $this->ok($summary, $results, 'listing');
    }
}
