<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services\AI\Tools;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Search the published Knowledge Base for the current tenant. Use when the
 * user is asking how to do something on the platform.
 */
class SearchKbTool extends AbstractTool
{
    public function name(): string
    {
        return 'search_kb';
    }

    public function description(): string
    {
        return 'Search the published Knowledge Base / Help articles. Use when the user asks "how do I", "what is", or asks for documentation on a platform feature. Returns up to 5 articles with title and excerpt.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Search query.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Max results (1-5, default 4).',
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function execute(array $arguments, int $userId): array
    {
        $tenantId = $this->tenantId();
        $query = $this->stringArg($arguments, 'query');
        $limit = $this->intArg($arguments, 'limit', 4, 1, 5);

        if ($query === '') {
            return $this->err('A non-empty query is required.');
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $query) . '%';

        $rows = DB::table('knowledge_base_articles')
            ->where('tenant_id', $tenantId)
            ->where('is_published', true)
            ->where(function ($q) use ($like) {
                $q->where('title', 'LIKE', $like)
                  ->orWhere('content', 'LIKE', $like);
            })
            ->orderByRaw('CASE WHEN title LIKE ? THEN 1 ELSE 0 END DESC', [$like])
            ->orderByDesc('helpful_yes')
            ->orderByDesc('views_count')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get(['id', 'title', 'slug', 'content']);

        if ($rows->isEmpty()) {
            return $this->ok('No published KB articles matched "' . $query . '".', [], 'kb');
        }

        $slugPrefix = TenantContext::getSlugPrefix();
        $results = $rows->map(function ($r) use ($slugPrefix) {
            $plain = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $r->content))) ?? '');
            return [
                'id' => (int) $r->id,
                'title' => (string) $r->title,
                'excerpt' => Str::limit($plain, 240),
                'url' => $slugPrefix . '/kb/' . (int) $r->id,
            ];
        })->all();

        return $this->ok(
            sprintf('Found %d KB article(s) matching "%s".', count($results), $query),
            $results,
            'kb'
        );
    }
}
