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
    public static function getById(int $id): ?VolOpportunity
    {
        return VolOpportunity::query()
            ->with(['creator', 'organization', 'category', 'shifts', 'applications'])
            ->find($id);
    }

    /**
     * Create a new volunteer opportunity.
     */
    public static function createOpportunity(int $userId, array $data): VolOpportunity
    {
        $opportunity = VolOpportunity::create([
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
    public static function apply(int $opportunityId, int $userId, array $data = []): VolApplication
    {
        $application = VolApplication::create([
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
    public static function getMyApplications(int $userId): array
    {
        return VolApplication::query()
            ->with(['opportunity:id,title,status', 'opportunity.organization:id,name'])
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    /**
     * Get shifts the user is signed up for.
     */
    public static function getMyShifts(int $userId): array
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
    public static function getMyHours(int $userId): array
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
    public static function getHoursSummary(int $userId): array
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

    // ========================================
    // ERROR TRACKING
    // ========================================

    /** @var array Validation/business errors from the last operation */
    private static array $errors = [];

    /** Cached decline status value for vol_logs (declined vs rejected schema variants) */
    private static ?string $declineStatusValue = null;

    /**
     * Get errors from the last operation.
     */
    public static function getErrors(): array
    {
        return self::$errors;
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
            WHERE opp.id = ? AND org.tenant_id = ?
        ", [$id, $tenantId]);

        if (!$opp) {
            return null;
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
            $formatted['is_owner'] = self::canManageOpportunity((array) $opp, $viewerId);
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
            WHERE opp.id = ? AND org.tenant_id = ?
        ", [$id, $tenantId]);

        if (!$opp) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Opportunity not found'];
            return false;
        }

        if (!self::canManageOpportunity((array) $opp, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to manage this opportunity'];
            return false;
        }

        try {
            $fields = [];
            $params = [];
            foreach (['title', 'description', 'location', 'skills_needed', 'start_date', 'end_date', 'category_id'] as $field) {
                if (array_key_exists($field, $data)) {
                    $fields[] = "{$field} = ?";
                    $params[] = $data[$field];
                }
            }

            if (empty($fields)) {
                return true;
            }

            $params[] = $id;
            $params[] = $tenantId;
            DB::update("UPDATE vol_opportunities SET " . implode(', ', $fields) . " WHERE id = ? AND tenant_id = ?", $params);

            return true;
        } catch (\Exception $e) {
            error_log("VolunteerService::updateOpportunity error: " . $e->getMessage());
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
            WHERE opp.id = ? AND org.tenant_id = ?
        ", [$id, $tenantId]);

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
            return true;
        } catch (\Exception $e) {
            error_log("VolunteerService::deleteOpportunity error: " . $e->getMessage());
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
            WHERE opp.id = ? AND org.tenant_id = ?
        ", [$opportunityId, $tenantId]);

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
            JOIN users u ON a.user_id = u.id
            LEFT JOIN vol_shifts s ON a.shift_id = s.id
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
            JOIN vol_opportunities opp ON a.opportunity_id = opp.id
            JOIN vol_organizations org ON opp.organization_id = org.id
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
            DB::update(
                "UPDATE vol_applications SET status = ?, org_note = ? WHERE id = ? AND tenant_id = ?",
                [$status, $orgNote !== '' ? $orgNote : null, $applicationId, $tenantId]
            );

            return true;
        } catch (\Exception $e) {
            error_log("VolunteerService::handleApplication error: " . $e->getMessage());
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
            error_log("VolunteerService::withdrawApplication error: " . $e->getMessage());
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

        $shift = DB::selectOne("SELECT * FROM vol_shifts WHERE id = ? AND tenant_id = ?", [$shiftId, $tenantId]);
        if (!$shift) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Shift not found'];
            return false;
        }

        $opportunityId = (int) $shift->opportunity_id;

        // Check user has approved application for this opportunity
        $app = DB::selectOne(
            "SELECT id FROM vol_applications WHERE opportunity_id = ? AND user_id = ? AND status = 'approved' AND tenant_id = ?",
            [$opportunityId, $userId, $tenantId]
        );

        if (!$app) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You must have an approved application to sign up for shifts'];
            return false;
        }

        // Check capacity
        $signupCount = (int) DB::selectOne(
            "SELECT COUNT(*) as cnt FROM vol_applications WHERE shift_id = ? AND status = 'approved' AND tenant_id = ?",
            [$shiftId, $tenantId]
        )->cnt;

        if ($shift->capacity && $signupCount >= (int) $shift->capacity) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'This shift is at capacity'];
            return false;
        }

        // Check shift hasn't passed
        if (strtotime($shift->start_time) < time()) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'This shift has already started'];
            return false;
        }

        try {
            DB::update(
                "UPDATE vol_applications SET shift_id = ? WHERE id = ? AND tenant_id = ?",
                [$shiftId, $app->id, $tenantId]
            );
            return true;
        } catch (\Exception $e) {
            error_log("VolunteerService::signUpForShift error: " . $e->getMessage());
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

            return true;
        } catch (\Exception $e) {
            error_log("VolunteerService::cancelShiftSignup error: " . $e->getMessage());
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
        $tenantId = self::getTenantId();

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

        // Verify organization exists
        $org = DB::selectOne("SELECT id FROM vol_organizations WHERE id = ? AND tenant_id = ?", [(int) $data['organization_id'], $tenantId]);
        if (!$org) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Organization not found'];
            return null;
        }

        // If opportunity_id provided, verify user has an approved application for it
        if (!empty($data['opportunity_id'])) {
            $hasApp = DB::selectOne(
                "SELECT id FROM vol_applications WHERE opportunity_id = ? AND user_id = ? AND status = 'approved' AND tenant_id = ?",
                [$data['opportunity_id'], $userId, $tenantId]
            );
            if (!$hasApp) {
                self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have an approved application for this opportunity', 'field' => 'opportunity_id'];
                return null;
            }
        }

        try {
            DB::insert(
                "INSERT INTO vol_logs (tenant_id, user_id, organization_id, opportunity_id, date_logged, hours, description, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())",
                [
                    $tenantId,
                    $userId,
                    (int) $data['organization_id'],
                    $data['opportunity_id'] ?? null,
                    $data['date'],
                    (float) $data['hours'],
                    $data['description'] ?? '',
                ]
            );

            return (int) DB::getPdo()->lastInsertId();
        } catch (\Exception $e) {
            error_log("VolunteerService::logHours error: " . $e->getMessage());
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
            JOIN vol_organizations org ON l.organization_id = org.id
            JOIN users u ON l.user_id = u.id
            LEFT JOIN vol_opportunities opp ON l.opportunity_id = opp.id
            WHERE org.user_id = ? AND l.tenant_id = ? AND l.status = 'pending'
        ";
        $params = [$userId, $tenantId];

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

        // Verify admin owns or is admin of the org
        $org = DB::selectOne("SELECT * FROM vol_organizations WHERE id = ? AND tenant_id = ?", [(int) $log->organization_id, $tenantId]);
        if (!$org) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to manage this organization'];
            return false;
        }

        $orgAdminRole = DB::selectOne(
            "SELECT role FROM org_members WHERE tenant_id = ? AND organization_id = ? AND user_id = ? AND status = 'active'",
            [$tenantId, (int) $org->id, $adminUserId]
        );

        if ((int) $org->user_id !== $adminUserId && !in_array($orgAdminRole->role ?? '', ['owner', 'admin'], true)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to manage this organization'];
            return false;
        }

        $status = $action === 'approve' ? 'approved' : self::getDeclineStatusValue();

        try {
            DB::update("UPDATE vol_logs SET status = ? WHERE id = ? AND tenant_id = ?", [$status, $logId, $tenantId]);
            return true;
        } catch (\Exception $e) {
            error_log("VolunteerService::verifyHours error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to verify hours'];
            return false;
        }
    }

    // ========================================
    // ORGANIZATIONS — create / getById / getMyOrganizations
    // ========================================

    /**
     * Get organisations the current user owns or is admin of.
     */
    public static function getMyOrganizations(int $userId): array
    {
        $tenantId = self::getTenantId();

        $rows = DB::select("
            SELECT vo.*, om.role as member_role
            FROM vol_organizations vo
            JOIN org_members om ON om.organization_id = vo.id AND om.tenant_id = vo.tenant_id
            WHERE om.user_id = ? AND om.tenant_id = ? AND om.status = 'active'
            ORDER BY vo.name ASC
        ", [$userId, $tenantId]);

        return array_map(function ($org) {
            return [
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
        }, $rows);
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

        $slug = self::generateOrgSlug($name, $tenantId);

        try {
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
        } catch (\Exception $e) {
            error_log("VolunteerService::createOrganization error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to register organisation'];
            return null;
        }
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

        if (!$includeNonApproved && ($org->status ?? null) !== 'approved') {
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
            error_log("VolunteerService::createReview error: " . $e->getMessage());
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

        $siteRole = DB::selectOne("SELECT role FROM users WHERE id = ?", [$userId]);
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
        ];
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
