<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\VolApplication;
use App\Models\VolLog;
use App\Models\VolOpportunity;
use App\Models\VolOrganization;
use App\Models\VolShift;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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
     * Create a new volunteer opportunity.
     */
    public function createOpportunity(int $userId, array $data): VolOpportunity
    {
        $opportunity = $this->opportunity->newInstance([
            'created_by'      => $userId,
            'organization_id' => $data['organization_id'] ?? null,
            'title'           => trim($data['title'] ?? ''),
            'description'     => trim($data['description'] ?? ''),
            'location'        => trim($data['location'] ?? ''),
            'skills_needed'   => trim($data['skills_needed'] ?? ''),
            'start_date'      => $data['start_date'] ?? null,
            'end_date'        => $data['end_date'] ?? null,
            'category_id'     => !empty($data['category_id']) ? (int) $data['category_id'] : null,
            'is_active'       => true,
        ]);

        $opportunity->save();

        return $opportunity->fresh(['creator', 'organization', 'category']);
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

    /**
     * Get shifts the user is signed up for.
     */
    public function getMyShifts(int $userId): array
    {
        return DB::table('vol_shift_signups as ss')
            ->join('vol_shifts as s', 'ss.shift_id', '=', 's.id')
            ->join('vol_opportunities as o', 's.opportunity_id', '=', 'o.id')
            ->where('ss.user_id', $userId)
            ->select('s.*', 'o.title as opportunity_title', 'o.location')
            ->orderBy('s.start_time')
            ->get()
            ->map(fn ($i) => (array) $i)
            ->all();
    }

    /**
     * Get logged hours for a user.
     */
    public function getMyHours(int $userId): array
    {
        return VolLog::query()
            ->with(['organization:id,name', 'opportunity:id,title'])
            ->where('user_id', $userId)
            ->orderByDesc('date_logged')
            ->get()
            ->toArray();
    }

    /**
     * Get hours summary/stats for a user.
     */
    public function getHoursSummary(int $userId): array
    {
        $total = VolLog::where('user_id', $userId)
            ->where('status', 'approved')
            ->sum('hours');

        $pending = VolLog::where('user_id', $userId)
            ->where('status', 'pending')
            ->sum('hours');

        $totalLogs = VolLog::where('user_id', $userId)->count();

        $thisMonth = VolLog::where('user_id', $userId)
            ->where('status', 'approved')
            ->where('date_logged', '>=', now()->startOfMonth())
            ->sum('hours');

        return [
            'total_approved_hours' => round((float) $total, 2),
            'pending_hours'        => round((float) $pending, 2),
            'this_month_hours'     => round((float) $thisMonth, 2),
            'total_entries'        => (int) $totalLogs,
        ];
    }

    /**
     * Get volunteer organisations with cursor pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getOrganisations(array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 50);
        $cursor = $filters['cursor'] ?? null;

        $query = VolOrganization::query()
            ->with('owner:id,first_name,last_name,avatar_url')
            ->where('status', 'approved');

        if (! empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'LIKE', $term)
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
     * Get a single organisation by ID with stats.
     */
    public function getOrganisationById(int $id): ?array
    {
        $org = VolOrganization::with('owner:id,first_name,last_name,avatar_url')
            ->find($id);

        if (! $org) {
            return null;
        }

        $data = $org->toArray();
        $data['opportunities_count'] = (int) VolOpportunity::where('organization_id', $id)->where('is_active', true)->count();
        $data['total_volunteers'] = (int) DB::table('vol_applications')
            ->whereIn('opportunity_id', function ($q) use ($id) {
                $q->select('id')->from('vol_opportunities')->where('organization_id', $id);
            })
            ->where('status', 'approved')
            ->distinct('user_id')
            ->count('user_id');

        return $data;
    }
}
