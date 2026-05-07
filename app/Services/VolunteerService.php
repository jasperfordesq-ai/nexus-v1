<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Events\VolLogStatusChanged;
use App\Events\VolunteerOpportunityCreated;
use App\Events\VolunteerOpportunityUpdated;
use App\I18n\LocaleContext;
use App\Models\VolApplication;
use App\Models\VolLog;
use App\Models\VolOpportunity;
use App\Models\VolOrganization;
use App\Models\VolShift;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * VolunteerService — Laravel DI-based service for volunteering operations.
 *
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
    public static function getOpportunities(array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 50);
        $cursor = $filters['cursor'] ?? null;

        $query = VolOpportunity::query()
            ->with(['creator:id,first_name,last_name,avatar_url', 'organization:id,name', 'category:id,name,color'])
            ->where('is_active', true)
            ->whereIn('status', ['open', 'active'])
            ->whereHas('organization', function (Builder $q) {
                $q->whereIn('status', ['approved', 'active']);
            });

        if (! empty($filters['organization_id'])) {
            $query->where('organization_id', (int) $filters['organization_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (!empty($filters['is_remote'])) {
            $query->where('is_remote', true);
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

        // Proximity / radius filter using Haversine formula
        $nearLat = isset($filters['near_lat']) ? (float) $filters['near_lat'] : null;
        $nearLng = isset($filters['near_lng']) ? (float) $filters['near_lng'] : null;
        $radiusKm = isset($filters['radius_km']) ? (float) $filters['radius_km'] : null;

        if ($nearLat !== null && $nearLng !== null && $radiusKm !== null) {
            $haversine = "(6371 * acos(LEAST(1.0, GREATEST(-1.0,
                cos(radians(?)) * cos(radians(vol_opportunities.latitude)) * cos(radians(vol_opportunities.longitude) - radians(?)) +
                sin(radians(?)) * sin(radians(vol_opportunities.latitude))
            ))))";
            $query->whereNotNull('vol_opportunities.latitude')
                  ->whereNotNull('vol_opportunities.longitude')
                  ->selectRaw("vol_opportunities.*, {$haversine} AS distance_km", [$nearLat, $nearLng, $nearLat])
                  ->having('distance_km', '<=', $radiusKm)
                  ->orderBy('distance_km');
        } else {
            $query->orderByDesc('id');
        }

        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        $results = $items->map(function ($opp) {
            $data = $opp->toArray();
            $data['is_remote'] = (bool) ($opp->is_remote ?? false);
            if (isset($opp->distance_km)) {
                $data['distance_km'] = round((float) $opp->distance_km, 2);
            }
            return $data;
        })->all();

        return [
            'items'    => array_values($results),
            'cursor'   => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single opportunity by ID.
     */
    public static function getById(int $id): ?VolOpportunity
    {
        return VolOpportunity::query()
            ->with(['creator', 'organization', 'category', 'shifts', 'applications'])
            ->where('id', $id)
            ->where('tenant_id', TenantContext::getId())
            ->first();
    }

    /**
     * Create a new volunteer opportunity.
     */
    public static function createOpportunity(int $userId, array $data): ?VolOpportunity
    {
        self::$errors = [];
        $tenantId = self::getTenantId();
        $organizationId = isset($data['organization_id']) ? (int) $data['organization_id'] : 0;

        if ($organizationId <= 0) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.volunteer_organization_required'), 'field' => 'organization_id'];
            return null;
        }

        $organization = DB::selectOne(
            "SELECT id, user_id, status FROM vol_organizations WHERE id = ? AND tenant_id = ?",
            [$organizationId, $tenantId]
        );

        if (!$organization || !self::isApprovedOrganizationStatus($organization->status ?? null)) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.organization_not_found'), 'field' => 'organization_id'];
            return null;
        }

        if (!self::canManageOrganization((array) $organization, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.volunteer_org_manage_forbidden'), 'field' => 'organization_id'];
            return null;
        }

        $categoryId = !empty($data['category_id']) ? (int) $data['category_id'] : null;
        if ($categoryId !== null) {
            $categoryQuery = "SELECT id FROM categories WHERE id = ?";
            $categoryParams = [$categoryId];
            if (Schema::hasColumn('categories', 'tenant_id')) {
                $categoryQuery .= " AND (tenant_id = ? OR tenant_id IS NULL)";
                $categoryParams[] = $tenantId;
            }
            $categoryQuery .= " LIMIT 1";

            if (!DB::selectOne($categoryQuery, $categoryParams)) {
                self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.category_not_found'), 'field' => 'category_id'];
                return null;
            }
        }

        $opportunityData = [
            'created_by'      => $userId,
            'organization_id' => $organizationId,
            'title'           => trim($data['title'] ?? ''),
            'description'     => trim($data['description'] ?? ''),
            'location'        => trim($data['location'] ?? ''),
            'is_remote'       => !empty($data['is_remote']),
            'skills_needed'   => trim($data['skills_needed'] ?? ''),
            'start_date'      => $data['start_date'] ?? null,
            'end_date'        => $data['end_date'] ?? null,
            'category_id'     => $categoryId,
            'status'          => 'active',
            'is_active'       => true,
        ];

        $opportunity = VolOpportunity::unguarded(
            fn () => VolOpportunity::create($opportunityData)
        );

        $opportunity->save();

        $fresh = $opportunity->fresh(['creator', 'organization', 'category']);

        try {
            VolunteerOpportunityCreated::dispatch($fresh ?? $opportunity, (int) TenantContext::getId());
        } catch (\Throwable $e) {
            Log::warning('Failed to dispatch VolunteerOpportunityCreated', [
                'opportunity_id' => $opportunity->id ?? null,
                'error'          => $e->getMessage(),
            ]);
        }

        return $fresh ?? $opportunity;
    }

    /**
     * Apply to a volunteer opportunity.
     */
    public static function apply(int $opportunityId, int $userId, array $data = []): VolApplication
    {
        self::$errors = [];
        $tenantId = self::getTenantId();

        // Verify opportunity exists and is active within the current tenant
        $opportunity = VolOpportunity::where('id', $opportunityId)
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();

        if (!$opportunity) {
            throw new \RuntimeException(__('api.volunteer_opportunity_not_active'), 404);
        }

        // Prevent duplicate applications
        $existing = VolApplication::where('opportunity_id', $opportunityId)
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($existing) {
            throw new \RuntimeException(__('api.already_applied'), 409);
        }

        $shiftId = !empty($data['shift_id']) ? (int) $data['shift_id'] : null;
        if ($shiftId !== null) {
            $shift = DB::selectOne(
                "SELECT id, opportunity_id, start_time, capacity FROM vol_shifts WHERE id = ? AND opportunity_id = ? AND tenant_id = ?",
                [$shiftId, $opportunityId, $tenantId]
            );

            if (!$shift) {
                throw new \RuntimeException(__('api.volunteer_shift_not_found'), 404);
            }

            if (strtotime((string) $shift->start_time) < time()) {
                throw new \RuntimeException(__('api.volunteer_shift_started'), 422);
            }

            if (!empty($shift->capacity)) {
                $signupCount = (int) DB::selectOne(
                    "SELECT COUNT(*) as cnt FROM vol_applications WHERE shift_id = ? AND status = 'approved' AND tenant_id = ?",
                    [$shiftId, $tenantId]
                )->cnt;

                if ($signupCount >= (int) $shift->capacity) {
                    throw new \RuntimeException(__('api.volunteer_shift_at_capacity'), 422);
                }
            }
        }

        $application = VolApplication::create([
            'opportunity_id' => $opportunityId,
            'user_id'        => $userId,
            'message'        => trim($data['message'] ?? ''),
            'shift_id'       => $shiftId,
        ]);

        $application->save();

        return $application->fresh(['user', 'opportunity']);
    }

    /**
     * Get applications submitted by a user with cursor pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public static function getMyApplications(int $userId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 50);
        $cursorId = self::decodeCursor($filters['cursor'] ?? null);
        $tenantId = self::getTenantId();

        $query = DB::table('vol_applications as a')
            ->join('vol_opportunities as o', function ($join) {
                $join->on('a.opportunity_id', '=', 'o.id')
                    ->whereColumn('o.tenant_id', 'a.tenant_id');
            })
            ->leftJoin('vol_organizations as org', function ($join) {
                $join->on('o.organization_id', '=', 'org.id')
                    ->whereColumn('org.tenant_id', 'a.tenant_id');
            })
            ->leftJoin('vol_shifts as s', function ($join) {
                $join->on('a.shift_id', '=', 's.id')
                    ->whereColumn('s.tenant_id', 'a.tenant_id');
            })
            ->where('a.user_id', $userId)
            ->where('a.tenant_id', $tenantId)
            ->select([
                'a.id',
                'a.status',
                'a.message',
                'a.org_note',
                'a.shift_id',
                'a.created_at',
                'o.id as opportunity_id',
                'o.title as opportunity_title',
                'o.location as opportunity_location',
                'org.id as organization_id',
                'org.name as organization_name',
                'org.logo_url as organization_logo_url',
                's.start_time as shift_start_time',
                's.end_time as shift_end_time',
            ]);

        if (!empty($filters['status']) && in_array($filters['status'], ['pending', 'approved', 'declined', 'withdrawn'], true)) {
            $query->where('a.status', $filters['status']);
        }

        if ($cursorId) {
            $query->where('a.id', '<', $cursorId);
        }

        $query->orderByDesc('a.id');
        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        $results = $items->map(fn ($row) => [
            'id' => (int) $row->id,
            'status' => $row->status,
            'message' => $row->message ?? '',
            'org_note' => $row->org_note ?? null,
            'opportunity' => [
                'id' => (int) $row->opportunity_id,
                'title' => $row->opportunity_title,
                'location' => $row->opportunity_location ?? '',
            ],
            'organization' => [
                'id' => $row->organization_id ? (int) $row->organization_id : 0,
                'name' => $row->organization_name ?? '',
                'logo_url' => $row->organization_logo_url ?? null,
            ],
            'shift' => $row->shift_id ? [
                'id' => (int) $row->shift_id,
                'start_time' => $row->shift_start_time,
                'end_time' => $row->shift_end_time,
            ] : null,
            'created_at' => $row->created_at,
        ])->all();

        return [
            'items'    => array_values($results),
            'cursor'   => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get shifts the user is signed up for with cursor pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public static function getMyShifts(int $userId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 50);
        $cursor = $filters['cursor'] ?? null;

        $tenantId = TenantContext::getId();

        $query = DB::table('vol_applications as a')
            ->join('vol_shifts as s', function ($join) {
                $join->on('a.shift_id', '=', 's.id')
                    ->on('a.tenant_id', '=', 's.tenant_id');
            })
            ->join('vol_opportunities as o', function ($join) {
                $join->on('s.opportunity_id', '=', 'o.id')
                    ->on('s.tenant_id', '=', 'o.tenant_id');
            })
            ->where('a.user_id', $userId)
            ->where('a.status', 'approved')
            ->where('a.tenant_id', $tenantId)
            ->whereNotNull('a.shift_id')
            ->select('s.*', 'o.title as opportunity_title', 'o.location', 'a.id as application_id');

        if ($cursor !== null && ($cid = base64_decode($cursor, true)) !== false) {
            $query->where('s.id', '<', (int) $cid);
        }

        $query->orderByDesc('s.id');
        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        return [
            'items'    => $items->map(fn ($i) => (array) $i)->all(),
            'cursor'   => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get logged hours for a user with cursor pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public static function getMyHours(int $userId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 50);
        $cursor = $filters['cursor'] ?? null;

        $query = VolLog::query()
            ->with(['organization:id,name', 'opportunity:id,title'])
            ->where('user_id', $userId);

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
     * Get hours summary/stats for a user.
     */
    public static function getHoursSummary(int $userId): array
    {
        $tenantId = self::getTenantId();

        $total = VolLog::where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->sum('hours');

        $pending = VolLog::where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->sum('hours');

        $declineStatus = self::getDeclineStatusValue();
        $declined = VolLog::where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', $declineStatus)
            ->sum('hours');

        $totalLogs = VolLog::where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->count();

        $thisMonth = VolLog::where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->where('date_logged', '>=', now()->startOfMonth())
            ->sum('hours');

        $byOrg = VolLog::where('vol_logs.user_id', $userId)
            ->where('vol_logs.tenant_id', $tenantId)
            ->where('vol_logs.status', 'approved')
            ->join('vol_organizations', function ($join) {
                $join->on('vol_logs.organization_id', '=', 'vol_organizations.id')
                    ->on('vol_logs.tenant_id', '=', 'vol_organizations.tenant_id');
            })
            ->selectRaw('vol_organizations.name, SUM(vol_logs.hours) as hours')
            ->groupBy('vol_organizations.id', 'vol_organizations.name')
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'hours' => round((float) $r->hours, 2)])
            ->toArray();

        $byMonth = VolLog::where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->selectRaw("DATE_FORMAT(date_logged, '%Y-%m') as month, SUM(hours) as hours")
            ->groupByRaw("DATE_FORMAT(date_logged, '%Y-%m')")
            ->orderBy('month')
            ->get()
            ->map(fn ($r) => ['month' => $r->month, 'hours' => round((float) $r->hours, 2)])
            ->toArray();

        return [
            'total_verified'       => round((float) $total, 2),
            'total_pending'        => round((float) $pending, 2),
            'total_declined'       => round((float) $declined, 2),
            'by_organization'      => $byOrg,
            'by_month'             => $byMonth,
            // Backward-compatible fields
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
    public static function getOrganisations(array $filters = []): array
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
    public static function getOrganisationById(int $id): ?array
    {
        $org = VolOrganization::with('owner:id,first_name,last_name,avatar_url')
            ->find($id);

        if (! $org) {
            return null;
        }

        $data = $org->toArray();
        $tenantId = self::getTenantId();
        $data['opportunities_count'] = (int) VolOpportunity::where('organization_id', $id)
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();
        $data['total_volunteers'] = (int) DB::table('vol_applications')
            ->where('tenant_id', $tenantId)
            ->whereIn('opportunity_id', function ($q) use ($id, $tenantId) {
                $q->select('id')
                    ->from('vol_opportunities')
                    ->where('organization_id', $id)
                    ->where('tenant_id', $tenantId);
            })
            ->where('status', 'approved')
            ->distinct('user_id')
            ->count('user_id');

        return $data;
    }

    // ========================================
    // ERROR TRACKING
    // ========================================

    /** @var array Validation/business errors from the last operation */
    private static array $errors = [];

    /** Cached decline status value for vol_logs (declined vs rejected schema variants) */
    private static ?string $declineStatusValue = null;

    /** Status assigned by the last successful hour-log operation. */
    private static string $lastLogStatus = 'pending';

    /**
     * Get errors from the last operation.
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }

    public static function getLastLogStatus(): string
    {
        return self::$lastLogStatus;
    }

    // ========================================
    // OPPORTUNITIES — update / delete / getById (legacy-compatible)
    // ========================================

    /**
     * Get single opportunity by ID (legacy-compatible format with shifts and viewer context).
     */
    public static function getOpportunityById(int $id, ?int $viewerId = null): ?array
    {
        $tenantId = self::getTenantId();

        $opp = DB::selectOne("
            SELECT opp.*, org.name as org_name, org.logo_url as org_logo,
                   org.status as org_status, org.user_id as org_owner_id,
                   cat.name as category_name
            FROM vol_opportunities opp
            JOIN vol_organizations org ON opp.organization_id = org.id
            LEFT JOIN categories cat ON opp.category_id = cat.id
            WHERE opp.id = ? AND opp.tenant_id = ? AND org.tenant_id = ?
        ", [$id, $tenantId, $tenantId]);

        if (!$opp) {
            return null;
        }

        $viewerCanManage = $viewerId ? self::canManageOpportunity((array) $opp, $viewerId) : false;
        if (!$viewerCanManage) {
            $isPublicStatus = ((int) ($opp->is_active ?? 0) === 1)
                && in_array((string) ($opp->status ?? ''), ['open', 'active'], true)
                && self::isApprovedOrganizationStatus($opp->org_status ?? null);

            if (!$isPublicStatus) {
                return null;
            }
        }

        $formatted = self::formatOpportunity((array) $opp);
        $formatted['shifts'] = self::getShiftsForOpportunity($id);

        if ($viewerId) {
            $formatted['has_applied'] = (bool) DB::selectOne(
                "SELECT 1 FROM vol_applications WHERE opportunity_id = ? AND user_id = ? AND tenant_id = ? LIMIT 1",
                [$id, $viewerId, $tenantId]
            );
            $userApp = DB::selectOne(
                "SELECT * FROM vol_applications WHERE opportunity_id = ? AND user_id = ? AND tenant_id = ?",
                [$id, $viewerId, $tenantId]
            );
            $formatted['application'] = $userApp ? [
                'id'         => (int) $userApp->id,
                'status'     => $userApp->status,
                'message'    => $userApp->message,
                'shift_id'   => $userApp->shift_id ? (int) $userApp->shift_id : null,
                'created_at' => $userApp->created_at,
            ] : null;
            $formatted['is_owner'] = $viewerCanManage;
        }

        return $formatted;
    }

    /**
     * Update an opportunity.
     */
    public static function updateOpportunity(int $id, int $userId, array $data): bool
    {
        self::$errors = [];
        $tenantId = self::getTenantId();

        $opp = DB::selectOne("
            SELECT opp.*, org.user_id as org_owner_id
            FROM vol_opportunities opp
            JOIN vol_organizations org ON opp.organization_id = org.id
            WHERE opp.id = ? AND opp.tenant_id = ? AND org.tenant_id = ?
        ", [$id, $tenantId, $tenantId]);

        if (!$opp) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Opportunity not found'];
            return false;
        }

        if (!self::canManageOpportunity((array) $opp, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to manage this opportunity'];
            return false;
        }

        if (array_key_exists('category_id', $data) && $data['category_id'] !== null) {
            $categoryQuery = "SELECT id FROM categories WHERE id = ?";
            $categoryParams = [(int) $data['category_id']];
            if (Schema::hasColumn('categories', 'tenant_id')) {
                $categoryQuery .= " AND (tenant_id = ? OR tenant_id IS NULL)";
                $categoryParams[] = $tenantId;
            }
            $categoryQuery .= " LIMIT 1";

            if (!DB::selectOne($categoryQuery, $categoryParams)) {
                self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.category_not_found'), 'field' => 'category_id'];
                return false;
            }
        }

        try {
            $fields = [];
            $params = [];
            foreach (['title', 'description', 'location', 'skills_needed', 'start_date', 'end_date', 'category_id', 'is_remote'] as $field) {
                if (array_key_exists($field, $data)) {
                    $fields[] = "{$field} = ?";
                    $params[] = $field === 'is_remote' ? (!empty($data[$field]) ? 1 : 0) : $data[$field];
                }
            }

            if (empty($fields)) {
                return true;
            }

            $params[] = $id;
            $params[] = $tenantId;
            DB::update("UPDATE vol_opportunities SET " . implode(', ', $fields) . " WHERE id = ? AND tenant_id = ?", $params);

            try {
                $updated = VolOpportunity::query()->find($id);
                if ($updated) {
                    VolunteerOpportunityUpdated::dispatch($updated, (int) $tenantId);
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to dispatch VolunteerOpportunityUpdated', [
                    'opportunity_id' => $id,
                    'error'          => $e->getMessage(),
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::warning("VolunteerService::updateOpportunity error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to update opportunity'];
            return false;
        }
    }

    /**
     * Delete (deactivate) an opportunity.
     */
    public static function deleteOpportunity(int $id, int $userId): bool
    {
        self::$errors = [];
        $tenantId = self::getTenantId();

        $opp = DB::selectOne("
            SELECT opp.*, org.user_id as org_owner_id
            FROM vol_opportunities opp
            JOIN vol_organizations org ON opp.organization_id = org.id
            WHERE opp.id = ? AND opp.tenant_id = ? AND org.tenant_id = ?
        ", [$id, $tenantId, $tenantId]);

        if (!$opp) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Opportunity not found'];
            return false;
        }

        if (!self::canManageOpportunity((array) $opp, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to manage this opportunity'];
            return false;
        }

        try {
            DB::update("UPDATE vol_opportunities SET is_active = 0 WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);

            // Notify volunteers with approved applications that the opportunity is cancelled
            try {
                $oppTitle = $opp->title ?? '';
                $signedUpUsers = DB::select(
                    "SELECT DISTINCT va.user_id, u.preferred_language
                     FROM vol_applications va
                     JOIN users u ON u.id = va.user_id AND u.tenant_id = va.tenant_id
                     WHERE va.opportunity_id = ? AND va.status = 'approved' AND va.tenant_id = ?",
                    [$id, $tenantId]
                );
                foreach ($signedUpUsers as $row) {
                    $recipientId = (int) $row->user_id;
                    LocaleContext::withLocale($row, function () use ($recipientId, $oppTitle) {
                        \App\Models\Notification::createNotification(
                            $recipientId,
                            __('api_controllers_3.volunteer.opportunity_cancelled', ['title' => $oppTitle]),
                            '/volunteering',
                            'volunteer_opportunity'
                        );
                    });
                }
            } catch (\Throwable $notifErr) {
                Log::warning('VolunteerService::deleteOpportunity notification failed', ['error' => $notifErr->getMessage()]);
            }

            return true;
        } catch (\Exception $e) {
            Log::warning("VolunteerService::deleteOpportunity error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to delete opportunity'];
            return false;
        }
    }

    // ========================================
    // APPLICATIONS — getForOpportunity / handle / withdraw
    // ========================================

    /**
     * Get applications for an opportunity (org admin only).
     */
    public static function getApplicationsForOpportunity(int $opportunityId, int $adminUserId, array $filters = []): ?array
    {
        self::$errors = [];
        $tenantId = self::getTenantId();

        $opp = DB::selectOne("
            SELECT opp.*, org.user_id as org_owner_id
            FROM vol_opportunities opp
            JOIN vol_organizations org ON opp.organization_id = org.id
            WHERE opp.id = ? AND opp.tenant_id = ? AND org.tenant_id = ?
        ", [$opportunityId, $tenantId, $tenantId]);

        if (!$opp) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Opportunity not found'];
            return null;
        }

        if (!self::canManageOpportunity((array) $opp, $adminUserId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to manage this opportunity'];
            return null;
        }

        $limit = min($filters['limit'] ?? 20, 50);
        $cursorId = self::decodeCursor($filters['cursor'] ?? null);

        $sql = "
            SELECT a.*, a.org_note, u.name as user_name, u.email as user_email, u.avatar_url as user_avatar,
                   s.start_time as shift_start, s.end_time as shift_end
            FROM vol_applications a
            JOIN users u ON a.user_id = u.id AND u.tenant_id = a.tenant_id
            LEFT JOIN vol_shifts s ON a.shift_id = s.id AND s.tenant_id = a.tenant_id
            WHERE a.opportunity_id = ? AND a.tenant_id = ?
        ";
        $params = [$opportunityId, $tenantId];

        if (!empty($filters['status'])) {
            $sql .= " AND a.status = ?";
            $params[] = $filters['status'];
        }

        if ($cursorId) {
            $sql .= " AND a.id < ?";
            $params[] = $cursorId;
        }

        $sql .= " ORDER BY a.created_at DESC, a.id DESC LIMIT " . ($limit + 1);

        $rows = DB::select($sql, $params);
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $items = [];
        $lastId = null;
        foreach ($rows as $row) {
            $lastId = $row->id;
            $items[] = [
                'id'      => (int) $row->id,
                'status'  => $row->status,
                'message' => $row->message,
                'org_note' => $row->org_note ?? null,
                'user'    => [
                    'id'         => (int) $row->user_id,
                    'name'       => $row->user_name,
                    'email'      => $row->user_email,
                    'avatar_url' => $row->user_avatar,
                ],
                'shift' => $row->shift_id ? [
                    'id'         => (int) $row->shift_id,
                    'start_time' => $row->shift_start,
                    'end_time'   => $row->shift_end,
                ] : null,
                'created_at' => $row->created_at,
            ];
        }

        return [
            'items'    => $items,
            'cursor'   => $hasMore && $lastId ? base64_encode((string) $lastId) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Handle application (approve/decline).
     */
    public static function handleApplication(int $applicationId, int $adminUserId, string $action, string $orgNote = ''): bool
    {
        self::$errors = [];

        if (!in_array($action, ['approve', 'decline'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Action must be approve or decline'];
            return false;
        }

        $tenantId = self::getTenantId();

        $app = DB::selectOne("
            SELECT a.*, opp.title, opp.organization_id, org.user_id as org_owner_id
            FROM vol_applications a
            JOIN vol_opportunities opp ON a.opportunity_id = opp.id AND opp.tenant_id = a.tenant_id
            JOIN vol_organizations org ON opp.organization_id = org.id AND org.tenant_id = a.tenant_id
            WHERE a.id = ? AND a.tenant_id = ?
        ", [$applicationId, $tenantId]);

        if (!$app) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Application not found'];
            return false;
        }

        if (!self::canManageOpportunity((array) $app, $adminUserId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to manage this opportunity'];
            return false;
        }

        $status = $action === 'approve' ? 'approved' : 'declined';

        try {
            DB::transaction(function () use ($status, $orgNote, $applicationId, $tenantId, $app) {
                if ($status === 'approved' && !self::shiftHasApprovalCapacity((int) ($app->shift_id ?? 0), $tenantId)) {
                    throw new \DomainException(__('api.volunteer_shift_at_capacity'));
                }

                DB::update(
                    "UPDATE vol_applications SET status = ?, org_note = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?",
                    [$status, $orgNote !== '' ? $orgNote : null, $applicationId, $tenantId]
                );
            });

            return true;
        } catch (\DomainException $e) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage(), 'field' => 'shift_id'];
            return false;
        } catch (\Exception $e) {
            Log::warning("VolunteerService::handleApplication error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to update application'];
            return false;
        }
    }

    /**
     * Withdraw an application.
     */
    public static function withdrawApplication(int $applicationId, int $userId): bool
    {
        self::$errors = [];
        $tenantId = self::getTenantId();

        $app = DB::selectOne("
            SELECT a.*, opp.title, opp.id as opportunity_id, org.user_id as org_owner_id
            FROM vol_applications a
            JOIN vol_opportunities opp ON a.opportunity_id = opp.id
            JOIN vol_organizations org ON opp.organization_id = org.id
            WHERE a.id = ? AND a.tenant_id = ?
        ", [$applicationId, $tenantId]);

        if (!$app) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Application not found'];
            return false;
        }

        if ((int) $app->user_id !== $userId) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'This is not your application'];
            return false;
        }

        if ($app->status === 'approved') {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'You cannot withdraw an approved application. Please contact the organisation directly.'];
            return false;
        }

        try {
            DB::delete("DELETE FROM vol_applications WHERE id = ? AND tenant_id = ?", [$applicationId, $tenantId]);
            return true;
        } catch (\Exception $e) {
            Log::warning("VolunteerService::withdrawApplication error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to withdraw application'];
            return false;
        }
    }

    // ========================================
    // SHIFTS — list / signUp / cancel
    // ========================================

    /**
     * Get shifts for an opportunity.
     */
    public static function getShiftsForOpportunity(int $opportunityId): array
    {
        $tenantId = self::getTenantId();

        $shifts = DB::select(
            "SELECT * FROM vol_shifts WHERE opportunity_id = ? AND tenant_id = ? ORDER BY start_time ASC",
            [$opportunityId, $tenantId]
        );

        return array_map(function ($shift) use ($tenantId) {
            $signupCount = (int) DB::selectOne(
                "SELECT COUNT(*) as cnt FROM vol_applications WHERE shift_id = ? AND status = 'approved' AND tenant_id = ?",
                [$shift->id, $tenantId]
            )->cnt;

            return [
                'id'              => (int) $shift->id,
                'start_time'      => $shift->start_time,
                'end_time'        => $shift->end_time,
                'capacity'        => $shift->capacity ? (int) $shift->capacity : null,
                'signup_count'    => $signupCount,
                'spots_available' => $shift->capacity ? max(0, (int) $shift->capacity - $signupCount) : null,
            ];
        }, $shifts);
    }

    /**
     * Sign up for a shift (requires approved application).
     */
    public static function signUpForShift(int $shiftId, int $userId): bool
    {
        self::$errors = [];
        $tenantId = self::getTenantId();

        // Check user has approved application for this opportunity (outside lock — read-only pre-check)
        $shift = DB::selectOne("SELECT * FROM vol_shifts WHERE id = ? AND tenant_id = ?", [$shiftId, $tenantId]);
        if (!$shift) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Shift not found'];
            return false;
        }

        $opportunityId = (int) $shift->opportunity_id;

        $app = DB::selectOne(
            "SELECT id FROM vol_applications WHERE opportunity_id = ? AND user_id = ? AND status = 'approved' AND tenant_id = ?",
            [$opportunityId, $userId, $tenantId]
        );

        if (!$app) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You must have an approved application to sign up for shifts'];
            return false;
        }

        // Check shift hasn't passed
        if (strtotime($shift->start_time) < time()) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'This shift has already started'];
            return false;
        }

        // Atomic capacity check + signup inside a transaction with row lock
        try {
            return DB::transaction(function () use ($shiftId, $tenantId, $app, $userId, $shift) {
                // Lock the shift row to prevent concurrent signups from exceeding capacity
                $lockedShift = DB::selectOne(
                    "SELECT * FROM vol_shifts WHERE id = ? AND tenant_id = ? FOR UPDATE",
                    [$shiftId, $tenantId]
                );

                if (!$lockedShift) {
                    self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Shift not found'];
                    return false;
                }

                // Re-check capacity under the lock
                $signupCount = (int) DB::selectOne(
                    "SELECT COUNT(*) as cnt FROM vol_applications WHERE shift_id = ? AND status = 'approved' AND tenant_id = ?",
                    [$shiftId, $tenantId]
                )->cnt;

                if ($lockedShift->capacity && $signupCount >= (int) $lockedShift->capacity) {
                    self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'This shift is at capacity'];
                    return false;
                }

                DB::update(
                    "UPDATE vol_applications SET shift_id = ? WHERE id = ? AND tenant_id = ?",
                    [$shiftId, $app->id, $tenantId]
                );

                // Notify volunteer of confirmed shift signup
                try {
                    $shiftDate = isset($lockedShift->start_time)
                        ? date('d M Y H:i', strtotime($lockedShift->start_time))
                        : date('d M Y', strtotime($shift->start_time));
                    $recipient = DB::table('users')
                        ->where('id', $userId)
                        ->where('tenant_id', $tenantId)
                        ->select(['id', 'preferred_language'])
                        ->first();
                    LocaleContext::withLocale($recipient, function () use ($userId, $shiftDate) {
                        \App\Models\Notification::createNotification(
                            $userId,
                            __('api_controllers_3.volunteer.shift_signup_confirmed', ['date' => $shiftDate]),
                            '/volunteering',
                            'volunteer_shift'
                        );
                    });
                } catch (\Throwable $notifErr) {
                    Log::warning('VolunteerService::signUpForShift notification failed', ['error' => $notifErr->getMessage()]);
                }

                return true;
            });
        } catch (\Exception $e) {
            Log::warning("VolunteerService::signUpForShift error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to sign up for shift'];
            return false;
        }
    }

    /**
     * Cancel shift signup.
     */
    public static function cancelShiftSignup(int $shiftId, int $userId): bool
    {
        self::$errors = [];
        $tenantId = self::getTenantId();

        $shift = DB::selectOne("SELECT * FROM vol_shifts WHERE id = ? AND tenant_id = ?", [$shiftId, $tenantId]);
        if (!$shift) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Shift not found'];
            return false;
        }

        if (strtotime($shift->start_time) < time()) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Cannot cancel a shift that has already started'];
            return false;
        }

        try {
            $affected = DB::update(
                "UPDATE vol_applications SET shift_id = NULL WHERE opportunity_id = ? AND user_id = ? AND shift_id = ? AND tenant_id = ?",
                [$shift->opportunity_id, $userId, $shiftId, $tenantId]
            );

            if ($affected === 0) {
                self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'You are not signed up for this shift'];
                return false;
            }

            // Notify volunteer of cancellation
            try {
                $recipient = DB::table('users')
                    ->where('id', $userId)
                    ->where('tenant_id', $tenantId)
                    ->select(['id', 'preferred_language'])
                    ->first();
                LocaleContext::withLocale($recipient, function () use ($userId) {
                    \App\Models\Notification::createNotification(
                        $userId,
                        __('api_controllers_3.volunteer.shift_signup_cancelled'),
                        '/volunteering',
                        'volunteer_shift'
                    );
                });
            } catch (\Throwable $notifErr) {
                Log::warning('VolunteerService::cancelShiftSignup notification failed', ['error' => $notifErr->getMessage()]);
            }

            return true;
        } catch (\Exception $e) {
            Log::warning("VolunteerService::cancelShiftSignup error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to cancel shift signup'];
            return false;
        }
    }

    // ========================================
    // HOURS — log / pending / verify
    // ========================================

    /**
     * Log volunteering hours.
     */
    public static function logHours(int $userId, array $data): ?int
    {
        self::$errors = [];
        self::$lastLogStatus = 'pending';
        $tenantId = self::getTenantId();
        $policy = app(CaringCommunityWorkflowPolicyService::class)->get($tenantId);

        if (!$policy['allow_member_self_log'] && !self::canBypassCaringWorkflowPolicy($userId, $tenantId)) {
            self::$errors[] = [
                'code' => 'FORBIDDEN',
                'message' => __('api.caring_self_log_disabled'),
                'field' => 'hours',
            ];
            return null;
        }

        if (empty($data['organization_id'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Organization is required', 'field' => 'organization_id'];
            return null;
        }

        if (empty($data['date'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Date is required', 'field' => 'date'];
            return null;
        }

        if (empty($data['hours']) || $data['hours'] <= 0) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Hours must be greater than 0', 'field' => 'hours'];
            return null;
        }

        if ($data['hours'] > 24) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Cannot log more than 24 hours in a single entry', 'field' => 'hours'];
            return null;
        }

        if (strtotime($data['date']) > time()) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Cannot log hours for a future date', 'field' => 'date'];
            return null;
        }

        $organizationId = (int) $data['organization_id'];
        $oppId = !empty($data['opportunity_id']) ? (int) $data['opportunity_id'] : null;

        // Verify organization exists and the user has a real volunteering relationship with it.
        $org = DB::selectOne("SELECT id, user_id, auto_pay_enabled FROM vol_organizations WHERE id = ? AND tenant_id = ?", [$organizationId, $tenantId]);
        if (!$org) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.organization_not_found'), 'field' => 'organization_id'];
            return null;
        }

        // If opportunity_id is provided, it must belong to the selected organization.
        if ($oppId !== null) {
            $opportunity = DB::selectOne(
                "SELECT id FROM vol_opportunities WHERE id = ? AND organization_id = ? AND tenant_id = ?",
                [$oppId, $organizationId, $tenantId]
            );

            if (!$opportunity) {
                self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.opportunity_not_found'), 'field' => 'opportunity_id'];
                return null;
            }

            $hasApp = DB::selectOne(
                "SELECT id FROM vol_applications WHERE opportunity_id = ? AND user_id = ? AND status = 'approved' AND tenant_id = ?",
                [$oppId, $userId, $tenantId]
            );
            if (!$hasApp) {
                self::$errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.volunteer_approved_application_required'), 'field' => 'opportunity_id'];
                return null;
            }
        } elseif (!self::userCanVolunteerForOrganization($tenantId, $userId, $organizationId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.volunteer_org_relationship_required'), 'field' => 'organization_id'];
            return null;
        }

        // Prevent duplicate hour logging for the same org + date + opportunity
        if ($oppId !== null) {
            $duplicateCheck = DB::selectOne(
                "SELECT id FROM vol_logs WHERE user_id = ? AND tenant_id = ? AND organization_id = ? AND date_logged = ? AND opportunity_id = ? AND status NOT IN ('declined', 'rejected')",
                [$userId, $tenantId, $organizationId, $data['date'], $oppId]
            );
        } else {
            $duplicateCheck = DB::selectOne(
                "SELECT id FROM vol_logs WHERE user_id = ? AND tenant_id = ? AND organization_id = ? AND date_logged = ? AND opportunity_id IS NULL AND status NOT IN ('declined', 'rejected')",
                [$userId, $tenantId, $organizationId, $data['date']]
            );
        }
        if ($duplicateCheck) {
            self::$errors[] = ['code' => 'ALREADY_EXISTS', 'message' => 'You have already logged hours for this organization and date'];
            return null;
        }

        try {
            $status = self::resolveCaringHourLogStatus($userId, $tenantId, $policy);
            $logId = null;

            DB::transaction(function () use ($tenantId, $userId, $data, $status, $org, $organizationId, $oppId, &$logId): void {
                DB::insert(
                    "INSERT INTO vol_logs (tenant_id, user_id, organization_id, opportunity_id, date_logged, hours, description, status, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $tenantId,
                        $userId,
                        $organizationId,
                        $oppId,
                        $data['date'],
                        (float) $data['hours'],
                        $data['description'] ?? '',
                        $status,
                    ]
                );

                $logId = (int) DB::getPdo()->lastInsertId();

                if ($status === 'approved' && (bool) $org->auto_pay_enabled) {
                    self::applyVolunteerAutoPayment(
                        $tenantId,
                        (int) $org->id,
                        (int) $org->user_id,
                        $userId,
                        $logId,
                        (float) $data['hours'],
                    );
                }
            });

            self::$lastLogStatus = $status;

            return $logId;
        } catch (\Exception $e) {
            Log::warning("VolunteerService::logHours error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to log hours'];
            return null;
        }
    }

    /**
     * Get pending hours waiting for approval by an org owner.
     */
    public static function getPendingHoursForOrgOwner(int $userId, array $filters = []): array
    {
        $tenantId = self::getTenantId();
        $limit = min($filters['limit'] ?? 20, 50);
        $cursorId = self::decodeCursor($filters['cursor'] ?? null);

        $sql = "
            SELECT l.id, l.hours, l.date_logged, l.description, l.status, l.created_at,
                   u.id as user_id, u.name as user_name, u.avatar_url as user_avatar,
                   org.id as org_id, org.name as org_name, org.logo_url as org_logo,
                   opp.id as opp_id, opp.title as opp_title
            FROM vol_logs l
            JOIN vol_organizations org ON l.organization_id = org.id AND org.tenant_id = l.tenant_id
            JOIN users u ON l.user_id = u.id
            LEFT JOIN vol_opportunities opp ON l.opportunity_id = opp.id AND opp.tenant_id = l.tenant_id
            LEFT JOIN org_members om
                ON om.tenant_id = l.tenant_id
                AND om.organization_id = org.id
                AND om.user_id = ?
                AND om.status = 'active'
                AND om.role IN ('owner', 'admin')
            WHERE (org.user_id = ? OR om.user_id IS NOT NULL) AND l.tenant_id = ? AND l.status = 'pending'
        ";
        $params = [$userId, $userId, $tenantId];

        if ($cursorId) {
            $sql .= " AND l.id < ?";
            $params[] = $cursorId;
        }

        $sql .= " ORDER BY l.created_at DESC, l.id DESC LIMIT " . ($limit + 1);

        $rows = DB::select($sql, $params);
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $items = [];
        $lastId = null;
        foreach ($rows as $row) {
            $lastId = $row->id;
            $items[] = [
                'id'           => (int) $row->id,
                'hours'        => (float) $row->hours,
                'date'         => $row->date_logged,
                'description'  => $row->description,
                'status'       => $row->status,
                'created_at'   => $row->created_at,
                'user'         => [
                    'id'         => (int) $row->user_id,
                    'name'       => $row->user_name,
                    'avatar_url' => $row->user_avatar,
                ],
                'organization' => [
                    'id'       => (int) $row->org_id,
                    'name'     => $row->org_name,
                    'logo_url' => $row->org_logo,
                ],
                'opportunity'  => $row->opp_id ? [
                    'id'    => (int) $row->opp_id,
                    'title' => $row->opp_title,
                ] : null,
            ];
        }

        return [
            'items'    => $items,
            'cursor'   => ($hasMore && $lastId) ? base64_encode((string) $lastId) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Verify hours (org admin).
     */
    public static function verifyHours(int $logId, int $adminUserId, string $action): bool
    {
        self::$errors = [];

        if (!in_array($action, ['approve', 'decline'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Action must be approve or decline'];
            return false;
        }

        $tenantId = self::getTenantId();

        $log = DB::selectOne("SELECT * FROM vol_logs WHERE id = ? AND tenant_id = ?", [$logId, $tenantId]);
        if (!$log) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Log entry not found'];
            return false;
        }

        if ($log->status !== 'pending') {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Only pending hours can be verified'];
            return false;
        }

        // Prevent users from approving their own hours
        if ((int) $log->user_id === $adminUserId) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You cannot verify your own logged hours'];
            return false;
        }

        // Verify admin owns or is admin of the org
        $org = DB::selectOne("SELECT * FROM vol_organizations WHERE id = ? AND tenant_id = ?", [(int) $log->organization_id, $tenantId]);
        if (!$org) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.volunteer_org_manage_forbidden')];
            return false;
        }

        $orgAdminRole = DB::selectOne(
            "SELECT role FROM org_members WHERE tenant_id = ? AND organization_id = ? AND user_id = ? AND status = 'active'",
            [$tenantId, (int) $org->id, $adminUserId]
        );

        if ((int) $org->user_id !== $adminUserId && !in_array($orgAdminRole->role ?? '', ['owner', 'admin'], true)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.volunteer_org_manage_forbidden')];
            return false;
        }

        $status = $action === 'approve' ? 'approved' : self::getDeclineStatusValue();

        try {
            $hours = (float) $log->hours;
            $volunteerId = (int) $log->user_id;
            $orgName = $org->name ?? __('emails.common.fallback_organization');
            $paymentResult = null;

            // DB transaction for data mutations only — notifications sent AFTER commit
            DB::transaction(function () use ($logId, $tenantId, $status, $action, $log, $org, $adminUserId, $hours, $volunteerId, &$paymentResult) {
                // 1. Update hours status
                DB::update("UPDATE vol_logs SET status = ? WHERE id = ? AND tenant_id = ?", [$status, $logId, $tenantId]);

                // 2. If approved and org has auto-pay enabled, pay inline (avoid nested transaction)
                if ($action === 'approve' && $org->auto_pay_enabled) {
                    $intHours = (int) floor($hours); // users.balance stores whole hours.
                    $orgDebit = (float) $intHours; // keep fractional remainders in the org wallet.
                    if ($intHours <= 0) {
                        return;
                    }
                    // Lock org row
                    $orgLocked = DB::selectOne(
                        "SELECT id, balance, user_id FROM vol_organizations WHERE id = ? AND tenant_id = ? FOR UPDATE",
                        [(int) $org->id, $tenantId]
                    );

                    if ($orgLocked && (float) $orgLocked->balance >= $orgDebit) {
                        // Deduct from org
                        if ($orgDebit > 0) {
                            DB::update(
                                "UPDATE vol_organizations SET balance = balance - ? WHERE id = ? AND tenant_id = ?",
                                [$orgDebit, (int) $org->id, $tenantId]
                            );
                        }
                        $newOrgBalance = (float) $orgLocked->balance - $orgDebit;

                        // Credit to volunteer (INT — use floor to match deduction)
                        if ($intHours > 0) {
                            DB::update(
                                "UPDATE users SET balance = balance + ? WHERE id = ? AND tenant_id = ?",
                                [$intHours, $volunteerId, $tenantId]
                            );
                        }

                        // Record in vol_org_transactions
                        $description = __('api.volunteer_auto_payment_description', ['hours' => $intHours]);
                        DB::insert("
                            INSERT INTO vol_org_transactions (tenant_id, vol_organization_id, user_id, vol_log_id, type, amount, balance_after, description, created_at)
                            VALUES (?, ?, ?, ?, 'volunteer_payment', ?, ?, ?, NOW())
                        ", [$tenantId, (int) $org->id, $volunteerId, $logId, -$orgDebit, $newOrgBalance, $description]);

                        // Record in main transactions table
                        DB::insert("
                            INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, description, transaction_type, status, created_at, updated_at)
                            VALUES (?, ?, ?, ?, ?, 'volunteer', 'completed', NOW(), NOW())
                        ", [$tenantId, (int) $org->user_id, $volunteerId, $intHours, $description]);

                        $paymentResult = 'paid';
                    } else {
                        $paymentResult = 'insufficient_balance';
                    }
                }
            });

            // 3. Notify the regional-points cascade-revert listener.
            // For pending -> approved or pending -> declined transitions the
            // listener is a no-op, but we dispatch unconditionally so that
            // if a future code path flips an approved log to another status
            // (e.g. an admin "revert approval" action) the cascade fires
            // automatically.
            try {
                VolLogStatusChanged::dispatch(
                    $tenantId,
                    $logId,
                    (string) $log->status,
                    $status,
                );
            } catch (\Throwable $e) {
                // Event dispatch failure must not break the parent flow.
            }

            // 4. Send notifications AFTER transaction committed successfully
            try {
                // Fetch volunteer preferred_language so the dispatched bell/push/email
                // render in the volunteer's locale, not the reviewing admin's.
                $volunteerRow = DB::table('users')
                    ->where('id', $volunteerId)
                    ->where('tenant_id', $tenantId)
                    ->select(['id', 'preferred_language'])
                    ->first();

                if ($action === 'approve' && $paymentResult === 'paid') {
                    LocaleContext::withLocale($volunteerRow, function () use ($volunteerId, $hours, $orgName) {
                        NotificationDispatcher::dispatch(
                            $volunteerId, 'global', 0, 'vol_hours_approved',
                            __('notifications.vol_hours_approved_paid_body', ['hours' => $hours]),
                            '/wallet',
                            NotificationDispatcher::buildVolHoursApprovedPaidEmail($hours, $orgName)
                        );
                    });
                } elseif ($action === 'approve' && $paymentResult === 'insufficient_balance') {
                    LocaleContext::withLocale($volunteerRow, function () use ($volunteerId, $hours, $orgName) {
                        NotificationDispatcher::dispatch(
                            $volunteerId, 'global', 0, 'vol_hours_approved',
                            __('notifications.vol_hours_approved_unpaid_body', ['hours' => $hours]),
                            '/volunteering?tab=hours',
                            NotificationDispatcher::buildVolHoursApprovedEmail($hours, $orgName)
                        );
                    });
                    $ownerId = (int) $org->user_id;
                    $ownerRow = DB::table('users')
                        ->where('id', $ownerId)
                        ->where('tenant_id', $tenantId)
                        ->select(['id', 'preferred_language'])
                        ->first();
                    LocaleContext::withLocale($ownerRow, function () use ($ownerId, $hours, $org) {
                        NotificationDispatcher::dispatch(
                            $ownerId, 'global', 0, 'vol_hours_approved',
                            __('notifications.vol_hours_org_wallet_insufficient_body', ['hours' => $hours]),
                            '/volunteering/org/' . (int) $org->id . '/dashboard?tab=wallet',
                            null
                        );
                    });
                } elseif ($action === 'approve') {
                    LocaleContext::withLocale($volunteerRow, function () use ($volunteerId, $hours, $orgName) {
                        NotificationDispatcher::dispatch(
                            $volunteerId, 'global', 0, 'vol_hours_approved',
                            __('notifications.vol_hours_approved_body', ['hours' => $hours]),
                            '/volunteering?tab=hours',
                            NotificationDispatcher::buildVolHoursApprovedEmail($hours, $orgName)
                        );
                    });
                } else {
                    LocaleContext::withLocale($volunteerRow, function () use ($volunteerId, $hours, $orgName) {
                        NotificationDispatcher::dispatch(
                            $volunteerId, 'global', 0, 'vol_hours_declined',
                            __('notifications.vol_hours_declined_body', ['hours' => $hours]),
                            '/volunteering?tab=hours',
                            NotificationDispatcher::buildVolHoursDeclinedEmail($hours, $orgName)
                        );
                    });
                }
            } catch (\Throwable $e) {
                // Notification failure must not affect the already-committed transaction
                Log::warning("VolunteerService::verifyHours notification error: " . $e->getMessage());
            }

            return true;
        } catch (\Exception $e) {
            Log::warning("VolunteerService::verifyHours error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to verify hours'];
            return false;
        }
    }

    // ========================================
    // ORGANIZATIONS — create / getById / getMyOrganizations
    // ========================================

    /**
     * Get organisations the current user owns or is admin of, with cursor pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public static function getMyOrganizations(int $userId, array $filters = []): array
    {
        $tenantId = self::getTenantId();
        $limit = min((int) ($filters['limit'] ?? 20), 50);
        $cursorId = self::decodeCursor($filters['cursor'] ?? null);

        $sql = "
            SELECT vo.*,
                   COALESCE(om.role, CASE WHEN vo.user_id = ? THEN 'owner' ELSE 'member' END) as member_role
            FROM vol_organizations vo
            LEFT JOIN org_members om
                ON om.organization_id = vo.id
                AND om.tenant_id = vo.tenant_id
                AND om.user_id = ?
                AND om.status = 'active'
            WHERE vo.tenant_id = ?
              AND (vo.user_id = ? OR om.user_id IS NOT NULL)
        ";
        $params = [$userId, $userId, $tenantId, $userId];

        if ($cursorId) {
            $sql .= " AND vo.id < ?";
            $params[] = $cursorId;
        }

        $sql .= " ORDER BY vo.id DESC LIMIT " . ($limit + 1);

        $rows = DB::select($sql, $params);
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $items = [];
        $lastId = null;
        foreach ($rows as $org) {
            $lastId = $org->id;
            $items[] = [
                'id'            => (int) $org->id,
                'name'          => $org->name,
                'description'   => $org->description ?? null,
                'status'        => $org->status ?? 'pending',
                'member_role'   => $org->member_role ?? 'member',
                'logo_url'      => $org->logo_url ?? null,
                'contact_email' => $org->contact_email ?? null,
                'website'       => $org->website ?? null,
                'created_at'    => $org->created_at ?? null,
            ];
        }

        return [
            'items'    => $items,
            'cursor'   => ($hasMore && $lastId) ? base64_encode((string) $lastId) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Register a new volunteer organisation (status='pending').
     */
    public static function createOrganization(int $userId, array $data): ?int
    {
        self::$errors = [];
        $tenantId = self::getTenantId();

        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $contactEmail = trim($data['contact_email'] ?? '');
        $website = trim($data['website'] ?? '');

        if (empty($name)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Organisation name is required', 'field' => 'name'];
            return null;
        }

        if (mb_strlen($name) < 3) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Organisation name must be at least 3 characters', 'field' => 'name'];
            return null;
        }

        if (mb_strlen($name) > 200) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Organisation name must be under 200 characters', 'field' => 'name'];
            return null;
        }

        if (empty($description)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Description is required', 'field' => 'description'];
            return null;
        }

        if (mb_strlen($description) < 20) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Description must be at least 20 characters', 'field' => 'description'];
            return null;
        }

        if (empty($contactEmail)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Contact email is required', 'field' => 'contact_email'];
            return null;
        }

        if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Please enter a valid email address', 'field' => 'contact_email'];
            return null;
        }

        if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Please enter a valid URL', 'field' => 'website'];
            return null;
        }

        // Check for duplicate name
        $existing = DB::selectOne(
            "SELECT id FROM vol_organizations WHERE tenant_id = ? AND LOWER(name) = LOWER(?) AND status != 'declined'",
            [$tenantId, $name]
        );

        if ($existing) {
            self::$errors[] = ['code' => 'ALREADY_EXISTS', 'message' => 'An organisation with this name already exists', 'field' => 'name'];
            return null;
        }

        // Wrap in transaction with retry to handle slug race conditions
        $maxRetries = 3;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                return DB::transaction(function () use ($tenantId, $userId, $name, $description, $contactEmail, $website) {
                    $slug = self::generateOrgSlug($name, $tenantId);

                    DB::insert(
                        "INSERT INTO vol_organizations (tenant_id, user_id, name, description, contact_email, website, slug, status, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())",
                        [$tenantId, $userId, $name, $description, $contactEmail, $website ?: null, $slug]
                    );

                    $orgId = (int) DB::getPdo()->lastInsertId();

                    // Initialize owner membership
                    DB::insert(
                        "INSERT INTO org_members (tenant_id, organization_id, user_id, role, status, created_at) VALUES (?, ?, ?, 'owner', 'active', NOW())",
                        [$tenantId, $orgId, $userId]
                    );

                    return $orgId;
                });
            } catch (\Illuminate\Database\QueryException $e) {
                // Retry on duplicate slug (integrity constraint violation)
                if ($attempt >= $maxRetries || $e->getCode() !== '23000') {
                    Log::warning("VolunteerService::createOrganization error: " . $e->getMessage());
                    self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to register organisation'];
                    return null;
                }
                // Retry — generateOrgSlug will pick a new suffix on next attempt
            } catch (\Exception $e) {
                Log::warning("VolunteerService::createOrganization error: " . $e->getMessage());
                self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to register organisation'];
                return null;
            }
        }

        self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to register organisation'];
        return null;
    }

    /**
     * Get single organization by ID (legacy-compatible format).
     */
    public static function getOrganizationById(int $id, bool $includeNonApproved = false): ?array
    {
        $tenantId = self::getTenantId();

        $org = DB::selectOne("SELECT * FROM vol_organizations WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
        if (!$org) {
            return null;
        }

        if (!$includeNonApproved && !self::isApprovedOrganizationStatus($org->status ?? null)) {
            return null;
        }

        $oppCount = (int) DB::selectOne(
            "SELECT COUNT(*) as cnt FROM vol_opportunities WHERE organization_id = ? AND is_active = 1 AND tenant_id = ?",
            [$id, $tenantId]
        )->cnt;

        $totalHours = (float) (DB::selectOne(
            "SELECT SUM(hours) as total FROM vol_logs WHERE organization_id = ? AND status = 'approved' AND tenant_id = ?",
            [$id, $tenantId]
        )->total ?? 0);

        $reviews = DB::select(
            "SELECT * FROM vol_reviews WHERE target_type = 'organization' AND target_id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        $avgRating = 0;
        if (!empty($reviews)) {
            $avgRating = array_sum(array_map(fn ($r) => (int) $r->rating, $reviews)) / count($reviews);
        }

        return [
            'id'               => (int) $org->id,
            'name'             => $org->name,
            'description'      => $org->description,
            'logo_url'         => $org->logo_url ?? null,
            'website'          => $org->website,
            'contact_email'    => $org->contact_email,
            'location'         => $org->location ?? null,
            'created_at'       => $org->created_at ?? null,
            'status'           => $org->status,
            'opportunity_count' => $oppCount,
            'total_hours'      => $totalHours,
            'volunteer_count'  => null,
            'review_count'     => count($reviews),
            'average_rating'   => round($avgRating, 1),
            'stats'            => [
                'opportunity_count'  => $oppCount,
                'total_hours_logged' => $totalHours,
                'total_hours'        => $totalHours,
                'review_count'       => count($reviews),
                'average_rating'     => round($avgRating, 1),
            ],
        ];
    }

    // ========================================
    // REVIEWS
    // ========================================

    /**
     * Create a volunteering review.
     */
    public static function createReview(int $reviewerId, string $targetType, int $targetId, int $rating, string $comment = ''): ?int
    {
        self::$errors = [];
        $tenantId = self::getTenantId();

        if (!in_array($targetType, ['organization', 'user'])) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Target type must be organization or user'];
            return null;
        }

        if ($rating < 1 || $rating > 5) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Rating must be between 1 and 5', 'field' => 'rating'];
            return null;
        }

        // Prevent self-review
        if ($targetType === 'user' && $targetId === $reviewerId) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'You cannot review yourself'];
            return null;
        }

        // Prevent duplicate reviews
        $existingReview = DB::selectOne(
            "SELECT id FROM vol_reviews WHERE reviewer_id = ? AND target_type = ? AND target_id = ? AND tenant_id = ?",
            [$reviewerId, $targetType, $targetId, $tenantId]
        );
        if ($existingReview) {
            self::$errors[] = ['code' => 'ALREADY_EXISTS', 'message' => 'You have already reviewed this ' . $targetType];
            return null;
        }

        // Verify target exists and reviewer has history
        if ($targetType === 'organization') {
            $org = DB::selectOne("SELECT id FROM vol_organizations WHERE id = ? AND tenant_id = ?", [$targetId, $tenantId]);
            if (!$org) {
                self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Organization not found'];
                return null;
            }
            $history = DB::selectOne("
                SELECT 1 FROM vol_logs WHERE user_id = ? AND organization_id = ? AND status = 'approved' AND tenant_id = ?
                UNION
                SELECT 1 FROM vol_applications a
                JOIN vol_opportunities opp ON a.opportunity_id = opp.id
                WHERE a.user_id = ? AND opp.organization_id = ? AND a.status = 'approved' AND a.tenant_id = ?
                LIMIT 1
            ", [$reviewerId, $targetId, $tenantId, $reviewerId, $targetId, $tenantId]);

            if (!$history) {
                self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You must have volunteered with this organisation to leave a review'];
                return null;
            }
        } else {
            $user = DB::selectOne("SELECT id FROM users WHERE id = ? AND tenant_id = ?", [$targetId, $tenantId]);
            if (!$user) {
                self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'User not found'];
                return null;
            }
            $history = DB::selectOne("
                SELECT 1 FROM vol_applications a1
                JOIN vol_applications a2 ON a1.opportunity_id = a2.opportunity_id
                WHERE a1.user_id = ? AND a2.user_id = ? AND a1.status = 'approved' AND a2.status = 'approved' AND a1.tenant_id = ?
                LIMIT 1
            ", [$reviewerId, $targetId, $tenantId]);

            if (!$history) {
                self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You must have volunteered together to leave a review'];
                return null;
            }
        }

        try {
            DB::insert(
                "INSERT INTO vol_reviews (tenant_id, reviewer_id, target_type, target_id, rating, comment, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [$tenantId, $reviewerId, $targetType, $targetId, $rating, $comment]
            );

            return (int) DB::getPdo()->lastInsertId();
        } catch (\Exception $e) {
            Log::warning("VolunteerService::createReview error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to create review'];
            return null;
        }
    }

    /**
     * Get reviews for a target.
     */
    public static function getReviews(string $targetType, int $targetId): array
    {
        $tenantId = self::getTenantId();

        $rows = DB::select("
            SELECT r.*, u.first_name, u.last_name, u.avatar_url
            FROM vol_reviews r
            JOIN users u ON r.reviewer_id = u.id
            WHERE r.target_type = ? AND r.target_id = ? AND r.tenant_id = ?
            ORDER BY r.created_at DESC
        ", [$targetType, $targetId, $tenantId]);

        return array_map(function ($r) {
            return [
                'id'      => (int) $r->id,
                'rating'  => (int) $r->rating,
                'comment' => $r->comment,
                'author'  => [
                    'id'     => (int) ($r->user_id ?? $r->reviewer_id ?? 0),
                    'name'   => trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')),
                    'avatar' => $r->avatar_url ?? null,
                ],
                'reviewer' => [
                    'name'       => trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')),
                    'avatar_url' => $r->avatar_url ?? null,
                ],
                'created_at' => $r->created_at,
            ];
        }, $rows);
    }

    // ========================================
    // PRIVATE HELPERS
    // ========================================

    /**
     * Get the current tenant ID.
     */
    private static function getTenantId(): int
    {
        return \App\Core\TenantContext::getId();
    }

    /**
     * Decode a base64-encoded numeric cursor.
     */
    private static function decodeCursor(?string $cursor): ?int
    {
        if (!$cursor) {
            return null;
        }
        $decoded = base64_decode($cursor, true);
        if ($decoded && is_numeric($decoded)) {
            return (int) $decoded;
        }
        return null;
    }

    /**
     * Check if a user can manage an opportunity.
     */
    private static function canManageOpportunity(array $opp, int $userId): bool
    {
        if ((int) ($opp['org_owner_id'] ?? 0) === $userId) {
            return true;
        }

        $siteRole = DB::selectOne("SELECT role FROM users WHERE id = ? AND tenant_id = ?", [$userId, self::getTenantId()]);
        if ($siteRole && in_array($siteRole->role, ['super_admin', 'admin', 'tenant_admin'], true)) {
            return true;
        }

        $orgId = (int) ($opp['organization_id'] ?? 0);
        if ($orgId <= 0) {
            return false;
        }

        $orgRole = DB::selectOne(
            "SELECT role FROM org_members WHERE tenant_id = ? AND organization_id = ? AND user_id = ? AND status = 'active'",
            [self::getTenantId(), $orgId, $userId]
        );

        return $orgRole && in_array($orgRole->role, ['owner', 'admin'], true);
    }

    private static function canManageOrganization(array $org, int $userId): bool
    {
        if ((int) ($org['user_id'] ?? 0) === $userId) {
            return true;
        }

        $tenantId = self::getTenantId();
        $siteRole = DB::selectOne("SELECT role FROM users WHERE id = ? AND tenant_id = ?", [$userId, $tenantId]);
        if ($siteRole && in_array($siteRole->role, ['super_admin', 'admin', 'tenant_admin'], true)) {
            return true;
        }

        $orgId = (int) ($org['id'] ?? 0);
        if ($orgId <= 0) {
            return false;
        }

        $orgRole = DB::selectOne(
            "SELECT role FROM org_members WHERE tenant_id = ? AND organization_id = ? AND user_id = ? AND status = 'active'",
            [$tenantId, $orgId, $userId]
        );

        return $orgRole && in_array($orgRole->role, ['owner', 'admin'], true);
    }

    private static function isApprovedOrganizationStatus(?string $status): bool
    {
        return in_array($status, ['approved', 'active'], true);
    }

    private static function userCanVolunteerForOrganization(int $tenantId, int $userId, int $organizationId): bool
    {
        $hasApprovedApplication = DB::selectOne(
            "SELECT 1
             FROM vol_applications a
             JOIN vol_opportunities opp
               ON opp.id = a.opportunity_id
              AND opp.tenant_id = a.tenant_id
              AND opp.organization_id = ?
             WHERE a.user_id = ?
               AND a.status = 'approved'
               AND a.tenant_id = ?
             LIMIT 1",
            [$organizationId, $userId, $tenantId]
        );

        if ($hasApprovedApplication) {
            return true;
        }

        $orgRelationship = DB::selectOne(
            "SELECT 1
             FROM vol_organizations org
             LEFT JOIN org_members om
               ON om.organization_id = org.id
              AND om.tenant_id = org.tenant_id
              AND om.user_id = ?
              AND om.status = 'active'
             WHERE org.id = ?
               AND org.tenant_id = ?
               AND (org.user_id = ? OR om.user_id IS NOT NULL)
             LIMIT 1",
            [$userId, $organizationId, $tenantId, $userId]
        );

        return (bool) $orgRelationship;
    }

    private static function resolveCaringHourLogStatus(int $userId, int $tenantId, array $policy): string
    {
        if (!$policy['approval_required']) {
            return 'approved';
        }

        if ($policy['auto_approve_trusted_reviewers'] && self::hasCaringWorkflowPermission($userId, $tenantId, 'volunteering.hours.review')) {
            return 'approved';
        }

        return 'pending';
    }

    private static function applyVolunteerAutoPayment(
        int $tenantId,
        int $organizationId,
        int $organizationOwnerId,
        int $volunteerId,
        int $logId,
        float $hours,
    ): string {
        $intHours = (int) floor($hours);
        $orgDebit = (float) $intHours;
        if ($intHours <= 0) {
            return 'no_payable_hours';
        }
        $orgLocked = DB::selectOne(
            "SELECT id, balance FROM vol_organizations WHERE id = ? AND tenant_id = ? FOR UPDATE",
            [$organizationId, $tenantId]
        );

        if (!$orgLocked || (float) $orgLocked->balance < $orgDebit) {
            return 'insufficient_balance';
        }

        if ($orgDebit > 0) {
            DB::update(
                "UPDATE vol_organizations SET balance = balance - ? WHERE id = ? AND tenant_id = ?",
                [$orgDebit, $organizationId, $tenantId]
            );
        }
        $newOrgBalance = (float) $orgLocked->balance - $orgDebit;

        if ($intHours > 0) {
            DB::update(
                "UPDATE users SET balance = balance + ? WHERE id = ? AND tenant_id = ?",
                [$intHours, $volunteerId, $tenantId]
            );
        }

        $description = __('api.volunteer_auto_payment_description', ['hours' => $intHours]);
        DB::insert("
            INSERT INTO vol_org_transactions (tenant_id, vol_organization_id, user_id, vol_log_id, type, amount, balance_after, description, created_at)
            VALUES (?, ?, ?, ?, 'volunteer_payment', ?, ?, ?, NOW())
        ", [$tenantId, $organizationId, $volunteerId, $logId, -$orgDebit, $newOrgBalance, $description]);

        DB::insert("
            INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, description, transaction_type, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'volunteer', 'completed', NOW(), NOW())
        ", [$tenantId, $organizationOwnerId, $volunteerId, $intHours, $description]);

        return 'paid';
    }

    private static function canBypassCaringWorkflowPolicy(int $userId, int $tenantId): bool
    {
        $user = DB::selectOne(
            'SELECT role, is_admin, is_super_admin, is_tenant_super_admin, is_god FROM users WHERE id = ? AND tenant_id = ?',
            [$userId, $tenantId]
        );

        if ($user && (
            in_array((string) $user->role, ['admin', 'tenant_admin', 'super_admin'], true)
            || (int) ($user->is_admin ?? 0) === 1
            || (int) ($user->is_super_admin ?? 0) === 1
            || (int) ($user->is_tenant_super_admin ?? 0) === 1
            || (int) ($user->is_god ?? 0) === 1
        )) {
            return true;
        }

        return self::hasCaringWorkflowPermission($userId, $tenantId, 'volunteering.hours.review');
    }

    private static function hasCaringWorkflowPermission(int $userId, int $tenantId, string $permission): bool
    {
        if (!Schema::hasTable('user_roles') || !Schema::hasTable('role_permissions') || !Schema::hasTable('permissions')) {
            return false;
        }

        $match = DB::selectOne(
            "SELECT 1
             FROM user_roles ur
             INNER JOIN role_permissions rp ON rp.role_id = ur.role_id
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE ur.user_id = ?
               AND p.name = ?
               AND (ur.tenant_id = ? OR ur.tenant_id IS NULL)
               AND (rp.tenant_id = ? OR rp.tenant_id IS NULL)
               AND (p.tenant_id = ? OR p.tenant_id IS NULL)
               AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
             LIMIT 1",
            [$userId, $permission, $tenantId, $tenantId, $tenantId]
        );

        return $match !== null;
    }

    /**
     * Format opportunity for API response.
     */
    private static function formatOpportunity(array $opp): array
    {
        return [
            'id'            => (int) $opp['id'],
            'title'         => $opp['title'],
            'description'   => $opp['description'],
            'location'      => $opp['location'],
            'skills_needed' => $opp['skills_needed'],
            'start_date'    => $opp['start_date'],
            'end_date'      => $opp['end_date'],
            'is_active'     => (bool) ($opp['is_active'] ?? true),
            'is_remote'     => (bool) ($opp['is_remote'] ?? false),
            'category'      => $opp['category_name'] ?? null,
            'organization'  => [
                'id'       => (int) $opp['organization_id'],
                'name'     => $opp['org_name'],
                'logo_url' => $opp['org_logo'] ?? $opp['logo_url'] ?? null,
            ],
            'created_at' => $opp['created_at'],
            'status'     => $opp['status'] ?? null,
        ];
    }

    private static function shiftHasApprovalCapacity(int $shiftId, int $tenantId): bool
    {
        if ($shiftId <= 0) {
            return true;
        }

        $shift = DB::selectOne(
            "SELECT id, capacity FROM vol_shifts WHERE id = ? AND tenant_id = ? FOR UPDATE",
            [$shiftId, $tenantId]
        );

        if (!$shift) {
            return false;
        }

        if (empty($shift->capacity)) {
            return true;
        }

        $approvedCount = (int) DB::selectOne(
            "SELECT COUNT(*) as cnt FROM vol_applications WHERE shift_id = ? AND status = 'approved' AND tenant_id = ?",
            [$shiftId, $tenantId]
        )->cnt;

        return $approvedCount < (int) $shift->capacity;
    }

    /**
     * Generate a unique slug for an organisation within a tenant.
     */
    private static function generateOrgSlug(string $name, int $tenantId): string
    {
        $slug = mb_strtolower($name, 'UTF-8');
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        if (empty($slug)) {
            $slug = 'organisation';
        }

        $baseSlug = $slug;
        $suffix = 0;
        while (true) {
            $existing = DB::selectOne(
                "SELECT id FROM vol_organizations WHERE tenant_id = ? AND slug = ?",
                [$tenantId, $slug]
            );

            if (!$existing) {
                break;
            }

            $suffix++;
            $slug = $baseSlug . '-' . $suffix;
        }

        return $slug;
    }

    /**
     * Resolve the "declined" state value for vol_logs across schema variants.
     */
    private static function getDeclineStatusValue(): string
    {
        if (self::$declineStatusValue !== null) {
            return self::$declineStatusValue;
        }

        try {
            $row = DB::selectOne("
                SELECT COLUMN_TYPE
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'vol_logs'
                  AND COLUMN_NAME = 'status'
                LIMIT 1
            ");
            $columnType = strtolower((string) ($row->COLUMN_TYPE ?? ''));

            if (str_contains($columnType, "'declined'")) {
                self::$declineStatusValue = 'declined';
            } elseif (str_contains($columnType, "'rejected'")) {
                self::$declineStatusValue = 'rejected';
            }
        } catch (\Throwable $e) {
            // Fallback below
        }

        if (self::$declineStatusValue === null) {
            self::$declineStatusValue = 'declined';
        }

        return self::$declineStatusValue;
    }
}
