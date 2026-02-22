<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Auth;
use Nexus\Core\TenantContext;
use Nexus\Services\InsuranceCertificateService;
use Nexus\Services\UploadService;

/**
 * UserInsuranceApiController - User-facing insurance certificate endpoints
 *
 * Allows members to view and upload their own insurance certificates.
 *
 * Endpoints:
 * - GET  /api/v2/users/me/insurance  - List own certificates
 * - POST /api/v2/users/me/insurance  - Upload new certificate
 */
class UserInsuranceApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/users/me/insurance
     */
    public function list(): void
    {
        $userId = $this->requireAuth();

        try {
            $records = InsuranceCertificateService::getUserCertificates($userId);
            $this->respondWithData($records);
        } catch (\Exception $e) {
            $this->respondWithData([]);
        }
    }

    /**
     * POST /api/v2/users/me/insurance
     *
     * Upload a new insurance certificate. Accepts multipart/form-data with:
     * - certificate_file (required): PDF, JPG, or PNG, max 10MB
     * - insurance_type: public_liability, professional_indemnity, etc.
     * - provider_name, policy_number, coverage_amount, start_date, expiry_date, notes
     */
    public function upload(): void
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        $validTypes = ['public_liability', 'professional_indemnity', 'employers_liability',
                        'product_liability', 'personal_accident', 'other'];

        $insuranceType = $_POST['insurance_type'] ?? 'public_liability';
        if (!in_array($insuranceType, $validTypes, true)) {
            $this->respondWithError('VALIDATION_ERROR', 'Invalid insurance type', 'insurance_type');
            return;
        }

        // Handle file upload
        $filePath = null;
        if (!empty($_FILES['certificate_file']) && $_FILES['certificate_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['certificate_file'];

            // Validate file type
            $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedMimes, true)) {
                $this->respondWithError('VALIDATION_ERROR', 'Only PDF, JPG, and PNG files are accepted', 'certificate_file');
                return;
            }

            // Validate file size (10MB max)
            if ($file['size'] > 10 * 1024 * 1024) {
                $this->respondWithError('VALIDATION_ERROR', 'File must be under 10MB', 'certificate_file');
                return;
            }

            // Store file
            $uploadDir = "uploads/insurance/{$tenantId}/{$userId}";
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'cert_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            if (!move_uploaded_file($file['tmp_name'], "{$uploadDir}/{$filename}")) {
                $this->respondWithError('SERVER_ERROR', 'Failed to save uploaded file', null, 500);
                return;
            }

            $filePath = "{$uploadDir}/{$filename}";
        }

        try {
            $data = [
                'user_id' => $userId,
                'insurance_type' => $insuranceType,
                'provider_name' => $_POST['provider_name'] ?? null,
                'policy_number' => $_POST['policy_number'] ?? null,
                'coverage_amount' => !empty($_POST['coverage_amount']) ? (float)$_POST['coverage_amount'] : null,
                'start_date' => $_POST['start_date'] ?? null,
                'expiry_date' => $_POST['expiry_date'] ?? null,
                'certificate_file_path' => $filePath,
                'status' => 'submitted',
                'notes' => $_POST['notes'] ?? null,
            ];

            $id = InsuranceCertificateService::create($data);
            $record = InsuranceCertificateService::getById($id);

            $this->respondWithData($record, null, 201);
        } catch (\Exception $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to upload insurance certificate', null, 500);
        }
    }
}
