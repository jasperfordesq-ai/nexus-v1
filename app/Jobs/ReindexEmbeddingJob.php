<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Jobs;

use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Background job that (re)generates the embedding for a single content row.
 *
 * Dispatched by model observers on create/update so that user-facing writes
 * are not blocked on the OpenAI embeddings API. Failures are swallowed
 * (logged inside EmbeddingService) — embeddings are a best-effort enhancement.
 */
class ReindexEmbeddingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly string $contentType,
        public readonly int $contentId,
        public readonly int $tenantId,
    ) {}

    public function handle(EmbeddingService $embeddings): void
    {
        $row = $this->fetchRow();
        if ($row === null) {
            $embeddings->delete($this->tenantId, $this->contentType, $this->contentId);
            return;
        }
        $row['tenant_id'] = $this->tenantId;
        $row['id'] = $this->contentId;
        $embeddings->generateFor($this->contentType, $row);
    }

    /**
     * Pull the current row from the right table. Returns null if the row no
     * longer exists or is not visible to indexing (e.g. soft-deleted).
     */
    private function fetchRow(): ?array
    {
        $map = [
            'listing' => ['listings', ['id', 'title', 'description', 'location']],
            'user' => ['users', ['id', 'first_name', 'last_name', 'bio', 'skills']],
            'event' => ['events', ['id', 'title', 'description', 'location']],
            'group' => ['groups', ['id', 'name', 'description']],
            'job' => ['job_vacancies', ['id', 'title', 'tagline', 'description', 'location', 'skills_required']],
            'marketplace' => ['marketplace_listings', ['id', 'title', 'tagline', 'description', 'condition', 'location']],
            'kb_article' => ['knowledge_base_articles', ['id', 'title', 'content']],
        ];

        if (!isset($map[$this->contentType])) {
            return null;
        }
        [$table, $cols] = $map[$this->contentType];
        $row = DB::table($table)
            ->where('tenant_id', $this->tenantId)
            ->where('id', $this->contentId)
            ->first($cols);

        return $row ? (array) $row : null;
    }
}
