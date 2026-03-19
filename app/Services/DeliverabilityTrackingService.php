<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * DeliverabilityTrackingService — Laravel DI wrapper for legacy \Nexus\Services\DeliverabilityTrackingService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class DeliverabilityTrackingService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy DeliverabilityTrackingService::createDeliverable().
     */
    public function createDeliverable($ownerId, $title, $description = null, $options = [])
    {
        return \Nexus\Services\DeliverabilityTrackingService::createDeliverable($ownerId, $title, $description, $options);
    }

    /**
     * Delegates to legacy DeliverabilityTrackingService::updateDeliverableStatus().
     */
    public function updateDeliverableStatus($deliverableId, $newStatus, $userId)
    {
        return \Nexus\Services\DeliverabilityTrackingService::updateDeliverableStatus($deliverableId, $newStatus, $userId);
    }

    /**
     * Delegates to legacy DeliverabilityTrackingService::completeDeliverable().
     */
    public function completeDeliverable($deliverableId, $userId, $options = [])
    {
        return \Nexus\Services\DeliverabilityTrackingService::completeDeliverable($deliverableId, $userId, $options);
    }

    /**
     * Delegates to legacy DeliverabilityTrackingService::recalculateProgress().
     */
    public function recalculateProgress($deliverableId, $userId)
    {
        return \Nexus\Services\DeliverabilityTrackingService::recalculateProgress($deliverableId, $userId);
    }

    /**
     * Delegates to legacy DeliverabilityTrackingService::getAnalytics().
     */
    public function getAnalytics($filters = [])
    {
        return \Nexus\Services\DeliverabilityTrackingService::getAnalytics($filters);
    }
}
