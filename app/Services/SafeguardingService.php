<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SafeguardingService — Laravel DI wrapper for legacy \Nexus\Services\SafeguardingService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 * New methods use DB:: facade directly with explicit tenant_id parameter.
 */
class SafeguardingService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy SafeguardingService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\SafeguardingService::getErrors();
    }

    /**
     * Delegates to legacy SafeguardingService::createAssignment().
     */
    public function createAssignment(int $guardianUserId, int $wardUserId, int $assignedBy, ?string $notes = null): array
    {
        return \Nexus\Services\SafeguardingService::createAssignment($guardianUserId, $wardUserId, $assignedBy, $notes);
    }

    /**
     * Delegates to legacy SafeguardingService::recordConsent().
     */
    public function recordConsent(int $wardUserId): bool
    {
        return \Nexus\Services\SafeguardingService::recordConsent($wardUserId);
    }

    /**
     * Delegates to legacy SafeguardingService::revokeAssignment().
     */
    public function revokeAssignment(int $assignmentId, int $revokedBy): bool
    {
        return \Nexus\Services\SafeguardingService::revokeAssignment($assignmentId, $revokedBy);
    }

    /**
     * Delegates to legacy SafeguardingService::listAssignments().
     */
    public function listAssignments(): array
    {
        return \Nexus\Services\SafeguardingService::listAssignments();
    }

    // =========================================================================
    // TRAINING (DB:: facade — tenant-scoped)
    // =========================================================================

    /**
     * Get all training records for a user.
     */
    public function getTrainingForUser(int $userId, int $tenantId): array
    {
        try {
            return DB::select(
                "SELECT st.*, v.name as verified_by_name
                 FROM vol_safeguarding_training st
                 LEFT JOIN users v ON st.verified_by = v.id
                 WHERE st.user_id = ? AND st.tenant_id = ?
                 ORDER BY st.completed_at DESC",
                [$userId, $tenantId]
            );
        } catch (\Exception $e) {
            Log::error('SafeguardingService::getTrainingForUser error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Record a training completion for a user.
     *
     * @param int $userId
     * @param array $data [training_type, training_name, provider, completed_at, expires_at, certificate_url, notes]
     * @param int $tenantId
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

            return $record ? (array) $record : [];
        } catch (\Exception $e) {
            Log::error('SafeguardingService::recordTraining error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get training records for admin view with pagination.
     *
     * @return array ['items' => [], 'total' => int, 'page' => int, 'per_page' => int]
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
                ->select(
                    'st.*',
                    'u.name as user_name',
                    'u.avatar_url as user_avatar',
                    'v.name as verified_by_name'
                )
                ->orderByDesc('st.created_at')
                ->offset($offset)
                ->limit($perPage)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();

            return [
                'items' => $items,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
            ];
        } catch (\Exception $e) {
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

            return true;
        } catch (\Exception $e) {
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

            return true;
        } catch (\Exception $e) {
            Log::error('SafeguardingService::rejectTraining error: ' . $e->getMessage());
            return false;
        }
    }

    // =========================================================================
    // INCIDENTS (DB:: facade — tenant-scoped)
    // =========================================================================

    /**
     * Report a safeguarding incident.
     *
     * @param int $reporterId
     * @param array $data [title, description, severity, incident_type, incident_date, involved_user_id, organization_id, shift_id, category]
     * @param int $tenantId
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

            return $record ? (array) $record : [];
        } catch (\Exception $e) {
            Log::error('SafeguardingService::reportIncident error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get safeguarding incidents with optional status filter and pagination.
     *
     * @return array ['items' => [], 'total' => int, 'page' => int, 'per_page' => int]
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

            return [
                'items' => $items,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
            ];
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
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

            // If resolving, set resolved_at
            if (isset($data['status']) && in_array($data['status'], ['resolved', 'closed'])) {
                $updates['resolved_at'] = now();
            }

            $updates['updated_at'] = now();

            DB::table('vol_safeguarding_incidents')
                ->where('id', $incidentId)
                ->where('tenant_id', $tenantId)
                ->update($updates);

            return true;
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
            Log::error('SafeguardingService::assignDlp error: ' . $e->getMessage());
            return false;
        }
    }
}
