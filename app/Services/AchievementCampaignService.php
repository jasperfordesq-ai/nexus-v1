<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * AchievementCampaignService — Laravel DI wrapper for legacy \Nexus\Services\AchievementCampaignService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class AchievementCampaignService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy AchievementCampaignService::getCampaigns().
     */
    public function getCampaigns($status = null)
    {
        return \Nexus\Services\AchievementCampaignService::getCampaigns($status);
    }

    /**
     * Delegates to legacy AchievementCampaignService::getCampaign().
     */
    public function getCampaign($id)
    {
        return \Nexus\Services\AchievementCampaignService::getCampaign($id);
    }

    /**
     * Delegates to legacy AchievementCampaignService::createCampaign().
     */
    public function createCampaign($data)
    {
        return \Nexus\Services\AchievementCampaignService::createCampaign($data);
    }

    /**
     * Delegates to legacy AchievementCampaignService::updateCampaign().
     */
    public function updateCampaign($id, $data)
    {
        return \Nexus\Services\AchievementCampaignService::updateCampaign($id, $data);
    }

    /**
     * Delegates to legacy AchievementCampaignService::activateCampaign().
     */
    public function activateCampaign($id)
    {
        return \Nexus\Services\AchievementCampaignService::activateCampaign($id);
    }

    /**
     * Delegates to legacy AchievementCampaignService::pauseCampaign().
     */
    public function pauseCampaign($id)
    {
        return \Nexus\Services\AchievementCampaignService::pauseCampaign($id);
    }

    /**
     * Delegates to legacy AchievementCampaignService::deleteCampaign().
     */
    public function deleteCampaign($id)
    {
        return \Nexus\Services\AchievementCampaignService::deleteCampaign($id);
    }
}
