<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\Enterprise;

/**
 * GdprService — Thin delegate forwarding to \App\Services\Enterprise\GdprService.
 *
 * The full implementation now lives in the App namespace.
 * This file exists for backwards compatibility only.
 *
 * @see \App\Services\Enterprise\GdprService
 */
class GdprService
{
    private ?\App\Services\Enterprise\GdprService $delegate = null;
    private ?int $tenantId;

    public function __construct(?int $tenantId = null)
    {
        $this->tenantId = $tenantId;
    }

    private function app(): \App\Services\Enterprise\GdprService
    {
        if ($this->delegate === null) {
            $this->delegate = new \App\Services\Enterprise\GdprService($this->tenantId);
        }
        return $this->delegate;
    }

    public function createRequest(int $userId, string $type, array $options = []): array
    {
        return $this->app()->createRequest($userId, $type, $options);
    }

    public function getRequest(int $requestId): ?array
    {
        return $this->app()->getRequest($requestId);
    }

    public function getPendingRequests(int $limit = 50, int $offset = 0): array
    {
        return $this->app()->getPendingRequests($limit, $offset);
    }

    public function getUserRequests(int $userId): array
    {
        return $this->app()->getUserRequests($userId);
    }

    public function processRequest(int $requestId, int $adminId): bool
    {
        return $this->app()->processRequest($requestId, $adminId);
    }

    public function generateDataExport(int $userId, int $requestId = null): string
    {
        return $this->app()->generateDataExport($userId, $requestId);
    }

    public function executeAccountDeletion(int $userId, ?int $adminId = null, ?int $requestId = null): void
    {
        $this->app()->executeAccountDeletion($userId, $adminId, $requestId);
    }

    public function recordConsent(int $userId, string $consentType, bool $consented, string $consentText, string $version): array
    {
        return $this->app()->recordConsent($userId, $consentType, $consented, $consentText, $version);
    }

    public function withdrawConsent(int $userId, string $consentType): bool
    {
        return $this->app()->withdrawConsent($userId, $consentType);
    }

    public function getUserConsents(int $userId): array
    {
        return $this->app()->getUserConsents($userId);
    }

    public function hasConsent(int $userId, string $consentType): bool
    {
        return $this->app()->hasConsent($userId, $consentType);
    }

    public function hasCurrentVersionConsent(int $userId, string $consentType): bool
    {
        return $this->app()->hasCurrentVersionConsent($userId, $consentType);
    }

    public function getOutdatedRequiredConsents(int $userId): array
    {
        return $this->app()->getOutdatedRequiredConsents($userId);
    }

    public function needsReConsent(int $userId): bool
    {
        return $this->app()->needsReConsent($userId);
    }

    public function acceptMultipleConsents(int $userId, array $consentSlugs): array
    {
        return $this->app()->acceptMultipleConsents($userId, $consentSlugs);
    }

    public function backfillConsentsForExistingUsers(string $consentType, string $version, string $consentText): int
    {
        return $this->app()->backfillConsentsForExistingUsers($consentType, $version, $consentText);
    }

    public function getEffectiveConsentVersion(string $consentSlug): ?array
    {
        return $this->app()->getEffectiveConsentVersion($consentSlug);
    }

    public function setTenantConsentVersion(string $consentSlug, string $version, ?string $text = null): bool
    {
        return $this->app()->setTenantConsentVersion($consentSlug, $version, $text);
    }

    public function removeTenantConsentOverride(string $consentSlug): bool
    {
        return $this->app()->removeTenantConsentOverride($consentSlug);
    }

    public function getTenantConsentOverrides(): array
    {
        return $this->app()->getTenantConsentOverrides();
    }

    public function getConsentTypes(): array
    {
        return $this->app()->getConsentTypes();
    }

    public function getActiveConsentTypes(): array
    {
        return $this->app()->getActiveConsentTypes();
    }

    public function updateUserConsent(int $userId, string $slug, bool $given): array
    {
        return $this->app()->updateUserConsent($userId, $slug, $given);
    }

    public function reportBreach(array $data, int $reportedBy): int
    {
        return $this->app()->reportBreach($data, $reportedBy);
    }

    public function getBreachDeadline(int $breachLogId): \DateTime
    {
        return $this->app()->getBreachDeadline($breachLogId);
    }

    public function logAction(int $userId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?int $adminId = null,
        $oldValue = null,
        $newValue = null): void
    {
        $this->app()->logAction($userId, $action, $entityType, $entityId, $adminId, $oldValue, $newValue);
    }

    public function getAuditLog(int $userId, int $limit = 100): array
    {
        return $this->app()->getAuditLog($userId, $limit);
    }

    public function getStatistics(): array
    {
        return $this->app()->getStatistics();
    }
}
