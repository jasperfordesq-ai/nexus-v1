<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\VolApplication;
use App\Models\VolOpportunity;
use Illuminate\Database\Eloquent\Builder;

/**
 * VolunteerService — Laravel DI-based service for volunteering operations.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\VolunteerService.
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class VolunteerService
{
    public function __construct(
        private readonly VolOpportunity $opportunity,
        private readonly VolApplication $application,
    ) {}

    /**
     * Get active volunteer opportunities with cursor pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getOpportunities(array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 50);
        $cursor = $filters['cursor'] ?? null;

        $query = $this->opportunity->newQuery()
            ->with(['creator:id,first_name,last_name,avatar_url', 'organization:id,name', 'category:id,name,color'])
            ->where('is_active', true);

        if (! empty($filters['organization_id'])) {
            $query->where('organization_id', (int) $filters['organization_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (! empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('title', 'LIKE', $term)
                  ->orWhere('description', 'LIKE', $term);
            });
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
     * Get a single opportunity by ID.
     */
    public function getById(int $id): ?VolOpportunity
    {
        return $this->opportunity->newQuery()
            ->with(['creator', 'organization', 'category', 'shifts', 'applications'])
            ->find($id);
    }

    /**
     * Apply to a volunteer opportunity.
     */
    public function apply(int $opportunityId, int $userId, array $data = []): VolApplication
    {
        $application = $this->application->newInstance([
            'opportunity_id' => $opportunityId,
            'user_id'        => $userId,
            'message'        => trim($data['message'] ?? ''),
            'shift_id'       => $data['shift_id'] ?? null,
        ]);

        $application->save();

        return $application->fresh(['user', 'opportunity']);
    }

    /**
     * Get applications submitted by a user.
     */
    public function getMyApplications(int $userId): array
    {
        return $this->application->newQuery()
            ->with(['opportunity:id,title,status', 'opportunity.organization:id,name'])
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }
}
