<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * GroupExchangeService — Laravel DI wrapper for legacy \Nexus\Services\GroupExchangeService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class GroupExchangeService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy GroupExchangeService::create().
     */
    public function create(int $organizerId, array $data): ?int
    {
        return \Nexus\Services\GroupExchangeService::create($organizerId, $data);
    }

    /**
     * Delegates to legacy GroupExchangeService::get().
     */
    public function get(int $id): ?array
    {
        return \Nexus\Services\GroupExchangeService::get($id);
    }

    /**
     * Delegates to legacy GroupExchangeService::listForUser().
     */
    public function listForUser(int $userId, array $filters = []): array
    {
        return \Nexus\Services\GroupExchangeService::listForUser($userId, $filters);
    }

    /**
     * Delegates to legacy GroupExchangeService::addParticipant().
     */
    public function addParticipant(int $exchangeId, int $userId, string $role, float $hours = 0, float $weight = 1.0): bool
    {
        return \Nexus\Services\GroupExchangeService::addParticipant($exchangeId, $userId, $role, $hours, $weight);
    }

    /**
     * Delegates to legacy GroupExchangeService::removeParticipant().
     */
    public function removeParticipant(int $exchangeId, int $userId): bool
    {
        return \Nexus\Services\GroupExchangeService::removeParticipant($exchangeId, $userId);
    }
}
