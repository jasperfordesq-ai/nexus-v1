<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\SafeguardingAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SafeguardingService — Laravel DI-based service for safeguarding operations.
 *
 * Manages guardian assignments, safeguarding training, and incident reporting.
 * All queries are tenant-scoped via HasTenantScope trait or explicit tenant_id.
 */
class SafeguardingService
{
    /** @var array Collected errors from the last operation */
    private array $errors = [];

    public function __construct(
        private readonly SafeguardingAssignment $assignment,
    ) {}

    /**
     * Get collected errors from the last operation.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    // =========================================================================
    // ASSIGNMENT MANAGEMENT
    // =========================================================================

    /**
     * Create a safeguarding assignment.
     */
    public function createAssignment(int $guardianUserId, int $wardUserId, int $assignedBy, ?string $notes = null): array
    {
        $this->errors = [];

        if ($guardianUserId === $wardUserId) {
            $this->errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Guardian and ward cannot be the same person'];
            return ['success' => false, 'errors' => $this->errors];
        }

        // Check both users exist in tenant
        $guardianExists = User::where('id', $guardianUserId)->where('tenant_id', TenantContext::getId())->where('status', 'active')->exists();
        $wardExists = User::where('id', $wardUserId)->where('tenant_id', TenantContext::getId())->where('status', 'active')->exists();

        if (!$guardianExists) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Guardian user not found'];
            return ['success' => false, 'errors' => $this->errors];
        }
        if (!$wardExists) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Ward user not found'];
            return ['success' => false, 'errors' => $this->errors];
        }

        try {
            // Use upsert: if assignment exists but was revoked, re-activate it
            DB::table('safeguarding_assignments')->updateOrInsert(
                [
                    'guardian_user_id' => $guardianUserId,
                    'ward_user_id' => $wardUserId,
                    'tenant_id' => TenantContext::getId(),
                ],
                [
                    'revoked_at' => null,
                    'assigned_by' => $assignedBy,
                    'assigned_at' => now(),
                    'notes' => $notes,
                ]
            );

            $this->logActivity($assignedBy, 'safeguarding_assignment_created', 'safeguarding_assignment', null, [
                'guardian_user_id' => $guardianUserId,
                'ward_user_id' => $wardUserId,
            ]);

            return ['success' => true, 'message' => 'Safeguarding assignment created'];
        } catch (\Throwable $e) {
            Log::error('SafeguardingService::createAssignment error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to create assignment'];
        }
    }

    /**
     * Record consent from the ward.
     */
    public function recordConsent(int $wardUserId): bool
    {
        try {
            $this->assignment->newQuery()
                ->where('ward_user_id', $wardUserId)
                ->whereNull('revoked_at')
                ->whereNull('consent_given_at')
                ->update(['consent_given_at' => now()]);
            return true;
        } catch (\Throwable $e) {
            Log::error('SafeguardingService::recordConsent error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Revoke an assignment.
     */
    public function revokeAssignment(int $assignmentId, int $revokedBy): bool
    {
        try {
            $this->assignment->newQuery()
                ->where('id', $assignmentId)
                ->update(['revoked_at' => now()]);

            $this->logActivity($revokedBy, 'safeguarding_assignment_revoked', 'safeguarding_assignment', $assignmentId);

            return true;
        } catch (\Throwable $e) {
            Log::error('SafeguardingService::revokeAssignment error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * List all active assignments for the current tenant.
     */
    public function listAssignments(): array
    {
        try {
            return $this->assignment->newQuery()
                ->with([
                    'guardian:id,name,avatar_url',
                    'ward:id,name,avatar_url',
                    'assigner:id,name',
                ])
                ->whereNull('revoked_at')
                ->orderByDesc('assigned_at')
                ->get()
                ->map(function (SafeguardingAssignment $sa) {
                    $data = $sa->toArray();
                    $data['guardian_name'] = $sa->guardian->name ?? null;
                    $data['guardian_avatar'] = $sa->guardian->avatar_url ?? null;
                    $data['ward_name'] = $sa->ward->name ?? null;
                    $data['ward_avatar'] = $sa->ward->avatar_url ?? null;
                    $data['assigned_by_name'] = $sa->assigner->name ?? null;
                    return $data;
                })
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    // =========================================================================
    // TRAINING (tenant-scoped via explicit tenant_id)
    // =========================================================================

    /**
     * Get all training records for a user.
     */
    public function getTrainingForUser(int $userId, int $tenantId): array
    {
        try {
            return DB::table('vol_safeguarding_training as st')
                ->leftJoin('users as v', 'st.verified_by', '=', 'v.id')
                ->where('st.user_id', $userId)
                ->where('st.tenant_id', $tenantId)
                ->select('st.*', 'v.name as verified_by_name')
                ->orderByDesc('st.completed_at')
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();
        } catch (\Throwable $e) {
            Log::error('SafeguardingService::getTrainingForUser error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Record a training completion for a user.
     *
     * @return array|false The created record or false on failure
     */
    public function recordTraining(int $userId, array $data, int $tenantId): array|false
    {
        $validTrainingTypes = ['children_first', 'vulnerable_adults', 'first_aid', 'manual_handling', 'other'];
        $trainingType = $data['training_type'] ?? '';
        if (!in_array($trainingType, $validTrainingTypes, true)) {
            return false;
        }

        try {
            $id = DB::table('vol_safeguarding_training')->insertGetId([
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'training_type' => $trainingType,
                'training_name' => $data['training_name'] ?? '',
                'provider' => $data['provider'] ?? null,
                'completed_at' => $data['completed_at'] ?? now()->toDateString(),
                'expires_at' => $data['expires_at'] ?? null,
                'certificate_url' => $data['certificate_url'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $record = DB::table('vol_safeguarding_training')
                ->where('id', $id)
                ->where('tenant_id', $tenantId)
                ->first();

            $this->logActivity($userId, 'safeguarding_training_recorded', 'safeguarding_training', $id, [
                'training_type' => $trainingType,
                'training_name' => $data['training_name'] ?? '',
            ]);

            return $record ? (array) $record : [];
        } catch (\Throwable $e) {
            Log::error('SafeguardingService::recordTraining error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get training records for admin view with pagination.
     *
     * @return array{items: array, total: int, page: int, per_page: int}
     */
    public function getTrainingForAdmin(int $tenantId, ?int $page = null, ?int $perPage = null): array
    {
        $page = max(1, $page ?? 1);
        $perPage = min(100, max(1, $perPage ?? 20));
        $offset = ($page - 1) * $perPage;

        try {
            $total = (int) DB::table('vol_safeguarding_training')
                ->where('tenant_id', $tenantId)
                ->count();

            $items = DB::table('vol_safeguarding_training as st')
                ->join('users as u', 'st.user_id', '=', 'u.id')
                ->leftJoin('users as v', 'st.verified_by', '=', 'v.id')
                ->where('st.tenant_id', $tenantId)
                ->select('st.*', 'u.name as user_name', 'u.avatar_url as user_avatar', 'v.name as verified_by_name')
                ->orderByDesc('st.created_at')
                ->offset($offset)
                ->limit($perPage)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();

            return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
        } catch (\Throwable $e) {
            Log::error('SafeguardingService::getTrainingForAdmin error: ' . $e->getMessage());
            return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }
    }

    /**
     * Verify a training record (admin/DLP approval).
     */
    public function verifyTraining(int $recordId, int $adminId, int $tenantId): bool
    {
        try {
            DB::table('vol_safeguarding_training')
                ->where('id', $recordId)
                ->where('tenant_id', $tenantId)
                ->update([
                    'status' => 'verified',
                    'verified_by' => $adminId,
                    'verified_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->logActivity($adminId, 'safeguarding_training_verified', 'safeguarding_training', $recordId);

            return true;
        } catch (\Throwable $e) {
            Log::error('SafeguardingService::verifyTraining error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Reject a training record.
     */
    public function rejectTraining(int $recordId, int $adminId, string $reason, int $tenantId): bool
    {
        try {
            DB::table('vol_safeguarding_training')
                ->where('id', $recordId)
                ->where('tenant_id', $tenantId)
                ->update([
                    'status' => 'rejected',
                    'verified_by' => $adminId,
                    'verified_at' => now(),
                    'notes' => $reason,
                    'updated_at' => now(),
                ]);

            $this->logActivity($adminId, 'safeguarding_training_rejected', 'safeguarding_training', $recordId, [
                'reason' => $reason,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('SafeguardingService::rejectTraining error: ' . $e->getMessage());
            return false;
        }
    }

    // =========================================================================
    // INCIDENTS (tenant-scoped via explicit tenant_id)
    // =========================================================================

    /**
     * Report a safeguarding incident.
     *
     * @return array|false The created incident or false on failure
     */
    public function reportIncident(int $reporterId, array $data, int $tenantId): array|false
    {
        $validSeverities = ['low', 'medium', 'high', 'critical'];
        $severity = $data['severity'] ?? 'medium';
        if (!in_array($severity, $validSeverities, true)) {
            return false;
        }

        $validIncidentTypes = ['concern', 'allegation', 'disclosure', 'near_miss', 'other'];
        $incidentType = $data['incident_type'] ?? 'other';
        if (!in_array($incidentType, $validIncidentTypes, true)) {
            return false;
        }

        try {
            $id = DB::table('vol_safeguarding_incidents')->insertGetId([
                'tenant_id' => $tenantId,
                'reported_by' => $reporterId,
                'title' => $data['title'] ?? '',
                'description' => $data['description'] ?? '',
                'severity' => $severity,
                'incident_type' => $incidentType,
                'incident_date' => $data['incident_date'] ?? now()->toDateString(),
                'involved_user_id' => $data['involved_user_id'] ?? null,
                'organization_id' => $data['organization_id'] ?? null,
                'shift_id' => $data['shift_id'] ?? null,
                'category' => $data['category'] ?? 'general',
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $record = DB::table('vol_safeguarding_incidents')
                ->where('id', $id)
                ->where('tenant_id', $tenantId)
                ->first();

            $this->logActivity($reporterId, 'safeguarding_incident_reported', 'safeguarding_incident', $id, [
                'severity' => $severity,
                'incident_type' => $incidentType,
                'title' => $data['title'] ?? '',
            ]);

            return $record ? (array) $record : [];
        } catch (\Throwable $e) {
            Log::error('SafeguardingService::reportIncident error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get safeguarding incidents with optional status filter and pagination.
     *
     * @return array{items: array, total: int, page: int, per_page: int}
     */
    public function getIncidents(int $tenantId, ?string $status = null, ?int $page = null, ?int $perPage = null): array
    {
        $page = max(1, $page ?? 1);
        $perPage = min(100, max(1, $perPage ?? 20));
        $offset = ($page - 1) * $perPage;

        try {
            $query = DB::table('vol_safeguarding_incidents as si')
                ->where('si.tenant_id', $tenantId);

            if ($status !== null) {
                $query->where('si.status', $status);
            }

            $total = (int) (clone $query)->count();

            $items = (clone $query)
                ->join('users as u', 'si.reported_by', '=', 'u.id')
                ->leftJoin('users as iu', 'si.involved_user_id', '=', 'iu.id')
                ->leftJoin('vol_organizations as org', 'si.organization_id', '=', 'org.id')
                ->leftJoin('users as au', 'si.assigned_to', '=', 'au.id')
                ->select(
                    'si.*',
                    'u.name as reported_by_name',
                    'u.avatar_url as reported_by_avatar',
                    'iu.name as involved_user_name',
                    'org.name as organization_name',
                    'au.name as assigned_to_name'
                )
                ->orderByDesc('si.created_at')
                ->offset($offset)
                ->limit($perPage)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();

            return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
        } catch (\Throwable $e) {
            Log::error('SafeguardingService::getIncidents error: ' . $e->getMessage());
            return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }
    }

    /**
     * Get a single safeguarding incident by ID.
     */
    public function getIncident(int $incidentId, int $tenantId): ?array
    {
        try {
            $record = DB::table('vol_safeguarding_incidents as si')
                ->join('users as u', 'si.reported_by', '=', 'u.id')
                ->leftJoin('users as iu', 'si.involved_user_id', '=', 'iu.id')
                ->leftJoin('vol_organizations as org', 'si.organization_id', '=', 'org.id')
                ->leftJoin('users as au', 'si.assigned_to', '=', 'au.id')
                ->where('si.id', $incidentId)
                ->where('si.tenant_id', $tenantId)
                ->select(
                    'si.*',
                    'u.name as reported_by_name',
                    'u.avatar_url as reported_by_avatar',
                    'iu.name as involved_user_name',
                    'iu.avatar_url as involved_user_avatar',
                    'org.name as organization_name',
                    'au.name as assigned_to_name'
                )
                ->first();

            return $record ? (array) $record : null;
        } catch (\Throwable $e) {
            Log::error('SafeguardingService::getIncident error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update a safeguarding incident.
     */
    public function updateIncident(int $incidentId, array $data, int $adminId, int $tenantId): bool
    {
        try {
            // Validate status enum if provided
            if (isset($data['status'])) {
                $validStatuses = ['open', 'investigating', 'resolved', 'closed'];
                if (!in_array($data['status'], $validStatuses, true)) {
                    return false;
                }
            }

            $allowedFields = ['status', 'action_taken', 'resolution_notes', 'assigned_to', 'severity'];
            $updates = [];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[$field] = $data[$field];
                }
            }

            if (empty($updates)) {
                return false;
            }

            if (isset($data['status']) && in_array($data['status'], ['resolved', 'closed'])) {
                $updates['resolved_at'] = now();
            }

            $updates['updated_at'] = now();

            DB::table('vol_safeguarding_incidents')
                ->where('id', $incidentId)
                ->where('tenant_id', $tenantId)
                ->update($updates);

            $this->logActivity($adminId, 'safeguarding_incident_updated', 'safeguarding_incident', $incidentId, $updates);

            return true;
        } catch (\Throwable $e) {
            Log::error('SafeguardingService::updateIncident error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Assign a DLP (Designated Liaison Person) to an incident.
     */
    public function assignDlp(int $incidentId, int $dlpUserId, int $adminId, int $tenantId): bool
    {
        try {
            DB::table('vol_safeguarding_incidents')
                ->where('id', $incidentId)
                ->where('tenant_id', $tenantId)
                ->update([
                    'assigned_to' => $dlpUserId,
                    'updated_at' => now(),
                ]);
            return true;
        } catch (\Throwable $e) {
            Log::error('SafeguardingService::assignDlp error: ' . $e->getMessage());
            return false;
        }
    }

    // =========================================================================
    // AUDIT LOGGING
    // =========================================================================

    private function logActivity(int $userId, string $action, string $entityType, ?int $entityId, array $details = []): void
    {
        try {
            DB::table('activity_log')->insert([
                'user_id' => $userId,
                'action' => $action,
                'action_type' => 'safeguarding',
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'details' => json_encode($details),
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('SafeguardingService: failed to log activity', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }
}
