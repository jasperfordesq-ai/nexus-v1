<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Console\Commands;

use App\Jobs\ReindexEmbeddingJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill / refresh AI semantic-search embeddings for the seven supported
 * content types. Dispatches one ReindexEmbeddingJob per row (queued), so a
 * large reindex doesn't tie up the CLI process or burn through the OpenAI
 * rate limit in a tight loop.
 *
 * Usage:
 *   php artisan ai:reindex                           # all types, all tenants
 *   php artisan ai:reindex --type=listing            # one type, all tenants
 *   php artisan ai:reindex --tenant=2                # all types, one tenant
 *   php artisan ai:reindex --type=kb_article --tenant=2 --force   # re-embed even if recent
 *   php artisan ai:reindex --sync                    # run inline (no queue)
 */
class AiReindexEmbeddings extends Command
{
    protected $signature = 'ai:reindex
                            {--type= : Content type to reindex (listing, user, event, group, job, marketplace, kb_article). Default: all.}
                            {--tenant= : Tenant ID to limit to. Default: all tenants.}
                            {--limit=0 : Cap rows per type (0 = no cap).}
                            {--force : Re-embed even rows that already have an up-to-date embedding.}
                            {--sync : Run inline instead of queueing.}';

    protected $description = 'Backfill / refresh AI semantic-search embeddings for tenant content.';

    private const TYPES = [
        'listing' => ['table' => 'listings', 'where' => null],
        'user' => ['table' => 'users', 'where' => null],
        'event' => ['table' => 'events', 'where' => null],
        'group' => ['table' => 'groups', 'where' => null],
        'job' => ['table' => 'job_vacancies', 'where' => null],
        'marketplace' => ['table' => 'marketplace_listings', 'where' => null],
        'kb_article' => ['table' => 'knowledge_base_articles', 'where' => ['is_published' => true]],
    ];

    public function handle(): int
    {
        $type = $this->option('type');
        $tenantId = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;
        $limit = (int) $this->option('limit');
        $force = (bool) $this->option('force');
        $sync = (bool) $this->option('sync');

        $types = $type ? [$type] : array_keys(self::TYPES);
        foreach ($types as $t) {
            if (!isset(self::TYPES[$t])) {
                $this->error("Unknown type: $t");
                return self::FAILURE;
            }
        }

        $total = 0;
        foreach ($types as $t) {
            $count = $this->reindexType($t, $tenantId, $limit, $force, $sync);
            $this->info("[$t] queued/processed: $count");
            $total += $count;
        }
        $this->info("Done. Total: $total");
        return self::SUCCESS;
    }

    private function reindexType(string $type, ?int $tenantId, int $limit, bool $force, bool $sync): int
    {
        [$table, $where] = [self::TYPES[$type]['table'], self::TYPES[$type]['where']];

        $q = DB::table($table)->select('id', 'tenant_id');
        if ($tenantId !== null) {
            $q->where('tenant_id', $tenantId);
        }
        if (is_array($where)) {
            foreach ($where as $col => $val) {
                $q->where($col, $val);
            }
        }
        if (!$force) {
            // Skip rows that already have an embedding refreshed in the last 30 days
            $existing = DB::table('content_embeddings')
                ->where('content_type', $type)
                ->where('updated_at', '>=', now()->subDays(30))
                ->when($tenantId, fn ($q2) => $q2->where('tenant_id', $tenantId))
                ->pluck('content_id', 'content_id')
                ->all();
            if ($existing !== []) {
                $q->whereNotIn('id', array_keys($existing));
            }
        }
        if ($limit > 0) {
            $q->limit($limit);
        }

        $count = 0;
        $q->orderBy('id')->chunk(200, function ($rows) use ($type, $sync, &$count) {
            foreach ($rows as $row) {
                if ($sync) {
                    (new ReindexEmbeddingJob($type, (int) $row->id, (int) $row->tenant_id))
                        ->handle(app(\App\Services\EmbeddingService::class));
                } else {
                    ReindexEmbeddingJob::dispatch($type, (int) $row->id, (int) $row->tenant_id);
                }
                $count++;
            }
        });
        return $count;
    }
}
