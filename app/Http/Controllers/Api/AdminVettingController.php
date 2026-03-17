<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Nexus\Core\TenantContext;
use Nexus\Models\ActivityLog;
use Nexus\Services\VettingService;

/**
 * AdminVettingController -- Admin member vetting and background check management.
 *
 * All methods require admin authentication.
 * All methods are native Laravel — no legacy delegation remains.
 */
class AdminVettingController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    /** GET /api/v2/admin/vetting */
    public function list(): JsonResponse
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

            return $this->respondWithPaginatedCollection(
                $result['data'],
                $result['pagination']['total'],
                $result['pagination']['page'],
                $result['pagination']['per_page']
            );
        } catch (\Exception $e) {
            return $this->respondWithPaginatedCollection([], 0, 1, 25);
        }
    }

    /** GET /api/v2/admin/vetting/stats */
    public function stats(): JsonResponse
    {
        $this->requireAdmin();
        return $this->respondWithData(VettingService::getStats());
    }

    /** GET /api/v2/admin/vetting/{id} */
    public function show(int $id): JsonResponse
    {
        $this->requireAdmin();

        try {
            $record = VettingService::getById($id);
            if (!$record) {
                return $this->respondWithError('NOT_FOUND', 'Vetting record not found', null, 404);
            }
            return $this->respondWithData($record);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to fetch vetting record', null, 500);
        }
    }

    /** POST /api/v2/admin/vetting */
    public function store(): JsonResponse
    {
        $adminId = $this->requireAdmin();

        $userId = $this->inputInt('user_id');
        $vettingType = $this->input('vetting_type');

        if (!$userId) {
            return $this->respondWithError('VALIDATION_ERROR', 'user_id is required', 'user_id');
        }

        $validTypes = ['dbs_basic', 'dbs_standard', 'dbs_enhanced', 'garda_vetting',
                        'access_ni', 'pvg_scotland', 'international', 'other'];
        if ($vettingType && !in_array($vettingType, $validTypes, true)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Invalid vetting type', 'vetting_type');
        }

        $validStatuses = ['pending', 'submitted', 'verified', 'expired', 'rejected', 'revoked'];
        $status = $this->input('status', 'pending');
        if (!in_array($status, $validStatuses, true)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Invalid status', 'status');
        }

        // Verify user exists in current tenant
        $tenantId = $this->getTenantId();
        $userExists = DB::selectOne("SELECT id FROM users WHERE id = ? AND tenant_id = ?", [$userId, $tenantId]);
        if (!$userExists) {
            return $this->respondWithError('VALIDATION_ERROR', 'User not found in this tenant', 'user_id');
        }

        // Validate dates
        $issueDate = $this->input('issue_date');
        $expiryDate = $this->input('expiry_date');
        if ($issueDate && !strtotime($issueDate)) return $this->respondWithError('VALIDATION_ERROR', 'Invalid issue date format', 'issue_date');
        if ($expiryDate && !strtotime($expiryDate)) return $this->respondWithError('VALIDATION_ERROR', 'Invalid expiry date format', 'expiry_date');
        if ($issueDate && $expiryDate && strtotime($expiryDate) < strtotime($issueDate)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Expiry date must be after issue date', 'expiry_date');
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

            $id = VettingService::create($data);
            ActivityLog::log($adminId, 'vetting_record_created', "Created vetting record #{$id} for user #{$userId} ({$data['vetting_type']})", false, null, 'admin', 'vetting_record', $id);

            $record = VettingService::getById($id);
            return $this->respondWithData($record, null, 201);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to create vetting record', null, 500);
        }
    }

    /** PUT /api/v2/admin/vetting/{id} */
    public function update(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();

        try {
            $existing = VettingService::getById($id);
            if (!$existing) {
                return $this->respondWithError('NOT_FOUND', 'Vetting record not found', null, 404);
            }

            $allInput = $this->getAllInput();

            $validTypes = ['dbs_basic', 'dbs_standard', 'dbs_enhanced', 'garda_vetting',
                            'access_ni', 'pvg_scotland', 'international', 'other'];
            $validStatuses = ['pending', 'submitted', 'verified', 'expired', 'rejected', 'revoked'];

            if (array_key_exists('vetting_type', $allInput) && $allInput['vetting_type'] !== null
                && !in_array($allInput['vetting_type'], $validTypes, true)) {
                return $this->respondWithError('VALIDATION_ERROR', 'Invalid vetting type', 'vetting_type');
            }
            if (array_key_exists('status', $allInput) && $allInput['status'] !== null
                && !in_array($allInput['status'], $validStatuses, true)) {
                return $this->respondWithError('VALIDATION_ERROR', 'Invalid status', 'status');
            }

            // Validate dates
            if (array_key_exists('issue_date', $allInput) && $allInput['issue_date'] !== null && !strtotime($allInput['issue_date'])) {
                return $this->respondWithError('VALIDATION_ERROR', 'Invalid issue date format', 'issue_date');
            }
            if (array_key_exists('expiry_date', $allInput) && $allInput['expiry_date'] !== null && !strtotime($allInput['expiry_date'])) {
                return $this->respondWithError('VALIDATION_ERROR', 'Invalid expiry date format', 'expiry_date');
            }
            $effectiveIssue = $allInput['issue_date'] ?? $existing['issue_date'] ?? null;
            $effectiveExpiry = $allInput['expiry_date'] ?? $existing['expiry_date'] ?? null;
            if ($effectiveIssue && $effectiveExpiry && strtotime($effectiveExpiry) < strtotime($effectiveIssue)) {
                return $this->respondWithError('VALIDATION_ERROR', 'Expiry date must be after issue date', 'expiry_date');
            }

            $data = [];
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
                return $this->respondWithError('VALIDATION_ERROR', 'No valid fields to update');
            }

            VettingService::update($id, $data);
            $changedFields = implode(', ', array_keys($data));
            ActivityLog::log($adminId, 'vetting_record_updated', "Updated vetting record #{$id} ({$changedFields})", false, null, 'admin', 'vetting_record', $id);

            $record = VettingService::getById($id);
            return $this->respondWithData($record);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to update vetting record', null, 500);
        }
    }

    /** POST /api/v2/admin/vetting/{id}/verify */
    public function verify(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();

        try {
            $existing = VettingService::getById($id);
            if (!$existing) {
                return $this->respondWithError('NOT_FOUND', 'Vetting record not found', null, 404);
            }
            if ($existing['status'] === 'verified') {
                return $this->respondWithError('INVALID_STATUS', 'Record is already verified');
            }

            VettingService::verify($id, $adminId);
            ActivityLog::log($adminId, 'vetting_record_verified', "Verified vetting record #{$id} for {$existing['first_name']} {$existing['last_name']}", false, null, 'admin', 'vetting_record', $id);

            $record = VettingService::getById($id);
            return $this->respondWithData($record);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to verify vetting record', null, 500);
        }
    }

    /** POST /api/v2/admin/vetting/{id}/reject */
    public function reject(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $reason = $this->input('reason', '');

        if (empty($reason)) {
            return $this->respondWithError('VALIDATION_ERROR', 'A reason is required to reject a vetting record', 'reason');
        }

        try {
            $existing = VettingService::getById($id);
            if (!$existing) {
                return $this->respondWithError('NOT_FOUND', 'Vetting record not found', null, 404);
            }

            VettingService::reject($id, $adminId, $reason);
            ActivityLog::log($adminId, 'vetting_record_rejected', "Rejected vetting record #{$id}: {$reason}", false, null, 'admin', 'vetting_record', $id);

            $record = VettingService::getById($id);
            return $this->respondWithData($record);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to reject vetting record', null, 500);
        }
    }

    /** DELETE /api/v2/admin/vetting/{id} */
    public function destroy(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();

        try {
            $existing = VettingService::getById($id);
            if (!$existing) {
                return $this->respondWithError('NOT_FOUND', 'Vetting record not found', null, 404);
            }

            VettingService::delete($id);
            ActivityLog::log($adminId, 'vetting_record_deleted', "Deleted vetting record #{$id} ({$existing['vetting_type']})", false, null, 'admin', 'vetting_record', $id);

            return $this->respondWithData(['deleted' => true]);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to delete vetting record', null, 500);
        }
    }

    /** POST /api/v2/admin/vetting/bulk */
    public function bulk(): JsonResponse
    {
        $adminId = $this->requireAdmin();

        $ids = $this->input('ids');
        $action = $this->input('action');
        $reason = $this->input('reason', '');

        if (!is_array($ids) || empty($ids)) {
            return $this->respondWithError('VALIDATION_ERROR', 'ids must be a non-empty array', 'ids');
        }
        if (count($ids) > 100) {
            return $this->respondWithError('VALIDATION_ERROR', 'Maximum 100 records per bulk action', 'ids');
        }
        if (!in_array($action, ['verify', 'reject', 'delete'], true)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Invalid action. Must be: verify, reject, or delete', 'action');
        }
        if ($action === 'reject' && empty($reason)) {
            return $this->respondWithError('VALIDATION_ERROR', 'A reason is required for bulk rejection', 'reason');
        }

        $processed = 0;
        $failed = 0;

        foreach ($ids as $id) {
            $id = (int) $id;
            try {
                $existing = VettingService::getById($id);
                if (!$existing) { $failed++; continue; }

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

        return $this->respondWithData([
            'action' => $action,
            'processed' => $processed,
            'failed' => $failed,
            'total' => count($ids),
        ]);
    }

    /** GET /api/v2/admin/vetting/user/{userId} */
    public function getUserRecords(int $userId): JsonResponse
    {
        $this->requireAdmin();
        try {
            $records = VettingService::getUserRecords($userId);
            return $this->respondWithData($records);
        } catch (\Exception $e) {
            return $this->respondWithData([]);
        }
    }

    /**
     * POST /api/v2/admin/vetting/{id}/document
     *
     * Upload a supporting document for a vetting record. Uses request()->file() (Laravel native).
     * Field name: 'file'. Allowed: PDF, JPEG, PNG, WebP. Max 10MB.
     */
    public function uploadDocument(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();

        try {
            $existing = VettingService::getById($id);
            if (!$existing) {
                return $this->respondWithError('NOT_FOUND', 'Vetting record not found', null, 404);
            }

            $file = request()->file('file');
            if (!$file || !$file->isValid()) {
                return $this->respondWithError('VALIDATION_ERROR', 'No file was uploaded or upload failed', 'file');
            }

            // Validate file type using file content
            $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file->getRealPath());
            if (!in_array($mimeType, $allowedMimes, true)) {
                return $this->respondWithError('VALIDATION_ERROR', 'Only PDF, JPEG, PNG, and WebP files are allowed', 'file');
            }

            // 10 MB limit
            if ($file->getSize() > 10 * 1024 * 1024) {
                return $this->respondWithError('VALIDATION_ERROR', 'File size must be under 10 MB', 'file');
            }

            // Build a $_FILES-compatible array for ImageUploader::upload()
            $fileArray = [
                'name'     => $file->getClientOriginalName(),
                'type'     => $mimeType,
                'tmp_name' => $file->getRealPath(),
                'error'    => UPLOAD_ERR_OK,
                'size'     => $file->getSize(),
            ];

            $url = \Nexus\Core\ImageUploader::upload($fileArray, 'vetting/documents');
            VettingService::updateDocumentUrl($id, $url);

            ActivityLog::log($adminId, 'vetting_document_uploaded', "Uploaded document for vetting record #{$id} ({$existing['first_name']} {$existing['last_name']})", false, null, 'admin', 'vetting_record', $id);

            $record = VettingService::getById($id);
            return $this->respondWithData($record);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to upload document', null, 500);
        }
    }
}
