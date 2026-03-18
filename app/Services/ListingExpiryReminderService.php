<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * ListingExpiryReminderService — Laravel DI wrapper for legacy \Nexus\Services\ListingExpiryReminderService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class ListingExpiryReminderService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy ListingExpiryReminderService::sendDueReminders().
     */
    public function sendDueReminders(): array
    {
        if (!class_exists('\Nexus\Services\ListingExpiryReminderService')) { return []; }
        return \Nexus\Services\ListingExpiryReminderService::sendDueReminders();
    }

    /**
     * Delegates to legacy ListingExpiryReminderService::cleanupOldRecords().
     */
    public function cleanupOldRecords(): int
    {
        if (!class_exists('\Nexus\Services\ListingExpiryReminderService')) { return 0; }
        return \Nexus\Services\ListingExpiryReminderService::cleanupOldRecords();
    }
}
