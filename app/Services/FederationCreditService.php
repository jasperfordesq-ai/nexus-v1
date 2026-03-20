<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class FederationCreditService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy FederationCreditService::getErrors().
     */
    public function getErrors(): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Static proxy: list agreements for a tenant.
     */
    public static function listAgreementsStatic(int $tenantId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederationCreditService::createAgreement().
     */
    public function createAgreement(int $fromTenantId, int $toTenantId, float $exchangeRate = 1.0, ?float $maxMonthlyCredits = null, int $approvedBy = 0): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Static proxy: create agreement.
     */
    public static function createAgreementStatic(int $fromTenantId, int $toTenantId, float $exchangeRate = 1.0, ?float $maxMonthlyCredits = null, int $approvedBy = 0): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederationCreditService::approveAgreement().
     */
    public function approveAgreement(int $agreementId, int $approvedBy): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederationCreditService::updateAgreementStatus().
     */
    public function updateAgreementStatus(int $agreementId, string $status): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederationCreditService::getAgreement().
     */
    public function getAgreement(int $tenantA, int $tenantB): ?array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }
}
