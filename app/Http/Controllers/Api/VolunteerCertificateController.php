<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Services\VolunteerCertificateService;
use App\Core\TenantContext;

/**
 * VolunteerCertificateController -- Certificates, credentials, and verification.
 */
class VolunteerCertificateController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly VolunteerCertificateService $volunteerCertificateService,
    ) {}

    private function ensureFeature(): void
    {
        if (!TenantContext::hasFeature('volunteering')) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->respondWithError('FEATURE_DISABLED', 'Volunteering module is not enabled for this community', null, 403)
            );
        }
    }

    private function getErrorStatus(array $errors): int
    {
        foreach ($errors as $error) {
            $code = $error['code'] ?? '';
            if ($code === 'NOT_FOUND') return 404;
            if ($code === 'FORBIDDEN') return 403;
            if ($code === 'ALREADY_EXISTS') return 409;
            if ($code === 'FEATURE_DISABLED') return 403;
        }
        return 400;
    }

    // ========================================
    // CERTIFICATES
    // ========================================

    public function myCertificates(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_certificates_list', 30, 60);

        $certs = $this->volunteerCertificateService->getUserCertificates($userId);
        return $this->respondWithData($certs);
    }

    public function generateCertificate(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_certificate', 5, 60);

        $options = [];
        if ($this->inputInt('organization_id')) {
            $options['organization_id'] = $this->inputInt('organization_id');
        }

        $cert = $this->volunteerCertificateService->generate($userId, $options);

        if ($cert === null) {
            $errors = $this->volunteerCertificateService->getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }

        return $this->respondWithData($cert, null, 201);
    }

    public function verifyCertificate($code): JsonResponse
    {
        $this->rateLimit('volunteering_cert_verify', 60, 60);

        $cert = $this->volunteerCertificateService->verify($code);

        if ($cert === null) {
            return $this->respondWithError('NOT_FOUND', 'Certificate not found or invalid', null, 404);
        }

        return $this->respondWithData($cert);
    }

    /** Returns raw HTML for certificate printing/PDF -- not JSON */
    public function certificateHtml($code): Response|JsonResponse
    {
        $this->rateLimit('volunteering_cert_html', 10, 60);

        $html = $this->volunteerCertificateService->generateHtml($code);

        if ($html === null) {
            return $this->respondWithError('NOT_FOUND', 'Certificate not found', null, 404);
        }

        $this->volunteerCertificateService->markDownloaded($code);

        return response($html, 200)->header('Content-Type', 'text/html; charset=utf-8');
    }

    // ========================================
    // CREDENTIALS
    // ========================================

    public function myCredentials(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_credentials', 30, 60);

        $tenantId = TenantContext::getId();

        $credentials = DB::select(
            "SELECT id, credential_type, file_url, file_name, status, expires_at, created_at, updated_at
             FROM vol_credentials
             WHERE user_id = ? AND tenant_id = ?
             ORDER BY created_at DESC",
            [$userId, $tenantId]
        );

        $mapped = array_map(static function ($row): array {
            $type = (string) ($row->credential_type ?? '');
            $typeLabel = ucwords(str_replace('_', ' ', $type));

            return [
                'id' => (int) ($row->id ?? 0),
                'credential_type' => $type,
                'file_url' => $row->file_url ?? null,
                'file_name' => $row->file_name ?? null,
                'status' => $row->status ?? 'pending',
                'expires_at' => $row->expires_at ?? null,
                'created_at' => $row->created_at ?? null,
                'updated_at' => $row->updated_at ?? null,
                'type' => $type,
                'type_label' => $typeLabel,
                'document_name' => $row->file_name ?? null,
                'upload_date' => $row->created_at ?? null,
                'expiry_date' => $row->expires_at ?? null,
                'rejection_reason' => null,
            ];
        }, $credentials);

        return $this->respondWithData(['credentials' => $mapped]);
    }

    public function uploadCredential(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_credential_upload', 10, 60);

        $tenantId = TenantContext::getId();
        $type = trim((string) ($this->input('credential_type') ?? $this->input('type') ?? ''));
        $expiresAt = $this->input('expires_at') ?? $this->input('expiry_date');

        if (empty($type)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Credential type is required', 'credential_type');
        }

        // Support both Laravel UploadedFile and raw $_FILES
        $file = request()->file('file') ?? request()->file('document');
        $uploadedFile = null;

        if ($file) {
            // Laravel UploadedFile path
            $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($file->getMimeType(), $allowedMimes, true)) {
                return $this->respondWithError('VALIDATION_ERROR', 'Only PDF, JPEG, PNG, and WebP files are allowed', 'file');
            }
            if ($file->getSize() > 10 * 1024 * 1024) {
                return $this->respondWithError('VALIDATION_ERROR', 'File size must be under 10 MB', 'file');
            }
            // Build $_FILES-compatible array for ImageUploader
            $uploadedFile = [
                'tmp_name' => $file->getRealPath(),
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'type' => $file->getMimeType(),
                'error' => UPLOAD_ERR_OK,
            ];
        } else {
            // Fallback to raw $_FILES
            $uploadedFile = $_FILES['file'] ?? $_FILES['document'] ?? null;
            if (empty($uploadedFile) || !isset($uploadedFile['error']) || $uploadedFile['error'] !== UPLOAD_ERR_OK) {
                return $this->respondWithError('VALIDATION_ERROR', 'A credential file is required', 'file');
            }
            $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($uploadedFile['tmp_name']);
            if (!in_array($mimeType, $allowedMimes, true)) {
                return $this->respondWithError('VALIDATION_ERROR', 'Only PDF, JPEG, PNG, and WebP files are allowed', 'file');
            }
            if (($uploadedFile['size'] ?? 0) > 10 * 1024 * 1024) {
                return $this->respondWithError('VALIDATION_ERROR', 'File size must be under 10 MB', 'file');
            }
        }

        $fileUrl = \App\Core\ImageUploader::upload($uploadedFile, 'credentials');
        $fileName = $uploadedFile['name'] ?? null;

        DB::insert(
            "INSERT INTO vol_credentials (tenant_id, user_id, credential_type, file_url, file_name, status, expires_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW(), NOW())",
            [$tenantId, $userId, $type, $fileUrl, $fileName, $expiresAt ?: null]
        );

        return $this->respondWithData([
            'success' => true,
            'id' => (int) DB::getPdo()->lastInsertId(),
        ], null, 201);
    }

    public function deleteCredential($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_credential_delete', 10, 60);

        $tenantId = TenantContext::getId();
        $affected = DB::delete(
            "DELETE FROM vol_credentials WHERE id = ? AND user_id = ? AND tenant_id = ?",
            [(int) $id, $userId, $tenantId]
        );

        if ($affected === 0) {
            return $this->respondWithError('NOT_FOUND', 'Credential not found', null, 404);
        }

        return $this->respondWithData(['success' => true]);
    }
}
