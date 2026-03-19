<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * ChallengeTagService — Laravel DI wrapper for legacy \Nexus\Services\ChallengeTagService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class ChallengeTagService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy ChallengeTagService::getErrors().
     */
    public function getErrors(): array
    {
        if (!class_exists('\Nexus\Services\ChallengeTagService')) { return []; }
        return \Nexus\Services\ChallengeTagService::getErrors();
    }

    /**
     * Delegates to legacy ChallengeTagService::getAll().
     */
    public function getAll(?string $tagType = null): array
    {
        if (!class_exists('\Nexus\Services\ChallengeTagService')) { return []; }
        return \Nexus\Services\ChallengeTagService::getAll($tagType);
    }

    /**
     * Delegates to legacy ChallengeTagService::getById().
     */
    public function getById(int $id): ?array
    {
        if (!class_exists('\Nexus\Services\ChallengeTagService')) { return null; }
        return \Nexus\Services\ChallengeTagService::getById($id);
    }

    /**
     * Delegates to legacy ChallengeTagService::create().
     */
    public function create(int $userId, array $data): ?int
    {
        if (!class_exists('\Nexus\Services\ChallengeTagService')) { return null; }
        return \Nexus\Services\ChallengeTagService::create($userId, $data);
    }

    /**
     * Delegates to legacy ChallengeTagService::delete().
     */
    public function delete(int $id, int $userId): bool
    {
        if (!class_exists('\Nexus\Services\ChallengeTagService')) { return false; }
        return \Nexus\Services\ChallengeTagService::delete($id, $userId);
    }
}
