<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * GroupEventService — Laravel DI wrapper for legacy \Nexus\Services\GroupEventService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class GroupEventService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy GroupEventService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\GroupEventService::getErrors();
    }

    /**
     * Delegates to legacy GroupEventService::listEvents().
     */
    public function listEvents(int $groupId, int $userId, array $filters = []): ?array
    {
        return \Nexus\Services\GroupEventService::listEvents($groupId, $userId, $filters);
    }

    /**
     * Delegates to legacy GroupEventService::createEvent().
     */
    public function createEvent(int $groupId, int $userId, array $data): ?array
    {
        return \Nexus\Services\GroupEventService::createEvent($groupId, $userId, $data);
    }

    /**
     * Delegates to legacy GroupEventService::rsvp().
     */
    public function rsvp(int $groupId, int $eventId, int $userId, string $status): ?array
    {
        return \Nexus\Services\GroupEventService::rsvp($groupId, $eventId, $userId, $status);
    }

    /**
     * Delegates to legacy GroupEventService::deleteEvent().
     */
    public function deleteEvent(int $groupId, int $eventId, int $userId): bool
    {
        return \Nexus\Services\GroupEventService::deleteEvent($groupId, $eventId, $userId);
    }
}
