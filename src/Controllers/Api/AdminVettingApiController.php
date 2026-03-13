<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\ImageUploader;
use Nexus\Core\TenantContext;
use Nexus\Models\ActivityLog;
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
 * - POST   /api/v2/admin/vetting/bulk         - Bulk verify/reject/delete (max 100)
 * - POST   /api/v2/admin/vetting/{id}/upload  - Upload document (PDF/image)
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
            return;
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
        $validStatuses = ['pending', 'submitted', 'verified', 'expired', 'rejected', 'revoked'];

        if ($vettingType && !in_array($vettingType, $validTypes, true)) {
            $this->respondWithError('VALIDATION_ERROR', 'Invalid vetting type', 'vetting_type');
            return;
        }

        $status = $this->input('status', 'pending');
        if (!in_array($status, $validStatuses, true)) {
            $this->respondWithError('VALIDATION_ERROR', 'Invalid status', 'status');
            return;
        }

        // Verify user exists in the current tenant
        $tenantId = TenantContext::getId();
        $userExists = \Nexus\Core\Database::query(
            "SELECT id FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetch();
        if (!$userExists) {
            $this->respondWithError('VALIDATION_ERROR', 'User not found in this tenant', 'user_id');
            return;
        }

        // Validate dates if provided
        $issueDate = $this->input('issue_date');
        $expiryDate = $this->input('expiry_date');
        if ($issueDate && !strtotime($issueDate)) {
            $this->respondWithError('VALIDATION_ERROR', 'Invalid issue date format', 'issue_date');
            return;
        }
        if ($expiryDate && !strtotime($expiryDate)) {
            $this->respondWithError('VALIDATION_ERROR', 'Invalid expiry date format', 'expiry_date');
            return;
        }
        if ($issueDate && $expiryDate && strtotime($expiryDate) < strtotime($issueDate)) {
            $this->respondWithError('VALIDATION_ERROR', 'Expiry date must be after issue date', 'expiry_date');
            return;
        }

        try {
            $data = [
                'user_id' => $userId,
                'vetting_type' => $vettingType ?? 'dbs_basic',
                'status' => $status,
                'reference_number' => $this->input('reference_number'),
                'issue_date' => $issueDate,
                'expiry_date' => $expiryDate,
                'notes' => $this->input('notes'),
                'works_with_children' => $this->inputBool('works_with_children') ? 1 : 0,
                'works_with_vulnerable_adults' => $this->inputBool('works_with_vulnerable_adults') ? 1 : 0,
                'requires_enhanced_check' => $this->inputBool('requires_enhanced_check') ? 1 : 0,
            ];

            $adminId = $this->getAuthenticatedUserId();
            $id = VettingService::create($data);

            ActivityLog::log($adminId, 'vetting_record_created', "Created vetting record #{$id} for user #{$userId} ({$data['vetting_type']})", false, null, 'admin', 'vetting_record', $id);

            $record = VettingService::getById($id);
            $this->respondWithData($record, null, 201);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to create vetting record', null, 500);
            return;
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

            $validTypes = ['dbs_basic', 'dbs_standard', 'dbs_enhanced', 'garda_vetting',
                            'access_ni', 'pvg_scotland', 'international', 'other'];
            $validStatuses = ['pending', 'submitted', 'verified', 'expired', 'rejected', 'revoked'];

            // Validate enum fields before processing
            if (array_key_exists('vetting_type', $allInput) && $allInput['vetting_type'] !== null
                && !in_array($allInput['vetting_type'], $validTypes, true)) {
                $this->respondWithError('VALIDATION_ERROR', 'Invalid vetting type', 'vetting_type');
                return;
            }
            if (array_key_exists('status', $allInput) && $allInput['status'] !== null
                && !in_array($allInput['status'], $validStatuses, true)) {
                $this->respondWithError('VALIDATION_ERROR', 'Invalid status', 'status');
                return;
            }

            // Validate dates if provided
            if (array_key_exists('issue_date', $allInput) && $allInput['issue_date'] !== null
                && !strtotime($allInput['issue_date'])) {
                $this->respondWithError('VALIDATION_ERROR', 'Invalid issue date format', 'issue_date');
                return;
            }
            if (array_key_exists('expiry_date', $allInput) && $allInput['expiry_date'] !== null
                && !strtotime($allInput['expiry_date'])) {
                $this->respondWithError('VALIDATION_ERROR', 'Invalid expiry date format', 'expiry_date');
                return;
            }
            $effectiveIssue = $allInput['issue_date'] ?? $existing['issue_date'] ?? null;
            $effectiveExpiry = $allInput['expiry_date'] ?? $existing['expiry_date'] ?? null;
            if ($effectiveIssue && $effectiveExpiry && strtotime($effectiveExpiry) < strtotime($effectiveIssue)) {
                $this->respondWithError('VALIDATION_ERROR', 'Expiry date must be after issue date', 'expiry_date');
                return;
            }

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

            $adminId = $this->getAuthenticatedUserId();
            $changedFields = implode(', ', array_keys($data));
            ActivityLog::log($adminId, 'vetting_record_updated', "Updated vetting record #{$id} ({$changedFields})", false, null, 'admin', 'vetting_record', $id);

            $record = VettingService::getById($id);
            $this->respondWithData($record);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to update vetting record', null, 500);
            return;
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

            ActivityLog::log($adminId, 'vetting_record_verified', "Verified vetting record #{$id} for {$existing['first_name']} {$existing['last_name']}", false, null, 'admin', 'vetting_record', $id);

            $record = VettingService::getById($id);
            $this->respondWithData($record);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to verify vetting record', null, 500);
            return;
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

            ActivityLog::log($adminId, 'vetting_record_rejected', "Rejected vetting record #{$id} for {$existing['first_name']} {$existing['last_name']}: {$reason}", false, null, 'admin', 'vetting_record', $id);

            $record = VettingService::getById($id);
            $this->respondWithData($record);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to reject vetting record', null, 500);
            return;
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

            $adminId = $this->getAuthenticatedUserId();
            ActivityLog::log($adminId, 'vetting_record_deleted', "Deleted vetting record #{$id} for {$existing['first_name']} {$existing['last_name']} ({$existing['vetting_type']})", false, null, 'admin', 'vetting_record', $id);

            $this->respondWithData(['deleted' => true]);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to delete vetting record', null, 500);
            return;
        }
    }

    /**
     * POST /api/v2/admin/vetting/bulk
     *
     * Bulk action on multiple vetting records.
     * Body: { "ids": [1, 2, 3], "action": "verify" | "reject" | "delete", "reason": "..." }
     */
    public function bulk(): void
    {
        $adminId = $this->requireAdmin();

        $ids = $this->input('ids');
        $action = $this->input('action');
        $reason = $this->input('reason', '');

        if (!is_array($ids) || empty($ids)) {
            $this->respondWithError('VALIDATION_ERROR', 'ids must be a non-empty array', 'ids');
            return;
        }

        if (count($ids) > 100) {
            $this->respondWithError('VALIDATION_ERROR', 'Maximum 100 records per bulk action', 'ids');
            return;
        }

        $validActions = ['verify', 'reject', 'delete'];
        if (!in_array($action, $validActions, true)) {
            $this->respondWithError('VALIDATION_ERROR', 'Invalid action. Must be: verify, reject, or delete', 'action');
            return;
        }

        if ($action === 'reject' && empty($reason)) {
            $this->respondWithError('VALIDATION_ERROR', 'A reason is required for bulk rejection', 'reason');
            return;
        }

        $processed = 0;
        $failed = 0;

        foreach ($ids as $id) {
            $id = (int)$id;
            try {
                $existing = VettingService::getById($id);
                if (!$existing) {
                    $failed++;
                    continue;
                }

                switch ($action) {
                    case 'verify':
                        if (in_array($existing['status'], ['pending', 'submitted'])) {
                            VettingService::verify($id, $adminId);
                            $processed++;
                        } else {
                            $failed++;
                        }
                        break;
                    case 'reject':
                        VettingService::reject($id, $adminId, $reason);
                        $processed++;
                        break;
                    case 'delete':
                        VettingService::delete($id);
                        $processed++;
                        break;
                }
            } catch (\Exception $e) {
                $failed++;
            }
        }

        ActivityLog::log($adminId, "vetting_bulk_{$action}", "Bulk {$action}: {$processed} records processed, {$failed} failed", false, null, 'admin', 'vetting_record', null);

        $this->respondWithData([
            'action' => $action,
            'processed' => $processed,
            'failed' => $failed,
            'total' => count($ids),
        ]);
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

    /**
     * POST /api/v2/admin/vetting/{id}/upload
     *
     * Upload a document (PDF/image) for a vetting record.
     * Expects multipart/form-data with 'file' field.
     */
    public function uploadDocument(int $id): void
    {
        $this->requireAdmin();

        try {
            $existing = VettingService::getById($id);
            if (!$existing) {
                $this->respondWithError('NOT_FOUND', 'Vetting record not found', null, 404);
                return;
            }

            $file = $_FILES['file'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                $this->respondWithError('VALIDATION_ERROR', 'No file was uploaded or upload failed', 'file');
                return;
            }

            // Validate file type
            $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            if (!in_array($mimeType, $allowedMimes, true)) {
                $this->respondWithError('VALIDATION_ERROR', 'Only PDF, JPEG, PNG, and WebP files are allowed', 'file');
                return;
            }

            // 10 MB limit
            if ($file['size'] > 10 * 1024 * 1024) {
                $this->respondWithError('VALIDATION_ERROR', 'File size must be under 10 MB', 'file');
                return;
            }

            $url = ImageUploader::upload($file, 'vetting/documents');
            VettingService::updateDocumentUrl($id, $url);

            $adminId = $this->getAuthenticatedUserId();
            ActivityLog::log($adminId, 'vetting_document_uploaded', "Uploaded document for vetting record #{$id} ({$existing['first_name']} {$existing['last_name']})", false, null, 'admin', 'vetting_record', $id);

            $record = VettingService::getById($id);
            $this->respondWithData($record);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to upload document', null, 500);
            return;
        }
    }
}
