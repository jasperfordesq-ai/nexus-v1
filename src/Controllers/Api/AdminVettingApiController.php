<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\TenantContext;
use Nexus\Services\VettingService;

/**
 * AdminVettingApiController - V2 API for DBS/Garda vetting record management
 *
 * Provides CRUD operations and broker workflow actions for vetting records.
 * All endpoints require admin authentication.
 *
 * Endpoints:
 * - GET    /api/v2/admin/vetting              - List vetting records (paginated, filterable)
 * - GET    /api/v2/admin/vetting/stats        - Summary stats (pending, verified, expired, expiring_soon)
 * - GET    /api/v2/admin/vetting/user/{userId} - All records for a specific user
 * - GET    /api/v2/admin/vetting/{id}         - Single record detail
 * - POST   /api/v2/admin/vetting              - Create new vetting record
 * - PUT    /api/v2/admin/vetting/{id}         - Update record fields
 * - POST   /api/v2/admin/vetting/{id}/verify  - Verify record (broker action)
 * - POST   /api/v2/admin/vetting/{id}/reject  - Reject record with reason
 * - DELETE /api/v2/admin/vetting/{id}         - Delete record
 */
class AdminVettingApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/admin/vetting
     *
     * List vetting records with pagination and filters.
     * Supports: ?status=, ?vetting_type=, ?search=, ?expiring_soon=1, ?expired=1, ?page=, ?per_page=
     */
    public function list(): void
    {
        $this->requireAdmin();

        try {
            $filters = [
                'status' => $this->query('status'),
                'vetting_type' => $this->query('vetting_type'),
                'search' => $this->query('search'),
                'expiring_soon' => $this->queryBool('expiring_soon'),
                'expired' => $this->queryBool('expired'),
                'page' => $this->queryInt('page', 1, 1),
                'per_page' => $this->queryInt('per_page', 25, 10, 100),
            ];

            $result = VettingService::getAll($filters);

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
     * GET /api/v2/admin/vetting/stats
     *
     * Returns aggregate counts for the vetting dashboard stat cards.
     */
    public function stats(): void
    {
        $this->requireAdmin();

        $stats = VettingService::getStats();
        $this->respondWithData($stats);
    }

    /**
     * GET /api/v2/admin/vetting/{id}
     *
     * Get a single vetting record with user and verifier details.
     */
    public function show(int $id): void
    {
        $this->requireAdmin();

        try {
            $record = VettingService::getById($id);

            if (!$record) {
                $this->respondWithError('NOT_FOUND', 'Vetting record not found', null, 404);
                return;
            }

            $this->respondWithData($record);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to fetch vetting record', null, 500);
        }
    }

    /**
     * POST /api/v2/admin/vetting
     *
     * Create a new vetting record. Requires { user_id, vetting_type }.
     */
    public function store(): void
    {
        $this->requireAdmin();

        $userId = $this->inputInt('user_id');
        $vettingType = $this->input('vetting_type');

        if (!$userId) {
            $this->respondWithError('VALIDATION_ERROR', 'user_id is required', 'user_id');
            return;
        }

        $validTypes = ['dbs_basic', 'dbs_standard', 'dbs_enhanced', 'garda_vetting',
                        'access_ni', 'pvg_scotland', 'international', 'other'];

        if ($vettingType && !in_array($vettingType, $validTypes, true)) {
            $this->respondWithError('VALIDATION_ERROR', 'Invalid vetting type', 'vetting_type');
            return;
        }

        try {
            $data = [
                'user_id' => $userId,
                'vetting_type' => $vettingType ?? 'dbs_basic',
                'status' => $this->input('status', 'pending'),
                'reference_number' => $this->input('reference_number'),
                'issue_date' => $this->input('issue_date'),
                'expiry_date' => $this->input('expiry_date'),
                'notes' => $this->input('notes'),
                'works_with_children' => $this->inputBool('works_with_children') ? 1 : 0,
                'works_with_vulnerable_adults' => $this->inputBool('works_with_vulnerable_adults') ? 1 : 0,
                'requires_enhanced_check' => $this->inputBool('requires_enhanced_check') ? 1 : 0,
            ];

            $id = VettingService::create($data);

            $record = VettingService::getById($id);
            $this->respondWithData($record, null, 201);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to create vetting record', null, 500);
        }
    }

    /**
     * PUT /api/v2/admin/vetting/{id}
     *
     * Update a vetting record's fields.
     */
    public function update(int $id): void
    {
        $this->requireAdmin();

        try {
            $existing = VettingService::getById($id);
            if (!$existing) {
                $this->respondWithError('NOT_FOUND', 'Vetting record not found', null, 404);
                return;
            }

            $data = [];
            $allInput = $this->getAllInput();

            $allowed = ['vetting_type', 'status', 'reference_number', 'issue_date', 'expiry_date',
                         'notes', 'works_with_children', 'works_with_vulnerable_adults', 'requires_enhanced_check'];

            foreach ($allowed as $field) {
                if (array_key_exists($field, $allInput)) {
                    if (in_array($field, ['works_with_children', 'works_with_vulnerable_adults', 'requires_enhanced_check'])) {
                        $data[$field] = $this->inputBool($field) ? 1 : 0;
                    } else {
                        $data[$field] = $allInput[$field];
                    }
                }
            }

            if (empty($data)) {
                $this->respondWithError('VALIDATION_ERROR', 'No valid fields to update');
                return;
            }

            VettingService::update($id, $data);

            $record = VettingService::getById($id);
            $this->respondWithData($record);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to update vetting record', null, 500);
        }
    }

    /**
     * POST /api/v2/admin/vetting/{id}/verify
     *
     * Mark a vetting record as verified by the current admin.
     */
    public function verify(int $id): void
    {
        $adminId = $this->requireAdmin();

        try {
            $existing = VettingService::getById($id);
            if (!$existing) {
                $this->respondWithError('NOT_FOUND', 'Vetting record not found', null, 404);
                return;
            }

            if ($existing['status'] === 'verified') {
                $this->respondWithError('INVALID_STATUS', 'Record is already verified');
                return;
            }

            VettingService::verify($id, $adminId);

            $record = VettingService::getById($id);
            $this->respondWithData($record);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to verify vetting record', null, 500);
        }
    }

    /**
     * POST /api/v2/admin/vetting/{id}/reject
     *
     * Reject a vetting record. Requires { reason } in body.
     */
    public function reject(int $id): void
    {
        $adminId = $this->requireAdmin();
        $reason = $this->input('reason', '');

        if (empty($reason)) {
            $this->respondWithError('VALIDATION_ERROR', 'A reason is required to reject a vetting record', 'reason');
            return;
        }

        try {
            $existing = VettingService::getById($id);
            if (!$existing) {
                $this->respondWithError('NOT_FOUND', 'Vetting record not found', null, 404);
                return;
            }

            VettingService::reject($id, $adminId, $reason);

            $record = VettingService::getById($id);
            $this->respondWithData($record);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to reject vetting record', null, 500);
        }
    }

    /**
     * DELETE /api/v2/admin/vetting/{id}
     *
     * Delete a vetting record permanently.
     */
    public function destroy(int $id): void
    {
        $this->requireAdmin();

        try {
            $existing = VettingService::getById($id);
            if (!$existing) {
                $this->respondWithError('NOT_FOUND', 'Vetting record not found', null, 404);
                return;
            }

            VettingService::delete($id);

            $this->respondWithData(['deleted' => true]);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to delete vetting record', null, 500);
        }
    }

    /**
     * GET /api/v2/admin/vetting/user/{userId}
     *
     * Get all vetting records for a specific user.
     */
    public function getUserRecords(int $userId): void
    {
        $this->requireAdmin();

        try {
            $records = VettingService::getUserRecords($userId);
            $this->respondWithData($records);
        } catch (\Exception $e) {
            $this->respondWithData([]);
        }
    }
}
