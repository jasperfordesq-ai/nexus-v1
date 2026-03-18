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

    /**
     * Send a newsletter immediately.
     *
     * Delegates to the legacy NewsletterService which handles recipient
     * resolution, queue building, A/B testing, and actual email dispatch.
     */
    public function sendNow(int $newsletterId, int $tenantId): bool
    {
        $newsletter = $this->newsletter->newQuery()->find($newsletterId);

        if (!$newsletter) {
            return false;
        }

        if ($newsletter->status === 'sent') {
            return false;
        }

        if (!class_exists('\Nexus\Services\NewsletterService')) { return false; }

        try {
            \Nexus\Services\NewsletterService::sendNow(
                $newsletterId,
                $newsletter->target_audience ?? 'all_members',
                $newsletter->segment_id
            );
            return true;
        } catch (\Exception $e) {
            \Log::error("NewsletterService::sendNow error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Render the email HTML for a newsletter (preview).
     *
     * Delegates to the legacy NewsletterService::renderEmail() which handles
     * dynamic content blocks, personalization, and full HTML template rendering.
     */
    public function renderEmail(int $newsletterId, int $tenantId): string
    {
        $newsletter = $this->newsletter->newQuery()->find($newsletterId);

        if (!$newsletter) {
            return '';
        }

        $tenantName = DB::table('tenants')
            ->where('id', $tenantId)
            ->value('name') ?? 'Community';

        if (!class_exists('\Nexus\Services\NewsletterService')) { return ''; }

        return \Nexus\Services\NewsletterService::renderEmail(
            $newsletter->toArray(),
            $tenantName
        );
    }

    /**
     * Get the count of recipients matching a segment.
     */
    public function getSegmentRecipientCount(int $segmentId, int $tenantId): int
    {
        if (!class_exists('\Nexus\Services\NewsletterService')) { return 0; }
        return (int) \Nexus\Services\NewsletterService::getSegmentRecipientCount($segmentId);
    }

    /**
     * Get the total recipient count for a newsletter based on its target audience.
     */
    public function getRecipientCount(int $newsletterId, int $tenantId): int
    {
        $newsletter = $this->newsletter->newQuery()->find($newsletterId);

        if (!$newsletter) {
            return 0;
        }

        if (!class_exists('\Nexus\Services\NewsletterService')) { return 0; }

        return (int) \Nexus\Services\NewsletterService::getRecipientCount(
            $newsletter->target_audience ?? 'all_members'
        );
    }
}
