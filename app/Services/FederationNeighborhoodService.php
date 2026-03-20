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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Static proxy: list all neighborhoods.
     */
    public static function listAllStatic(): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederationNeighborhoodService::create().
     */
    public function create(string $name, ?string $description = null, ?string $region = null, int $createdBy = 0): ?int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Static proxy: create a neighborhood.
     */
    public static function createStatic(string $name, ?string $description = null, ?string $region = null, int $createdBy = 0): ?int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy FederationNeighborhoodService::update().
     */
    public function update(int $id, array $data): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy FederationNeighborhoodService::delete().
     */
    public function delete(int $id): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy FederationNeighborhoodService::getById().
     */
    public function getById(int $id): ?array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }
}
