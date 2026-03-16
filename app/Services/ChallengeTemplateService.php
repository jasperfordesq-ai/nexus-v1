<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * ChallengeTemplateService — Laravel DI wrapper for legacy \Nexus\Services\ChallengeTemplateService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class ChallengeTemplateService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy ChallengeTemplateService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\ChallengeTemplateService::getErrors();
    }

    /**
     * Delegates to legacy ChallengeTemplateService::getAll().
     */
    public function getAll(): array
    {
        return \Nexus\Services\ChallengeTemplateService::getAll();
    }

    /**
     * Delegates to legacy ChallengeTemplateService::getById().
     */
    public function getById(int $id): ?array
    {
        return \Nexus\Services\ChallengeTemplateService::getById($id);
    }

    /**
     * Delegates to legacy ChallengeTemplateService::create().
     */
    public function create(int $userId, array $data): ?int
    {
        return \Nexus\Services\ChallengeTemplateService::create($userId, $data);
    }

    /**
     * Delegates to legacy ChallengeTemplateService::update().
     */
    public function update(int $id, int $userId, array $data): bool
    {
        return \Nexus\Services\ChallengeTemplateService::update($id, $userId, $data);
    }
}
