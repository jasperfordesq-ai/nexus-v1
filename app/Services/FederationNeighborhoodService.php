<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * FederationNeighborhoodService — Laravel DI wrapper for legacy \Nexus\Services\FederationNeighborhoodService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class FederationNeighborhoodService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy FederationNeighborhoodService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\FederationNeighborhoodService::getErrors();
    }

    /**
     * Delegates to legacy FederationNeighborhoodService::create().
     */
    public function create(string $name, ?string $description = null, ?string $region = null, int $createdBy = 0): ?int
    {
        return \Nexus\Services\FederationNeighborhoodService::create($name, $description, $region, $createdBy);
    }

    /**
     * Delegates to legacy FederationNeighborhoodService::update().
     */
    public function update(int $id, array $data): bool
    {
        return \Nexus\Services\FederationNeighborhoodService::update($id, $data);
    }

    /**
     * Delegates to legacy FederationNeighborhoodService::delete().
     */
    public function delete(int $id): bool
    {
        return \Nexus\Services\FederationNeighborhoodService::delete($id);
    }

    /**
     * Delegates to legacy FederationNeighborhoodService::getById().
     */
    public function getById(int $id): ?array
    {
        return \Nexus\Services\FederationNeighborhoodService::getById($id);
    }
}
