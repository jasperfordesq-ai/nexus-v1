<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\Enterprise;

use Nexus\Services\Enterprise\GdprService as LegacyGdprService;

/**
 * GdprService — Laravel DI wrapper for legacy \Nexus\Services\Enterprise\GdprService.
 *
 * Delegates to the legacy instance-based GdprService. The legacy service uses
 * constructor injection for tenantId, so we instantiate it on demand.
 */
class GdprService
{
    private ?LegacyGdprService $legacy = null;

    public function __construct(private ?int $tenantId = null)
    {
    }

    /**
     * Get the underlying legacy service instance.
     */
    private function legacy(): LegacyGdprService
    {
        if ($this->legacy === null) {
            $this->legacy = new LegacyGdprService($this->tenantId);
        }
        return $this->legacy;
    }

    // =========================================================================
    // DATA SUBJECT REQUESTS
    // =========================================================================

    /**
     * Create a new GDPR data subject request.
     */
    public function createRequest(int $userId, string $type, array $options = []): array
    {
        return $this->legacy()->createRequest($userId, $type, $options);
    }

    /**
     * Get a GDPR request by ID.
     */
    public function getRequest(int $requestId): ?array
    {
        return $this->legacy()->getRequest($requestId);
    }

    /**
     * Get pending GDPR requests.
     */
    public function getPendingRequests(int $limit = 50, int $offset = 0): array
    {
        return $this->legacy()->getPendingRequests($limit, $offset);
    }

    /**
     * Get all GDPR requests for a user.
     */
    public function getUserRequests(int $userId): array
    {
        return $this->legacy()->getUserRequests($userId);
    }

    /**
     * Process a GDPR request (admin action).
     */
    public function processRequest(int $requestId, int $adminId): bool
    {
        return $this->legacy()->processRequest($requestId, $adminId);
    }

    /**
     * Generate a data export ZIP for a user.
     */
    public function generateDataExport(int $userId, int $requestId = null): string
    {
        return $this->legacy()->generateDataExport($userId, $requestId);
    }

    /**
     * Execute account deletion for a user.
     */
    public function executeAccountDeletion(int $userId, ?int $adminId = null, ?int $requestId = null): void
    {
        $this->legacy()->executeAccountDeletion($userId, $adminId, $requestId);
    }

    // =========================================================================
    // CONSENT MANAGEMENT
    // =========================================================================

    /**
     * Record consent for a user.
     */
    public function recordConsent(int $userId, string $consentType, bool $consented, string $consentText, string $version): array
    {
        return $this->legacy()->recordConsent($userId, $consentType, $consented, $consentText, $version);
    }

    /**
     * Withdraw consent for a user.
     */
    public function withdrawConsent(int $userId, string $consentType): bool
    {
        return $this->legacy()->withdrawConsent($userId, $consentType);
    }

    /**
     * Get all consents for a user.
     */
    public function getUserConsents(int $userId): array
    {
        return $this->legacy()->getUserConsents($userId);
    }

    /**
     * Check if a user has active consent of a given type.
     */
    public function hasConsent(int $userId, string $consentType): bool
    {
        return $this->legacy()->hasConsent($userId, $consentType);
    }

    /**
     * Check if a user has the current version of consent.
     */
    public function hasCurrentVersionConsent(int $userId, string $consentType): bool
    {
        return $this->legacy()->hasCurrentVersionConsent($userId, $consentType);
    }

    /**
     * Get outdated required consents for a user.
     */
    public function getOutdatedRequiredConsents(int $userId): array
    {
        return $this->legacy()->getOutdatedRequiredConsents($userId);
    }

    /**
     * Check if a user needs to re-consent to any required consents.
     */
    public function needsReConsent(int $userId): bool
    {
        return $this->legacy()->needsReConsent($userId);
    }

    /**
     * Accept multiple consents at once.
     */
    public function acceptMultipleConsents(int $userId, array $consentSlugs): array
    {
        return $this->legacy()->acceptMultipleConsents($userId, $consentSlugs);
    }

    /**
     * Backfill consents for existing users.
     */
    public function backfillConsentsForExistingUsers(string $consentType, string $version, string $consentText): int
    {
        return $this->legacy()->backfillConsentsForExistingUsers($consentType, $version, $consentText);
    }

    /**
     * Get effective consent version (tenant override or global).
     */
    public function getEffectiveConsentVersion(string $consentSlug): ?array
    {
        return $this->legacy()->getEffectiveConsentVersion($consentSlug);
    }

    /**
     * Set tenant-specific consent version override.
     */
    public function setTenantConsentVersion(string $consentSlug, string $version, ?string $text = null): bool
    {
        return $this->legacy()->setTenantConsentVersion($consentSlug, $version, $text);
    }

    /**
     * Remove tenant consent version override.
     */
    public function removeTenantConsentOverride(string $consentSlug): bool
    {
        return $this->legacy()->removeTenantConsentOverride($consentSlug);
    }

    /**
     * Get all tenant consent overrides.
     */
    public function getTenantConsentOverrides(): array
    {
        return $this->legacy()->getTenantConsentOverrides();
    }

    /**
     * Get consent types.
     */
    public function getConsentTypes(): array
    {
        return $this->legacy()->getConsentTypes();
    }

    /**
     * Get active consent types.
     */
    public function getActiveConsentTypes(): array
    {
        return $this->legacy()->getActiveConsentTypes();
    }

    /**
     * Update a user's consent for a specific slug.
     */
    public function updateUserConsent(int $userId, string $slug, bool $given): array
    {
        return $this->legacy()->updateUserConsent($userId, $slug, $given);
    }

    // =========================================================================
    // DATA BREACH
    // =========================================================================

    /**
     * Report a data breach.
     */
    public function reportBreach(array $data, int $reportedBy): int
    {
        return $this->legacy()->reportBreach($data, $reportedBy);
    }

    /**
     * Get breach notification deadline.
     */
    public function getBreachDeadline(int $breachLogId): \DateTime
    {
        return $this->legacy()->getBreachDeadline($breachLogId);
    }

    // =========================================================================
    // AUDIT
    // =========================================================================

    /**
     * Log a GDPR action.
     */
    public function logAction(
        string $actionType,
        int $userId,
        ?int $adminId = null,
        ?string $details = null,
        ?string $ipAddress = null
    ): void {
        $this->legacy()->logAction($actionType, $userId, $adminId, $details, $ipAddress);
    }

    /**
     * Get GDPR audit log for a user.
     */
    public function getAuditLog(int $userId, int $limit = 100): array
    {
        return $this->legacy()->getAuditLog($userId, $limit);
    }

    /**
     * Get GDPR statistics for the tenant.
     */
    public function getStatistics(): array
    {
        return $this->legacy()->getStatistics();
    }
}
