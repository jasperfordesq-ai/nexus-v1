<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\TenantContext;
use Nexus\Services\InsuranceCertificateService;

/**
 * AdminInsuranceCertificateApiController - V2 API for insurance certificate management
 *
 * Provides CRUD operations and broker workflow actions for insurance certificates.
 * All endpoints require admin authentication.
 *
 * Endpoints:
 * - GET    /api/v2/admin/insurance              - List certificates (paginated, filterable)
 * - GET    /api/v2/admin/insurance/stats        - Summary stats
 * - GET    /api/v2/admin/insurance/user/{userId} - All certificates for a specific user
 * - GET    /api/v2/admin/insurance/{id}         - Single certificate detail
 * - POST   /api/v2/admin/insurance              - Create new certificate
 * - PUT    /api/v2/admin/insurance/{id}         - Update certificate fields
 * - POST   /api/v2/admin/insurance/{id}/verify  - Verify certificate (broker action)
 * - POST   /api/v2/admin/insurance/{id}/reject  - Reject certificate with reason
 * - DELETE /api/v2/admin/insurance/{id}         - Delete certificate
 */
class AdminInsuranceCertificateApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/admin/insurance
     */
    public function list(): void
    {
        $this->requireAdmin();

        try {
            $filters = [
                'status' => $this->query('status'),
                'insurance_type' => $this->query('insurance_type'),
                'search' => $this->query('search'),
                'expiring_soon' => $this->queryBool('expiring_soon'),
                'expired' => $this->queryBool('expired'),
                'page' => $this->queryInt('page', 1, 1),
                'per_page' => $this->queryInt('per_page', 25, 10, 100),
            ];

            $result = InsuranceCertificateService::getAll($filters);

            $this->respondWithPaginatedCollection(
                $result['data'],
                $result['pagination']['total'],
                $result['pagination']['page'],
                $result['pagination']['per_page']
            );
        } catch (\Exception $e) {
            $this->respondWithPaginatedCollection([], 0, 1, 25);
        }
    }

    /**
     * GET /api/v2/admin/insurance/stats
     */
    public function stats(): void
    {
        $this->requireAdmin();

        $stats = InsuranceCertificateService::getStats();
        $this->respondWithData($stats);
    }

    /**
     * GET /api/v2/admin/insurance/{id}
     */
    public function show(int $id): void
    {
        $this->requireAdmin();

        try {
            $record = InsuranceCertificateService::getById($id);

            if (!$record) {
                $this->respondWithError('NOT_FOUND', 'Insurance certificate not found', null, 404);
                return;
            }

            $this->respondWithData($record);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to fetch insurance certificate', null, 500);
        }
    }

    /**
     * POST /api/v2/admin/insurance
     */
    public function store(): void
    {
        $this->requireAdmin();

        $userId = $this->inputInt('user_id');
        $insuranceType = $this->input('insurance_type');

        if (!$userId) {
            $this->respondWithError('VALIDATION_ERROR', 'user_id is required', 'user_id');
            return;
        }

        $validTypes = ['public_liability', 'professional_indemnity', 'employers_liability',
                        'product_liability', 'personal_accident', 'other'];

        if ($insuranceType && !in_array($insuranceType, $validTypes, true)) {
            $this->respondWithError('VALIDATION_ERROR', 'Invalid insurance type', 'insurance_type');
            return;
        }

        try {
            $data = [
                'user_id' => $userId,
                'insurance_type' => $insuranceType ?? 'public_liability',
                'status' => $this->input('status', 'pending'),
                'provider_name' => $this->input('provider_name'),
                'policy_number' => $this->input('policy_number'),
                'coverage_amount' => $this->input('coverage_amount'),
                'start_date' => $this->input('start_date'),
                'expiry_date' => $this->input('expiry_date'),
                'notes' => $this->input('notes'),
            ];

            $id = InsuranceCertificateService::create($data);

            $record = InsuranceCertificateService::getById($id);
            $this->respondWithData($record, null, 201);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to create insurance certificate', null, 500);
        }
    }

    /**
     * PUT /api/v2/admin/insurance/{id}
     */
    public function update(int $id): void
    {
        $this->requireAdmin();

        try {
            $existing = InsuranceCertificateService::getById($id);
            if (!$existing) {
                $this->respondWithError('NOT_FOUND', 'Insurance certificate not found', null, 404);
                return;
            }

            $data = [];
            $allInput = $this->getAllInput();

            $allowed = ['insurance_type', 'provider_name', 'policy_number', 'coverage_amount',
                         'start_date', 'expiry_date', 'status', 'notes'];

            foreach ($allowed as $field) {
                if (array_key_exists($field, $allInput)) {
                    $data[$field] = $allInput[$field];
                }
            }

            if (empty($data)) {
                $this->respondWithError('VALIDATION_ERROR', 'No valid fields to update');
                return;
            }

            InsuranceCertificateService::update($id, $data);

            $record = InsuranceCertificateService::getById($id);
            $this->respondWithData($record);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to update insurance certificate', null, 500);
        }
    }

    /**
     * POST /api/v2/admin/insurance/{id}/verify
     */
    public function verify(int $id): void
    {
        $adminId = $this->requireAdmin();

        try {
            $existing = InsuranceCertificateService::getById($id);
            if (!$existing) {
                $this->respondWithError('NOT_FOUND', 'Insurance certificate not found', null, 404);
                return;
            }

            if ($existing['status'] === 'verified') {
                $this->respondWithError('INVALID_STATUS', 'Certificate is already verified');
                return;
            }

            InsuranceCertificateService::verify($id, $adminId);

            $record = InsuranceCertificateService::getById($id);
            $this->respondWithData($record);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to verify insurance certificate', null, 500);
        }
    }

    /**
     * POST /api/v2/admin/insurance/{id}/reject
     */
    public function reject(int $id): void
    {
        $adminId = $this->requireAdmin();
        $reason = $this->input('reason', '');

        if (empty($reason)) {
            $this->respondWithError('VALIDATION_ERROR', 'A reason is required to reject an insurance certificate', 'reason');
            return;
        }

        try {
            $existing = InsuranceCertificateService::getById($id);
            if (!$existing) {
                $this->respondWithError('NOT_FOUND', 'Insurance certificate not found', null, 404);
                return;
            }

            InsuranceCertificateService::reject($id, $adminId, $reason);

            $record = InsuranceCertificateService::getById($id);
            $this->respondWithData($record);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to reject insurance certificate', null, 500);
        }
    }

    /**
     * DELETE /api/v2/admin/insurance/{id}
     */
    public function destroy(int $id): void
    {
        $this->requireAdmin();

        try {
            $existing = InsuranceCertificateService::getById($id);
            if (!$existing) {
                $this->respondWithError('NOT_FOUND', 'Insurance certificate not found', null, 404);
                return;
            }

            InsuranceCertificateService::delete($id);

            $this->respondWithData(['deleted' => true]);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to delete insurance certificate', null, 500);
        }
    }

    /**
     * GET /api/v2/admin/insurance/user/{userId}
     */
    public function getUserCertificates(int $userId): void
    {
        $this->requireAdmin();

        try {
            $records = InsuranceCertificateService::getUserCertificates($userId);
            $this->respondWithData($records);
        } catch (\Exception $e) {
            $this->respondWithData([]);
        }
    }
}
