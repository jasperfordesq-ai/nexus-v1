<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * TeamDocumentService — Laravel DI wrapper for legacy \Nexus\Services\TeamDocumentService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class TeamDocumentService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy TeamDocumentService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\TeamDocumentService::getErrors();
    }

    /**
     * Delegates to legacy TeamDocumentService::getDocuments().
     */
    public function getDocuments(int $groupId, array $filters = []): array
    {
        return \Nexus\Services\TeamDocumentService::getDocuments($groupId, $filters);
    }

    /**
     * Delegates to legacy TeamDocumentService::upload().
     */
    public function upload(int $groupId, int $userId, array $fileData, ?string $title = null): ?int
    {
        return \Nexus\Services\TeamDocumentService::upload($groupId, $userId, $fileData, $title);
    }

    /**
     * Delegates to legacy TeamDocumentService::delete().
     */
    public function delete(int $documentId, int $userId): bool
    {
        return \Nexus\Services\TeamDocumentService::delete($documentId, $userId);
    }
}
