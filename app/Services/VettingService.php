<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * VettingService — Native Laravel implementation.
 *
 * Manages vetting/background check records (DBS, Garda vetting, etc.)
 * for the admin vetting panel. All queries are tenant-scoped.
 */
class VettingService
{
    public function __construct()
    {
    }

    /**
     * Get all vetting records for a specific user within the current tenant.
     *
     * @return array<int, array>
     */
    public function getUserRecords(int $userId): array
    {
        try {
            $tenantId = TenantContext::getId();

            $records = DB::table('vetting_records')
                ->join('users', 'vetting_records.user_id', '=', 'users.id')
                ->where('vetting_records.tenant_id', $tenantId)
                ->where('vetting_records.user_id', $userId)
                ->select([
                    'vetting_records.*',
                    'users.first_name', 'users.last_name', 'users.email',
                ])
                ->orderByDesc('vetting_records.created_at')
                ->get();

            return $records->map(fn($row) => (array) $row)->all();
        } catch (\Throwable $e) {
            Log::error('VettingService::getUserRecords failed', ['error' => $e->getMessage(), 'user_id' => $userId]);
            return [];
        }
    }

    /**
     * Get a single vetting record by ID within the current tenant.
     */
    public function getById(int $id): ?array
    {
        try {
            $tenantId = TenantContext::getId();

            $record = DB::table('vetting_records')
                ->join('users', 'vetting_records.user_id', '=', 'users.id')
                ->where('vetting_records.id', $id)
                ->where('vetting_records.tenant_id', $tenantId)
                ->select([
                    'vetting_records.*',
                    'users.first_name', 'users.last_name', 'users.email',
                ])
                ->first();

            if (!$record) {
                return null;
            }

            $row = (array) $record;

            // Add verified_by admin name if present
            if (!empty($row['verified_by'])) {
                $verifier = DB::table('users')
                    ->where('id', $row['verified_by'])
                    ->select(['first_name', 'last_name'])
                    ->first();
                $row['verified_by_name'] = $verifier
                    ? trim($verifier->first_name . ' ' . $verifier->last_name)
                    : null;
            }

            // Add rejected_by admin name if present
            if (!empty($row['rejected_by'])) {
                $rejector = DB::table('users')
                    ->where('id', $row['rejected_by'])
                    ->select(['first_name', 'last_name'])
                    ->first();
                $row['rejected_by_name'] = $rejector
                    ? trim($rejector->first_name . ' ' . $rejector->last_name)
                    : null;
            }

            // Boolean cast for frontend
            $row['works_with_children'] = (bool) ($row['works_with_children'] ?? false);
            $row['works_with_vulnerable_adults'] = (bool) ($row['works_with_vulnerable_adults'] ?? false);
            $row['requires_enhanced_check'] = (bool) ($row['requires_enhanced_check'] ?? false);

            return $row;
        } catch (\Throwable $e) {
            Log::error('VettingService::getById failed', ['error' => $e->getMessage(), 'id' => $id]);
            return null;
        }
    }

    /**
     * Get all vetting records with filtering and pagination.
     *
     * Supported filters: status, vetting_type, search, expiring_soon, expired, page, per_page.
     *
     * @return array{data: array, pagination: array}
     */
    public function getAll(array $filters = []): array
    {
        try {
            $tenantId = TenantContext::getId();

            $query = DB::table('vetting_records')
                ->join('users', 'vetting_records.user_id', '=', 'users.id')
                ->where('vetting_records.tenant_id', $tenantId)
                ->select([
                    'vetting_records.*',
                    'users.first_name', 'users.last_name', 'users.email',
                ]);

            if (!empty($filters['status'])) {
                $query->where('vetting_records.status', $filters['status']);
            }

            if (!empty($filters['vetting_type'])) {
                $query->where('vetting_records.vetting_type', $filters['vetting_type']);
            }

            if (!empty($filters['search'])) {
                $search = '%' . $filters['search'] . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('users.first_name', 'LIKE', $search)
                      ->orWhere('users.last_name', 'LIKE', $search)
                      ->orWhere('users.email', 'LIKE', $search)
                      ->orWhere('vetting_records.reference_number', 'LIKE', $search)
                      ->orWhere('vetting_records.notes', 'LIKE', $search);
                });
            }

