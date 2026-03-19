<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * GroupFileService — Laravel DI wrapper for legacy \Nexus\Services\GroupFileService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class GroupFileService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy GroupFileService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\GroupFileService::getErrors();
    }

    /**
     * Delegates to legacy GroupFileService::listFiles().
     */
    public function listFiles(int $groupId, int $userId, array $filters = []): ?array
    {
        return \Nexus\Services\GroupFileService::listFiles($groupId, $userId, $filters);
    }

    /**
     * Delegates to legacy GroupFileService::upload().
     */
    public function upload(int $groupId, int $userId, array $file): ?array
    {
        return \Nexus\Services\GroupFileService::upload($groupId, $userId, $file);
    }

    /**
     * Delegates to legacy GroupFileService::delete().
     */
    public function delete(int $groupId, int $fileId, int $userId): bool
    {
        return \Nexus\Services\GroupFileService::delete($groupId, $fileId, $userId);
    }

    /**
     * Delegates to legacy GroupFileService::getFile().
     */
    public function getFile(int $groupId, int $fileId, int $userId): ?array
    {
        return \Nexus\Services\GroupFileService::getFile($groupId, $fileId, $userId);
    }
}
