<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupExchangeService::get().
     */
    public function get(int $id): ?array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupExchangeService::listForUser().
     */
    public function listForUser(int $userId, array $filters = []): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy GroupExchangeService::addParticipant().
     */
    public function addParticipant(int $exchangeId, int $userId, string $role, float $hours = 0, float $weight = 1.0): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy GroupExchangeService::removeParticipant().
     */
    public function removeParticipant(int $exchangeId, int $userId): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy GroupExchangeService::calculateSplit().
     */
    public function calculateSplit(int $exchangeId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy GroupExchangeService::update().
     */
    public function update(int $id, array $data): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy GroupExchangeService::updateStatus().
     */
    public function updateStatus(int $id, string $status): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy GroupExchangeService::confirmParticipation().
     */
    public function confirmParticipation(int $exchangeId, int $userId): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy GroupExchangeService::complete().
     */
    public function complete(int $exchangeId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }
}
