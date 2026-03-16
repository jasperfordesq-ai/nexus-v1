<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * SafeguardingService — Laravel DI wrapper for legacy \Nexus\Services\SafeguardingService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class SafeguardingService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy SafeguardingService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\SafeguardingService::getErrors();
    }

    /**
     * Delegates to legacy SafeguardingService::createAssignment().
     */
    public function createAssignment(int $guardianUserId, int $wardUserId, int $assignedBy, ?string $notes = null): array
    {
        return \Nexus\Services\SafeguardingService::createAssignment($guardianUserId, $wardUserId, $assignedBy, $notes);
    }

    /**
     * Delegates to legacy SafeguardingService::recordConsent().
     */
    public function recordConsent(int $wardUserId): bool
    {
        return \Nexus\Services\SafeguardingService::recordConsent($wardUserId);
    }

    /**
     * Delegates to legacy SafeguardingService::revokeAssignment().
     */
    public function revokeAssignment(int $assignmentId, int $revokedBy): bool
    {
        return \Nexus\Services\SafeguardingService::revokeAssignment($assignmentId, $revokedBy);
    }

    /**
     * Delegates to legacy SafeguardingService::listAssignments().
     */
    public function listAssignments(): array
    {
        return \Nexus\Services\SafeguardingService::listAssignments();
    }
}
