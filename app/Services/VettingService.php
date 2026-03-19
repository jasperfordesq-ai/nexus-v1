<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * VettingService — Laravel DI wrapper for legacy \Nexus\Services\VettingService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class VettingService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy VettingService::getUserRecords().
     */
    public function getUserRecords(int $userId): array
    {
        return \Nexus\Services\VettingService::getUserRecords($userId);
    }

    /**
     * Delegates to legacy VettingService::getById().
     */
    public function getById(int $id): ?array
    {
        return \Nexus\Services\VettingService::getById($id);
    }

    /**
     * Delegates to legacy VettingService::getAll().
     */
    public function getAll(array $filters = []): array
    {
        return \Nexus\Services\VettingService::getAll($filters);
    }

    /**
     * Delegates to legacy VettingService::getStats().
     */
    public function getStats(): array
    {
        return \Nexus\Services\VettingService::getStats();
    }

    /**
     * Delegates to legacy VettingService::create().
     */
    public function create(array $data): int
    {
        return \Nexus\Services\VettingService::create($data);
    }

    /**
     * Delegates to legacy VettingService::update().
     */
    public function update(int $id, array $data): bool
    {
        return \Nexus\Services\VettingService::update($id, $data);
    }

    /**
     * Delegates to legacy VettingService::verify().
     */
    public function verify(int $id, int $adminId): bool
    {
        return \Nexus\Services\VettingService::verify($id, $adminId);
    }

    /**
     * Delegates to legacy VettingService::reject().
     */
    public function reject(int $id, int $adminId, string $reason): bool
    {
        return \Nexus\Services\VettingService::reject($id, $adminId, $reason);
    }

    /**
     * Delegates to legacy VettingService::delete().
     */
    public function delete(int $id): bool
    {
        return \Nexus\Services\VettingService::delete($id);
    }

    /**
     * Delegates to legacy VettingService::updateDocumentUrl().
     */
    public function updateDocumentUrl(int $id, string $url): bool
    {
        return \Nexus\Services\VettingService::updateDocumentUrl($id, $url);
    }
}
