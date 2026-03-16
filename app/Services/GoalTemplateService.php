<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * GoalTemplateService — Laravel DI wrapper for legacy \Nexus\Services\GoalTemplateService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class GoalTemplateService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy GoalTemplateService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\GoalTemplateService::getErrors();
    }

    /**
     * Delegates to legacy GoalTemplateService::getAll().
     */
    public function getAll(array $filters = []): array
    {
        return \Nexus\Services\GoalTemplateService::getAll($filters);
    }

    /**
     * Delegates to legacy GoalTemplateService::getById().
     */
    public function getById(int $id): ?array
    {
        return \Nexus\Services\GoalTemplateService::getById($id);
    }

    /**
     * Delegates to legacy GoalTemplateService::create().
     */
    public function create(int $userId, array $data): ?int
    {
        return \Nexus\Services\GoalTemplateService::create($userId, $data);
    }

    /**
     * Delegates to legacy GoalTemplateService::createGoalFromTemplate().
     */
    public function createGoalFromTemplate(int $templateId, int $userId, array $overrides = []): ?int
    {
        return \Nexus\Services\GoalTemplateService::createGoalFromTemplate($templateId, $userId, $overrides);
    }
}
