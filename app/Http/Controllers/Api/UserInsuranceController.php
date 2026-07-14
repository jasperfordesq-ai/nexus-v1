<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\InsuranceCertificateService;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

/** Metadata-only insurance records. Raw documents are never accepted. */
class UserInsuranceController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly InsuranceCertificateService $insuranceCertificateService,
    ) {}

    public function list(): JsonResponse
    {
        $userId = $this->requireAuth();

        try {
            return $this->respondWithData($this->insuranceCertificateService->getUserCertificates($userId));
        } catch (\Throwable) {
            return $this->respondWithData([]);
        }
    }

    /** Store only the type, optional provider, and required expiry date. */
    public function store(): JsonResponse
    {
        $userId = $this->requireAuth();

        if (request()->hasFile('certificate_file') || request()->has('certificate_file')) {
            return $this->respondWithError(
                'DOCUMENT_UPLOAD_FORBIDDEN',
                __('api.insurance_documents_forbidden'),
                'certificate_file',
                422,
            );
        }

        $validTypes = [
            'public_liability', 'professional_indemnity', 'employers_liability',
            'product_liability', 'personal_accident', 'other',
        ];
        $insuranceType = (string) request()->input('insurance_type', 'public_liability');
        if (! in_array($insuranceType, $validTypes, true)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_insurance_type'), 'insurance_type', 422);
        }

        $expiryDate = trim((string) request()->input('expiry_date', ''));
        $parsedExpiry = DateTimeImmutable::createFromFormat('!Y-m-d', $expiryDate);
        if ($parsedExpiry === false || $parsedExpiry->format('Y-m-d') !== $expiryDate) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.insurance_expiry_required'), 'expiry_date', 422);
        }

        try {
            $provider = mb_substr(trim((string) request()->input('provider_name', '')), 0, 255);
            $id = $this->insuranceCertificateService->create([
                'user_id' => $userId,
                'insurance_type' => $insuranceType,
                'provider_name' => $provider !== '' ? $provider : null,
                'expiry_date' => $expiryDate,
                'status' => 'submitted',
            ]);

            return $this->respondWithData($this->insuranceCertificateService->getById($id), null, 201);
        } catch (\Throwable) {
            return $this->respondWithError('SERVER_ERROR', __('api.insurance_record_failed'), null, 500);
        }
    }
}
