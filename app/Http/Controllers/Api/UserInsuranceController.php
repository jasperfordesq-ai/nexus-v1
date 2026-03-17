<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Nexus\Core\TenantContext;
use Nexus\Services\InsuranceCertificateService;

/**
 * UserInsuranceController -- User insurance certificate upload and listing.
 *
 * All methods are native Laravel — no legacy delegation remains.
 *
 * Endpoints:
 *   GET  /api/v2/users/me/insurance  list()
 *   POST /api/v2/users/me/insurance  upload()  (delegation — file upload)
 */
class UserInsuranceController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/users/me/insurance
     *
     * List the authenticated user's insurance certificates.
     */
    public function list(): JsonResponse
    {
        $userId = $this->requireAuth();

        try {
            $records = InsuranceCertificateService::getUserCertificates($userId);
            return $this->respondWithData($records);
        } catch (\Exception $e) {
            return $this->respondWithData([]);
        }
    }

    /**
     * POST /api/v2/users/me/insurance
     *
     * Upload a new insurance certificate. Uses request()->file() (Laravel native).
     * Field name: 'certificate_file'. Form fields: insurance_type, provider_name,
     * policy_number, coverage_amount, start_date, expiry_date, notes.
     */
    public function upload(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        $validTypes = ['public_liability', 'professional_indemnity', 'employers_liability',
                        'product_liability', 'personal_accident', 'other'];

        $insuranceType = request()->input('insurance_type', 'public_liability');
        if (!in_array($insuranceType, $validTypes, true)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Invalid insurance type', 'insurance_type');
        }

        // Handle file upload
        $filePath = null;
        $file = request()->file('certificate_file');
        if ($file && $file->isValid()) {
            // Validate MIME type using file content
            $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file->getRealPath());
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedMimes, true)) {
                return $this->respondWithError('VALIDATION_ERROR', 'Only PDF, JPG, and PNG files are accepted', 'certificate_file');
            }

            // Validate file size (10MB max)
            if ($file->getSize() > 10 * 1024 * 1024) {
                return $this->respondWithError('VALIDATION_ERROR', 'File must be under 10MB', 'certificate_file');
            }

            // Store file — derive extension from validated MIME type (not user filename)
            $uploadDir = "uploads/insurance/{$tenantId}/{$userId}";
            $extMap = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
            $ext = $extMap[$mimeType] ?? 'bin';
            $filename = 'cert_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $file->move($uploadDir, $filename);
            $filePath = "{$uploadDir}/{$filename}";
        }

        try {
            $data = [
                'user_id' => $userId,
                'insurance_type' => $insuranceType,
                'provider_name' => request()->input('provider_name'),
                'policy_number' => request()->input('policy_number'),
                'coverage_amount' => request()->input('coverage_amount') !== null && request()->input('coverage_amount') !== ''
                    ? (float) request()->input('coverage_amount') : null,
                'start_date' => request()->input('start_date'),
                'expiry_date' => request()->input('expiry_date'),
                'certificate_file_path' => $filePath,
                'status' => 'submitted',
                'notes' => request()->input('notes'),
            ];

            $id = InsuranceCertificateService::create($data);
            $record = InsuranceCertificateService::getById($id);

            return $this->respondWithData($record, null, 201);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to upload insurance certificate', null, 500);
        }
    }
}
