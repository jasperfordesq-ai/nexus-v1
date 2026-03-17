<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * CampaignService — Laravel DI wrapper for legacy \Nexus\Services\CampaignService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class CampaignService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy CampaignService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\CampaignService::getErrors();
    }

    /**
     * Delegates to legacy CampaignService::getAll().
     */
    public function getAll(array $filters = []): array
    {
        return \Nexus\Services\CampaignService::getAll($filters);
    }

    /**
     * Delegates to legacy CampaignService::getById().
     */
    public function getById(int $id): ?array
    {
        return \Nexus\Services\CampaignService::getById($id);
    }

    /**
     * Delegates to legacy CampaignService::create().
     */
    public function create(int $userId, array $data): ?int
    {
        return \Nexus\Services\CampaignService::create($userId, $data);
    }

    /**
     * Delegates to legacy CampaignService::update().
     */
    public function update(int $id, int $userId, array $data): bool
    {
        return \Nexus\Services\CampaignService::update($id, $userId, $data);
    }

    /**
     * Delegates to legacy CampaignService::delete().
     */
    public function delete(int $id, int $userId): bool
    {
        return \Nexus\Services\CampaignService::delete($id, $userId);
    }

    /**
     * Delegates to legacy CampaignService::linkChallenge().
     */
    public function linkChallenge(int $campaignId, int $challengeId, int $userId, int $sortOrder = 0): bool
    {
        return \Nexus\Services\CampaignService::linkChallenge($campaignId, $challengeId, $userId, $sortOrder);
    }

    /**
     * Delegates to legacy CampaignService::unlinkChallenge().
     */
    public function unlinkChallenge(int $campaignId, int $challengeId, int $userId): bool
    {
        return \Nexus\Services\CampaignService::unlinkChallenge($campaignId, $challengeId, $userId);
    }
}