            if (!empty($filters['expiring_soon'])) {
                $query->where('vetting_records.expiry_date', '<=', now()->addDays(30)->toDateString())
                      ->where('vetting_records.expiry_date', '>=', now()->toDateString())
                      ->where('vetting_records.status', 'verified');
            }

            if (!empty($filters['expired'])) {
                $query->where('vetting_records.expiry_date', '<', now()->toDateString())
                      ->whereIn('vetting_records.status', ['verified', 'expired']);
            }

            // Count before pagination
            $total = (clone $query)->count();

            $page = max(1, (int) ($filters['page'] ?? 1));
            $perPage = max(10, min(100, (int) ($filters['per_page'] ?? 25)));
            $offset = ($page - 1) * $perPage;

            $records = $query
                ->orderByDesc('vetting_records.created_at')
                ->limit($perPage)
                ->offset($offset)
                ->get();

            $data = $records->map(function ($row) {
                $r = (array) $row;
                $r['works_with_children'] = (bool) ($r['works_with_children'] ?? false);
                $r['works_with_vulnerable_adults'] = (bool) ($r['works_with_vulnerable_adults'] ?? false);
                $r['requires_enhanced_check'] = (bool) ($r['requires_enhanced_check'] ?? false);
                return $r;
            })->all();

            return [
                'data' => $data,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => (int) ceil($total / $perPage),
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('VettingService::getAll failed', ['error' => $e->getMessage()]);
            return [
                'data' => [],
                'pagination' => ['total' => 0, 'page' => 1, 'per_page' => 25, 'total_pages' => 0],
            ];
        }
    }

    /**
     * Get vetting statistics for the current tenant.
     */
    public function getStats(): array
    {
        try {
            $tenantId = TenantContext::getId();

            $total = DB::table('vetting_records')
                ->where('tenant_id', $tenantId)
                ->count();

            $byStatus = DB::table('vetting_records')
                ->where('tenant_id', $tenantId)
                ->select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->all();

            $byType = DB::table('vetting_records')
                ->where('tenant_id', $tenantId)
                ->select('vetting_type', DB::raw('COUNT(*) as count'))
                ->groupBy('vetting_type')
                ->pluck('count', 'vetting_type')
                ->all();

            $expiringSoon = DB::table('vetting_records')
                ->where('tenant_id', $tenantId)
                ->where('status', 'verified')
                ->where('expiry_date', '<=', now()->addDays(30)->toDateString())
                ->where('expiry_date', '>=', now()->toDateString())
                ->count();

            $expired = DB::table('vetting_records')
                ->where('tenant_id', $tenantId)
                ->whereIn('status', ['verified', 'expired'])
                ->where('expiry_date', '<', now()->toDateString())
                ->count();

            return [
                'total' => $total,
                'by_status' => $byStatus,
                'by_type' => $byType,
                'expiring_soon' => $expiringSoon,
                'expired' => $expired,
                'pending' => (int) ($byStatus['pending'] ?? 0),
                'verified' => (int) ($byStatus['verified'] ?? 0),
                'rejected' => (int) ($byStatus['rejected'] ?? 0),
            ];
        } catch (\Throwable $e) {
            Log::error('VettingService::getStats failed', ['error' => $e->getMessage()]);
            return [
                'total' => 0, 'by_status' => [], 'by_type' => [],
                'expiring_soon' => 0, 'expired' => 0,
                'pending' => 0, 'verified' => 0, 'rejected' => 0,
            ];
        }
    }

    /**
     * Create a new vetting record.
     *
     * @return int The newly created record ID
     */
    public function create(array $data): int
    {
        $tenantId = TenantContext::getId();

        $id = DB::table('vetting_records')->insertGetId([
            'tenant_id'                   => $tenantId,
            'user_id'                     => (int) $data['user_id'],
            'vetting_type'                => $data['vetting_type'] ?? 'dbs_basic',
            'status'                      => $data['status'] ?? 'pending',
            'reference_number'            => $data['reference_number'] ?? null,
            'issue_date'                  => $data['issue_date'] ?? null,
            'expiry_date'                 => $data['expiry_date'] ?? null,
            'notes'                       => $data['notes'] ?? null,
            'works_with_children'         => (int) ($data['works_with_children'] ?? 0),
            'works_with_vulnerable_adults' => (int) ($data['works_with_vulnerable_adults'] ?? 0),
            'requires_enhanced_check'     => (int) ($data['requires_enhanced_check'] ?? 0),
            'created_at'                  => now(),
        ]);

        // Update user vetting status for quick lookup
        $this->syncUserVettingStatus((int) $data['user_id']);

        return (int) $id;
    }

    /**
     * Update a vetting record.
     */
    public function update(int $id, array $data): bool
    {
        $tenantId = TenantContext::getId();

        $allowed = ['reference_number', 'issue_date', 'expiry_date', 'notes', 'document_url'];
        $updates = collect($data)->only($allowed)->all();
        $updates['updated_at'] = now();

        $affected = DB::table('vetting_records')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update($updates);

        // If status changed, sync the user's vetting_status
        if (isset($data['status'])) {
            $record = DB::table('vetting_records')
                ->where('id', $id)
                ->first();
            if ($record) {
                $this->syncUserVettingStatus((int) $record->user_id);
            }
        }

        return $affected > 0;
    }

    /**
     * Verify a vetting record (mark as verified by admin).
     */
    public function verify(int $id, int $adminId): bool
    {
        $tenantId = TenantContext::getId();

        $affected = DB::table('vetting_records')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update([
                'status'      => 'verified',
                'verified_by' => $adminId,
                'verified_at' => now(),
                'updated_at'  => now(),
            ]);

        if ($affected > 0) {
            $record = DB::table('vetting_records')->where('id', $id)->first();
            if ($record) {
                $this->syncUserVettingStatus((int) $record->user_id);
            }
        }

        return $affected > 0;
    }

    /**
     * Reject a vetting record with a reason.
     */
    public function reject(int $id, int $adminId, string $reason): bool
    {
        $tenantId = TenantContext::getId();

        $affected = DB::table('vetting_records')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update([
                'status'           => 'rejected',
                'rejected_by'      => $adminId,
                'rejected_at'      => now(),
                'rejection_reason' => $reason,
                'updated_at'       => now(),
            ]);

        if ($affected > 0) {
            $record = DB::table('vetting_records')->where('id', $id)->first();
            if ($record) {
                $this->syncUserVettingStatus((int) $record->user_id);
            }
        }

        return $affected > 0;
    }

    /**
     * Delete a vetting record.
     */
    public function delete(int $id): bool
    {
        $tenantId = TenantContext::getId();

        // Get user_id before deletion for status sync
        $record = DB::table('vetting_records')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$record) {
            return false;
        }

        $affected = DB::table('vetting_records')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->delete();

        if ($affected > 0) {
            $this->syncUserVettingStatus((int) $record->user_id);
        }

        return $affected > 0;
    }

    /**
     * Update the document URL for a vetting record.
     */
    public function updateDocumentUrl(int $id, string $url): bool
    {
        $tenantId = TenantContext::getId();

        $affected = DB::table('vetting_records')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update([
                'document_url' => $url,
                'updated_at'   => now(),
            ]);

        return $affected > 0;
    }

    /**
     * Sync the user's vetting_status and vetting_expires_at columns
     * based on their most recent vetting record.
     */
    private function syncUserVettingStatus(int $userId): void
    {
        try {
            // Find the best active vetting record for this user
            $bestRecord = DB::table('vetting_records')
                ->where('user_id', $userId)
                ->orderByRaw("FIELD(status, 'verified', 'pending', 'submitted', 'expired', 'rejected', 'revoked')")
                ->orderByDesc('created_at')
                ->first();

            if (!$bestRecord) {
                DB::table('users')->where('id', $userId)->update([
                    'vetting_status' => 'none',
                    'vetting_expires_at' => null,
                ]);
                return;
            }

            $statusMap = [
                'verified'  => 'verified',
                'pending'   => 'pending',
                'submitted' => 'pending',
                'expired'   => 'expired',
                'rejected'  => 'none',
                'revoked'   => 'none',
            ];

            $vettingStatus = $statusMap[$bestRecord->status] ?? 'none';

            // Check if verified but expired
            if ($vettingStatus === 'verified' && $bestRecord->expiry_date && $bestRecord->expiry_date < now()->toDateString()) {
                $vettingStatus = 'expired';
            }

            DB::table('users')->where('id', $userId)->update([
                'vetting_status' => $vettingStatus,
                'vetting_expires_at' => $bestRecord->expiry_date,
            ]);
        } catch (\Throwable $e) {
            Log::error('VettingService::syncUserVettingStatus failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);
        }
    }
}
