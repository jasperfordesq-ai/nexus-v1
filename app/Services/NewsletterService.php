<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\Newsletter;
use Illuminate\Support\Facades\DB;

/**
 * NewsletterService — Laravel DI-based service for newsletter operations.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\NewsletterService.
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class NewsletterService
{
    public function __construct(
        private readonly Newsletter $newsletter,
    ) {}

    /**
     * Get newsletters with cursor pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getAll(array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        $query = $this->newsletter->newQuery()
            ->with(['creator:id,first_name,last_name']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if ($cursor !== null && ($cid = base64_decode($cursor, true)) !== false) {
            $query->where('id', '<', (int) $cid);
        }

        $query->orderByDesc('id');
        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        return [
            'items'    => $items->toArray(),
            'cursor'   => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single newsletter by ID.
     */
    public function getById(int $id): ?Newsletter
    {
        return $this->newsletter->newQuery()
            ->with(['creator'])
            ->find($id);
    }

    /**
     * Create a new newsletter draft.
     */
    public function create(int $createdBy, array $data): Newsletter
    {
        $newsletter = $this->newsletter->newInstance([
            'created_by'      => $createdBy,
            'subject'         => trim($data['subject']),
            'preview_text'    => trim($data['preview_text'] ?? ''),
            'content'         => $data['content'] ?? '',
            'status'          => 'draft',
            'target_audience' => $data['target_audience'] ?? 'all_members',
            'segment_id'      => $data['segment_id'] ?? null,
        ]);

        $newsletter->save();

        return $newsletter->fresh(['creator']);
    }

    /**
     * Mark a newsletter as queued for sending.
     * Actual email dispatch is handled by a background job/cron.
     */
    public function send(int $id): Newsletter
    {
        $newsletter = $this->newsletter->newQuery()->findOrFail($id);

        if ($newsletter->status === 'sent') {
            throw new \RuntimeException('Newsletter has already been sent.');
        }

        $newsletter->status = 'sending';
        $newsletter->save();

        return $newsletter;
    }
}
