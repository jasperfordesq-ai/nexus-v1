<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * CommunityProjectService — Laravel DI wrapper for legacy \Nexus\Services\CommunityProjectService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class CommunityProjectService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy CommunityProjectService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\CommunityProjectService::getErrors();
    }

    /**
     * Delegates to legacy CommunityProjectService::propose().
     */
    public function propose(int $userId, array $data): array
    {
        return \Nexus\Services\CommunityProjectService::propose($userId, $data);
    }

    /**
     * Delegates to legacy CommunityProjectService::getProposals().
     */
    public function getProposals(array $filters = []): array
    {
        return \Nexus\Services\CommunityProjectService::getProposals($filters);
    }

    /**
     * Delegates to legacy CommunityProjectService::getProposal().
     */
    public function getProposal(int $id): ?array
    {
        return \Nexus\Services\CommunityProjectService::getProposal($id);
    }

    /**
     * Delegates to legacy CommunityProjectService::updateProposal().
     */
    public function updateProposal(int $id, int $userId, array $data): bool
    {
        return \Nexus\Services\CommunityProjectService::updateProposal($id, $userId, $data);
    }
}
