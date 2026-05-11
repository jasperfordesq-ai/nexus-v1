<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Observers\Concerns;

use App\Jobs\ReindexEmbeddingJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Shared helper for observers that need to keep AI semantic-search embeddings
 * in sync. Dispatches a queued job so the OpenAI API call doesn't block the
 * request. Failures here must never break the underlying write — every call
 * is wrapped in a try/catch.
 */
trait IndexesEmbeddings
{
    protected function reindexEmbedding(Model $model, string $contentType): void
    {
        try {
            $tenantId = (int) ($model->tenant_id ?? 0);
            $id = (int) ($model->id ?? 0);
            if ($tenantId <= 0 || $id <= 0) {
                return;
            }
            ReindexEmbeddingJob::dispatch($contentType, $id, $tenantId);
        } catch (\Throwable $e) {
            Log::info('IndexesEmbeddings dispatch failed', [
                'content_type' => $contentType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function deleteEmbedding(Model $model, string $contentType): void
    {
        try {
            $tenantId = (int) ($model->tenant_id ?? 0);
            $id = (int) ($model->id ?? 0);
            if ($tenantId <= 0 || $id <= 0) {
                return;
            }
            // ReindexEmbeddingJob deletes when the source row is gone, but for
            // an explicit hard delete we don't want to wait for the queue —
            // do it inline so subsequent semantic searches don't surface it.
            app(\App\Services\EmbeddingService::class)
                ->delete($tenantId, $contentType, $id);
        } catch (\Throwable $e) {
            Log::info('IndexesEmbeddings delete failed', [
                'content_type' => $contentType,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
