<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * VolunteerDonationService — Laravel DI wrapper for legacy \Nexus\Services\VolunteerDonationService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class VolunteerDonationService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy VolunteerDonationService::getDonations().
     */
    public function getDonations(array $filters = []): array
    {
        return \Nexus\Services\VolunteerDonationService::getDonations($filters);
    }

    /**
     * Delegates to legacy VolunteerDonationService::createDonation().
     */
    public function createDonation(int $userId, array $data): array
    {
        return \Nexus\Services\VolunteerDonationService::createDonation($userId, $data);
    }

    /**
     * Delegates to legacy VolunteerDonationService::getGivingDays().
     */
    public function getGivingDays(): array
    {
        return \Nexus\Services\VolunteerDonationService::getGivingDays();
    }

    /**
     * Delegates to legacy VolunteerDonationService::getGivingDayStats().
     */
    public function getGivingDayStats(int $givingDayId): array
    {
        return \Nexus\Services\VolunteerDonationService::getGivingDayStats($givingDayId);
    }

    /**
     * Delegates to legacy VolunteerDonationService::adminGetGivingDays().
     */
    public function adminGetGivingDays(): array
    {
        return \Nexus\Services\VolunteerDonationService::adminGetGivingDays();
    }
}
