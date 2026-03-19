<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * FederationCreditService — Laravel DI wrapper for legacy \Nexus\Services\FederationCreditService.
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
        return \Nexus\Services\FederationCreditService::getErrors();
    }

    /**
     * Delegates to legacy FederationCreditService::createAgreement().
     */
    public function createAgreement(int $fromTenantId, int $toTenantId, float $exchangeRate = 1.0, ?float $maxMonthlyCredits = null, int $approvedBy = 0): array
    {
        return \Nexus\Services\FederationCreditService::createAgreement($fromTenantId, $toTenantId, $exchangeRate, $maxMonthlyCredits, $approvedBy);
    }

    /**
     * Delegates to legacy FederationCreditService::approveAgreement().
     */
    public function approveAgreement(int $agreementId, int $approvedBy): array
    {
        return \Nexus\Services\FederationCreditService::approveAgreement($agreementId, $approvedBy);
    }

    /**
     * Delegates to legacy FederationCreditService::updateAgreementStatus().
     */
    public function updateAgreementStatus(int $agreementId, string $status): array
    {
        return \Nexus\Services\FederationCreditService::updateAgreementStatus($agreementId, $status);
    }

    /**
     * Delegates to legacy FederationCreditService::getAgreement().
     */
    public function getAgreement(int $tenantA, int $tenantB): ?array
    {
        return \Nexus\Services\FederationCreditService::getAgreement($tenantA, $tenantB);
    }
}
