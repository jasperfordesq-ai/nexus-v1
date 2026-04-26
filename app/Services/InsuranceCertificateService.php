<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * InsuranceCertificateService — Native Eloquent/DB implementation for insurance certificate management.
 *
 * Handles CRUD, verification, rejection for insurance certificates.
 * Used by both AdminInsuranceCertificateController and UserInsuranceController.
 */
class InsuranceCertificateService
{
    /**
     * Get all certificates for a specific user (tenant-scoped).
     */
    public function getUserCertificates(int $userId): array
    {
        $tenantId = TenantContext::getId();

        return DB::table('insurance_certificates')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    /**
     * Get a single certificate by ID (tenant-scoped).
     */
    public function getById(int $id): ?array
    {
        $tenantId = TenantContext::getId();

        $record = DB::table('insurance_certificates')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        return $record ? (array) $record : null;
    }

    /**
     * Get all certificates with filters and pagination (admin).
     *
     * @return array{data: array, pagination: array{total: int, page: int, per_page: int}}
     */
    public function getAll(array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 25)));

        $query = DB::table('insurance_certificates')
            ->where('insurance_certificates.tenant_id', $tenantId);

        if (!empty($filters['status'])) {
            // 'pending_review' is a UI-level alias for the union of
            // pending + submitted — both are pre-verification states a
            // broker still needs to action. Same semantic as the Vetting
            // module; without this, a "Pending Review" stat-card click
            // would only show literal-pending records and hide submitted
            // certificates the broker still owns.
            if ($filters['status'] === 'pending_review') {
                $query->whereIn('insurance_certificates.status', ['pending', 'submitted']);
            } else {
                $query->where('insurance_certificates.status', $filters['status']);
            }
        }

        if (!empty($filters['insurance_type'])) {
            $query->where('insurance_certificates.insurance_type', $filters['insurance_type']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('insurance_certificates.provider_name', 'LIKE', $search)
                  ->orWhere('insurance_certificates.policy_number', 'LIKE', $search);
            });
        }

        if (!empty($filters['expiring_soon'])) {
            $query->where('insurance_certificates.expiry_date', '<=', now()->addDays(30)->toDateString())
                  ->where('insurance_certificates.expiry_date', '>=', now()->toDateString())
                  ->where('insurance_certificates.status', '!=', 'expired');
        }

        if (!empty($filters['expired'])) {
            $query->where(function ($q) {
                $q->where('insurance_certificates.expiry_date', '<', now()->toDateString())
                  ->orWhere('insurance_certificates.status', 'expired');
            });
        }

        $total = $query->count();

        $data = $query->orderByDesc('insurance_certificates.created_at')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();

        return [
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ];
    }

    /**
     * Get aggregate stats for insurance certificates (admin dashboard).
     */
    public function getStats(): array
    {
        $tenantId = TenantContext::getId();

        $counts = DB::table('insurance_certificates')
            ->where('tenant_id', $tenantId)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
                SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'revoked' THEN 1 ELSE 0 END) as revoked,
                SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND expiry_date >= CURDATE() AND status NOT IN ('expired','rejected','revoked') THEN 1 ELSE 0 END) as expiring_soon
            ")
            ->first();

        return [
            'total' => (int) ($counts->total ?? 0),
            'pending' => (int) ($counts->pending ?? 0),
            'submitted' => (int) ($counts->submitted ?? 0),
            // pending_review = pending + submitted — pre-verification states
            // the broker still owns. Mirrors VettingService::getStats and
            // matches what a "Pending Review" stat card means to a broker.
            'pending_review' => (int) ($counts->pending ?? 0) + (int) ($counts->submitted ?? 0),
            'verified' => (int) ($counts->verified ?? 0),
            'expired' => (int) ($counts->expired ?? 0),
            'rejected' => (int) ($counts->rejected ?? 0),
            'revoked' => (int) ($counts->revoked ?? 0),
            'expiring_soon' => (int) ($counts->expiring_soon ?? 0),
        ];
    }

    /**
     * Create a new insurance certificate record.
     */
    public function create(array $data): int
    {
        $tenantId = TenantContext::getId();

        $id = DB::table('insurance_certificates')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => (int) $data['user_id'],
            'insurance_type' => $data['insurance_type'] ?? 'public_liability',
            'provider_name' => $data['provider_name'] ?? null,
            'policy_number' => $data['policy_number'] ?? null,
            'coverage_amount' => isset($data['coverage_amount']) ? (float) $data['coverage_amount'] : null,
            'start_date' => $data['start_date'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'certificate_file_path' => $data['certificate_file_path'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'notes' => $data['notes'] ?? null,
            'created_at' => now(),
        ]);

        return (int) $id;
    }

    /**
     * Update an existing insurance certificate.
     */
    public function update(int $id, array $data): bool
    {
        $tenantId = TenantContext::getId();

        $allowedFields = [
            'insurance_type', 'provider_name', 'policy_number',
            'coverage_amount', 'start_date', 'expiry_date',
            'certificate_file_path', 'notes',
        ];

        $updates = collect($data)->only($allowedFields)->all();
        $updates['updated_at'] = now();

        $affected = DB::table('insurance_certificates')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update($updates);

        return $affected > 0;
    }

    /**
     * Verify an insurance certificate (admin action).
     */
    public function verify(int $id, int $adminId): bool
    {
        $tenantId = TenantContext::getId();
        $now = now();

        $affected = DB::table('insurance_certificates')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update([
                'status' => 'verified',
                'verified_by' => $adminId,
                'verified_at' => $now,
                'updated_at' => $now,
            ]);

        return $affected > 0;
    }

    /**
     * Reject an insurance certificate (admin action).
     */
    public function reject(int $id, int $adminId, string $reason): bool
    {
        $tenantId = TenantContext::getId();
        $now = now();

        $affected = DB::table('insurance_certificates')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update([
                'status' => 'rejected',
                'verified_by' => $adminId,
                'verified_at' => $now,
                'notes' => $reason,
                'updated_at' => $now,
            ]);

        return $affected > 0;
    }

    /**
     * Delete an insurance certificate.
     */
    public function delete(int $id): bool
    {
        $tenantId = TenantContext::getId();

        // Delete the physical file if it exists
        $record = DB::table('insurance_certificates')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$record) {
            return false;
        }

        if (!empty($record->certificate_file_path) && file_exists($record->certificate_file_path)) {
            @unlink($record->certificate_file_path);
        }

        DB::table('insurance_certificates')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->delete();

        return true;
    }
}
