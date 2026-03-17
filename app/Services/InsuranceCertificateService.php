<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * InsuranceCertificateService — Laravel DI wrapper for legacy \Nexus\Services\InsuranceCertificateService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class InsuranceCertificateService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy InsuranceCertificateService::getUserCertificates().
     */
    public function getUserCertificates(int $userId): array
    {
        return \Nexus\Services\InsuranceCertificateService::getUserCertificates($userId);
    }

    /**
     * Delegates to legacy InsuranceCertificateService::getById().
     */
    public function getById(int $id): ?array
    {
        return \Nexus\Services\InsuranceCertificateService::getById($id);
    }

    /**
     * Delegates to legacy InsuranceCertificateService::getAll().
     */
    public function getAll(array $filters = []): array
    {
        return \Nexus\Services\InsuranceCertificateService::getAll($filters);
    }

    /**
     * Delegates to legacy InsuranceCertificateService::getStats().
     */
    public function getStats(): array
    {
        return \Nexus\Services\InsuranceCertificateService::getStats();
    }

    /**
     * Delegates to legacy InsuranceCertificateService::create().
     */
    public function create(array $data): int
    {
        return \Nexus\Services\InsuranceCertificateService::create($data);
    }

    /**
     * Delegates to legacy InsuranceCertificateService::update().
     */
    public function update(int $id, array $data): bool
    {
        return \Nexus\Services\InsuranceCertificateService::update($id, $data);
    }

    /**
     * Delegates to legacy InsuranceCertificateService::verify().
     */
    public function verify(int $id, int $adminId): bool
    {
        return \Nexus\Services\InsuranceCertificateService::verify($id, $adminId);
    }

    /**
     * Delegates to legacy InsuranceCertificateService::reject().
     */
    public function reject(int $id, int $adminId, string $reason): bool
    {
        return \Nexus\Services\InsuranceCertificateService::reject($id, $adminId, $reason);
    }

    /**
     * Delegates to legacy InsuranceCertificateService::delete().
     */
    public function delete(int $id): bool
    {
        return \Nexus\Services\InsuranceCertificateService::delete($id);
    }
}
