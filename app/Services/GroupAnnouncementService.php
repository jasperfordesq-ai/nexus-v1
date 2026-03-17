<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * GroupAnnouncementService — Laravel DI wrapper for legacy \Nexus\Services\GroupAnnouncementService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class GroupAnnouncementService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy GroupAnnouncementService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\GroupAnnouncementService::getErrors();
    }

    /**
     * Delegates to legacy GroupAnnouncementService::list().
     */
    public function list(int $groupId, int $userId, array $filters = []): ?array
    {
        return \Nexus\Services\GroupAnnouncementService::list($groupId, $userId, $filters);
    }

    /**
     * Delegates to legacy GroupAnnouncementService::getById().
     */
    public function getById(int $groupId, int $announcementId, int $userId): ?array
    {
        return \Nexus\Services\GroupAnnouncementService::getById($groupId, $announcementId, $userId);
    }

    /**
     * Delegates to legacy GroupAnnouncementService::create().
     */
    public function create(int $groupId, int $userId, array $data): ?array
    {
        return \Nexus\Services\GroupAnnouncementService::create($groupId, $userId, $data);
    }

    /**
     * Delegates to legacy GroupAnnouncementService::update().
     */
    public function update(int $groupId, int $announcementId, int $userId, array $data): ?array
    {
        return \Nexus\Services\GroupAnnouncementService::update($groupId, $announcementId, $userId, $data);
    }

    /**
     * Delete a group announcement.
     *
     * Delegates to legacy GroupAnnouncementService::delete().
     */
    public function delete(int $groupId, int $announcementId, int $userId): bool
    {
        return \Nexus\Services\GroupAnnouncementService::delete($groupId, $announcementId, $userId);
    }
}
