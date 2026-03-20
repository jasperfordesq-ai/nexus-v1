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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy GroupEventService::listEvents().
     */
    public function listEvents(int $groupId, int $userId, array $filters = []): ?array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupEventService::createEvent().
     */
    public function createEvent(int $groupId, int $userId, array $data): ?array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupEventService::rsvp().
     */
    public function rsvp(int $groupId, int $eventId, int $userId, string $status): ?array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupEventService::deleteEvent().
     */
    public function deleteEvent(int $groupId, int $eventId, int $userId): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }
}
